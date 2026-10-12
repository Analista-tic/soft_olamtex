<?php

use App\Models\Settings\Setting;
use App\Models\Settings\User;
use Spatie\Permission\Models\Permission;

/**
 * Una sección creada desde la sábana ("nueva") debe traer su tipo de prenda; antes nacía siempre en "ambos".
 */
beforeEach(function () {
    $user = User::factory()->create();
    foreach (['crear sabana productos', 'editar sabana productos'] as $permission) {
        $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }
    $this->actingAs($user);

    $this->setting = Setting::create([
        'kind' => 'parameter', 'key' => 'sections', 'label' => 'Secciones', 'status' => true,
        'value' => [['id' => 1, 'name' => 'CAMISA', 'allowed_garment_type' => 'superior']],
    ]);
});

it('requires the garment type when a section is created from the sheet', function () {
    $this->postJson(route('produccion.product-sheets.parameters.store-option'), ['key' => 'sections', 'name' => 'Chaleco Inline'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('allowed_garment_type');

    expect($this->setting->fresh()->value)->toHaveCount(1);
});

it('stores the chosen garment type for the new section', function () {
    $this->postJson(route('produccion.product-sheets.parameters.store-option'), [
        'key' => 'sections', 'name' => 'Pantaloneta Inline', 'allowed_garment_type' => 'inferior',
    ])->assertCreated()->assertJsonPath('data.allowed_garment_type', 'inferior');

    $created = collect($this->setting->fresh()->value)->first(fn ($s) => mb_strtoupper($s['name']) === 'PANTALONETA INLINE');
    expect($created['allowed_garment_type'])->toBe('inferior');
});
