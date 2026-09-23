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

it('creates a group with a code', function () {
    $response = $this->postJson(route('categorias.groups.store'), [
        'name' => 'Grupo Con Codigo Test',
        'code' => '21',
    ]);

    $response->assertCreated();
    expect(CategoryGroup::where('name', 'Grupo Con Codigo Test')->first()->code)->toBe('21');
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

it('auto-assigns a consecutive code when creating a group without one', function () {
    $response = $this->postJson(route('categorias.groups.store'), [
        'name' => 'Grupo Sin Codigo Test',
    ]);

    $response->assertCreated();
    $group = CategoryGroup::where('name', 'Grupo Sin Codigo Test')->firstOrFail();
    expect($group->code)->not->toBeNull()->and($group->code)->toMatch('/^\d{2}$/');
});

it('auto-assigns a distinct consecutive code to each subgroup created in the same request', function () {
    $group = CategoryGroup::create(['name' => 'Grupo Multi Subgrupo Codigo Test']);

    $response = $this->postJson(route('categorias.subgroups.store'), [
        'category_group_id' => $group->id,
        'subgroups' => [
            ['name' => 'Subgrupo Auto Uno'],
            ['name' => 'Subgrupo Auto Dos'],
        ],
    ]);

    $response->assertOk();
    $created = CategorySubGroup::where('category_group_id', $group->id)->orderBy('id')->get();

    expect($created)->toHaveCount(2);
    expect($created[0]->code)->not->toBeNull();
    expect($created[1]->code)->not->toBeNull();
    expect($created[0]->code)->not->toBe($created[1]->code);
});

it('stores and returns the subgroup name in uppercase, not title case', function () {
    $group = CategoryGroup::create(['name' => 'Grupo Mayusculas Test']);

    $response = $this->postJson(route('categorias.subgroups.store'), [
        'category_group_id' => $group->id,
        'subgroups' => [
            ['name' => 'algodon peinado'],
        ],
    ]);

    $response->assertOk();
    $response->assertJsonPath('data.0.name', 'ALGODON PEINADO');
    expect(CategorySubGroup::where('category_group_id', $group->id)->first()->name)->toBe('ALGODON PEINADO');
});
