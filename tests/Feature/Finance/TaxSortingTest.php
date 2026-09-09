<?php

use App\Models\Finance\Tax;
use App\Models\Settings\User;
use Spatie\Permission\Models\Permission;

/**
 * Fase 11: TaxController::data() ya ordenaba fijo por nombre. Ahora acepta
 * sort_by (name/percentage) + sort_direction.
 */
beforeEach(function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('ver impuestos', 'web'));
    $this->actingAs($user);

    Tax::create(['name' => 'IVA 19%', 'percentage' => 19, 'status' => true]);
    Tax::create(['name' => 'IVA 5%', 'percentage' => 5, 'status' => true]);
    Tax::create(['name' => 'Exento', 'percentage' => 0, 'status' => true]);
});

it('sorts taxes by percentage ascending when requested', function () {
    $response = $this->getJson(route('impuestos.data', ['sort_by' => 'percentage', 'sort_direction' => 'asc']));

    $response->assertOk();
    $names = collect($response->json('data'))->pluck('name');

    expect($names->all())->toBe(['Exento', 'IVA 5%', 'IVA 19%']);
});

it('sorts taxes by name ascending by default', function () {
    $response = $this->getJson(route('impuestos.data'));

    $response->assertOk();
    $names = collect($response->json('data'))->pluck('name');

    expect($names->all())->toBe(['Exento', 'IVA 19%', 'IVA 5%']);
});
