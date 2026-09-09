<?php

use App\Models\Inventory\Warehouse;
use App\Models\Settings\User;

/**
 * Fase 11: WarehouseController::getData() ordenaba fijo por id desc. Ahora acepta
 * sort_by (name/observation) + sort_direction.
 */
beforeEach(function () {
    $this->actingAs(User::factory()->create());

    Warehouse::create(['name' => 'Bodega Norte', 'observation' => 'Zona norte']);
    Warehouse::create(['name' => 'Bodega Centro', 'observation' => 'Zona centro']);
    Warehouse::create(['name' => 'Bodega Sur', 'observation' => 'Zona sur']);
});

it('sorts warehouses by name ascending when requested', function () {
    $response = $this->getJson(route('warehouses.getData', ['sort_by' => 'name', 'sort_direction' => 'asc']));

    $response->assertOk();
    $names = collect($response->json('data'))->pluck('name');

    expect($names->all())->toBe(['Bodega Centro', 'Bodega Norte', 'Bodega Sur']);
});

it('falls back to id descending when no sort is given', function () {
    $response = $this->getJson(route('warehouses.getData'));

    $response->assertOk();
    $names = collect($response->json('data'))->pluck('name');

    expect($names->all())->toBe(['Bodega Sur', 'Bodega Centro', 'Bodega Norte']);
});
