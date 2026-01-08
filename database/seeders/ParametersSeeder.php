<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ParametersSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $parameters = [
            ['nombre' => 'costo operaciones', 'valor' => 406],
            ['nombre' => 'costo hilo', 'valor' => 140],
        ];

        foreach ($parameters as $parameter) {
            DB::table('parameters')->updateOrInsert(
                ['nombre' => $parameter['nombre']],
                ['valor' => json_encode($parameter['valor'])]
            );
        }
    }
}
