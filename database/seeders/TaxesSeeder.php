<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TaxesSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $taxes = [
            ['name' => 'IVA 19%', 'percentage' => 19.00, 'status' => true],
            ['name' => 'Exento', 'percentage' => 0.00, 'status' => true],
            ['name' => 'IVA 5%', 'percentage' => 5.00, 'status' => true],
        ];

        foreach ($taxes as $tax) {
            $exists = DB::table('taxes')->where('name', $tax['name'])->exists();
            if (!$exists) {
                DB::table('taxes')->insert(array_merge($tax, [
                    'created_at' => $now,
                    'updated_at' => $now,
                ]));
            }
        }
    }
}
