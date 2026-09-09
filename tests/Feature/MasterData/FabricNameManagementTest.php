<?php

use App\Models\MasterData\FabricName;
use App\Models\Production\FabricBase;
use App\Models\Settings\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $user = User::factory()->create();
    foreach (['ver nombres materiales', 'crear nombres materiales', 'editar nombres materiales', 'eliminar nombres materiales'] as $permission) {
        $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }
    $this->actingAs($user);

    $this->fabricBase = FabricBase::create([
        'codigo' => 'BT-FN-TEST',
        'nombre' => 'ALGODÓN TEST',
        'factor' => 1.5,
        'is_active' => true,
    ]);
});

it('creates, updates and deletes a fabric name through the catalog endpoints', function () {
    $create = $this->postJson(route('nombres-tela.store'), [
        'nombre' => 'jersey 24',
        'fabric_base_id' => $this->fabricBase->id,
        'is_active' => true,
    ]);

    $create->assertOk();
    $fabricNameId = $create->json('id');

    $fabricName = FabricName::query()->findOrFail($fabricNameId);
    expect($fabricName->nombre)->toBe('JERSEY 24')
        ->and($fabricName->fabric_base_id)->toBe($this->fabricBase->id);

    $otherBase = FabricBase::create([
        'codigo' => 'BT-FN-TEST-2',
        'nombre' => 'POLIÉSTER TEST',
        'factor' => 1.0,
        'is_active' => true,
    ]);

    $update = $this->putJson(route('nombres-tela.update', ['fabricName' => $fabricNameId]), [
        'nombre' => 'jersey 24 premium',
        'fabric_base_id' => $otherBase->id,
        'is_active' => false,
    ]);

    $update->assertOk();

    $updatedFabricName = FabricName::query()->findOrFail($fabricNameId);
    expect($updatedFabricName->nombre)->toBe('JERSEY 24 PREMIUM')
        ->and($updatedFabricName->fabric_base_id)->toBe($otherBase->id)
        ->and($updatedFabricName->is_active)->toBeFalse();

    $delete = $this->deleteJson(route('nombres-tela.destroy', ['fabricName' => $fabricNameId]));
    $delete->assertNoContent();
    expect(FabricName::query()->find($fabricNameId))->toBeNull();
});

it('rejects creating a fabric name without a base', function () {
    $response = $this->postJson(route('nombres-tela.store'), [
        'nombre' => 'Sin base',
    ]);

    $response->assertStatus(422);
});

it('rejects creating a fabric name without a name', function () {
    $response = $this->postJson(route('nombres-tela.store'), [
        'fabric_base_id' => $this->fabricBase->id,
    ]);

    $response->assertStatus(422);
});

it('does not allow deleting a fabric base that has fabric names associated', function () {
    FabricName::create([
        'nombre' => 'NOMBRE EN USO',
        'fabric_base_id' => $this->fabricBase->id,
        'is_active' => true,
    ]);

    $service = app(\App\Services\Production\FabricBaseService::class);

    expect(fn () => $service->delete($this->fabricBase))->toThrow(\Illuminate\Validation\ValidationException::class);
});

it('exposes the base factor and name in the options endpoint', function () {
    FabricName::create([
        'nombre' => 'NOMBRE OPCIONES',
        'fabric_base_id' => $this->fabricBase->id,
        'is_active' => true,
    ]);

    $response = $this->getJson(route('nombres-tela.options'));

    $response->assertOk();
    $option = collect($response->json())->firstWhere('nombre', 'NOMBRE OPCIONES');

    expect($option)->not->toBeNull()
        ->and($option['base_nombre'])->toBe('ALGODÓN TEST')
        ->and((float) $option['factor'])->toBe(1.5);
});
