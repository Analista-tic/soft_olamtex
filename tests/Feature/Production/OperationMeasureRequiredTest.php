<?php

use App\Models\Production\Operation;
use App\Models\Settings\User;
use Spatie\Permission\Models\Permission;

/**
 * La medida de una operación es obligatoria (0 = no aplica) y la descripción no debe deformar las medidas enteras.
 */
beforeEach(function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('crear listados operacionales', 'web'));
    $this->actingAs($user);
});

function operationPayloadWithoutMeasure(): array
{
    return [
        'action' => 'PEGAR',
        'garment_part' => 'CUELLO',
        'section_id' => 1,
        'sam_base' => 0.5,
        'factor_operario' => 100,
        'product_sheet_id_reference' => 1,
        'machine_id_reference' => 1,
    ];
}

it('requires the measure when creating an operation', function () {
    $response = $this->postJson(route('produccion.operaciones.store'), operationPayloadWithoutMeasure())->assertUnprocessable();

    expect($response->json('errors'))->toHaveKey('measure');
});

it('accepts 0 as the measure (does not apply)', function () {
    $payload = operationPayloadWithoutMeasure() + ['measure' => 0];

    $response = $this->postJson(route('produccion.operaciones.store'), $payload);

    expect($response->json('errors') ?? [])->not->toHaveKey('measure');
});

it('keeps zeros of whole measures in the description and hides a measure of 0', function () {
    expect(Operation::formatMeasure(10))->toBe('10')
        ->and(Operation::formatMeasure('100.0000'))->toBe('100')
        ->and(Operation::formatMeasure(10.5))->toBe('10.5')
        ->and(Operation::formatMeasure(0.25))->toBe('0.25')
        ->and(Operation::formatMeasure(0))->toBeNull()
        ->and(Operation::formatMeasure(null))->toBeNull();
});
