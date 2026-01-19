<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class WarehousesSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $warehouses = [
            ['name' => 'Principal', 'observation' => 'Almacén principal'],
            ['name' => 'Secundario', 'observation' => 'Bodega secundaria'],
        ];

        foreach ($warehouses as $w) {
            $exists = DB::table('warehouses')->where('name', $w['name'])->exists();
            if (!$exists) {
                DB::table('warehouses')->insert([
                    'name' => $w['name'],
                    'observation' => $w['observation'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }
}
