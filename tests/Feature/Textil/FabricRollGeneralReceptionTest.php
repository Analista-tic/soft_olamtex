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

it('corrects a registered general reception with auditable inventory movements', function () {
    $item = createGeneralReceptionTestItem('correction');
    $warehouse = Warehouse::create(['name' => 'Bodega recepción correction', 'observation' => 'Test']);
    [$invoice, $line] = createGeneralReceptionTestInvoice($item, quantity: 100);

    $this->service->registerGeneralReception($invoice->id, [
        ['purchase_document_item_id' => $line->id, 'warehouse_id' => $warehouse->id, 'quantity' => 40],
    ]);

    $reception = PurchaseDocumentItemReception::where('purchase_document_item_id', $line->id)->firstOrFail();
    $originalMovementId = $reception->inventory_movement_id;

    $updatedReception = $this->service->updateGeneralReception($invoice->id, $reception->id, [
        'warehouse_id' => $warehouse->id,
        'quantity' => 25,
        'over_received_reason' => null,
    ]);

    expect((float) $updatedReception->quantity)->toEqual(25.0);
    expect($updatedReception->inventory_movement_id)->not->toBe($originalMovementId);
    expect(WarehouseItem::where('item_id', $item->id)->where('warehouse_id', $warehouse->id)->first()->quantity)->toEqual(25.0);
    expect(InventoryMovement::where('source_id', $invoice->id)->count())->toBe(2);
    expect(InventoryMovement::findOrFail($originalMovementId)->quantity)->toEqual('40.0000');
});

it('corrects the same general reception twice without repeating the movement number', function () {
    $item = createGeneralReceptionTestItem('double-correction');
    $warehouse = Warehouse::create(['name' => 'Bodega recepción double-correction', 'observation' => 'Test']);
    [$invoice, $line] = createGeneralReceptionTestInvoice($item, quantity: 100);

    $this->service->registerGeneralReception($invoice->id, [
        ['purchase_document_item_id' => $line->id, 'warehouse_id' => $warehouse->id, 'quantity' => 40],
    ]);
    $reception = PurchaseDocumentItemReception::where('purchase_document_item_id', $line->id)->firstOrFail();

    foreach ([25, 30] as $quantity) {
        $this->service->updateGeneralReception($invoice->id, $reception->id, [
            'warehouse_id' => $warehouse->id,
            'quantity' => $quantity,
            'over_received_reason' => null,
        ]);
    }

    $numbers = InventoryMovement::where('source_id', $invoice->id)->pluck('document_number');
    expect($numbers)->toHaveCount(3)
        ->and($numbers->unique())->toHaveCount(3)
        ->and(WarehouseItem::where('item_id', $item->id)->where('warehouse_id', $warehouse->id)->first()->quantity)->toEqual(30.0);
});

it('does not correct a general reception once the reception is approved', function () {
    $item = createGeneralReceptionTestItem('approved-correction');
    $warehouse = Warehouse::create(['name' => 'Bodega recepción approved-correction', 'observation' => 'Test']);
    [$invoice, $line] = createGeneralReceptionTestInvoice($item, quantity: 100);

    $this->service->registerGeneralReception($invoice->id, [
        ['purchase_document_item_id' => $line->id, 'warehouse_id' => $warehouse->id, 'quantity' => 40],
    ]);
    $reception = PurchaseDocumentItemReception::where('purchase_document_item_id', $line->id)->firstOrFail();
    $invoice->forceFill(['status' => 'approved'])->save();

    expect(fn () => $this->service->updateGeneralReception($invoice->id, $reception->id, [
        'warehouse_id' => $warehouse->id,
        'quantity' => 25,
        'over_received_reason' => null,
    ]))->toThrow(ValidationException::class);

    expect((float) $reception->fresh()->quantity)->toEqual(40.0)
        ->and(InventoryMovement::where('source_id', $invoice->id)->count())->toBe(1);
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

it('receives the pending balance into the chosen warehouse when closing the invoice', function () {
    $item = createGeneralReceptionTestItem('close-pending');
    $warehouse = Warehouse::create(['name' => 'Bodega recepción close-pending', 'observation' => 'Test']);
    $closingWarehouse = Warehouse::create(['name' => 'Bodega cierre close-pending', 'observation' => 'Test']);
    [$invoice, $line] = createGeneralReceptionTestInvoice($item, quantity: 100, price: 250);

    $this->service->registerGeneralReception($invoice->id, [
        ['purchase_document_item_id' => $line->id, 'warehouse_id' => $warehouse->id, 'quantity' => 40],
    ]);

    $closed = $this->service->approveReception($invoice->id, $closingWarehouse->id);

    expect($closed->status)->toBe('approved');
    expect(WarehouseItem::where('item_id', $item->id)->where('warehouse_id', $warehouse->id)->first()->quantity)->toEqual(40.0);
    $pending = WarehouseItem::where('item_id', $item->id)->where('warehouse_id', $closingWarehouse->id)->first();
    expect($pending->quantity)->toEqual(60.0)
        ->and((float) $pending->weighted_average_price)->toEqual(250.0);
    expect(PurchaseDocumentItemReception::where('purchase_document_item_id', $line->id)->sum('quantity'))->toEqual(100.0);
});

it('does not close an invoice with a pending balance when no warehouse is given', function () {
    $item = createGeneralReceptionTestItem('close-no-warehouse');
    [$invoice] = createGeneralReceptionTestInvoice($item, quantity: 10);

    expect(fn () => $this->service->approveReception($invoice->id))->toThrow(ValidationException::class);

    expect($invoice->fresh()->status)->toBe('pending_receipt')
        ->and(InventoryMovement::where('item_id', $item->id)->count())->toBe(0);
});

it('closes a fully received invoice without asking for a warehouse', function () {
    $item = createGeneralReceptionTestItem('close-complete');
    $warehouse = Warehouse::create(['name' => 'Bodega recepción close-complete', 'observation' => 'Test']);
    [$invoice, $line] = createGeneralReceptionTestInvoice($item, quantity: 10);

    $this->service->registerGeneralReception($invoice->id, [
        ['purchase_document_item_id' => $line->id, 'warehouse_id' => $warehouse->id, 'quantity' => 10],
    ]);

    expect($this->service->approveReception($invoice->id)->status)->toBe('approved');
});

it('refuses a purchase entry whose line has no price', function () {
    $item = createGeneralReceptionTestItem('no-price');
    $warehouse = Warehouse::create(['name' => 'Bodega recepción no-price', 'observation' => 'Test']);
    [$invoice, $line] = createGeneralReceptionTestInvoice($item, quantity: 10, price: 0);

    expect(fn () => $this->service->registerGeneralReception($invoice->id, [
        ['purchase_document_item_id' => $line->id, 'warehouse_id' => $warehouse->id, 'quantity' => 10],
    ]))->toThrow(ValidationException::class);

    expect(InventoryMovement::where('item_id', $item->id)->count())->toBe(0);
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
