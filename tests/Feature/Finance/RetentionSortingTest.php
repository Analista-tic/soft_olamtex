<?php

use App\Models\Finance\Retention;
use App\Models\Settings\User;
use Spatie\Permission\Models\Permission;

/**
 * Fase 11: RetentionService::paginate() ya paginaba de verdad (LengthAwarePaginator)
 * pero el orden estaba fijo en id desc. Ahora acepta sort_by (name/percent/type) +
 * sort_direction, con el mismo fallback de siempre si no se pide orden.
 */
beforeEach(function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('ver retenciones', 'web'));
    $this->actingAs($user);

    Retention::create(['name' => 'Retefuente B', 'percent' => 2.5, 'type' => 'renta', 'status' => 'active']);
    Retention::create(['name' => 'Retefuente A', 'percent' => 3.5, 'type' => 'renta', 'status' => 'active']);
    Retention::create(['name' => 'Reteica C', 'percent' => 1.0, 'type' => 'ica', 'status' => 'active']);
});

it('sorts retentions by name ascending when requested', function () {
    $response = $this->getJson(route('retenciones.data', ['sort_by' => 'name', 'sort_direction' => 'asc']));

    $response->assertOk();
    $names = collect($response->json('data'))->pluck('name');

    expect($names->all())->toBe(['Retefuente A', 'Retefuente B', 'Reteica C']);
});

it('sorts retentions by percent descending when requested', function () {
    $response = $this->getJson(route('retenciones.data', ['sort_by' => 'percent', 'sort_direction' => 'desc']));

    $response->assertOk();
    $names = collect($response->json('data'))->pluck('name');

    expect($names->first())->toBe('Retefuente A')
        ->and($names->last())->toBe('Reteica C');
});

it('falls back to id order when sort_by is not whitelisted', function () {
    $response = $this->getJson(route('retenciones.data', ['sort_by' => 'status', 'sort_direction' => 'asc']));

    $response->assertOk();
    $names = collect($response->json('data'))->pluck('name');

    expect($names->all())->toBe(['Reteica C', 'Retefuente A', 'Retefuente B']);
});
