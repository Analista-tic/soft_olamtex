<?php

use App\Models\Settings\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->withoutMiddleware();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('updates a role without requiring area fields and persists permissions', function () {
    $oldPermission = Permission::create([
        'name' => 'roles.old.permission',
        'guard_name' => 'web',
    ]);

    $newPermission = Permission::create([
        'name' => 'roles.new.permission',
        'guard_name' => 'web',
    ]);

    $role = Role::create([
        'name' => 'Supervisor',
        'guard_name' => 'web',
    ]);

    $role->syncPermissions([$oldPermission->name]);

    $response = $this->putJson("/roles/{$role->id}", [
        'name' => 'Supervisor actualizado',
        'permissions' => [$newPermission->name],
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('name', 'Supervisor actualizado');

    expect($role->fresh()->name)->toBe('Supervisor actualizado');
    expect($role->fresh()->permissions->pluck('name')->all())->toBe([$newPermission->name]);
});

it('returns distinct records when requesting different role pages', function () {
    foreach (range(1, 25) as $index) {
        Role::create([
            'name' => "Role {$index}",
            'guard_name' => 'web',
        ]);
    }

    $pageOne = $this->getJson('/roles-data?page=1&per_page=10');
    $pageTwo = $this->getJson('/roles-data?page=2&per_page=10');

    $pageOne
        ->assertOk()
        ->assertJsonPath('current_page', 1)
        ->assertJsonCount(10, 'data');

    $pageTwo
        ->assertOk()
        ->assertJsonPath('current_page', 2)
        ->assertJsonCount(10, 'data');

    expect($pageOne->json('data.0.id'))->not->toBe($pageTwo->json('data.0.id'));
    expect($pageOne->json('data.*.id'))->not->toBe($pageTwo->json('data.*.id'));
});
