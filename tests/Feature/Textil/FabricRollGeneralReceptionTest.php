<?php

use App\Models\Expenses\PurchaseDocument;
use App\Models\Expenses\PurchaseDocumentItem;
use App\Models\Expenses\PurchaseDocumentItemReception;
use App\Models\Inventory\InventoryMovement;
use App\Models\Inventory\Warehouse;
use App\Models\Inventory\WarehouseItem;
use App\Models\MasterData\CategoryGroup;
use App\Models\MasterData\CategorySubGroup;
use App\Models\MasterData\Item;
use App\Models\Settings\User;
use App\Services\Textil\FabricRollService;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;

function createGeneralReceptionTestItem(string $suffix): Item
{
    $group = CategoryGroup::firstOrCreate(['name' => 'GENERAL RECEPTION TEST']);
    $subGroup = CategorySubGroup::firstOrCreate([
        'name' => 'Insumo Test',
        'category_group_id' => $group->id,
    ]);

    return Item::create([
        'category_sub_group_id' => $subGroup->id,
        'name' => 'Item recepción general '.$suffix,
        'description' => 'Item de prueba de recepción general '.$suffix,
        'status' => true,
    ]);
}

function createGeneralReceptionTestInvoice(Item $item, float $quantity, float $price = 100): array
{
    $invoice = PurchaseDocument::create([
        'document_type' => PurchaseDocument::TYPE_INVOICE,
        'document_number' => 'FC-TEST-'.uniqid(),
        'status' => 'pending_receipt',
        'date' => now()->toDateString(),
    ]);

    $line = PurchaseDocumentItem::create([
        'purchase_document_id' => $invoice->id,
        'item_id' => $item->id,
        'quantity' => $quantity,
        'price' => $price,
        'total' => $quantity * $price,
    ]);

    return [$invoice, $line];
}

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    $this->service = app(FabricRollService::class);
});

it('creates an inventory movement for a partial round, not the full invoiced quantity', function () {
    $item = createGeneralReceptionTestItem('partial');
    $warehouse = Warehouse::create(['name' => 'Bodega recepción partial', 'observation' => 'Test']);
    [$invoice, $line] = createGeneralReceptionTestInvoice($item, quantity: 100);

    $this->service->registerGeneralReception($invoice->id, [
        [
            'purchase_document_item_id' => $line->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 40,
        ],
    ]);

    expect(WarehouseItem::where('item_id', $item->id)->where('warehouse_id', $warehouse->id)->first()->quantity)
        ->toEqual(40.0);
    expect(InventoryMovement::where('item_id', $item->id)->where('source_id', $invoice->id)->count())->toBe(1);
    expect(PurchaseDocumentItemReception::where('purchase_document_item_id', $line->id)->sum('quantity'))->toEqual(40.0);
    expect($invoice->fresh()->status)->toBe('pending_receipt');
});

it('accumulates a second round on top of the first without duplicating or resetting the total', function () {
    $item = createGeneralReceptionTestItem('rounds');
    $warehouse = Warehouse::create(['name' => 'Bodega recepción rounds', 'observation' => 'Test']);
    [$invoice, $line] = createGeneralReceptionTestInvoice($item, quantity: 100);

    $this->service->registerGeneralReception($invoice->id, [
        ['purchase_document_item_id' => $line->id, 'warehouse_id' => $warehouse->id, 'quantity' => 40],
    ]);
    $this->service->registerGeneralReception($invoice->id, [
        ['purchase_document_item_id' => $line->id, 'warehouse_id' => $warehouse->id, 'quantity' => 60],
    ]);

    expect(WarehouseItem::where('item_id', $item->id)->where('warehouse_id', $warehouse->id)->first()->quantity)
        ->toEqual(100.0);
    expect(InventoryMovement::where('item_id', $item->id)->where('source_id', $invoice->id)->count())->toBe(2);
    expect(PurchaseDocumentItemReception::where('purchase_document_item_id', $line->id)->count())->toBe(2);
});

it('rejects a round that would over-receive without a reason', function () {
    $item = createGeneralReceptionTestItem('overreceive-fail');
    $warehouse = Warehouse::create(['name' => 'Bodega recepción overreceive-fail', 'observation' => 'Test']);
    [$invoice, $line] = createGeneralReceptionTestInvoice($item, quantity: 50);

    expect(fn () => $this->service->registerGeneralReception($invoice->id, [
        ['purchase_document_item_id' => $line->id, 'warehouse_id' => $warehouse->id, 'quantity' => 60],
    ]))->toThrow(ValidationException::class);

    expect(InventoryMovement::where('item_id', $item->id)->where('source_id', $invoice->id)->count())->toBe(0);
});

it('accepts an over-received round when a reason is provided', function () {
    $item = createGeneralReceptionTestItem('overreceive-ok');
    $warehouse = Warehouse::create(['name' => 'Bodega recepción overreceive-ok', 'observation' => 'Test']);
    [$invoice, $line] = createGeneralReceptionTestInvoice($item, quantity: 50);

    $this->service->registerGeneralReception($invoice->id, [
        [
            'purchase_document_item_id' => $line->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 60,
            'over_received_reason' => 'El proveedor envió 10 unidades de bonificación.',
        ],
    ]);

    expect(WarehouseItem::where('item_id', $item->id)->where('warehouse_id', $warehouse->id)->first()->quantity)
        ->toEqual(60.0);
    expect(PurchaseDocumentItemReception::where('purchase_document_item_id', $line->id)->first()->over_received_reason)
        ->toBe('El proveedor envió 10 unidades de bonificación.');
});

it('does not create a movement for a service item that does not track inventory', function () {
    $item = createGeneralReceptionTestItem('service');
    $item->update(['tracks_inventory' => false]);
    $warehouse = Warehouse::create(['name' => 'Bodega recepción service', 'observation' => 'Test']);
    [$invoice, $line] = createGeneralReceptionTestInvoice($item, quantity: 10);

    $this->service->registerGeneralReception($invoice->id, [
        ['purchase_document_item_id' => $line->id, 'warehouse_id' => $warehouse->id, 'quantity' => 10],
    ]);

    expect(InventoryMovement::where('item_id', $item->id)->where('source_id', $invoice->id)->count())->toBe(0);
    expect(PurchaseDocumentItemReception::where('purchase_document_item_id', $line->id)->count())->toBe(0);
});

it('closes a general invoice with pending balance without blocking, same as today', function () {
    $item = createGeneralReceptionTestItem('close-pending');
    $warehouse = Warehouse::create(['name' => 'Bodega recepción close-pending', 'observation' => 'Test']);
    [$invoice, $line] = createGeneralReceptionTestInvoice($item, quantity: 100);

    $this->service->registerGeneralReception($invoice->id, [
        ['purchase_document_item_id' => $line->id, 'warehouse_id' => $warehouse->id, 'quantity' => 40],
    ]);

    $closed = $this->service->approveReception($invoice->id);

    expect($closed->status)->toBe('approved');
    expect(WarehouseItem::where('item_id', $item->id)->where('warehouse_id', $warehouse->id)->first()->quantity)
        ->toEqual(40.0);
});

it('rejects a warehouse that does not exist through the HTTP endpoint', function () {
    $user = User::factory()->create();
    // El grupo de rutas kardex-telas exige "ver telas rollos" en todo el grupo,
    // además del permiso propio de la ruta.
    $user->givePermissionTo(Permission::findOrCreate('ver telas rollos', 'web'));
    $user->givePermissionTo(Permission::findOrCreate('aprobar recepcion tela', 'web'));
    $this->actingAs($user);

    $item = createGeneralReceptionTestItem('invalid-warehouse');
    [$invoice, $line] = createGeneralReceptionTestInvoice($item, quantity: 10);

    $response = $this->patchJson("/kardex-telas/recepcion/{$invoice->id}/registrar", [
        'lines' => [
            ['purchase_document_item_id' => $line->id, 'warehouse_id' => 999999, 'quantity' => 10],
        ],
    ]);

    $response->assertStatus(422);
    expect(InventoryMovement::where('item_id', $item->id)->where('source_id', $invoice->id)->count())->toBe(0);
});
