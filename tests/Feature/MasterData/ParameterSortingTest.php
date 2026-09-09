<?php

use App\Models\Settings\Setting;
use App\Models\Settings\User;
use Spatie\Permission\Models\Permission;

/**
 * Fase 11: la pantalla de Parámetros (MasterData/Parameters/Index.vue) ahora ordena
 * por columna, usando sort_by/sort_direction que el backend ya soportaba pero el
 * frontend no explotaba.
 */
beforeEach(function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('ver parametros', 'web'));
    $this->actingAs($user);

    Setting::create(['kind' => Setting::KIND_PARAMETER, 'key' => 'sort-test-b', 'label' => 'B Parametro', 'value' => [], 'status' => true]);
    Setting::create(['kind' => Setting::KIND_PARAMETER, 'key' => 'sort-test-a', 'label' => 'A Parametro', 'value' => [], 'status' => true]);
    Setting::create(['kind' => Setting::KIND_PARAMETER, 'key' => 'sort-test-c', 'label' => 'C Parametro', 'value' => [], 'status' => true]);
});

it('sorts parameters by label descending when requested', function () {
    $response = $this->getJson(route('parametros.data', ['sort_by' => 'label', 'sort_direction' => 'desc']));

    $response->assertOk();
    $labels = collect($response->json('data'))->pluck('label')->filter(fn ($l) => str_contains($l, 'Parametro'))->values();

    expect($labels->first())->toBe('C Parametro')
        ->and($labels->last())->toBe('A Parametro');
});

it('sorts parameters by label ascending when requested', function () {
    $response = $this->getJson(route('parametros.data', ['sort_by' => 'label', 'sort_direction' => 'asc']));

    $response->assertOk();
    $labels = collect($response->json('data'))->pluck('label')->filter(fn ($l) => str_contains($l, 'Parametro'))->values();

    expect($labels->first())->toBe('A Parametro')
        ->and($labels->last())->toBe('C Parametro');
});

it('falls back to sorting by label when an invalid sort_by is given', function () {
    $response = $this->getJson(route('parametros.data', ['sort_by' => 'value', 'sort_direction' => 'asc']));

    $response->assertOk();
    $labels = collect($response->json('data'))->pluck('label')->filter(fn ($l) => str_contains($l, 'Parametro'))->values();

    expect($labels->first())->toBe('A Parametro');
});
