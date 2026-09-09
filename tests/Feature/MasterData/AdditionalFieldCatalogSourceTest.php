<?php

use App\Models\MasterData\FabricName;
use App\Models\Production\FabricBase;
use App\Models\Settings\Setting;
use App\Models\Settings\User;
use App\Services\MasterData\AdditionalFieldService;
use Spatie\Permission\Models\Permission;

/**
 * Fase 7 del refactor de materiales: un campo adicional de tipo "select" puede tomar
 * sus opciones de un catálogo del sistema en vez de una lista escrita a mano. El valor
 * guardado en extra_fields sigue siendo texto: ningún consumidor existente cambia.
 */
beforeEach(function () {
    $user = User::factory()->create();
    foreach (['ver campos adicionales', 'crear campos adicionales', 'editar campos adicionales'] as $permission) {
        $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }
    $this->actingAs($user);

    $this->fabricBase = FabricBase::create([
        'codigo' => 'BT-F7-TEST',
        'nombre' => 'BASE CATALOGO TEST',
        'factor' => 1.0,
        'is_active' => true,
    ]);
});

it('resolves the options of a catalog-sourced field live from the catalog', function () {
    FabricName::create(['nombre' => 'MILE', 'fabric_base_id' => $this->fabricBase->id, 'is_active' => true]);
    FabricName::create(['nombre' => 'VALENTINA', 'fabric_base_id' => $this->fabricBase->id, 'is_active' => true]);

    $service = app(AdditionalFieldService::class);
    $field = $service->create([
        'name' => 'Tela sugerida',
        'field_type' => 'select',
        'source_type' => 'catalog',
        'catalog_key' => 'fabric_names',
    ]);

    expect($field->options)->toBeNull();

    $resolved = $service->options()->firstWhere('id', $field->id);
    expect($resolved['options'])->toContain('MILE', 'VALENTINA');
});

it('shows a fabric name created after the field without touching the field', function () {
    $service = app(AdditionalFieldService::class);
    $field = $service->create([
        'name' => 'Tela sugerida 2',
        'field_type' => 'select',
        'source_type' => 'catalog',
        'catalog_key' => 'fabric_names',
    ]);

    // Precalienta el cache de definiciones de campos, como lo haría el formulario de ítem.
    $service->options();

    FabricName::create(['nombre' => 'CAPRI', 'fabric_base_id' => $this->fabricBase->id, 'is_active' => true]);

    $resolved = $service->options()->firstWhere('id', $field->id);
    expect($resolved['options'])->toContain('CAPRI');
});

it('still works as a plain manual list when source_type is list', function () {
    $service = app(AdditionalFieldService::class);
    $field = $service->create([
        'name' => 'Textura',
        'field_type' => 'select',
        'options' => ['Suave', 'Rígida'],
    ]);

    expect($field->source_type)->toBe('list')
        ->and($field->options)->toBe(['Suave', 'Rígida']);

    $resolved = $service->options()->firstWhere('id', $field->id);
    expect($resolved['options'])->toBe(['Suave', 'Rígida']);
});

it('rejects creating a catalog-sourced select field without a catalog key', function () {
    $response = $this->postJson(route('inventory.additional-fields.store'), [
        'name' => 'Tela sugerida inválida',
        'field_type' => 'select',
        'source_type' => 'catalog',
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors('catalog_key');
});

it('rejects creating a manual-list select field without options', function () {
    $response = $this->postJson(route('inventory.additional-fields.store'), [
        'name' => 'Sin opciones',
        'field_type' => 'select',
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors('options');
});

it('does not require options for a catalog-sourced field on the http endpoint', function () {
    $response = $this->postJson(route('inventory.additional-fields.store'), [
        'name' => 'Tela sugerida http',
        'field_type' => 'select',
        'source_type' => 'catalog',
        'catalog_key' => 'fabric_names',
    ]);

    $response->assertCreated();

    $field = Setting::fields()->where('label', 'Tela sugerida http')->firstOrFail();
    expect($field->source_type)->toBe('catalog')
        ->and($field->catalog_key)->toBe('fabric_names')
        ->and($field->options)->toBeNull();
});
