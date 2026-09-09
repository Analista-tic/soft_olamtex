<?php

use App\Models\Income\SaleDocument;
use App\Models\MasterData\Contact;
use App\Models\Settings\User;
use Spatie\Permission\Models\Permission;

/**
 * Fase 11: las 5 pantallas de documentos de venta (Cotizaciones, Pedidos, Facturas,
 * Notas crédito/débito) comparten SaleDocumentService::paginateDocuments()/
 * paginateDocumentsByTypes(), que antes ordenaba siempre por fecha descendente sin
 * posibilidad de cambiarlo. Ahora acepta sort_by/sort_direction (whitelist), con el
 * mismo comportamiento de siempre como fallback.
 */
beforeEach(function () {
    $user = User::factory()->create();
    foreach (['ver todos cotizaciones', 'ver todos pedidos venta', 'ver todos facturas venta'] as $permission) {
        $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }
    $this->actingAs($user);

    $this->contact = Contact::factory()->create();

    SaleDocument::create([
        'document_type' => SaleDocument::TYPE_QUOTE,
        'document_number' => 'COT-002',
        'contact_id' => $this->contact->id,
        'date' => '2026-01-15',
        'status' => 'borrador',
        'created_by_user_id' => $user->id,
    ]);
    SaleDocument::create([
        'document_type' => SaleDocument::TYPE_QUOTE,
        'document_number' => 'COT-001',
        'contact_id' => $this->contact->id,
        'date' => '2026-03-20',
        'status' => 'borrador',
        'created_by_user_id' => $user->id,
    ]);
    SaleDocument::create([
        'document_type' => SaleDocument::TYPE_QUOTE,
        'document_number' => 'COT-003',
        'contact_id' => $this->contact->id,
        'date' => '2026-02-10',
        'status' => 'borrador',
        'created_by_user_id' => $user->id,
    ]);
});

it('sorts quotes by document_number ascending when requested', function () {
    $response = $this->getJson(route('income.quotes.data', ['sort_by' => 'document_number', 'sort_direction' => 'asc']));

    $response->assertOk();
    $numbers = collect($response->json('data'))->pluck('document_number');

    expect($numbers->all())->toBe(['COT-001', 'COT-002', 'COT-003']);
});

it('sorts quotes by date descending by default when no sort is given', function () {
    $response = $this->getJson(route('income.quotes.data'));

    $response->assertOk();
    $numbers = collect($response->json('data'))->pluck('document_number');

    expect($numbers->all())->toBe(['COT-001', 'COT-003', 'COT-002']);
});

it('ignores an invalid sort_by and falls back to date descending', function () {
    $response = $this->getJson(route('income.quotes.data', ['sort_by' => 'contact_id', 'sort_direction' => 'asc']));

    $response->assertOk();
    $numbers = collect($response->json('data'))->pluck('document_number');

    expect($numbers->all())->toBe(['COT-001', 'COT-003', 'COT-002']);
});
