<?php

use App\Models\Production\FabricBase;
use App\Models\Settings\User;
use Spatie\Permission\Models\Permission;

/**
 * El diálogo de sábana de productos permite crear una base de tela al vuelo desde su
 * select. Antes la creaba siempre con factor fijo 1.0, en silencio — así nacían bases
 * con el SAM mal calculado hasta que alguien lo notara y lo corrigiera manualmente en
 * Producción. Ahora exige el factor en el payload y el permiso de Diseño.
 */
beforeEach(function () {
    $user = User::factory()->create();
    foreach (['crear sabana productos', 'editar sabana productos'] as $permission) {
        $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }
    $this->actingAs($user);
});

it('rejects creating a fabric base inline without the material catalog permission', function () {
    $response = $this->postJson(route('produccion.product-sheets.parameters.store-option'), [
        'key' => 'fabric_bases',
        'name' => 'DENIM',
        'factor' => 1.2,
    ]);

    $response->assertStatus(403);
    expect(FabricBase::where('nombre', 'DENIM')->exists())->toBeFalse();
});

it('rejects creating a fabric base inline without a factor', function () {
    auth()->user()->givePermissionTo(Permission::findOrCreate('crear catalogo materiales', 'web'));

    $response = $this->postJson(route('produccion.product-sheets.parameters.store-option'), [
        'key' => 'fabric_bases',
        'name' => 'DENIM',
    ]);

    $response->assertStatus(422);
    expect(FabricBase::where('nombre', 'DENIM')->exists())->toBeFalse();
});

it('creates a fabric base inline with the given factor, not a silent 1.0', function () {
    auth()->user()->givePermissionTo(Permission::findOrCreate('crear catalogo materiales', 'web'));

    $response = $this->postJson(route('produccion.product-sheets.parameters.store-option'), [
        'key' => 'fabric_bases',
        'name' => 'DENIM',
        'factor' => 1.2,
    ]);

    $response->assertCreated();
    $base = FabricBase::where('nombre', 'DENIM')->firstOrFail();
    expect((float) $base->factor)->toBe(1.2);
});
