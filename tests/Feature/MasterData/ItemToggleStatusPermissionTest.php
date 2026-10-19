<?php

use App\Models\MasterData\CategoryGroup;
use App\Models\MasterData\CategorySubGroup;
use App\Models\MasterData\Item;
use App\Models\Settings\User;
use Spatie\Permission\Models\Permission;

/**
 * Deshabilitar un ítem sigue abierto a cualquiera con 'activar desactivar item'
 * (permiso que hoy tienen todos los roles operativos). Habilitar uno que estaba
 * inactivo ahora exige además 'habilitar item' — permiso más restringido, para
 * que no cualquiera pueda reactivar un ítem sin supervisión. Cubre tanto el
 * toggle individual como el nuevo masivo.
 */
beforeEach(function () {
    $group = CategoryGroup::firstOrCreate(['name' => 'MATERIA PRIMA TOGGLE STATUS TEST']);
    $this->subGroup = CategorySubGroup::updateOrCreate(
        ['name' => 'Subgrupo Toggle Status Test', 'category_group_id' => $group->id],
    );

    $this->activeItem = Item::create([
        'category_sub_group_id' => $this->subGroup->id,
        'name' => 'ITEM ACTIVO TOGGLE TEST',
        'description' => 'Item activo',
        'reference' => 'REF-TOGGLE-ACTIVE-'.uniqid(),
        'status' => true,
        'tax' => 0,
    ]);

    $this->inactiveItem = Item::create([
        'category_sub_group_id' => $this->subGroup->id,
        'name' => 'ITEM INACTIVO TOGGLE TEST',
        'description' => 'Item inactivo',
        'reference' => 'REF-TOGGLE-INACTIVE-'.uniqid(),
        'status' => false,
        'tax' => 0,
    ]);
});

function toggleStatusTestUserWithoutEnablePermission(): User
{
    $user = User::factory()->create();
    foreach (['ver catalogo items', 'activar desactivar item'] as $permission) {
        $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }

    return $user;
}

it('lets a user without "habilitar item" disable an active item', function () {
    $this->actingAs(toggleStatusTestUserWithoutEnablePermission());

    $response = $this->postJson(route('item.toggle-status', $this->activeItem->id));

    $response->assertOk();
    expect($this->activeItem->fresh()->status)->toBeFalse();
});

it('blocks a user without "habilitar item" from enabling an inactive item', function () {
    $this->actingAs(toggleStatusTestUserWithoutEnablePermission());

    $response = $this->postJson(route('item.toggle-status', $this->inactiveItem->id));

    $response->assertStatus(403);
    expect($this->inactiveItem->fresh()->status)->toBeFalse();
});

it('lets a user with "habilitar item" enable an inactive item', function () {
    $user = toggleStatusTestUserWithoutEnablePermission();
    $user->givePermissionTo(Permission::findOrCreate('habilitar item', 'web'));
    $this->actingAs($user);

    $response = $this->postJson(route('item.toggle-status', $this->inactiveItem->id));

    $response->assertOk();
    expect($this->inactiveItem->fresh()->status)->toBeTrue();
});

it('bulk-disables items for a user without "habilitar item"', function () {
    $this->actingAs(toggleStatusTestUserWithoutEnablePermission());
    $otherActiveItem = Item::create([
        'category_sub_group_id' => $this->subGroup->id,
        'name' => 'ITEM ACTIVO 2 TOGGLE TEST',
        'description' => 'Item activo 2',
        'reference' => 'REF-TOGGLE-ACTIVE-2-'.uniqid(),
        'status' => true,
        'tax' => 0,
    ]);

    $response = $this->postJson(route('item.bulk-toggle-status'), [
        'ids' => [$this->activeItem->id, $otherActiveItem->id],
        'status' => false,
    ]);

    $response->assertOk();
    expect($response->json('updated'))->toBe(2)
        ->and($this->activeItem->fresh()->status)->toBeFalse()
        ->and($otherActiveItem->fresh()->status)->toBeFalse();
});

it('blocks bulk-enabling items for a user without "habilitar item"', function () {
    $this->actingAs(toggleStatusTestUserWithoutEnablePermission());

    $response = $this->postJson(route('item.bulk-toggle-status'), [
        'ids' => [$this->inactiveItem->id],
        'status' => true,
    ]);

    $response->assertStatus(403);
    expect($this->inactiveItem->fresh()->status)->toBeFalse();
});

it('bulk-enables items for a user with "habilitar item"', function () {
    $user = toggleStatusTestUserWithoutEnablePermission();
    $user->givePermissionTo(Permission::findOrCreate('habilitar item', 'web'));
    $this->actingAs($user);

    $response = $this->postJson(route('item.bulk-toggle-status'), [
        'ids' => [$this->inactiveItem->id],
        'status' => true,
    ]);

    $response->assertOk();
    expect($response->json('updated'))->toBe(1)
        ->and($this->inactiveItem->fresh()->status)->toBeTrue();
});
