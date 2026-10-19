<?php

use App\Models\Expenses\PurchaseDocument;
use App\Models\Expenses\PurchaseDocumentItem;
use App\Models\Finance\AccountingAccount;
use App\Models\Finance\JournalEntry;
use App\Models\Finance\Tax;
use App\Models\Inventory\Warehouse;
use App\Models\MasterData\CategoryGroup;
use App\Models\MasterData\CategorySubGroup;
use App\Models\MasterData\Contact;
use App\Models\MasterData\Item;
use App\Models\Settings\User;
use App\Services\Finance\AccountingPostingService;
use App\Services\Finance\CreditDebitNoteService;
use App\Support\MasterData\MaterialClassifier;
use Spatie\Permission\Models\Permission;

/*
 * Auto-reparación: una nota crédito sobre una factura de tela aprobada (o recibida) que nunca tuvo asiento
 * contabiliza primero la factura y luego la nota. Factura $59.500 (50.000 + IVA 9.500); nota de $11.900.
 */

function cnfInvoiceWithoutEntry(bool $withAccounts, string $status = 'approved'): PurchaseDocument
{
    $group = CategoryGroup::firstOrCreate(['name' => 'TELAS NC AUTOPOST TEST']);
    $subGroup = CategorySubGroup::firstOrCreate(['name' => 'Tela nc autopost test', 'category_group_id' => $group->id]);
    $subGroup->forceFill(['material_kind' => 'fabric'])->save();
    MaterialClassifier::forgetCache();
    Warehouse::firstOrCreate(['name' => 'Bodega nc autopost tela'], ['observation' => 'Test']);

    $attributes = [
        'category_sub_group_id' => $subGroup->id,
        'name' => 'Tela nc autopost '.uniqid(),
        'description' => 'Tela de prueba',
        'status' => true,
        'tracks_inventory' => false,
    ];

    if ($withAccounts) {
        $account = fn (string $code, string $type, ?string $special = null) => AccountingAccount::firstOrCreate(
            ['code' => $code],
            ['name' => 'Cuenta '.$code, 'account_type' => $type, 'level' => 1, 'allows_entries' => true, 'is_active' => true, 'special_type' => $special]
        );
        $cost = $account('5135', AccountingAccount::TYPE_COST);
        $vat = $account('240810', AccountingAccount::TYPE_ASSET);
        $account('220505', AccountingAccount::TYPE_LIABILITY, AccountingAccount::SPECIAL_ACCOUNTS_PAYABLE);
        $tax = Tax::firstOrCreate(['name' => 'IVA 19% nc autopost'], ['percentage' => 19, 'debit_account_id' => $vat->id, 'status' => true]);
        $attributes += ['tax_id' => $tax->id, 'cost_of_sale_account_id' => $cost->id];
    }

    $item = Item::create($attributes);

    // Factura ya aprobada/recibida sin asiento (histórica: aprobada antes de que la recepción contabilizara).
    $invoice = PurchaseDocument::create([
        'document_type' => PurchaseDocument::TYPE_INVOICE,
        'document_number' => 'FT-NCA-'.uniqid(),
        'contact_id' => Contact::factory()->create()->id,
        'status' => $status,
        'received_at' => now(),
        'date' => now()->toDateString(),
    ]);
    PurchaseDocumentItem::create([
        'purchase_document_id' => $invoice->id,
        'item_id' => $item->id,
        'quantity' => 500,
        'price' => 100,
        'tax' => 19,
        'total' => 50000,
        'total_tax' => 9500,
    ]);

    return $invoice;
}

function cnfNotePayload(PurchaseDocument $invoice): array
{
    return [
        'document_type' => PurchaseDocument::TYPE_CREDIT_NOTE,
        'parent_document_id' => $invoice->id,
        'contact_id' => $invoice->contact_id,
        'reason_code' => 'defecto',
        'status' => 'pending',
        'items' => [[
            'item_name' => 'Rollo rechazado',
            'quantity' => 1,
            'price' => 10000,
            'total' => 10000,
            'total_tax' => 1900,
        ]],
    ];
}

beforeEach(function () {
    $this->user = User::factory()->create();
    foreach (['ver notas credito debito', 'crear notas credito debito', 'editar notas credito debito', 'eliminar notas credito debito'] as $permission) {
        $this->user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }
    $this->actingAs($this->user);
    $this->notes = app(CreditDebitNoteService::class);
});

it('posts an approved fabric invoice without entry first, then the credit note', function () {
    $invoice = cnfInvoiceWithoutEntry(true);
    expect(JournalEntry::forSource(AccountingPostingService::SOURCE_PURCHASE_INVOICE, $invoice->id)->count())->toBe(0);

    $note = $this->notes->create(cnfNotePayload($invoice));

    $invoiceEntry = JournalEntry::forSource(AccountingPostingService::SOURCE_PURCHASE_INVOICE, $invoice->id)->first();
    $noteEntry = JournalEntry::forSource(AccountingPostingService::SOURCE_CREDIT_NOTE, $note->id)->first();

    expect($invoiceEntry)->not->toBeNull()
        ->and((float) $invoiceEntry->total_credit)->toBe(59500.0)
        ->and($invoiceEntry->status)->toBe(JournalEntry::STATUS_POSTED)
        ->and($noteEntry)->not->toBeNull()
        ->and((float) $noteEntry->total_credit)->toBe(11900.0)
        ->and(JournalEntry::count())->toBe(2);
});

it('also repairs a received invoice that never had an entry', function () {
    $invoice = cnfInvoiceWithoutEntry(true, 'received');

    $note = $this->notes->create(cnfNotePayload($invoice));

    expect(JournalEntry::forSource(AccountingPostingService::SOURCE_PURCHASE_INVOICE, $invoice->id)->count())->toBe(1)
        ->and(JournalEntry::forSource(AccountingPostingService::SOURCE_CREDIT_NOTE, $note->id)->count())->toBe(1);
});

it('keeps the clear error when the invoice cannot be auto-posted and leaves no traces', function () {
    $invoice = cnfInvoiceWithoutEntry(false);

    try {
        $this->notes->create(cnfNotePayload($invoice));
        $this->fail('Sin cuentas configuradas la nota no puede contabilizarse.');
    } catch (RuntimeException $e) {
        expect($e->getMessage())
            ->toContain('La factura de compra padre no tiene asiento contable registrado')
            ->toContain('No se pudo contabilizar automáticamente');
    }

    expect(JournalEntry::count())->toBe(0)
        ->and(PurchaseDocument::where('document_type', PurchaseDocument::TYPE_CREDIT_NOTE)->count())->toBe(0);
});

it('does not repost an invoice whose entry was already fully reversed by a credit note', function () {
    $invoice = cnfInvoiceWithoutEntry(true);
    $posting = app(AccountingPostingService::class);
    $posting->postPurchaseInvoice($invoice);

    $full = cnfNotePayload($invoice);
    $full['items'][0] = ['item_name' => 'Devolución total', 'quantity' => 1, 'price' => 50000, 'total' => 50000, 'total_tax' => 9500];
    $this->notes->create($full);

    expect(JournalEntry::forSource(AccountingPostingService::SOURCE_PURCHASE_INVOICE, $invoice->id)
        ->where('status', JournalEntry::STATUS_REVERSED)->count())->toBe(1);

    expect(fn () => $this->notes->create(cnfNotePayload($invoice)))->toThrow(Exception::class);

    // La auto-reparación tampoco reposta una factura cuyo asiento fue revertido.
    $existing = $posting->postPurchaseInvoiceIfMissing($invoice->fresh());
    expect($existing->status)->toBe(JournalEntry::STATUS_REVERSED);

    // Sigue habiendo un único asiento de factura (el revertido): no se creó otro.
    expect(JournalEntry::forSource(AccountingPostingService::SOURCE_PURCHASE_INVOICE, $invoice->id)->count())->toBe(1);
});
