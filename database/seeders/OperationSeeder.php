<?php

namespace Database\Seeders;

use App\Models\Production\OperationAction;
use App\Models\Production\OperationGarmentPart;
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
        $this->seedSections();
        $this->seedActions();
        $this->seedGarmentParts();
    }

    private function seedSections(): void
    {
        $sections = [
            ['name' => 'PREPARACION', 'type' => 'PREPARACION'],
            ['name' => 'ENSAMBLE',    'type' => 'ENSAMBLE'],
            ['name' => 'TERMINACION', 'type' => 'TERMINACION'],
        ];

        foreach ($sections as $data) {
            $section = OperationSection::withTrashed()->firstOrNew(['name' => $data['name']]);
            $section->type = $data['type'];
            $section->is_active = true;
            $section->deleted_at = null;
            $section->save();
        }
    }

    private function seedActions(): void
    {
        $actions = [
            'ABRIR', 'ABOTONAR', 'AMARRAR', 'ATRACAR', 'BOTONAR',
            'CABECEAR', 'CERRAR', 'COLOCAR', 'CORTAR', 'CUARTEAR',
            'DOBLADILLAR', 'EMBOLSAR', 'EMBONAR', 'ENTALEGAR',
            'FIJAR', 'FILETEAR', 'FUSIONAR', 'HACER', 'INSERTAR',
            'MARCAR', 'MEDIR Y CORTAR', 'OJALAR', 'PESPUNTAR',
            'PICAR Y VOLTEAR', 'PLANCHAR', 'PREPARAR', 'PRENDER',
            'QUITAR', 'REVOCAR', 'REVISAR', 'SESGAR', 'TERMINAR',
            'TIQUETEAR', 'UBICAR', 'UNIR', 'VAPORIZAR', 'VOLTEAR', 'ZURCIR',
        ];

        foreach ($actions as $name) {
            $normalized = mb_strtoupper(trim($name));
            if ($normalized === '') {
                continue;
            }

            $action = OperationAction::withTrashed()->firstOrNew(['name' => $normalized]);
            $action->is_active = true;
            $action->deleted_at = null;
            $action->save();
        }
    }

    private function seedGarmentParts(): void
    {
        $parts = [
            'ALETILLA', 'ALETILLON', 'BOTON', 'BOLSILLO', 'CANASTA',
            'CAPOTA', 'CARTERA', 'CHARRETERA', 'CIERRE', 'CINTURON',
            'COLAS', 'CODIGO BARRA', 'CORDON', 'CUELLO', 'DELANTEROS',
            'ELASTICO', 'ENTREPIERNA', 'ESPALDA', 'FAJON', 'FRENTES',
            'GAFFETE', 'HILADILLA', 'HOMBROS', 'LATERAL', 'MANGA CORTA',
            'MANGA LARGA', 'MANGAS', 'MOÑO', 'PERILLA', 'PRETINA',
            'PRENDA COMPLETA', 'PUÑO', 'PUÑOS', 'RUEDO', 'SOLAPA',
            'TAPA', 'TIRA', 'TRASEROS', 'FORRO', 'NUDO', 'GLOBO', 'PERLA',
        ];

        foreach ($parts as $name) {
            $normalized = mb_strtoupper(trim($name));
            if ($normalized === '') {
                continue;
            }

            $part = OperationGarmentPart::withTrashed()->firstOrNew(['name' => $normalized]);
            $part->is_active = true;
            $part->deleted_at = null;
            $part->save();
        }
    }
}
