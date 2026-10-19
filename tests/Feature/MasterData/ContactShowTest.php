<?php

use App\Models\MasterData\Contact;
use App\Models\Settings\User;
use Spatie\Permission\Models\Permission;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

/**
 * El diálogo de edición hidrata el formulario con GET /contactos/{id}:
 * debe devolver todos los campos, incluidos los JSON (groups, contactos adicionales, sucursales).
 */
it('devuelve el contacto completo para hidratar el formulario de edición', function () {
    Permission::findOrCreate('ver contactos', 'web');
    $user = User::factory()->create();
    $user->givePermissionTo('ver contactos');

    $contact = Contact::create([
        'contact_type' => 'cliente',
        'id_type' => 'nit',
        'id_number' => '900123456',
        'dv' => '8',
        'full_name' => 'Textiles Prueba SAS',
        'person_type' => 'persona_juridica',
        'groups' => [1, 2],
        'municipality' => 'Medellín',
        'department' => 'Antioquia',
        'address' => 'Calle 1 # 2-3',
        'email' => 'prueba@example.com',
        'phone' => '3001234567',
        'additional_contacts' => [['name' => 'Ana', 'phone' => '3000000000', 'email' => 'ana@example.com']],
        'branches' => [['name' => 'Sede Norte', 'address' => 'Carrera 5', 'phone' => '3111111111']],
    ]);

    $this->actingAs($user)
        ->getJson("/contactos/{$contact->id}")
        ->assertOk()
        ->assertJsonPath('id', $contact->id)
        ->assertJsonPath('contact_type', 'cliente')
        ->assertJsonPath('id_type', 'nit')
        ->assertJsonPath('id_number', '900123456')
        ->assertJsonPath('groups', [1, 2])
        ->assertJsonPath('additional_contacts.0.email', 'ana@example.com')
        ->assertJsonPath('branches.0.name', 'Sede Norte');
});
