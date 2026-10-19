<?php

use App\Models\Settings\User;
use Spatie\Permission\Models\Permission;

/**
 * Los ajustes de inventario son el único camino manual para cambiar el saldo de un ítem
 * (la ficha del ítem ya no permite editar cantidades), así que cada acción exige su permiso.
 */
function adjustmentPermissionUser(array $permissions): User
{
    $user = User::factory()->create();
    foreach ($permissions as $permission) {
        $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }

    return $user;
}

it('blocks the adjustment page and data for a user without "ver ajustes inventario"', function () {
    $this->actingAs(adjustmentPermissionUser([]));

    $this->get(route('inventario.ajuste-inventario'))->assertForbidden();
    $this->getJson(route('ajustes-inventario.data'))->assertForbidden();
});

it('lets a user with "ver ajustes inventario" list adjustments', function () {
    $this->actingAs(adjustmentPermissionUser(['ver ajustes inventario']));

    $this->getJson(route('ajustes-inventario.data'))->assertOk();
});

it('blocks creating, editing and deleting adjustments without their permission', function () {
    $this->actingAs(adjustmentPermissionUser(['ver ajustes inventario']));

    $this->postJson(route('ajustes-inventario.store'), [])->assertForbidden();
    $this->putJson(route('ajustes-inventario.update', 1), [])->assertForbidden();
    $this->deleteJson(route('ajustes-inventario.destroy', 1))->assertForbidden();
});
