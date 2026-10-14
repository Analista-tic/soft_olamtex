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

/**
 * El destino "solicitado a" SUMA visibilidad: "ver solicitudes compra a X"
 * agrega las solicitudes dirigidas a X a lo que el usuario ya ve por alcance
 * (propios / área). "Ver todos" ve todo, sin importar los permisos de destino.
 */
function visibleIdsForSolicitedTo(array $permissions): array
{
    $viewer = User::factory()->create();
    foreach ($permissions as $permission) {
        $viewer->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }

    $other = User::factory()->create();
    $ids = [];
    foreach (['gerencia', 'logistica', null] as $destination) {
        $ids[$destination ?? 'sin'] = PurchaseRequest::create([
            'requester_user_id' => $other->id,
            'date' => now()->toDateString(),
            'status' => 'pending',
            'solicited_to' => $destination,
        ])->id;
    }
    $ids['propia'] = PurchaseRequest::create([
        'requester_user_id' => $viewer->id,
        'date' => now()->toDateString(),
        'status' => 'pending',
        'solicited_to' => 'gerencia',
    ])->id;

    $visible = collect(app(PurchaseRequestService::class)->paginate([], 50, null, $viewer)->items())->pluck('id');

    return array_keys(array_filter($ids, fn ($id) => $visible->contains($id)));
}

it('adds the requests directed to a destination to the user scope', function (array $permissions, array $expected) {
    expect(visibleIdsForSolicitedTo($permissions))->toEqualCanonicalizing($expected);
})->with([
    'propios + a logistica (rol logistica)' => [
        ['ver propios solicitudes compra', 'ver solicitudes compra a logistica'], ['logistica', 'propia'],
    ],
    'propios + a gerencia' => [
        ['ver propios solicitudes compra', 'ver solicitudes compra a gerencia'], ['gerencia', 'propia'],
    ],
    'propios + ambos destinos' => [
        ['ver propios solicitudes compra', 'ver solicitudes compra a gerencia', 'ver solicitudes compra a logistica'],
        ['gerencia', 'logistica', 'propia'],
    ],
    'solo a logistica (sin alcance)' => [['ver solicitudes compra a logistica'], ['logistica']],
    'solo propios' => [['ver propios solicitudes compra'], ['propia']],
    'ver todos' => [['ver todos solicitudes compra'], ['gerencia', 'logistica', 'sin', 'propia']],
    'ver todos + a logistica no restringe' => [
        ['ver todos solicitudes compra', 'ver solicitudes compra a logistica'], ['gerencia', 'logistica', 'sin', 'propia'],
    ],
]);

it('keeps other users drafts hidden even when directed to a permitted destination', function () {
    $viewer = User::factory()->create();
    $viewer->givePermissionTo(Permission::findOrCreate('ver solicitudes compra a logistica', 'web'));

    $draft = PurchaseRequest::create([
        'requester_user_id' => User::factory()->create()->id,
        'date' => now()->toDateString(),
        'status' => 'draft',
        'solicited_to' => 'logistica',
    ]);

    $ids = collect(app(PurchaseRequestService::class)->paginate([], 50, null, $viewer)->items())->pluck('id');

    expect($ids)->not->toContain($draft->id);
});

it('lets a user with only a destination permission open the module and the request', function () {
    $viewer = User::factory()->create();
    $viewer->givePermissionTo(Permission::findOrCreate('ver solicitudes compra a logistica', 'web'));

    $request = PurchaseRequest::create([
        'requester_user_id' => User::factory()->create()->id,
        'date' => now()->toDateString(),
        'status' => 'pending',
        'solicited_to' => 'logistica',
    ]);

    $this->actingAs($viewer)->getJson(route('gastos.purchase-requests.getData'))
        ->assertOk()
        ->assertJsonPath('data.0.id', $request->id);

    $this->actingAs($viewer)->getJson(route('gastos.purchase-requests.show', $request))->assertOk();
});
