<?php

use App\Models\Expenses\PurchaseDocument;
use App\Models\Expenses\PurchaseDocumentItem;
use App\Models\Finance\AccountingAccount;
use App\Models\Finance\JournalEntry;
use App\Models\Finance\Retention;
use App\Models\Finance\Tax;
use App\Models\MasterData\CategoryGroup;
use App\Models\MasterData\CategorySubGroup;
use App\Models\MasterData\Contact;
use App\Models\MasterData\Item;
use App\Models\Settings\User;
use App\Services\Finance\AccountingPostingService;
use App\Services\Finance\CreditDebitNoteService;
use Illuminate\Validation\ValidationException;

/*
 * Notas crédito de compra con asiento contable proporcional.
 *
 * Factura de prueba (total 1.190,00):
 *   línea A: 333,33 + IVA 63,33 | línea B: 666,67 + IVA 126,67   -> subtotal 1.000,00 + IVA 190,00
 *   ReteFuente 2,5% sobre el subtotal = 25,00
 * Asiento de la factura: Debe 5135 = 1.000,00 y IVA = 190,00 | Haber Retención = 25,00 y CxP = 1.165,00.
 *
 * Nota parcial de 396,67 (ratio = 396,67 / 1.190 = 0,33333...): las líneas se invierten y se escalan; el redondeo
 * a centavos deja 396,66 en cada lado y el residuo (0,01) se suma a la línea de mayor monto de cada lado.
 */

const CNP_INVOICE_TOTAL = 1190.00;

function cnpAccounts(): array
{
    $make = fn (string $code, string $type, ?string $special = null) => AccountingAccount::firstOrCreate(
        ['code' => $code],
        ['name' => 'Cuenta '.$code, 'account_type' => $type, 'level' => 1, 'allows_entries' => true, 'is_active' => true, 'special_type' => $special]
    );

    return [
        'cost' => $make('5135', AccountingAccount::TYPE_COST),
        'vat' => $make('240810', AccountingAccount::TYPE_ASSET),
        'retention' => $make('236540', AccountingAccount::TYPE_LIABILITY),
        'payable' => $make('220505', AccountingAccount::TYPE_LIABILITY, AccountingAccount::SPECIAL_ACCOUNTS_PAYABLE),
    ];
}

/** Factura recibida y contabilizada de verdad; devuelve [factura, asiento, cuentas]. */
function cnpPostedInvoice(Contact $contact): array
{
    $accounts = cnpAccounts();
    $group = CategoryGroup::firstOrCreate(['name' => 'GRUPO NC PARCIAL']);
    $subGroup = CategorySubGroup::firstOrCreate(['name' => 'Subgrupo NC parcial', 'category_group_id' => $group->id]);
    $tax = Tax::firstOrCreate(['name' => 'IVA 19% nc parcial'], ['percentage' => 19, 'debit_account_id' => $accounts['vat']->id, 'status' => true]);
    $retention = Retention::firstOrCreate(
        ['name' => 'ReteFuente nc parcial'],
        ['percent' => 2.5, 'type' => 'retefuente', 'account_id' => $accounts['retention']->id, 'status' => true]
    );

    $item = Item::create([
        'category_sub_group_id' => $subGroup->id,
        'name' => 'Insumo '.uniqid(),
        'description' => 'Insumo de prueba',
        'status' => true,
        'tax_id' => $tax->id,
        'tracks_inventory' => false,
        'cost_of_sale_account_id' => $accounts['cost']->id,
    ]);

    $invoice = PurchaseDocument::create([
        'document_type' => PurchaseDocument::TYPE_INVOICE,
        'document_number' => 'FC-CNP-'.uniqid(),
        'contact_id' => $contact->id,
        'status' => 'received',
        'date' => now()->toDateString(),
        'withholdings' => [['retention_id' => $retention->id, 'name' => $retention->name, 'percentage' => 2.5]],
    ]);

    foreach ([[333.33, 63.33], [666.67, 126.67]] as [$total, $totalTax]) {
        PurchaseDocumentItem::create([
            'purchase_document_id' => $invoice->id,
            'item_id' => $item->id,
            'quantity' => 1,
            'price' => $total,
            'tax' => 19,
            'total' => $total,
            'total_tax' => $totalTax,
        ]);
    }

    $entry = app(AccountingPostingService::class)->postPurchaseInvoice($invoice->fresh());

    return [$invoice, $entry, $accounts];
}

/** Nota crédito/débito con un único ítem de $total + $totalTax. */
function cnpNotePayload(PurchaseDocument $invoice, float $total, float $totalTax, array $overrides = []): array
{
    return array_merge([
        'document_type' => PurchaseDocument::TYPE_CREDIT_NOTE,
        'parent_document_id' => $invoice->id,
        'contact_id' => $invoice->contact_id,
        'status' => 'pending',
        'items' => [[
            'item_name' => 'Rollo rechazado',
            'quantity' => 1,
            'price' => $total,
            'total' => $total,
            'total_tax' => $totalTax,
        ]],
    ], $overrides);
}

/** Suma de débito/crédito de un asiento por cuenta: [account_id => [debit, credit]]. */
function cnpByAccount(JournalEntry $entry): array
{
    return $entry->lines()->get()->groupBy('account_id')->map(fn ($lines) => [
        round((float) $lines->sum('debit'), 2),
        round((float) $lines->sum('credit'), 2),
    ])->all();
}

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    $this->contact = Contact::factory()->create();
    $this->notes = app(CreditDebitNoteService::class);
});

it('posts a partial credit note as a new balanced proportional entry with inverted lines and keeps the invoice entry', function () {
    [$invoice, $invoiceEntry, $accounts] = cnpPostedInvoice($this->contact);
    expect(round((float) $invoiceEntry->total_credit, 2))->toBe(CNP_INVOICE_TOTAL);

    $note = $this->notes->create(cnpNotePayload($invoice, 333.33, 63.34)); // 396,67

    $entry = JournalEntry::query()->forSource(AccountingPostingService::SOURCE_CREDIT_NOTE, $note->id)->sole();
    expect($entry->status)->toBe(JournalEntry::STATUS_POSTED)
        ->and($entry->entry_type)->toBe(JournalEntry::TYPE_CREDIT_NOTE)
        ->and($entry->reverses_entry_id)->toBeNull()
        ->and($entry->id)->not->toBe($invoiceEntry->id)
        ->and($entry->isBalanced())->toBeTrue()
        ->and(round((float) $entry->total_debit, 2))->toBe(396.67)
        ->and(round((float) $entry->total_credit, 2))->toBe(396.67);

    // Líneas invertidas y escaladas: la factura debitaba costo/IVA y acreditaba retención/CxP; la nota al revés.
    // Costo: 111,11 + 222,22 (+0,01 de residuo en la mayor) = 333,34; IVA 63,33; retención 8,33; CxP 388,33 (+0,01) = 388,34.
    expect(cnpByAccount($entry))->toBe([
        $accounts['cost']->id => [0.0, 333.34],
        $accounts['vat']->id => [0.0, 63.33],
        $accounts['retention']->id => [8.33, 0.0],
        $accounts['payable']->id => [388.34, 0.0],
    ]);

    // NO revierte el asiento original.
    $invoiceEntry->refresh();
    expect($invoiceEntry->status)->toBe(JournalEntry::STATUS_POSTED)
        ->and($invoiceEntry->reversed_by_entry_id)->toBeNull()
        ->and(JournalEntry::query()->forSource(AccountingPostingService::SOURCE_PURCHASE_INVOICE, $invoice->id)->activePosted()->count())->toBe(1);
});

it('accumulates two partial credit notes and rejects one that exceeds the remaining balance without leaving traces', function () {
    [$invoice, $invoiceEntry] = cnpPostedInvoice($this->contact);

    $first = $this->notes->create(cnpNotePayload($invoice, 400.00, 76.00));   // 476 = 40%
    $second = $this->notes->create(cnpNotePayload($invoice, 300.00, 57.00));  // 357 = 30%

    $accounting = app(AccountingPostingService::class);
    expect($accounting->creditedTotalForInvoice($invoice->id))->toBe(833.0)
        ->and(JournalEntry::query()->forSource('credit_note', $first->id)->sole()->isBalanced())->toBeTrue()
        ->and(round((float) JournalEntry::query()->forSource('credit_note', $second->id)->sole()->total_debit, 2))->toBe(357.0)
        ->and($invoiceEntry->fresh()->status)->toBe(JournalEntry::STATUS_POSTED);

    $entriesBefore = JournalEntry::count();
    $notesBefore = PurchaseDocument::where('document_type', PurchaseDocument::TYPE_CREDIT_NOTE)->count();

    // Saldo acreditable restante: 1.190 - 833 = 357. Una nota de 357,01 lo supera.
    try {
        $this->notes->create(cnpNotePayload($invoice, 300.00, 57.02));
        $this->fail('La nota supera el saldo acreditable.');
    } catch (ValidationException $e) {
        expect($e->errors()['document_type'][0])->toBe('La nota crédito supera el saldo disponible de la factura.');
    }

    expect(JournalEntry::count())->toBe($entriesBefore)
        ->and(PurchaseDocument::where('document_type', PurchaseDocument::TYPE_CREDIT_NOTE)->count())->toBe($notesBefore);

    // El saldo exacto sí se puede acreditar (queda el resto del asiento sin revertir por completo).
    $third = $this->notes->create(cnpNotePayload($invoice, 300.00, 57.00));
    expect($accounting->creditedTotalForInvoice($invoice->id))->toBe(1190.0)
        ->and(JournalEntry::query()->forSource('credit_note', $third->id)->sole()->isBalanced())->toBeTrue();
});

it('rejects a single credit note above the invoice total without creating a note or an entry', function () {
    [$invoice] = cnpPostedInvoice($this->contact);
    $entriesBefore = JournalEntry::count();

    expect(fn () => $this->notes->create(cnpNotePayload($invoice, 1000.00, 190.05)))
        ->toThrow(ValidationException::class, 'supera el saldo disponible');

    expect(JournalEntry::count())->toBe($entriesBefore)
        ->and(PurchaseDocument::where('document_type', PurchaseDocument::TYPE_CREDIT_NOTE)->count())->toBe(0);
});

it('keeps the full reversal path when the note equals the invoice total', function () {
    [$invoice, $invoiceEntry] = cnpPostedInvoice($this->contact);

    $note = $this->notes->create(cnpNotePayload($invoice, 1000.00, 190.00)); // 1.190 = total

    $entry = JournalEntry::query()->forSource('credit_note', $note->id)->sole();
    expect($entry->reverses_entry_id)->toBe($invoiceEntry->id)
        ->and($entry->entry_type)->toBe(JournalEntry::TYPE_CREDIT_NOTE)
        ->and(round((float) $entry->total_debit, 2))->toBe(CNP_INVOICE_TOTAL)
        ->and($invoiceEntry->fresh()->status)->toBe(JournalEntry::STATUS_REVERSED)
        ->and($invoiceEntry->fresh()->reversed_by_entry_id)->toBe($entry->id);

    // Con la factura ya revertida por completo no cabe otra nota.
    expect(fn () => $this->notes->create(cnpNotePayload($invoice, 100.00, 19.00)))
        ->toThrow(ValidationException::class, 'supera el saldo disponible');
});

it('is idempotent: posting the same credit note again returns the active entry without creating another', function () {
    [$invoice] = cnpPostedInvoice($this->contact);
    $accounting = app(AccountingPostingService::class);

    $partial = $this->notes->create(cnpNotePayload($invoice, 100.00, 19.00));
    $entriesBefore = JournalEntry::count();

    $again = $accounting->postCreditNote($partial->fresh());
    expect($again->id)->toBe(JournalEntry::query()->forSource('credit_note', $partial->id)->sole()->id)
        ->and(JournalEntry::count())->toBe($entriesBefore);

    // También con la nota por el total (cuyo asiento es la reversión de la factura).
    [$invoice2] = cnpPostedInvoice($this->contact);
    $full = $this->notes->create(cnpNotePayload($invoice2, 1000.00, 190.00));
    $entriesBefore = JournalEntry::count();

    expect($accounting->postCreditNote($full->fresh())->id)->toBe(JournalEntry::query()->forSource('credit_note', $full->id)->sole()->id)
        ->and(JournalEntry::count())->toBe($entriesBefore);
});

it('does not post any journal entry for a debit note nor call postCreditNote', function () {
    [$invoice] = cnpPostedInvoice($this->contact);

    $this->partialMock(AccountingPostingService::class, function ($mock) {
        $mock->shouldNotReceive('postCreditNote');
        $mock->shouldNotReceive('syncCreditNoteEntry');
    });
    $notes = app(CreditDebitNoteService::class);
    $entriesBefore = JournalEntry::count();

    $note = $notes->create(cnpNotePayload($invoice, 100.00, 19.00, ['document_type' => PurchaseDocument::TYPE_DEBIT_NOTE]));

    expect($note->document_type)->toBe(PurchaseDocument::TYPE_DEBIT_NOTE)
        ->and(JournalEntry::count())->toBe($entriesBefore)
        ->and(JournalEntry::query()->forSource('credit_note', $note->id)->exists())->toBeFalse();
});

it('reposts a pending partial credit note when its amount is edited, and voids its entry when it is deleted', function () {
    [$invoice, $invoiceEntry, $accounts] = cnpPostedInvoice($this->contact);
    $accounting = app(AccountingPostingService::class);

    $note = $this->notes->create(cnpNotePayload($invoice, 100.00, 19.00)); // 119
    $original = JournalEntry::query()->forSource('credit_note', $note->id)->activePosted()->sole();

    // Sin cambio de monto no se toca el asiento.
    $this->notes->update($note, cnpNotePayload($invoice, 100.00, 19.00, ['description' => 'Solo texto']));
    expect(JournalEntry::query()->forSource('credit_note', $note->id)->count())->toBe(1);

    // Cambio de monto: el asiento vigente se revierte y se crea uno nuevo por 238.
    $this->notes->update($note->fresh(), cnpNotePayload($invoice, 200.00, 38.00));
    $active = JournalEntry::query()->forSource('credit_note', $note->id)->activePosted()->sole();
    expect($active->id)->not->toBe($original->id)
        ->and(round((float) $active->total_debit, 2))->toBe(238.0)
        ->and($original->fresh()->status)->toBe(JournalEntry::STATUS_REVERSED)
        ->and($accounting->creditedTotalForInvoice($invoice->id))->toBe(238.0)
        ->and(cnpByAccount($active)[$accounts['payable']->id][0])->toBeGreaterThan(0.0);

    // Eliminar la nota anula su asiento: el par asiento + reversión neutraliza el efecto y el crédito acumulado es 0.
    $this->notes->delete($note->fresh());
    expect(JournalEntry::query()->forSource('credit_note', $note->id)->activePosted()->count())->toBe(0)
        ->and($active->fresh()->status)->toBe(JournalEntry::STATUS_REVERSED)
        ->and($accounting->creditedTotalForInvoice($invoice->id))->toBe(0.0)
        ->and($invoiceEntry->fresh()->status)->toBe(JournalEntry::STATUS_POSTED);

    // Con el saldo liberado se puede acreditar la factura completa.
    $full = $this->notes->create(cnpNotePayload($invoice, 1000.00, 190.00));
    expect(JournalEntry::query()->forSource('credit_note', $full->id)->sole()->reverses_entry_id)->toBe($invoiceEntry->id);
});
