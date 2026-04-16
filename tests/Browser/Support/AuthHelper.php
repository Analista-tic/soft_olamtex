<?php

namespace Tests\Browser\Support;

use App\Models\Settings\User;
use Laravel\Dusk\Browser;

class AuthHelper
{
    public static function createUser(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'email'    => 'tester@olamtex.test',
            'password' => bcrypt('password'),
        ], $attributes));
    }

    public static function loginAs(Browser $browser, User $user): Browser
    {
        return $browser
            ->visit('/login')
            ->waitFor('[dusk="campo-email"]')
            ->type('[dusk="campo-email"]', $user->email)
            ->type('[dusk="campo-password"]', 'password')
            ->click('[dusk="btn-submit"]')
            ->waitUntilMissing('[dusk="campo-email"]', 15);
    }

    public static function loginWithRole(Browser $browser, string $role): User
    {
        $user = User::firstOrCreate(
            ['email' => 'compras_tester@olamtex.test'],
            [
                'name' => 'Compras Tester',
                'password' => bcrypt('password'),
            ]
        );
        $user->syncRoles([$role]);
        $browser->loginAs($user);

        return $user;
    }
}
