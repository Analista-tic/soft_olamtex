<?php

use App\Models\Settings\Setting;
use App\Models\Settings\User;
use App\Services\MasterData\ParameterService;
use Illuminate\Validation\ValidationException;

/**
 * Cada sección de producto debe traer su tipo de prenda (superior, inferior o ambos); sin él la sábana la deja "abierta".
 */
beforeEach(function () {
    $this->actingAs(User::factory()->create());
    $this->service = app(ParameterService::class);
    $this->setting = Setting::create([
        'kind' => 'parameter', 'key' => 'sections', 'label' => 'Secciones', 'status' => true,
        'value' => [['id' => 1, 'name' => 'CAMISA', 'allowed_garment_type' => 'superior']],
    ]);
});

it('refuses a new section without its garment type and names it', function () {
    $value = [
        ['id' => 1, 'name' => 'CAMISA', 'allowed_garment_type' => 'superior'],
        ['name' => 'CHAMARRA'],
    ];

    expect(fn () => $this->service->update($this->setting->id, ['value' => $value]))
        ->toThrow(ValidationException::class, 'Chamarra');

    expect($this->setting->fresh()->value)->toHaveCount(1);
});

it('refuses an unknown garment type', function () {
    $value = [['id' => 1, 'name' => 'CAMISA', 'allowed_garment_type' => 'mixto']];

    expect(fn () => $this->service->update($this->setting->id, ['value' => $value]))->toThrow(ValidationException::class);
});

it('saves a section with its garment type', function () {
    $value = [
        ['id' => 1, 'name' => 'CAMISA', 'allowed_garment_type' => 'superior'],
        ['name' => 'PANTALONETA', 'allowed_garment_type' => 'inferior'],
    ];

    $this->service->update($this->setting->id, ['value' => $value]);

    expect(collect($this->setting->fresh()->value)->mapWithKeys(fn ($s) => [mb_strtoupper($s['name']) => $s['allowed_garment_type']])->all())
        ->toBe(['CAMISA' => 'superior', 'PANTALONETA' => 'inferior']);
});

it('does not ask for a garment type in other parameters', function () {
    $other = Setting::create(['kind' => 'parameter', 'key' => 'brands', 'label' => 'Marcas', 'value' => [['name' => 'OLAMTEX']], 'status' => true]);

    $this->service->update($other->id, ['value' => [['name' => 'OLAMTEX'], ['name' => 'NUEVA']]]);

    expect($other->fresh()->value)->toHaveCount(2);
});
