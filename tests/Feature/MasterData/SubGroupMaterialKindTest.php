<?php

use App\Models\MasterData\CategoryGroup;
use App\Models\MasterData\CategorySubGroup;
use App\Models\Settings\User;
use App\Support\MasterData\MaterialClassifier;
use Spatie\Permission\Models\Permission;

/**
 * Fase 3 del refactor de materiales: la clasificación tela/hilaza se edita ahora desde
 * la UI de subgrupos (columna material_kind), no desplegando un cambio de config.
 */
beforeEach(function () {
    MaterialClassifier::forgetCache();

    $user = User::factory()->create();
    foreach (['ver categorias', 'crear categorias', 'editar categorias'] as $permission) {
        $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }
    $this->actingAs($user);

    $this->group = CategoryGroup::firstOrCreate(['name' => 'MATERIA PRIMA SUBGROUP TEST']);
    $this->subGroup = CategorySubGroup::create([
        'name' => 'Botón Subgroup Test',
        'category_group_id' => $this->group->id,
        'material_kind' => null,
    ]);
});

it('reclassifies a subgroup as fabric through the update endpoint and the classifier sees it immediately', function () {
    expect(MaterialClassifier::isFabricSubgroup($this->subGroup))->toBeFalse();

    $response = $this->putJson(route('categorias.subgroups.update', ['subgroup' => $this->subGroup->id]), [
        'name' => $this->subGroup->name,
        'category_group_id' => $this->group->id,
        'material_kind' => 'fabric',
    ]);

    $response->assertOk();

    $this->subGroup->refresh();
    expect($this->subGroup->material_kind)->toBe('fabric')
        ->and(MaterialClassifier::isFabricSubgroup($this->subGroup))->toBeTrue();
});

it('clears a subgroup classification back to null through the update endpoint', function () {
    $this->subGroup->update(['material_kind' => 'yarn']);
    MaterialClassifier::forgetCache();

    $response = $this->putJson(route('categorias.subgroups.update', ['subgroup' => $this->subGroup->id]), [
        'name' => $this->subGroup->name,
        'category_group_id' => $this->group->id,
        'material_kind' => null,
    ]);

    $response->assertOk();

    $this->subGroup->refresh();
    expect($this->subGroup->material_kind)->toBeNull()
        ->and(MaterialClassifier::isYarnSubgroup($this->subGroup))->toBeFalse();
});

it('rejects an invalid material_kind value', function () {
    $response = $this->putJson(route('categorias.subgroups.update', ['subgroup' => $this->subGroup->id]), [
        'name' => $this->subGroup->name,
        'category_group_id' => $this->group->id,
        'material_kind' => 'not-a-real-kind',
    ]);

    $response->assertStatus(422);
});

it('exposes material_kind in the paginated subgroup listing', function () {
    $this->subGroup->update(['material_kind' => 'fabric']);

    $response = $this->getJson(route('categorias.subgroups.getData', ['group_id' => $this->group->id]));

    $response->assertOk();
    $row = collect($response->json('data'))->firstWhere('id', $this->subGroup->id);
    expect($row['material_kind'])->toBe('fabric');
});
