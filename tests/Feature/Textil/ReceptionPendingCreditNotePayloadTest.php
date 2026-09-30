<?php

use App\Models\Expenses\PurchaseDocument;
use App\Models\Expenses\PurchaseDocumentItem;
use App\Models\Inventory\Warehouse;
use App\Models\MasterData\CategoryGroup;
use App\Models\MasterData\CategorySubGroup;
use App\Models\MasterData\Item;
use App\Models\Settings\User;
use App\Services\Expenses\PurchaseDocumentInvoiceService;
use App\Services\Textil\FabricRollService;
use App\Support\MasterData\MaterialClassifier;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function pcnFabricInvoice(float $invoicedMeters): array
{
    $group = CategoryGroup::firstOrCreate(['name' => 'TELAS PAYLOAD NC TEST']);
    $subGroup = CategorySubGroup::firstOrCreate(['name' => 'Tela payload nc test', 'category_group_id' => $group->id]);
    $subGroup->forceFill(['material_kind' => 'fabric'])->save();
    MaterialClassifier::forgetCache();

    $item = Item::create([
        'category_sub_group_id' => $subGroup->id,
        'name' => 'Tela payload '.uniqid(),
        'description' => 'Tela de prueba',
        'status' => true,
    ]);
    Warehouse::firstOrCreate(['name' => 'Bodega payload nc'], ['observation' => 'Test']);

    $invoice = PurchaseDocument::create([
        'document_type' => PurchaseDocument::TYPE_INVOICE,
        'document_number' => 'FT-PNC-'.uniqid(),
        'status' => 'pending_receipt',
        'date' => now()->toDateString(),
    ]);
    $line = PurchaseDocumentItem::create([
        'purchase_document_id' => $invoice->id,
        'item_id' => $item->id,
        'quantity' => $invoicedMeters,
        'price' => 100,
        'total' => $invoicedMeters * 100,
    ]);

    return [$invoice, $line];
}

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    $this->service = app(FabricRollService::class);
});

it('exposes pending credit note rolls count and amount in the invoice detail and list payloads (batched)', function () {
    [$invoice, $line] = pcnFabricInvoice(500);
    [$other] = pcnFabricInvoice(200);

    $roll = $this->service->storeFromReception([
        'purchase_document_item_id' => $line->id,
        'quantity' => 500,
        'roll_width' => 1.5,
        'entry_date' => now()->toDateString(),
    ]);

    $invoiceService = app(PurchaseDocumentInvoiceService::class);

    // Sin rollos rechazados: cero.
    $detail = $invoiceService->loadWithRelations($invoice->fresh());
    expect($detail->pending_credit_note_rolls_count)->toBe(0)
        ->and($detail->pending_credit_note_amount)->toBe(0.0);

    $this->service->rejectRoll($roll->id, 'Tela con defecto');

    $detail = $invoiceService->loadWithRelations($invoice->fresh());
    expect($detail->pending_credit_note_rolls_count)->toBe(1)
        ->and($detail->pending_credit_note_amount)->toBeGreaterThan(0.0)
        ->and($detail->toArray())->toHaveKeys(['pending_credit_note_rolls_count', 'pending_credit_note_amount']);

    // Listado: una fila con rollos pendientes y otra sin ellos.
    Permission::findOrCreate('ver todos facturas compra', 'web');
    Role::findOrCreate('admin', 'web');
    $admin = User::factory()->create();
    $admin->givePermissionTo('ver todos facturas compra');
    $admin->assignRole('admin');

    $page = $invoiceService->paginateDocuments(PurchaseDocument::TYPE_INVOICE, [], 500, null, $admin);
    $rows = $page->getCollection()->keyBy('id');

    expect($rows[$invoice->id]->pending_credit_note_rolls_count)->toBe(1)
        ->and($rows[$invoice->id]->pending_credit_note_amount)->toBe($detail->pending_credit_note_amount)
        ->and($rows[$other->id]->pending_credit_note_rolls_count)->toBe(0)
        ->and($rows[$other->id]->pending_credit_note_amount)->toBe(0.0);
});
