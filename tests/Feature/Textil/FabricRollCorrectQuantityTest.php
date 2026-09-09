<?php

use App\Models\Settings\User;
use App\Models\Textil\FabricRoll;
use Spatie\Permission\Models\Permission;

/**
 * correctQuantity() antes solo validaba contra actual_output — permitía bajar la
 * cantidad del rollo por debajo de lo ya reservado (partial_output), dejando una
 * reserva que ya no cabe físicamente en el rollo. Corregido para validar contra
 * actual_output + partial_output.
 */
beforeEach(function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('ver telas rollos', 'web'));
    $user->givePermissionTo(Permission::findOrCreate('ajustar telas rollos', 'web'));
    $this->actingAs($user);
});

it('rejects lowering the quantity below actual_output plus reserved partial_output', function () {
    $roll = FabricRoll::create([
        'roll_number' => 'RL-CORRECT-1',
        'item_id' => null,
        'quantity' => 100,
        'actual_output' => 20,
        'partial_output' => 50,
        'entry_date' => now()->toDateString(),
        'approval_status' => FabricRoll::APPROVAL_APPROVED,
    ]);

    // 20 (actual) + 50 (reservado) = 70 — bajar a 30 dejaría reservas que ya no caben.
    $response = $this->patchJson(route('fabric-rolls.correct-quantity', $roll->id), [
        'new_quantity' => 30,
        'reason' => 'Prueba de corrección',
    ]);

    $response->assertStatus(422);
    $roll->refresh();
    expect((float) $roll->quantity)->toEqual(100.0);
});

it('allows lowering the quantity down to exactly actual_output plus reserved partial_output', function () {
    $roll = FabricRoll::create([
        'roll_number' => 'RL-CORRECT-2',
        'item_id' => null,
        'quantity' => 100,
        'actual_output' => 20,
        'partial_output' => 50,
        'entry_date' => now()->toDateString(),
        'approval_status' => FabricRoll::APPROVAL_APPROVED,
    ]);

    $response = $this->patchJson(route('fabric-rolls.correct-quantity', $roll->id), [
        'new_quantity' => 70,
        'reason' => 'Prueba de corrección',
    ]);

    $response->assertOk();
    $roll->refresh();
    expect((float) $roll->quantity)->toEqual(70.0);
});
