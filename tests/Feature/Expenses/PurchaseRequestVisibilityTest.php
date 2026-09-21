<?php

use App\Models\Expenses\PurchaseRequest;
use App\Models\Settings\User;
use App\Services\Expenses\PurchaseRequestService;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Un borrador siempre debe ser visible para quien lo creó, sin importar la
 * combinación de permiso de alcance (propios / área / todos) y rol
 * (logistica / admin) que tenga.
 */
dataset('visibility combinations', [
    'propios' => [['ver propios solicitudes compra'], null],
    'todos' => [['ver todos solicitudes compra'], null],
    'todos + logistica' => [['ver todos solicitudes compra'], 'logistica'],
    'todos + admin' => [['ver todos solicitudes compra'], 'admin'],
]);

it('shows a draft to its creator', function (array $permissions, ?string $role) {
    $user = User::factory()->create();
    foreach ($permissions as $permission) {
        $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }
    if ($role) {
        $user->assignRole(Role::findOrCreate($role, 'web'));
    }

    $draft = PurchaseRequest::create([
        'requester_user_id' => $user->id,
        'date' => now()->toDateString(),
        'status' => 'draft',
        'solicited_to' => 'gerencia',
    ]);

    $ids = collect(app(PurchaseRequestService::class)->paginate([], 50, null, $user)->items())->pluck('id');

    expect($ids)->toContain($draft->id);
})->with('visibility combinations');

it('hides other users drafts from logistica and admin even with ver todos', function (string $role) {
    $viewer = User::factory()->create();
    $viewer->givePermissionTo(Permission::findOrCreate('ver todos solicitudes compra', 'web'));
    $viewer->assignRole(Role::findOrCreate($role, 'web'));

    $other = User::factory()->create();
    $draft = PurchaseRequest::create([
        'requester_user_id' => $other->id,
        'date' => now()->toDateString(),
        'status' => 'draft',
        'solicited_to' => null,
    ]);

    $ids = collect(app(PurchaseRequestService::class)->paginate([], 50, null, $viewer)->items())->pluck('id');

    expect($ids)->not->toContain($draft->id);
})->with(['logistica', 'admin']);

it('always stores the logged user as requester, ignoring requester_user_id', function () {
    $user = User::factory()->create();
    foreach (['ver propios solicitudes compra', 'crear solicitudes compra'] as $permission) {
        $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }
    $other = User::factory()->create();

    $this->actingAs($user)->postJson(route('gastos.purchase-requests.store'), [
        'date' => now()->toDateString(),
        'requester_user_id' => $other->id,
        'description' => 'Compra de prueba',
        'items' => [['name' => 'Tela', 'quantity' => 1, 'price' => 10, 'total' => 10]],
    ])->assertCreated();

    $request = PurchaseRequest::latest('id')->first();
    expect($request->requester_user_id)->toBe($user->id);
});

it('returns a 422 with field errors instead of a generic 500 on invalid payload', function () {
    $user = User::factory()->create();
    foreach (['ver propios solicitudes compra', 'crear solicitudes compra'] as $permission) {
        $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }

    $response = $this->actingAs($user)->postJson(route('gastos.purchase-requests.store'), [
        'provider_name' => str_repeat('a', 300),
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors(['provider_name']);
});
