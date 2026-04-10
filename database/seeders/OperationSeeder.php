<?php

namespace Database\Seeders;

use App\Models\Production\OperationSection;
use Illuminate\Database\Seeder;

/**
 * Seeder de parametrización para módulo de operaciones.
 *
 * Regla: este seeder NO crea registros en tabla `operations`.
 * Solo asegura las secciones base para el formulario/listados.
 */
class OperationSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['PREPARACION', 'ENSAMBLE', 'TERMINACION'] as $name) {
            $section = OperationSection::withTrashed()->firstOrNew(['name' => $name]);
            $section->is_active = true;
            $section->deleted_at = null;
            $section->save();
        }
    }
}
