<?php

use App\Models\Settings\User;
use Laravel\Dusk\Browser;
use Tests\Browser\Support\AuthHelper;

beforeEach(function () {
    User::where('email', 'tester@olamtex.test')->forceDelete();
});

test('un usuario válido puede iniciar sesión y ver el dashboard', function () {
    $user = AuthHelper::createUser();

    $this->browse(function (Browser $browser) use ($user) {
        AuthHelper::loginAs($browser, $user)
            ->assertPathIs('/dashboard');
    });
});

test('credenciales incorrectas no permiten el acceso', function () {
    $this->browse(function (Browser $browser) {
        $browser
            ->visit('/login')
            ->waitFor('[dusk="campo-email"]')
            ->type('[dusk="campo-email"]', 'noexiste@olamtex.test')
            ->type('[dusk="campo-password"]', 'incorrecta')
            ->click('[dusk="btn-submit"]')
            ->waitForText('credenciales')
            ->assertPathIs('/login');
    });
});
