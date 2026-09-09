<?php

use App\Models\MasterData\FabricName;
use App\Models\Production\FabricBase;
use App\Models\Settings\User;
use Spatie\Permission\Models\Permission;

/**
 * Fase 12: el catálogo de materiales (bases y nombres) pasó de devolver listas
 * completas sin paginar a un listado real con búsqueda/orden/página por pestaña —
 * necesario porque fabric_bases ya tiene 38+ filas reales y fabric_names crecerá
 * cuando se cargue el catálogo completo del cliente (pendiente del usuario).
 */
beforeEach(function () {
    $user = User::factory()->create();
    foreach (['ver catalogo materiales', 'crear catalogo materiales', 'ver nombres materiales'] as $permission) {
        $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }
    $this->actingAs($user);
});

it('paginates fabric bases and searches by name', function () {
    FabricBase::create(['codigo' => 'BT-S-001', 'nombre' => 'ALGODÓN PIMA', 'factor' => 1.0, 'is_active' => true]);
    FabricBase::create(['codigo' => 'BT-S-002', 'nombre' => 'POLIÉSTER', 'factor' => 1.0, 'is_active' => true]);
    FabricBase::create(['codigo' => 'BT-S-003', 'nombre' => 'ALGODÓN ORGÁNICO', 'factor' => 1.0, 'is_active' => true]);

    $response = $this->getJson(route('catalogo-materiales.index', ['type' => 'fabric', 'search' => 'ALGODÓN', 'per_page' => 10]));

    $response->assertOk();
    $names = collect($response->json('data'))->pluck('nombre');

    expect($names)->toContain('ALGODÓN PIMA', 'ALGODÓN ORGÁNICO')
        ->and($names)->not->toContain('POLIÉSTER');
});

it('sorts fabric bases by name descending when requested', function () {
    FabricBase::create(['codigo' => 'BT-SORT-001', 'nombre' => 'AAA BASE', 'factor' => 1.0, 'is_active' => true]);
    FabricBase::create(['codigo' => 'BT-SORT-002', 'nombre' => 'ZZZ BASE', 'factor' => 1.0, 'is_active' => true]);

    $response = $this->getJson(route('catalogo-materiales.index', [
        'type' => 'fabric', 'sort_by' => 'nombre', 'sort_direction' => 'desc', 'per_page' => 100,
    ]));

    $response->assertOk();
    $names = collect($response->json('data'))->pluck('nombre');
    $sortIndex = fn ($needle) => $names->search($needle);

    expect($sortIndex('ZZZ BASE'))->toBeLessThan($sortIndex('AAA BASE'));
});

it('limits the number of fabric bases returned per page', function () {
    for ($i = 1; $i <= 5; $i++) {
        FabricBase::create(['codigo' => "BT-PAGE-{$i}", 'nombre' => "BASE PAGINA {$i}", 'factor' => 1.0, 'is_active' => true]);
    }

    $response = $this->getJson(route('catalogo-materiales.index', ['type' => 'fabric', 'per_page' => 2, 'page' => 1]));

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(2)
        ->and($response->json('total'))->toBeGreaterThanOrEqual(5);
});

it('filters fabric names by base_id, the mechanism behind clicking a base row', function () {
    $baseA = FabricBase::create(['codigo' => 'BT-FILT-A', 'nombre' => 'BASE FILTRO A', 'factor' => 1.0, 'is_active' => true]);
    $baseB = FabricBase::create(['codigo' => 'BT-FILT-B', 'nombre' => 'BASE FILTRO B', 'factor' => 1.0, 'is_active' => true]);

    FabricName::create(['nombre' => 'NOMBRE DE A', 'fabric_base_id' => $baseA->id, 'is_active' => true]);
    FabricName::create(['nombre' => 'NOMBRE DE B', 'fabric_base_id' => $baseB->id, 'is_active' => true]);

    $response = $this->getJson(route('nombres-tela.index', ['base_id' => $baseA->id]));

    $response->assertOk();
    $names = collect($response->json('data'))->pluck('nombre');

    expect($names)->toContain('NOMBRE DE A')
        ->and($names)->not->toContain('NOMBRE DE B');
});

it('searches fabric names by text', function () {
    $base = FabricBase::create(['codigo' => 'BT-SEARCH-N', 'nombre' => 'BASE BUSQUEDA NOMBRE', 'factor' => 1.0, 'is_active' => true]);

    FabricName::create(['nombre' => 'JERSEY PREMIUM', 'fabric_base_id' => $base->id, 'is_active' => true]);
    FabricName::create(['nombre' => 'DENIM CLASICO', 'fabric_base_id' => $base->id, 'is_active' => true]);

    $response = $this->getJson(route('nombres-tela.index', ['search' => 'JERSEY']));

    $response->assertOk();
    $names = collect($response->json('data'))->pluck('nombre');

    expect($names)->toContain('JERSEY PREMIUM')
        ->and($names)->not->toContain('DENIM CLASICO');
});
