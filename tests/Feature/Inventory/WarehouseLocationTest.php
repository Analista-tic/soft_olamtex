<?php

use App\Models\Inventory\WarehouseLocation;
use App\Models\Settings\User;

/**
 * Smoke test del catálogo de ubicaciones de bodega: ahora se gestiona (crear/editar/eliminar)
 * desde el select de ubicación en la recepción de rollos de tela, además de en ítems de MasterData.
 */
beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('creates a warehouse location', function () {
    $response = $this->postJson(route('warehouse-locations.store'), ['name' => 'A1']);

    $response->assertCreated();
    $response->assertJsonFragment(['name' => 'A1']);
    expect(WarehouseLocation::where('name', 'A1')->exists())->toBeTrue();
});

it('updates a warehouse location', function () {
    $location = WarehouseLocation::create(['name' => 'A1', 'status' => true]);

    $response = $this->putJson(route('warehouse-locations.update', $location), ['name' => 'A1 - Estante nuevo']);

    $response->assertOk();
    $response->assertJsonFragment(['name' => 'A1 - Estante nuevo']);
    expect($location->fresh()->name)->toBe('A1 - Estante nuevo');
});

it('deletes a warehouse location that is not in use', function () {
    $location = WarehouseLocation::create(['name' => 'A1', 'status' => true]);

    $response = $this->deleteJson(route('warehouse-locations.destroy', $location));

    $response->assertNoContent();
    expect(WarehouseLocation::find($location->id))->toBeNull();
});

it('lists warehouse locations for the select', function () {
    WarehouseLocation::create(['name' => 'B2', 'status' => true]);
    WarehouseLocation::create(['name' => 'A1', 'status' => true]);

    $response = $this->getJson(route('warehouse-locations.index'));

    $response->assertOk();
    $names = collect($response->json('data'))->pluck('name');
    expect($names->all())->toContain('A1', 'B2');
});
