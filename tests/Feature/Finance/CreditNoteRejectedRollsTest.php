<?php

use App\Models\Expenses\PurchaseDocument;
use App\Models\Expenses\PurchaseDocumentItem;
use App\Models\Expenses\PurchaseDocumentReceptionEvent;
use App\Models\Finance\AccountingAccount;
use App\Models\Finance\Tax;
use App\Models\Inventory\InventoryMovement;
use App\Models\Inventory\Warehouse;
use App\Models\MasterData\CategoryGroup;
use App\Models\MasterData\CategorySubGroup;
use App\Models\MasterData\Contact;
use App\Models\MasterData\Item;
use App\Models\Settings\User;
use App\Models\Textil\FabricRoll;
use App\Models\Textil\FabricRollCreditNoteLink;
use App\Services\Expenses\PurchaseDocumentInvoiceService;
use App\Services\Expenses\PurchasePaymentService;
use App\Services\Finance\AccountingPostingService;
use App\Services\Finance\CreditDebitNoteService;
use App\Services\Textil\FabricRollCreditNoteService;
use App\Services\Textil\FabricRollService;
use App\Support\MasterData\MaterialClassifier;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;

/*
 * Rollos rechazados en recepción <-> nota crédito (enlace, baja al aprobar) y retención de pago por monto.
 * Números: línea de 500 m a $100/m = $50.000 + IVA 19% ($9.500) = $59.500. Cada rollo de 100 m rechazado
 * "vale" 100 * 100 * 1.19 = $11.900.
 */

function cnrFabricItem(): Item
{
    $group = CategoryGroup::firstOrCreate(['name' => 'TELAS NOTA CREDITO TEST']);
    $subGroup = CategorySubGroup::firstOrCreate(['name' => 'Tela nota credito test', 'category_group_id' => $group->id]);
    $subGroup->forceFill(['material_kind' => 'fabric'])->save();
    MaterialClassifier::forgetCache();
    Warehouse::firstOrCreate(['name' => 'Bodega nota credito tela'], ['observation' => 'Test']);

    // Plan de cuentas mínimo para contabilizar la factura de verdad (cuenta de costo, IVA y cuentas por pagar).
    $account = fn (string $code, string $type, ?string $special = null) => AccountingAccount::firstOrCreate(
        ['code' => $code],
        ['name' => 'Cuenta '.$code, 'account_type' => $type, 'level' => 1, 'allows_entries' => true, 'is_active' => true, 'special_type' => $special]
    );
    $cost = $account('5135', AccountingAccount::TYPE_COST);
    $vat = $account('240810', AccountingAccount::TYPE_ASSET);
    $account('220505', AccountingAccount::TYPE_LIABILITY, AccountingAccount::SPECIAL_ACCOUNTS_PAYABLE);
    $tax = Tax::firstOrCreate(['name' => 'IVA 19% test'], ['percentage' => 19, 'debit_account_id' => $vat->id, 'status' => true]);

    return Item::create([
        'category_sub_group_id' => $subGroup->id,
        'name' => 'Tela nota credito '.uniqid(),
        'description' => 'Tela de prueba',
        'status' => true,
        'tax_id' => $tax->id,
        'tracks_inventory' => false,
        'cost_of_sale_account_id' => $cost->id,
    ]);
}

/** Factura de tela pendiente de recepción con 5 rollos de 100 m; devuelve [factura, línea, rollos]. */
function cnrInvoiceWithRolls(Contact $contact, int $rollCount = 5): array
{
    $item = cnrFabricItem();

    $invoice = PurchaseDocument::create([
        'document_type' => PurchaseDocument::TYPE_INVOICE,
        'document_number' => 'FT-CNR-'.uniqid(),
        'contact_id' => $contact->id,
        'status' => 'pending_receipt',
        'date' => now()->toDateString(),
    ]);
    $line = PurchaseDocumentItem::create([
        'purchase_document_id' => $invoice->id,
        'item_id' => $item->id,
        'quantity' => 500,
        'price' => 100,
        'tax' => 19,
        'total' => 50000,
        'total_tax' => 9500,
    ]);

    $service = app(FabricRollService::class);
    $rolls = collect(range(1, $rollCount))->map(fn () => $service->storeFromReception([
        'purchase_document_item_id' => $line->id,
        'quantity' => 100,
        'roll_width' => 1.5,
        'entry_date' => now()->toDateString(),
    ]));

    return [$invoice, $line, $rolls];
}

function cnrReject(FabricRoll $roll, string $reason = 'Tela manchada'): FabricRoll
{
    return app(FabricRollService::class)->rejectRoll($roll->id, $reason);
}

/**
 * Tras rechazar rollos la factura pasa a "recibida" (estado válido para notas y pagos) y se contabiliza
 * con el posteo real: la nota crédito exige el asiento de la factura padre (total $59.500).
 */
function cnrMarkReceived(PurchaseDocument $invoice): void
{
    $invoice->forceFill(['status' => 'received'])->save();
    app(AccountingPostingService::class)->postPurchaseInvoice($invoice->fresh());
}

function cnrNotePayload(PurchaseDocument $invoice, array $overrides = []): array
{
    return array_merge([
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
    ], $overrides);
}

beforeEach(function () {
    $this->user = User::factory()->create();
    foreach (['ver notas credito debito', 'crear notas credito debito', 'editar notas credito debito', 'eliminar notas credito debito'] as $permission) {
        $this->user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }
    $this->actingAs($this->user);

    $this->contact = Contact::factory()->create();
    $this->notes = app(CreditDebitNoteService::class);
    $this->links = app(FabricRollCreditNoteService::class);
});

// ─── Enlace rollo <-> nota crédito ────────────────────────────────────────────

it('links valid rejected rolls to a credit note and records an event per roll', function () {
    [$invoice, , $rolls] = cnrInvoiceWithRolls($this->contact);
    $a = cnrReject($rolls[0]);
    $b = cnrReject($rolls[1], 'Ancho incorrecto');
    cnrMarkReceived($invoice);

    $note = $this->notes->create(cnrNotePayload($invoice, ['fabric_roll_ids' => [$a->id, $b->id]]));

    expect(FabricRollCreditNoteLink::where('credit_note_document_id', $note->id)->pluck('fabric_roll_id')->sort()->values()->all())
        ->toBe([$a->id, $b->id])
        ->and(PurchaseDocumentReceptionEvent::where('purchase_document_id', $invoice->id)
            ->where('event_type', PurchaseDocumentReceptionEvent::TYPE_ROLL_LINKED_TO_NOTE)->count())->toBe(2)
        ->and($a->fresh()->resolved_at)->toBeNull()
        ->and(collect($this->notes->listLinkedRolls($note))->pluck('id')->sort()->values()->all())->toBe([$a->id, $b->id]);
});

it('does not link anything when fabric_roll_ids is omitted', function () {
    [$invoice, , $rolls] = cnrInvoiceWithRolls($this->contact);
    cnrReject($rolls[0]);
    cnrMarkReceived($invoice);

    $note = $this->notes->create(cnrNotePayload($invoice));

    expect(FabricRollCreditNoteLink::where('credit_note_document_id', $note->id)->count())->toBe(0);
});

it('rejects linking rolls to a debit note', function () {
    [$invoice, , $rolls] = cnrInvoiceWithRolls($this->contact);
    $a = cnrReject($rolls[0]);
    cnrMarkReceived($invoice);

    try {
        $this->notes->create(cnrNotePayload($invoice, [
            'document_type' => PurchaseDocument::TYPE_DEBIT_NOTE,
            'fabric_roll_ids' => [$a->id],
        ]));
        $this->fail('Una nota débito no puede cubrir rollos.');
    } catch (ValidationException $e) {
        expect($e->errors()['fabric_roll_ids'][0])->toContain('nota crédito');
    }

    expect(FabricRollCreditNoteLink::count())->toBe(0)
        ->and(PurchaseDocument::where('document_type', PurchaseDocument::TYPE_DEBIT_NOTE)->count())->toBe(0);
});

it('rejects a roll that belongs to another invoice', function () {
    [$invoice, , $rolls] = cnrInvoiceWithRolls($this->contact);
    cnrReject($rolls[0]);
    cnrMarkReceived($invoice);

    [$otherInvoice, , $otherRolls] = cnrInvoiceWithRolls($this->contact);
    $foreign = cnrReject($otherRolls[0]);
    cnrMarkReceived($otherInvoice);

    try {
        $this->notes->create(cnrNotePayload($invoice, ['fabric_roll_ids' => [$foreign->id]]));
        $this->fail('El rollo es de otra factura.');
    } catch (ValidationException $e) {
        expect($e->errors()['fabric_roll_ids'][0])->toContain('no pertenece a la factura');
    }

    expect(FabricRollCreditNoteLink::count())->toBe(0);
});

it('rejects a roll that is not rejected or is already resolved', function () {
    [$invoice, , $rolls] = cnrInvoiceWithRolls($this->contact);
    $rejected = cnrReject($rolls[0]);
    $notRejected = $rolls[1];
    cnrMarkReceived($invoice);

    expect(fn () => $this->notes->create(cnrNotePayload($invoice, ['fabric_roll_ids' => [$notRejected->id]])))
        ->toThrow(ValidationException::class, 'no está rechazado');

    $rejected->forceFill(['resolved_at' => now()])->save();
    expect(fn () => $this->notes->create(cnrNotePayload($invoice, ['fabric_roll_ids' => [$rejected->id]])))
        ->toThrow(ValidationException::class, 'ya fue resuelto');

    expect(FabricRollCreditNoteLink::count())->toBe(0);
});

it('rejects a roll that already has an active link to another note, but allows it after that note is deleted', function () {
    [$invoice, , $rolls] = cnrInvoiceWithRolls($this->contact);
    $a = cnrReject($rolls[0]);
    cnrMarkReceived($invoice);

    $first = $this->notes->create(cnrNotePayload($invoice, ['fabric_roll_ids' => [$a->id]]));

    try {
        $this->notes->create(cnrNotePayload($invoice, ['fabric_roll_ids' => [$a->id]]));
        $this->fail('El rollo ya está enlazado a otra nota.');
    } catch (ValidationException $e) {
        expect($e->errors()['fabric_roll_ids'][0])->toContain($first->document_number);
    }

    $this->notes->delete($first);
    $second = $this->notes->create(cnrNotePayload($invoice, ['fabric_roll_ids' => [$a->id]]));

    expect(FabricRollCreditNoteLink::where('fabric_roll_id', $a->id)->pluck('credit_note_document_id')->all())->toBe([$second->id]);
});

it('rejects linking rolls to a note of a non-fabric invoice', function () {
    $invoice = PurchaseDocument::create([
        'document_type' => PurchaseDocument::TYPE_INVOICE,
        'document_number' => 'FG-CNR-'.uniqid(),
        'contact_id' => $this->contact->id,
        'status' => 'received',
        'date' => now()->toDateString(),
    ]);
    PurchaseDocumentItem::create([
        'purchase_document_id' => $invoice->id,
        'item_name' => 'Tornillos',
        'quantity' => 10,
        'price' => 100,
        'total' => 1000,
        'total_tax' => 190,
    ]);

    expect(fn () => $this->notes->create(cnrNotePayload($invoice, ['fabric_roll_ids' => [999999]])))
        ->toThrow(ValidationException::class, 'factura de tela');
});

it('syncs the linked rolls on update (adds, removes, and leaves them untouched when the key is omitted)', function () {
    [$invoice, , $rolls] = cnrInvoiceWithRolls($this->contact);
    $a = cnrReject($rolls[0]);
    $b = cnrReject($rolls[1]);
    cnrMarkReceived($invoice);

    $note = $this->notes->create(cnrNotePayload($invoice, ['fabric_roll_ids' => [$a->id]]));

    $this->notes->update($note, cnrNotePayload($invoice, ['fabric_roll_ids' => [$b->id]]));
    expect(FabricRollCreditNoteLink::where('credit_note_document_id', $note->id)->pluck('fabric_roll_id')->all())->toBe([$b->id]);

    $this->notes->update($note->fresh(), cnrNotePayload($invoice));
    expect(FabricRollCreditNoteLink::where('credit_note_document_id', $note->id)->pluck('fabric_roll_id')->all())->toBe([$b->id]);

    $this->notes->update($note->fresh(), cnrNotePayload($invoice, ['fabric_roll_ids' => []]));
    expect(FabricRollCreditNoteLink::where('credit_note_document_id', $note->id)->count())->toBe(0);
});

// ─── Aprobar la nota da de baja los rollos ────────────────────────────────────

it('writes the rolls off when the note is approved, with an event each and no inventory movement', function () {
    [$invoice, , $rolls] = cnrInvoiceWithRolls($this->contact);
    $a = cnrReject($rolls[0]);
    $b = cnrReject($rolls[1]);
    cnrMarkReceived($invoice);
    $movementsBefore = InventoryMovement::count();

    $note = $this->notes->create(cnrNotePayload($invoice, ['fabric_roll_ids' => [$a->id, $b->id]]));
    expect($a->fresh()->written_off_at)->toBeNull();

    $this->notes->update($note, cnrNotePayload($invoice, ['status' => 'approved']));

    foreach ([$a, $b] as $roll) {
        $fresh = $roll->fresh();
        expect($fresh->resolved_at)->not->toBeNull()
            ->and($fresh->written_off_at)->not->toBeNull()
            ->and($fresh->approval_status)->toBe(FabricRoll::APPROVAL_NOT_APPROVED)
            ->and($fresh->tolerance_status)->toBe(FabricRoll::TOLERANCE_REJECTED);
    }

    expect(PurchaseDocumentReceptionEvent::where('purchase_document_id', $invoice->id)
        ->where('event_type', PurchaseDocumentReceptionEvent::TYPE_ROLL_WRITTEN_OFF)->count())->toBe(2)
        ->and(InventoryMovement::count())->toBe($movementsBefore);

    // Una nota aprobada ya no se edita ni se elimina, y los rollos resueltos no se pueden volver a enlazar.
    expect(fn () => $this->notes->delete($note->fresh()))->toThrow(ValidationException::class);
    expect(FabricRollCreditNoteLink::where('credit_note_document_id', $note->id)->count())->toBe(2);
});

it('writes the rolls off immediately when the note is created already approved', function () {
    [$invoice, , $rolls] = cnrInvoiceWithRolls($this->contact);
    $a = cnrReject($rolls[0]);
    cnrMarkReceived($invoice);

    $this->notes->create(cnrNotePayload($invoice, ['status' => 'approved', 'fabric_roll_ids' => [$a->id]]));

    expect($a->fresh()->resolved_at)->not->toBeNull()
        ->and($a->fresh()->written_off_at)->not->toBeNull();
});

it('does not write anything off on a plain update that keeps the note pending', function () {
    [$invoice, , $rolls] = cnrInvoiceWithRolls($this->contact);
    $a = cnrReject($rolls[0]);
    cnrMarkReceived($invoice);

    $note = $this->notes->create(cnrNotePayload($invoice, ['fabric_roll_ids' => [$a->id]]));
    $this->notes->update($note, cnrNotePayload($invoice, ['description' => 'Cambio de texto']));

    expect($a->fresh()->resolved_at)->toBeNull();
});

it('deletes the links when the note is deleted', function () {
    [$invoice, , $rolls] = cnrInvoiceWithRolls($this->contact);
    $a = cnrReject($rolls[0]);
    cnrMarkReceived($invoice);

    $note = $this->notes->create(cnrNotePayload($invoice, ['fabric_roll_ids' => [$a->id]]));
    expect(FabricRollCreditNoteLink::count())->toBe(1);

    $this->notes->delete($note);

    expect(FabricRollCreditNoteLink::count())->toBe(0)
        ->and($a->fresh()->resolved_at)->toBeNull()
        ->and($a->fresh()->tolerance_status)->toBe(FabricRoll::TOLERANCE_REJECTED);
});

// ─── Endpoints ────────────────────────────────────────────────────────────────

it('lists the coverable rejected rolls of the parent invoice over HTTP, hiding those linked to another note', function () {
    [$invoice, , $rolls] = cnrInvoiceWithRolls($this->contact);
    $a = cnrReject($rolls[0]);
    $b = cnrReject($rolls[1]);
    cnrMarkReceived($invoice);
    $note = $this->notes->create(cnrNotePayload($invoice, ['fabric_roll_ids' => [$a->id]]));

    $free = $this->getJson(route('notas-credito-debito.rejected-rolls', ['parent_document_id' => $invoice->id]))->assertOk();
    expect(collect($free->json('data'))->pluck('id')->all())->toBe([$b->id])
        ->and($free->json('data.0.amount'))->toEqual(11900)
        ->and($free->json('data.0.reason'))->toBe('Tela manchada');

    $editing = $this->getJson(route('notas-credito-debito.rejected-rolls', ['parent_document_id' => $invoice->id, 'note_id' => $note->id]))->assertOk();
    expect(collect($editing->json('data'))->pluck('id')->sort()->values()->all())->toBe([$a->id, $b->id]);

    $show = $this->getJson(route('notas-credito-debito.show', $note->id))->assertOk();
    expect(collect($show->json('data.linked_fabric_rolls'))->pluck('id')->all())->toBe([$a->id]);
});

it('validates fabric_roll_ids over HTTP and answers 422 for a debit note', function () {
    [$invoice, , $rolls] = cnrInvoiceWithRolls($this->contact);
    $a = cnrReject($rolls[0]);
    cnrMarkReceived($invoice);

    $this->postJson(route('notas-credito-debito.store'), cnrNotePayload($invoice, [
        'document_type' => PurchaseDocument::TYPE_DEBIT_NOTE,
        'fabric_roll_ids' => [$a->id],
    ]))->assertStatus(422)->assertJsonValidationErrors('fabric_roll_ids');

    $this->postJson(route('notas-credito-debito.store'), cnrNotePayload($invoice, ['fabric_roll_ids' => ['abc']]))
        ->assertStatus(422)->assertJsonValidationErrors('fabric_roll_ids.0');

    $created = $this->postJson(route('notas-credito-debito.store'), cnrNotePayload($invoice, ['fabric_roll_ids' => [$a->id]]))->assertCreated();
    expect(collect($created->json('data.linked_fabric_rolls'))->pluck('id')->all())->toBe([$a->id]);
});

// ─── Monto en disputa ─────────────────────────────────────────────────────────

it('computes the disputed amount as quantity x price + proportional tax of the rejected, unresolved rolls', function () {
    [$invoice, , $rolls] = cnrInvoiceWithRolls($this->contact);

    expect($this->links->disputedAmount($invoice))->toBe(0.0);

    $a = cnrReject($rolls[0]);
    cnrReject($rolls[1]);
    // 100 m * $100 * (1 + 9500/50000) = $11.900 por rollo.
    expect($this->links->disputedAmount($invoice))->toBe(23800.0)
        ->and($this->links->disputedSummary($invoice))->toBe(['amount' => 23800.0, 'rolls' => 2]);

    $a->forceFill(['resolved_at' => now()])->save();
    expect($this->links->disputedSummary($invoice))->toBe(['amount' => 11900.0, 'rolls' => 1]);
});

// ─── Pago con tope = saldo - disputa ──────────────────────────────────────────

it('caps the payment at balance minus disputed amount and lets the undisputed rest be paid', function () {
    [$invoice, , $rolls] = cnrInvoiceWithRolls($this->contact);
    cnrReject($rolls[0]);
    cnrReject($rolls[1]);
    cnrMarkReceived($invoice);
    $payments = app(PurchasePaymentService::class);

    $summary = $payments->getInvoicePaymentSummary($invoice);
    expect($summary['current_balance'])->toEqual(59500)
        ->and($summary['disputed_amount'])->toEqual(23800)
        ->and($summary['disputed_rolls_count'])->toBe(2)
        ->and($summary['payable_balance'])->toEqual(35700);

    // Un peso por encima del saldo pagable -> 422 con el monto retenido.
    try {
        $payments->registerInvoicePayment(['purchase_invoice_id' => $invoice->id, 'amount' => 35700.01]);
        $this->fail('Debió superar el saldo pagable.');
    } catch (ValidationException $e) {
        expect($e->errors()['amount'][0])
            ->toBe('El pago supera el saldo pagable; $23,800.00 están retenidos por 2 rollos rechazados sin nota crédito aprobada.');
    }
    expect($invoice->payments()->count())->toBe(0);

    // El resto no disputado sí se paga.
    $payments->registerInvoicePayment(['purchase_invoice_id' => $invoice->id, 'amount' => 35700]);
    $after = $payments->getInvoicePaymentSummary($invoice);
    expect($after['current_balance'])->toEqual(23800)
        ->and($after['payable_balance'])->toEqual(0);

    expect(fn () => $payments->registerInvoicePayment(['purchase_invoice_id' => $invoice->id, 'amount' => 1]))
        ->toThrow(ValidationException::class, 'retenidos por 2 rollos rechazados');
});

it('uses singular wording when a single roll is retained', function () {
    [$invoice, , $rolls] = cnrInvoiceWithRolls($this->contact);
    cnrReject($rolls[0]);
    cnrMarkReceived($invoice);

    expect(fn () => app(PurchasePaymentService::class)->registerInvoicePayment(['purchase_invoice_id' => $invoice->id, 'amount' => 59500]))
        ->toThrow(ValidationException::class, '$11,900.00 están retenidos por 1 rollo rechazado sin nota crédito aprobada.');
});

it('frees the payment once the credit note is approved: the amount leaves the dispute and the balance at once', function () {
    [$invoice, , $rolls] = cnrInvoiceWithRolls($this->contact);
    $a = cnrReject($rolls[0]);
    cnrMarkReceived($invoice);
    $payments = app(PurchasePaymentService::class);

    // La nota cubre exactamente el rollo ($10.000 + $1.900 = $11.900), aún pendiente: sigue la retención.
    $note = $this->notes->create(cnrNotePayload($invoice, ['fabric_roll_ids' => [$a->id]]));
    expect($payments->getInvoicePaymentSummary($invoice)['payable_balance'])->toEqual(47600);
    expect(fn () => $payments->registerInvoicePayment(['purchase_invoice_id' => $invoice->id, 'amount' => 59500]))
        ->toThrow(ValidationException::class, 'retenidos');

    $this->notes->update($note, cnrNotePayload($invoice, ['status' => 'approved']));

    // Sin doble conteo: saldo 59.500 - 11.900 (nota) = 47.600 y disputa 0 -> pagable 47.600.
    $summary = $payments->getInvoicePaymentSummary($invoice->fresh());
    expect($summary['credit_notes_total'])->toEqual(11900)
        ->and($summary['current_balance'])->toEqual(47600)
        ->and($summary['disputed_amount'])->toEqual(0)
        ->and($summary['payable_balance'])->toEqual(47600);

    $payments->registerInvoicePayment(['purchase_invoice_id' => $invoice->id, 'amount' => 47600]);
    expect($payments->getInvoicePaymentSummary($invoice->fresh())['current_balance'])->toEqual(0);
});

it('changes nothing when the invoice has no disputed rolls', function () {
    [$invoice] = cnrInvoiceWithRolls($this->contact);
    cnrMarkReceived($invoice);
    $payments = app(PurchasePaymentService::class);

    $summary = $payments->getInvoicePaymentSummary($invoice);
    expect($summary['disputed_amount'])->toEqual(0)
        ->and($summary['disputed_rolls_count'])->toBe(0)
        ->and($summary['payable_balance'])->toEqual($summary['current_balance']);

    // Mensaje original intacto.
    expect(fn () => $payments->registerInvoicePayment(['purchase_invoice_id' => $invoice->id, 'amount' => 59500.01]))
        ->toThrow(ValidationException::class, 'El pago supera el saldo pendiente de la factura.');

    $payments->registerInvoicePayment(['purchase_invoice_id' => $invoice->id, 'amount' => 59500]);
    expect($payments->getInvoicePaymentSummary($invoice)['current_balance'])->toEqual(0);
});

it('exposes disputed_amount and payable_balance in accounts payable rows', function () {
    [$invoice, , $rolls] = cnrInvoiceWithRolls($this->contact);
    cnrReject($rolls[0]);
    cnrMarkReceived($invoice);
    [$clean] = cnrInvoiceWithRolls($this->contact);
    cnrMarkReceived($clean);

    $rows = collect(app(PurchaseDocumentInvoiceService::class)->paginateAccountsPayable(['provider_id' => $this->contact->id])->items())
        ->keyBy('invoice_id');

    expect($rows[$invoice->id]['disputed_amount'])->toEqual(11900)
        ->and($rows[$invoice->id]['disputed_rolls_count'])->toBe(1)
        ->and($rows[$invoice->id]['payable_balance'])->toEqual(47600)
        ->and($rows[$invoice->id]['current_balance'])->toEqual(59500)
        ->and($rows[$clean->id]['disputed_amount'])->toEqual(0)
        ->and($rows[$clean->id]['payable_balance'])->toEqual(59500);
});
