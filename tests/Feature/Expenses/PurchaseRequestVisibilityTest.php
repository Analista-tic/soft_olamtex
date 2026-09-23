<?php

use App\Models\Expenses\PurchaseRequest;
use App\Models\Settings\Setting;
use App\Models\Settings\User;
use App\Services\Expenses\PurchaseRequestService;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Regresión para "se guarda como borrador pero no aparece en el listado":
 * confirma que, para cada combinación de permiso/rol de visibilidad que existe
 * en el módulo, el creador de una solicitud SIEMPRE puede ver su propio
 * borrador a través de PurchaseRequestService::paginate() — el mismo método
 * que usa el endpoint /gastos/solicitudes-compra/data.
 */
function purchaseRequestVisible(PurchaseRequest $request, User $user): bool
{
    $service = app(PurchaseRequestService::class);
    $result = $service->paginate([], 20, Request::create('/'), $user);

    return $result->getCollection()->pluck('id')->contains($request->id);
}

it('shows the creator own draft with "ver propios solicitudes compra"', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('ver propios solicitudes compra', 'web'));

    $request = PurchaseRequest::factory()->withRequester($user)->draft()->create();

    expect(purchaseRequestVisible($request, $user))->toBeTrue();
});

it('shows the creator own draft with "ver area solicitudes compra"', function () {
    $area = Setting::create(['kind' => Setting::KIND_PARAMETER, 'key' => 'area-test-'.uniqid(), 'label' => 'Area Test']);
    $user = User::factory()->create(['area_id' => $area->id]);
    $user->givePermissionTo(Permission::findOrCreate('ver area solicitudes compra', 'web'));

    $request = PurchaseRequest::factory()->withRequester($user)->draft()->create();

    expect(purchaseRequestVisible($request, $user))->toBeTrue();
});

it('shows the creator own draft with "ver todos solicitudes compra" and role logistica', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('ver todos solicitudes compra', 'web'));
    $user->assignRole(Role::findOrCreate('logistica', 'web'));

    $request = PurchaseRequest::factory()->withRequester($user)->draft()->create();

    expect(purchaseRequestVisible($request, $user))->toBeTrue();
});

it('shows the creator own draft with "ver todos solicitudes compra" and role admin', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('ver todos solicitudes compra', 'web'));
    $user->assignRole(Role::findOrCreate('admin', 'web'));

    $request = PurchaseRequest::factory()->withRequester($user)->draft()->create();

    expect(purchaseRequestVisible($request, $user))->toBeTrue();
});

it('shows the creator own draft with "ver todos solicitudes compra" and no special role', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('ver todos solicitudes compra', 'web'));

    $request = PurchaseRequest::factory()->withRequester($user)->draft()->create();

    expect(purchaseRequestVisible($request, $user))->toBeTrue();
});

it('hides a draft from a user without any visibility permission', function () {
    $owner = User::factory()->create();
    $owner->givePermissionTo(Permission::findOrCreate('ver propios solicitudes compra', 'web'));
    $request = PurchaseRequest::factory()->withRequester($owner)->draft()->create();

    $otherUser = User::factory()->create();

    expect(purchaseRequestVisible($request, $otherUser))->toBeFalse();
});
