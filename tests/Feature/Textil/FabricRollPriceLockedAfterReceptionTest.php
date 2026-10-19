<?php

use App\Models\Expenses\PurchaseDocument;
use App\Models\Expenses\PurchaseDocumentItem;
use App\Models\Inventory\Warehouse;
use App\Models\MasterData\CategoryGroup;
use App\Models\MasterData\CategorySubGroup;
use App\Models\MasterData\Contact;
use App\Models\MasterData\Item;
use App\Models\Settings\User;
use App\Models\Textil\FabricRoll;
use App\Services\Textil\FabricRollService;
use App\Support\MasterData\MaterialClassifier;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;

/**
 * El precio de un rollo recepcionado por factura es un dato de la factura (precio pactado
 * con el proveedor), no del inventario: igual que el resto de la edición directa del rollo,
 * debe bloquearse en cuanto la recepción queda aprobada. Antes "Precio por metro" (el
 * quick-edit del inventario) solo chequeaba que el rollo no tuviera descargues, así que un
 * rollo recién aprobado (sin descargues todavía) podía desligarse silenciosamente del precio
 * de su factura, sin ningún rastro.
 */
function priceLockFabricInvoiceRoll(): array
{
    $group = CategoryGroup::firstOrCreate(['name' => 'TELAS PRICE LOCK TEST']);
    $subGroup = CategorySubGroup::firstOrCreate(['name' => 'Tela price lock test', 'category_group_id' => $group->id]);
    $subGroup->forceFill(['material_kind' => 'fabric'])->save();
    MaterialClassifier::forgetCache();
    Warehouse::firstOrCreate(['name' => 'Bodega price lock tela'], ['observation' => 'Test']);

    $item = Item::create([
        'category_sub_group_id' => $subGroup->id,
        'name' => 'Tela price lock '.uniqid(),
        'description' => 'Tela de prueba',
        'status' => true,
        'tracks_inventory' => false,
    ]);

    $invoice = PurchaseDocument::create([
        'document_type' => PurchaseDocument::TYPE_INVOICE,
        'document_number' => 'FT-PL-'.uniqid(),
        'contact_id' => Contact::factory()->create()->id,
        'status' => 'pending_receipt',
        'date' => now()->toDateString(),
    ]);
    $line = PurchaseDocumentItem::create([
        'purchase_document_id' => $invoice->id,
        'item_id' => $item->id,
        'quantity' => 100,
        'price' => 100,
        'tax' => 0,
        'total' => 10000,
        'total_tax' => 0,
    ]);

    $service = app(FabricRollService::class);
    $roll = $service->storeFromReception([
        'purchase_document_item_id' => $line->id,
        'quantity' => 100,
        'roll_width' => 1.5,
        'entry_date' => now()->toDateString(),
    ]);

    $service->approveReception($invoice->id);

    return [$roll->fresh(), $invoice];
}

beforeEach(function () {
    $user = User::factory()->create();
    foreach (['ver telas rollos', 'ajustar telas rollos'] as $permission) {
        $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }
    $this->actingAs($user);
    $this->service = app(FabricRollService::class);
});

it('rejects updating the price of a roll whose invoice reception is already approved', function () {
    [$roll] = priceLockFabricInvoiceRoll();

    expect((float) $roll->price_per_meter)->toBe(100.0);

    expect(fn () => $this->service->updatePrice($roll, 250))->toThrow(ValidationException::class);

    expect((float) $roll->fresh()->price_per_meter)->toBe(100.0);
});

it('surfaces the invoice-locked error through the HTTP endpoint with a clear message', function () {
    [$roll] = priceLockFabricInvoiceRoll();

    $response = $this->patchJson(route('fabric-rolls.update-price', $roll->id), ['price_per_meter' => 250]);

    $response->assertStatus(422);
    expect($response->json('errors.general.0'))->toContain('ya está recepcionada');
    expect((float) $roll->fresh()->price_per_meter)->toBe(100.0);
});

it('still allows correcting the price of a roll with no linked purchase invoice (legacy/imported)', function () {
    $roll = FabricRoll::create([
        'roll_number' => 'RL-PRICE-NOINV',
        'item_id' => null,
        'quantity' => 50,
        'actual_output' => 0,
        'price_per_meter' => 5000,
        'entry_date' => now()->toDateString(),
        'approval_status' => FabricRoll::APPROVAL_APPROVED,
    ]);

    $updated = $this->service->updatePrice($roll, 7500);

    expect((float) $updated->price_per_meter)->toBe(7500.0);
});
