<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ContactsSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $contacts = [
            [
                'contact_type' => 'proveedor',
                'id_type' => 'nit',
                'id_number' => '900000001',
                'dv' => null,
                'full_name' => 'Proveedor Textiles Andinos',
                'person_type' => 'persona_juridica',
                'email' => 'proveedor1@ejemplo.com',
                'phone' => '6015550001',
            ],
            [
                'contact_type' => 'proveedor',
                'id_type' => 'nit',
                'id_number' => '900000002',
                'dv' => null,
                'full_name' => 'Hilazas del Café SAS',
                'person_type' => 'persona_juridica',
                'email' => 'proveedor2@ejemplo.com',
                'phone' => '6015550002',
            ],
        ];

        foreach ($contacts as $c) {
            $exists = DB::table('contacts')->where('id_number', $c['id_number'])->exists();
            if (!$exists) {
                DB::table('contacts')->insert(array_merge($c, [
                    'groups' => null,
                    'municipality' => null,
                    'department' => null,
                    'address' => null,
                    'additional_contacts' => null,
                    'branches' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]));
            }
        }
    }
}
