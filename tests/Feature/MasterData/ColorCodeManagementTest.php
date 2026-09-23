<?php

use App\Models\MasterData\Color;
use App\Models\Settings\User;
use Spatie\Permission\Models\Permission;

/**
 * `code` en colors es opcional al crear: si no se manda, se autogenera con
 * el siguiente consecutivo (con lock); si se manda, se valida formato
 * (numérico, hasta 3 dígitos) y unicidad, y se normaliza a 3 dígitos con
 * ceros a la izquierda. GET /colors/next-code expone ese consecutivo sin
 * reservarlo, para precargar la sugerencia en el formulario de creación.
 */
beforeEach(function () {
    $user = User::factory()->create();
    foreach (['ver colores', 'crear colores'] as $permission) {
        $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }
    $this->actingAs($user);
});

it('auto-generates the next consecutive code when none is provided', function () {
    Color::create(['name' => 'Color Base Code Test', 'code' => '005']);

    $response = $this->postJson('/colors', [
        'name' => 'Color Sin Code Test',
    ]);

    $response->assertCreated();
    expect($response->json('code'))->toBe('006');
});

it('previews the next code without reserving it', function () {
    Color::create(['name' => 'Color Preview Base Test', 'code' => '010']);

    $preview = $this->getJson('/colors/next-code');
    $preview->assertOk();
    expect($preview->json('code'))->toBe('011');

    // Pedir la sugerencia de nuevo no debe haber reservado nada: sigue igual.
    $previewAgain = $this->getJson('/colors/next-code');
    expect($previewAgain->json('code'))->toBe('011');
});

it('accepts a manual code and normalizes it to 3 digits', function () {
    $response = $this->postJson('/colors', [
        'name' => 'Color Manual Code Test',
        'code' => '7',
    ]);

    $response->assertCreated();
    expect($response->json('code'))->toBe('007');
});

it('rejects a duplicate manual code', function () {
    Color::create(['name' => 'Color Duplicado Base Test', 'code' => '042']);

    $response = $this->postJson('/colors', [
        'name' => 'Color Duplicado Code Test',
        'code' => '42',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('code');
});

it('rejects a non-numeric manual code', function () {
    $response = $this->postJson('/colors', [
        'name' => 'Color Invalido Code Test',
        'code' => 'AB',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('code');
});
