<?php

use App\Models\Settings\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('guests are redirected to login', function () {
    $this->get('/catalog-sync?keys=items')->assertRedirect('/login');
    $this->post('/catalog-sync/touch', ['key' => 'items'])->assertRedirect('/login');
});

test('unknown catalogs come back as null', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->getJson('/catalog-sync?keys=items,taxes');

    $response->assertOk();
    $response->assertJson(['items' => null, 'taxes' => null]);
});

test('touching a catalog updates its timestamp for later reads', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->postJson('/catalog-sync/touch', ['key' => 'items'])->assertNoContent();

    $response = $this->getJson('/catalog-sync?keys=items,taxes');

    $response->assertOk();
    $data = $response->json();

    expect($data['items'])->not->toBeNull();
    expect($data['taxes'])->toBeNull();
});

test('touching a catalog again advances its timestamp', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->postJson('/catalog-sync/touch', ['key' => 'items'])->assertNoContent();
    $first = $this->getJson('/catalog-sync?keys=items')->json('items');

    // El primer touch pudo caer en el mismo segundo que este: se fuerza el reloj
    // hacia adelante para probar que sí se actualiza, sin depender de timing real.
    $this->travel(2)->seconds();

    $this->postJson('/catalog-sync/touch', ['key' => 'items'])->assertNoContent();
    $second = $this->getJson('/catalog-sync?keys=items')->json('items');

    expect($second)->not->toEqual($first);
});
