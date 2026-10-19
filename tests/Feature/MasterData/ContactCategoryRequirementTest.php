<?php

use App\Models\MasterData\CategoryGroup;
use App\Models\Settings\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $user = User::factory()->create();
    foreach (['ver contactos', 'crear contactos'] as $permission) {
        $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }
    $this->actingAs($user);

    $this->group = CategoryGroup::create(['name' => 'Grupo Categoria Contacto']);
});

function contactPayload(string $contactType, array $overrides = []): array
{
    return array_merge([
        'contact_type' => $contactType,
        'id_type' => 'nit',
        'id_number' => (string) random_int(100000000, 999999999),
        'full_name' => 'Contacto Categoria Test',
        'person_type' => 'persona_juridica',
        'email' => uniqid('cat-test-', true).'@example.com',
        'groups' => [],
    ], $overrides);
}

it('permite crear un contacto tipo cliente sin categoría', function () {
    $this->postJson(route('contactos.store'), contactPayload('cliente'))
        ->assertOk();
});

it('exige categoría al crear un contacto tipo proveedor', function () {
    $this->postJson(route('contactos.store'), contactPayload('proveedor'))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('groups');
});

it('permite crear un contacto tipo cliente_proveedor sin categoría', function () {
    $this->postJson(route('contactos.store'), contactPayload('cliente_proveedor'))
        ->assertOk();
});

it('permite recrear un contacto eliminado con el mismo número de identificación y correo', function () {
    $user = auth()->user();
    $user->givePermissionTo(Permission::findOrCreate('eliminar contactos', 'web'));

    $payload = contactPayload('cliente');
    $id = $this->postJson(route('contactos.store'), $payload)->assertOk()->json('id');

    $this->deleteJson(route('contactos.destroy', $id))->assertOk();

    $this->postJson(route('contactos.store'), $payload)->assertOk();

    // Mientras exista uno activo, el duplicado sigue bloqueado.
    $this->postJson(route('contactos.store'), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['id_number', 'email']);
});

it('acepta un contacto tipo proveedor con categoría', function () {
    $this->postJson(route('contactos.store'), contactPayload('proveedor', ['groups' => [$this->group->id]]))
        ->assertOk();
});
