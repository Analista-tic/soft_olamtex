<?php

use App\Models\Production\ProductSheet;
use App\Models\Settings\User;
use Spatie\Permission\Models\Permission;

/**
 * Fase 11: ProductSheetController::getData() ordenaba fijo por id desc. Ahora acepta
 * sort_by (id/sample_reference/product_reference/status) + sort_direction.
 */
beforeEach(function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('ver sabana productos', 'web'));
    $this->actingAs($user);

    ProductSheet::create([
        'section_id' => 1,
        'group_id' => 1,
        'brand_id' => 1,
        'world_id' => 1,
        'type' => 'producto',
        'billing_price' => 1000,
        'selling_price' => 1200,
        'sizes' => ['M'],
        'woven_product' => false,
        'description' => 'Ficha B',
        'sample_reference' => 'MUESTRA-B',
        'product_reference' => 'PROD-B',
        'processes' => ['Corte'],
        'status' => 'complete',
    ]);
    ProductSheet::create([
        'section_id' => 1,
        'group_id' => 1,
        'brand_id' => 1,
        'world_id' => 1,
        'type' => 'producto',
        'billing_price' => 1000,
        'selling_price' => 1200,
        'sizes' => ['M'],
        'woven_product' => false,
        'description' => 'Ficha A',
        'sample_reference' => 'MUESTRA-A',
        'product_reference' => 'PROD-A',
        'processes' => ['Corte'],
        'status' => 'complete',
    ]);
});

it('sorts product sheets by sample_reference ascending when requested', function () {
    $response = $this->getJson(route('produccion.product-sheets.getData', ['sort_by' => 'sample_reference', 'sort_direction' => 'asc']));

    $response->assertOk();
    $refs = collect($response->json('products_sheets.data'))->pluck('sample_reference');

    expect($refs->all())->toBe(['MUESTRA-A', 'MUESTRA-B']);
});

it('falls back to id descending when no sort is given', function () {
    $response = $this->getJson(route('produccion.product-sheets.getData'));

    $response->assertOk();
    $refs = collect($response->json('products_sheets.data'))->pluck('sample_reference');

    expect($refs->all())->toBe(['MUESTRA-A', 'MUESTRA-B']);
});
