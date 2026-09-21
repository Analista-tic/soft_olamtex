<?php

use App\Models\MasterData\CategoryGroup;
use App\Models\MasterData\CategorySubGroup;
use App\Models\Settings\User;
use Spatie\Permission\Models\Permission;

/**
 * Fase 13 del refactor de materiales: grupos y subgrupos ganaron un `code` (2
 * dígitos) usado por la referencia automática de ítems. Los grupos no tenían
 * endpoint de edición en absoluto — se agregó junto con este campo.
 */
beforeEach(function () {
    $user = User::factory()->create();
    foreach (['ver categorias', 'crear categorias', 'editar categorias'] as $permission) {
        $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }
    $this->actingAs($user);
});

it('assigns a consecutive two-digit code automatically when creating a group', function () {
    // El código ya no se pide en el alta: CategoryGroupService lo asigna (consecutivo, 2 dígitos).
    $this->postJson(route('categorias.groups.store'), ['name' => 'Grupo Con Codigo Test'])->assertCreated();
    $this->postJson(route('categorias.groups.store'), ['name' => 'Grupo Con Codigo Test 2'])->assertCreated();

    $first = CategoryGroup::where('name', 'Grupo Con Codigo Test')->first()->code;
    $second = CategoryGroup::where('name', 'Grupo Con Codigo Test 2')->first()->code;

    expect($first)->toMatch('/^\d{2}$/')
        ->and($second)->toBe(str_pad((string) ((int) $first + 1), 2, '0', STR_PAD_LEFT));
});

it('updates a group name and code — there was no update endpoint for groups before this phase', function () {
    $group = CategoryGroup::create(['name' => 'Grupo Original Test']);

    $response = $this->putJson(route('categorias.groups.update', $group), [
        'name' => 'Grupo Renombrado Test',
        'code' => '22',
    ]);

    $response->assertOk();
    $group->refresh();
    expect($group->name)->toBe('Grupo Renombrado Test')
        ->and($group->code)->toBe('22');
});

it('rejects a duplicate group code', function () {
    CategoryGroup::create(['name' => 'Grupo A Codigo Test', 'code' => '23']);
    $groupB = CategoryGroup::create(['name' => 'Grupo B Codigo Test']);

    $response = $this->putJson(route('categorias.groups.update', $groupB), [
        'name' => 'Grupo B Codigo Test',
        'code' => '23',
    ]);

    $response->assertStatus(422);
});

it('saves a code on a subgroup through the update endpoint', function () {
    $group = CategoryGroup::create(['name' => 'Grupo Subgrupo Codigo Test']);
    $subGroup = CategorySubGroup::create(['name' => 'Subgrupo Codigo Test', 'category_group_id' => $group->id]);

    $response = $this->putJson(route('categorias.subgroups.update', $subGroup), [
        'name' => 'Subgrupo Codigo Test',
        'category_group_id' => $group->id,
        'code' => '30',
    ]);

    $response->assertOk();
    expect($subGroup->fresh()->code)->toBe('30');
});
