<?php

use App\Models\Settings\Area;
use App\Models\Settings\Role;
use App\Models\Settings\User;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    // Sin withoutMiddleware() a secas: desactivaría también SubstituteBindings y los
    // parámetros {area}/{usuario} llegarían como modelos vacíos en vez de resolverse por id.
    $this->withoutMiddleware([
        \Illuminate\Auth\Middleware\Authenticate::class,
        \Illuminate\Auth\Middleware\EnsureEmailIsVerified::class,
        \Spatie\Permission\Middleware\PermissionMiddleware::class,
    ]);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('assigns an area from the areas table to a user (regression: 422 on area_id)', function () {
    $area = Area::create(['name' => 'Tics']);
    Role::create(['name' => 'admin', 'guard_name' => 'web']);
    $user = User::factory()->create();

    $this->putJson("/usuarios/{$user->id}", [
        'name' => $user->name,
        'email' => $user->email,
        'roles' => ['admin'],
        'area_id' => $area->id,
    ])->assertOk();

    expect($user->fresh()->area_id)->toBe($area->id);
});

it('rejects an area_id that does not exist in the areas table', function () {
    Role::create(['name' => 'admin', 'guard_name' => 'web']);
    $user = User::factory()->create();

    $this->putJson("/usuarios/{$user->id}", [
        'name' => $user->name,
        'email' => $user->email,
        'roles' => ['admin'],
        'area_id' => 999999,
    ])->assertUnprocessable()->assertJsonValidationErrors('area_id');
});

it('creates an area with an uppercase name and rejects duplicates', function () {
    $this->postJson('/areas', ['name' => 'Calidad'])
        ->assertCreated()
        ->assertJsonPath('name', 'CALIDAD');

    $this->postJson('/areas', ['name' => 'calidad'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('name');
});

it('updates and deactivates an area, keeping its own name valid', function () {
    $area = Area::create(['name' => 'Corte']);

    $this->putJson("/areas/{$area->id}", ['name' => 'Corte', 'is_active' => false])
        ->assertOk()
        ->assertJsonPath('is_active', false);

    expect($area->fresh()->is_active)->toBeFalse();
});

it('lists areas with their users count', function () {
    $area = Area::create(['name' => 'Diseño']);
    User::factory()->count(2)->create(['area_id' => $area->id]);

    $this->getJson('/areas/list')
        ->assertOk()
        ->assertJsonFragment(['id' => $area->id, 'users_count' => 2]);
});

it('refuses to delete an area that has users and deletes an empty one', function () {
    $used = Area::create(['name' => 'Logística']);
    User::factory()->create(['area_id' => $used->id]);
    $empty = Area::create(['name' => 'SST']);

    $this->deleteJson("/areas/{$used->id}")->assertStatus(409);
    expect(Area::find($used->id))->not->toBeNull();

    $this->deleteJson("/areas/{$empty->id}")->assertNoContent();
    expect(Area::find($empty->id))->toBeNull();
});
