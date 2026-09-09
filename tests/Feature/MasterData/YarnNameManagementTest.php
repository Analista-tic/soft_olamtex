<?php

use App\Models\MasterData\YarnName;
use App\Models\Production\YarnBase;
use App\Models\Settings\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $user = User::factory()->create();
    foreach (['ver nombres materiales', 'crear nombres materiales', 'editar nombres materiales', 'eliminar nombres materiales'] as $permission) {
        $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }
    $this->actingAs($user);

    $this->yarnBase = YarnBase::create([
        'codigo' => 'BH-YN-TEST',
        'nombre' => 'ALGODÓN TEST',
        'is_active' => true,
    ]);
});

it('creates, updates and deletes a yarn name through the catalog endpoints', function () {
    $create = $this->postJson(route('nombres-hilaza.store'), [
        'nombre' => 'hilaza 30/1',
        'yarn_base_id' => $this->yarnBase->id,
        'is_active' => true,
    ]);

    $create->assertOk();
    $yarnNameId = $create->json('id');

    $yarnName = YarnName::query()->findOrFail($yarnNameId);
    expect($yarnName->nombre)->toBe('HILAZA 30/1')
        ->and($yarnName->yarn_base_id)->toBe($this->yarnBase->id);

    $otherBase = YarnBase::create([
        'codigo' => 'BH-YN-TEST-2',
        'nombre' => 'NYLON TEST',
        'is_active' => true,
    ]);

    $update = $this->putJson(route('nombres-hilaza.update', ['yarnName' => $yarnNameId]), [
        'nombre' => 'hilaza 30/1 premium',
        'yarn_base_id' => $otherBase->id,
        'is_active' => false,
    ]);

    $update->assertOk();

    $updatedYarnName = YarnName::query()->findOrFail($yarnNameId);
    expect($updatedYarnName->nombre)->toBe('HILAZA 30/1 PREMIUM')
        ->and($updatedYarnName->yarn_base_id)->toBe($otherBase->id)
        ->and($updatedYarnName->is_active)->toBeFalse();

    $delete = $this->deleteJson(route('nombres-hilaza.destroy', ['yarnName' => $yarnNameId]));
    $delete->assertNoContent();
    expect(YarnName::query()->find($yarnNameId))->toBeNull();
});

it('rejects creating a yarn name without a base', function () {
    $response = $this->postJson(route('nombres-hilaza.store'), [
        'nombre' => 'Sin base',
    ]);

    $response->assertStatus(422);
});

it('does not allow deleting a yarn base that has yarn names associated', function () {
    YarnName::create([
        'nombre' => 'NOMBRE EN USO',
        'yarn_base_id' => $this->yarnBase->id,
        'is_active' => true,
    ]);

    $service = app(\App\Services\Production\YarnBaseService::class);

    expect(fn () => $service->delete($this->yarnBase))->toThrow(\Illuminate\Validation\ValidationException::class);
});

it('exposes the base name in the options endpoint', function () {
    YarnName::create([
        'nombre' => 'NOMBRE OPCIONES',
        'yarn_base_id' => $this->yarnBase->id,
        'is_active' => true,
    ]);

    $response = $this->getJson(route('nombres-hilaza.options'));

    $response->assertOk();
    $option = collect($response->json())->firstWhere('nombre', 'NOMBRE OPCIONES');

    expect($option)->not->toBeNull()
        ->and($option['base_nombre'])->toBe('ALGODÓN TEST');
});
