<?php

namespace Database\Seeders;

use App\Models\Settings\Area;
use Illuminate\Database\Seeder;

class AreaSeeder extends Seeder
{
    public function run(): void
    {
        $names = [
            'Gerencia', 'Producción', 'Corte', 'Logística', 'Diseño', 'Marketing', 'Comercial',
            'Tics', 'Recursos Humanos', 'Financiero', 'Desarrollo Organizacional', 'SST',
        ];

        foreach ($names as $name) {
            // El modelo normaliza a mayúsculas al guardar; se busca con el mismo valor.
            Area::query()->firstOrCreate(['name' => mb_strtoupper($name, 'UTF-8')], ['is_active' => true]);
        }
    }
}
