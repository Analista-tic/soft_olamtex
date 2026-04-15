<?php

use App\Models\Settings\User;
use Laravel\Dusk\Browser;
use Tests\Browser\Support\AuthHelper;

beforeEach(function () {
    User::where('email', 'tester@olamtex.test')->forceDelete();
    User::where('email', 'nopermiso@olamtex.test')->forceDelete();
});

test('un usuario sin permiso recibe 403 al acceder a órdenes de compra', function () {
    $user = User::factory()->create([
        'email'    => 'nopermiso@olamtex.test',
        'password' => bcrypt('password'),
    ]);
    // Sin asignar ningún rol ni permiso

    $this->browse(function (Browser $browser) use ($user) {
        AuthHelper::loginAs($browser, $user)
            ->visit('/gastos/ordenes-compra')
            ->assertSee('403');
    });
});

test('un usuario con permiso puede acceder a órdenes de compra', function () {
    $user = AuthHelper::createUser();
    $user->givePermissionTo('ver ordenes compra');

    $this->browse(function (Browser $browser) use ($user) {
        AuthHelper::loginAs($browser, $user)
            ->visit('/gastos/ordenes-compra')
            ->assertDontSee('403')
            ->assertPathIs('/gastos/ordenes-compra');
    });
});
