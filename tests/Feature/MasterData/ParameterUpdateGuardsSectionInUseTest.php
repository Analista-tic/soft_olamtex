<?php

use App\Exceptions\MasterData\ParameterInUseException;
use App\Models\Production\ProductSheet;
use App\Models\Settings\Setting;
use App\Models\Settings\User;
use App\Services\MasterData\ParameterService;

/**
 * Tests de humo: editar el catálogo de Secciones (o Grupo/Marca/Mundo) no
 * debe poder quitar en silencio una opción que todavía esté en uso por una
 * ProductSheet — ver ParameterUsageService::assertNoRemovedOptionsInUse().
 */
function parameterGuardTestProductSheet(int $sectionId): ProductSheet
{
    return ProductSheet::create([
        'section_id' => $sectionId,
        'group_id' => 1,
        'brand_id' => 1,
        'world_id' => 1,
        'type' => 'producto',
        'billing_price' => 1000,
        'selling_price' => 1200,
        'sizes' => ['S'],
        'woven_product' => false,
        'description' => 'Sábana de prueba',
        'sample_reference' => 'MUESTRA-' . uniqid(),
        'product_reference' => 'PRODUCTO-' . uniqid(),
        'processes' => [],
        'status' => 'complete',
    ]);
}

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    $this->service = app(ParameterService::class);
});

test('blocks removing a section option that is still used by a product sheet', function () {
    $setting = Setting::create([
        'kind' => 'parameter',
        'key' => 'sections',
        'label' => 'Secciones',
        'value' => [['id' => 1, 'name' => 'JOGGER']],
        'status' => true,
    ]);

    parameterGuardTestProductSheet(sectionId: 1);

    expect(fn () => $this->service->update($setting->id, ['value' => []]))
        ->toThrow(ParameterInUseException::class);

    $setting->refresh();
    expect($setting->value)->toBe([['id' => 1, 'name' => 'JOGGER']]);
});

test('allows removing a section option that is not used by any product sheet', function () {
    $setting = Setting::create([
        'kind' => 'parameter',
        'key' => 'sections',
        'label' => 'Secciones',
        'value' => [['id' => 1, 'name' => 'JOGGER'], ['id' => 2, 'name' => 'CAMISETA']],
        'status' => true,
    ]);

    parameterGuardTestProductSheet(sectionId: 1);

    $updated = $this->service->update($setting->id, ['value' => [['id' => 1, 'name' => 'JOGGER']]]);

    // ParameterService::normalizeValue() aplica title-case a los nombres al
    // guardar (comportamiento existente, ajeno a este cambio) — solo importa
    // aquí que el id 1 sobrevivió y el id 2 (no usado) se quitó sin error.
    expect($updated->value)->toHaveCount(1)
        ->and($updated->value[0]['id'])->toBe(1);
});

test('allows adding or renaming options without removing any id', function () {
    $setting = Setting::create([
        'kind' => 'parameter',
        'key' => 'sections',
        'label' => 'Secciones',
        'value' => [['id' => 1, 'name' => 'JOGGER']],
        'status' => true,
    ]);

    parameterGuardTestProductSheet(sectionId: 1);

    $updated = $this->service->update($setting->id, [
        'value' => [['id' => 1, 'name' => 'JOGGER RENOMBRADO'], ['id' => 2, 'name' => 'NUEVA SECCION']],
    ]);

    expect($updated->value)->toHaveCount(2)
        ->and(collect($updated->value)->pluck('id')->all())->toBe([1, 2]);
});

test('assigns a fresh id to a newly added option that arrives without one, keeping existing ids untouched', function () {
    // Reproduce el bug real: ChipInputField solo maneja strings, así que un valor
    // recién agregado desde el diálogo de Parámetros llega sin id — sin este
    // respaldo se quedaría sin identidad estable (ver normalizeValue()).
    $setting = Setting::create([
        'kind' => 'parameter',
        'key' => 'sections',
        'label' => 'Secciones',
        'value' => [['id' => 1, 'name' => 'JOGGER'], ['id' => 5, 'name' => 'CAMISETA']],
        'status' => true,
    ]);

    parameterGuardTestProductSheet(sectionId: 1);

    $updated = $this->service->update($setting->id, [
        'value' => [['id' => 1, 'name' => 'JOGGER'], ['id' => 5, 'name' => 'CAMISETA'], ['name' => 'NUEVA SECCION']],
    ]);

    expect(collect($updated->value)->pluck('id')->all())->toBe([1, 5, 6]);
});
