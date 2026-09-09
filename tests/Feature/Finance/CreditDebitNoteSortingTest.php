<?php

use App\Models\Expenses\PurchaseDocument;
use App\Models\MasterData\Contact;
use App\Models\Settings\User;
use Spatie\Permission\Models\Permission;

/**
 * Fase 11: CreditDebitNoteService::paginate() (compras) tenía el orden fijo en
 * fecha desc. Ahora acepta sort_by (date/document_number/status) + sort_direction,
 * con el mismo fallback de siempre.
 */
beforeEach(function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('ver notas credito debito', 'web'));
    $this->actingAs($user);

    $contact = Contact::factory()->create();

    PurchaseDocument::create([
        'document_number' => 'NC-002',
        'document_type' => PurchaseDocument::TYPE_CREDIT_NOTE,
        'contact_id' => $contact->id,
        'date' => '2026-01-10',
        'status' => 'pending',
    ]);
    PurchaseDocument::create([
        'document_number' => 'NC-001',
        'document_type' => PurchaseDocument::TYPE_CREDIT_NOTE,
        'contact_id' => $contact->id,
        'date' => '2026-03-05',
        'status' => 'pending',
    ]);
    PurchaseDocument::create([
        'document_number' => 'NC-003',
        'document_type' => PurchaseDocument::TYPE_CREDIT_NOTE,
        'contact_id' => $contact->id,
        'date' => '2026-02-15',
        'status' => 'pending',
    ]);
});

it('sorts credit/debit notes by document_number ascending when requested', function () {
    $response = $this->getJson(route('notas-credito-debito.data', ['sort_by' => 'document_number', 'sort_direction' => 'asc']));

    $response->assertOk();
    $numbers = collect($response->json('data'))->pluck('document_number');

    expect($numbers->all())->toBe(['NC-001', 'NC-002', 'NC-003']);
});

it('sorts credit/debit notes by date descending by default', function () {
    $response = $this->getJson(route('notas-credito-debito.data'));

    $response->assertOk();
    $numbers = collect($response->json('data'))->pluck('document_number');

    expect($numbers->all())->toBe(['NC-001', 'NC-003', 'NC-002']);
});
