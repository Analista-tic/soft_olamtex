<?php

namespace Database\Seeders;

use App\Models\Production\Operation;
use App\Models\Production\OperationSection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class OperationsSeeder extends Seeder
{
    public function run(): void
    {
        $sections = $this->ensureSections();
        $hasLocationColumn = Schema::hasColumn('operations', 'location');
        $hasDescriptionColumn = Schema::hasColumn('operations', 'description');

        $operations = [
            // Delantero
            ['action' => 'CERRAR', 'garment_part' => 'TIRO DELANTERO', 'section' => 'Delantero', 'location' => 'LÍNEA 1', 'measure' => null, 'quantity' => 1, 'sam_base' => 0.45],
            ['action' => 'CERRAR', 'garment_part' => 'BOLSILLO DELANTERO', 'section' => 'Delantero', 'location' => 'LÍNEA 1', 'measure' => 12.5, 'quantity' => 2, 'sam_base' => 0.38],
            ['action' => 'CERRAR', 'garment_part' => 'BOLSILLO BOCA OJAL', 'section' => 'Delantero', 'location' => 'LÍNEA 1', 'measure' => 14.0, 'quantity' => 1, 'sam_base' => 0.28],
            ['action' => 'CERRAR', 'garment_part' => 'BOLSILLO DELANTERO CURVO CON MALLA', 'section' => 'Delantero', 'location' => 'LÍNEA 2', 'measure' => 13.0, 'quantity' => 2, 'sam_base' => 0.42],
            ['action' => 'ASENTAR', 'garment_part' => 'LOMO BOLSILLO DELANTERO', 'section' => 'Delantero', 'location' => 'LÍNEA 1', 'measure' => null, 'quantity' => 2, 'sam_base' => 0.15],

            // Trasero
            ['action' => 'CERRAR', 'garment_part' => 'TIRO TRASERO', 'section' => 'Trasero', 'location' => 'LÍNEA 1', 'measure' => null, 'quantity' => 1, 'sam_base' => 0.52],
            ['action' => 'PLANCHAR', 'garment_part' => 'BOLSILLO TRASERO', 'section' => 'Trasero', 'location' => 'PLANCHA', 'measure' => 15.0, 'quantity' => 2, 'sam_base' => 0.22],
            ['action' => 'PLANCHAR', 'garment_part' => 'BOLSILLO PARCHE TRASERO', 'section' => 'Trasero', 'location' => 'PLANCHA', 'measure' => 16.0, 'quantity' => 2, 'sam_base' => 0.25],
            ['action' => 'ASENTAR', 'garment_part' => 'PRENDIDA DE TAPA', 'section' => 'Trasero', 'location' => 'LÍNEA 2', 'measure' => null, 'quantity' => 2, 'sam_base' => 0.18],

            // Pretina
            ['action' => 'ARMAR', 'garment_part' => 'PRETINA', 'section' => 'Pretina', 'location' => 'LÍNEA 1', 'measure' => 3.5, 'quantity' => 1, 'sam_base' => 0.35],
            ['action' => 'PEGAR', 'garment_part' => 'PRETINA', 'section' => 'Pretina', 'location' => 'LÍNEA 1', 'measure' => null, 'quantity' => 1, 'sam_base' => 0.28],
            ['action' => 'ASENTAR', 'garment_part' => 'ELASTICO PRETINA INCORPORADA', 'section' => 'Pretina', 'location' => 'LÍNEA 1', 'measure' => null, 'quantity' => 1, 'sam_base' => 0.32],
            ['action' => 'ASENTAR', 'garment_part' => 'PRETINA ANTIFLUIDA', 'section' => 'Pretina', 'location' => 'LÍNEA 1', 'measure' => null, 'quantity' => 1, 'sam_base' => 0.30],

            // Ensamble
            ['action' => 'FILETEAR', 'garment_part' => 'COSTADOS', 'section' => 'Ensamble', 'location' => 'LÍNEA 1', 'measure' => null, 'quantity' => 2, 'sam_base' => 0.58],
            ['action' => 'FILETEAR', 'garment_part' => 'TIRO', 'section' => 'Ensamble', 'location' => 'LÍNEA 1', 'measure' => null, 'quantity' => 1, 'sam_base' => 0.42],
            ['action' => 'COSER', 'garment_part' => 'BOLSILLO DELANTERO', 'section' => 'Ensamble', 'location' => 'LÍNEA 1', 'measure' => 12.5, 'quantity' => 2, 'sam_base' => 0.25],
            ['action' => 'ATRACAR', 'garment_part' => 'CORDON TUBULAR BOTAS', 'section' => 'Ensamble', 'location' => 'ATRACADORA', 'measure' => null, 'quantity' => 2, 'sam_base' => 0.12],
            ['action' => 'ATRACAR', 'garment_part' => 'CORDON TUBULAR CINTURA', 'section' => 'Ensamble', 'location' => 'ATRACADORA', 'measure' => null, 'quantity' => 1, 'sam_base' => 0.10],
            ['action' => 'ATRACAR', 'garment_part' => 'HILADILLA', 'section' => 'Ensamble', 'location' => 'ATRACADORA', 'measure' => null, 'quantity' => 1, 'sam_base' => 0.08],

            // Terminación
            ['action' => 'HACER', 'garment_part' => 'OJALES', 'section' => 'Terminacion', 'location' => 'OJALADORA', 'measure' => null, 'quantity' => 5, 'sam_base' => 0.15],
            ['action' => 'PEGAR', 'garment_part' => 'BOTONES', 'section' => 'Terminacion', 'location' => 'BOTONADORA', 'measure' => null, 'quantity' => 5, 'sam_base' => 0.20],
            ['action' => 'REVISAR', 'garment_part' => 'CALIDAD', 'section' => 'Terminacion', 'location' => 'INSPECCIÓN', 'measure' => null, 'quantity' => 1, 'sam_base' => 0.35],

            // Presentación
            ['action' => 'PLANCHAR', 'garment_part' => 'PRENDA COMPLETA', 'section' => 'Presentacion', 'location' => 'PLANCHA FINAL', 'measure' => null, 'quantity' => 1, 'sam_base' => 0.45],
            ['action' => 'DOBLAR', 'garment_part' => 'PRENDA', 'section' => 'Presentacion', 'location' => 'MESA DOBLADO', 'measure' => null, 'quantity' => 1, 'sam_base' => 0.12],
            ['action' => 'EMBOLSAR', 'garment_part' => 'PRENDA', 'section' => 'Presentacion', 'location' => 'EMPAQUE', 'measure' => null, 'quantity' => 1, 'sam_base' => 0.08],

            // Blusa manga corta
            ['action' => 'UNIR', 'garment_part' => 'CUELLO REDONDO Y VOLTEAR', 'section' => 'Ensamble', 'location' => 'MF3H', 'measure' => null, 'quantity' => 1, 'sam_base' => 0.25],
            ['action' => 'UNIR', 'garment_part' => 'HOMBROS', 'section' => 'Ensamble', 'location' => 'MF3H', 'measure' => null, 'quantity' => 1, 'sam_base' => 0.35],
            ['action' => 'PRENDER', 'garment_part' => 'CUELLO REDONDO', 'section' => 'Ensamble', 'location' => 'MF3H', 'measure' => null, 'quantity' => 1, 'sam_base' => 0.90],
            ['action' => 'FIJAR', 'garment_part' => 'CONTORNO DE CUELLO', 'section' => 'Ensamble', 'location' => 'MPP', 'measure' => null, 'quantity' => 1, 'sam_base' => 0.50],
            ['action' => 'FILETEAR', 'garment_part' => 'CUELLO EN RUCHE (MU298)', 'section' => 'Ensamble', 'location' => 'MF3H', 'measure' => null, 'quantity' => 1, 'sam_base' => 0.50],
            ['action' => 'PRENDER', 'garment_part' => 'MANGA CORTA X2', 'section' => 'Ensamble', 'location' => 'MF5H', 'measure' => null, 'quantity' => 2, 'sam_base' => 0.53],
            ['action' => 'CERRAR', 'garment_part' => 'LADOS MC', 'section' => 'Ensamble', 'location' => 'MF5H', 'measure' => null, 'quantity' => 1, 'sam_base' => 0.75],
            ['action' => 'FILETEAR', 'garment_part' => 'RUEDO MANGA EN RUCHE X2 (301333)', 'section' => 'Ensamble', 'location' => 'MF3H', 'measure' => null, 'quantity' => 2, 'sam_base' => 0.45],
            ['action' => 'FILETEAR', 'garment_part' => 'RUEDO EN RUCHE (301333)', 'section' => 'Ensamble', 'location' => 'MF3H', 'measure' => null, 'quantity' => 1, 'sam_base' => 0.50],
            ['action' => 'REVISAR', 'garment_part' => 'BLUSA MANGA CORTA', 'section' => 'Terminacion', 'location' => 'OFM', 'measure' => null, 'quantity' => 1, 'sam_base' => 0.63],
            ['action' => 'PLANCHAR', 'garment_part' => 'BLUSA/CHALECO', 'section' => 'Presentacion', 'location' => 'PLANCH', 'measure' => null, 'quantity' => 1, 'sam_base' => 0.50],
            ['action' => 'DOBLAR', 'garment_part' => 'FINAL BLUSA/CHALECO', 'section' => 'Presentacion', 'location' => 'OFM', 'measure' => null, 'quantity' => 1, 'sam_base' => 0.60],
            ['action' => 'PREPARAR', 'garment_part' => 'STICKER', 'section' => 'Presentacion', 'location' => 'OFM', 'measure' => null, 'quantity' => 1, 'sam_base' => 0.08],
            ['action' => 'TIQUETEAR', 'garment_part' => 'PRENDA', 'section' => 'Presentacion', 'location' => 'OFM', 'measure' => null, 'quantity' => 1, 'sam_base' => 0.08],
            ['action' => 'EMBOLSAR', 'garment_part' => 'BLUSA', 'section' => 'Presentacion', 'location' => 'OFM', 'measure' => null, 'quantity' => 1, 'sam_base' => 0.14],
            ['action' => 'EMPACAR', 'garment_part' => 'EN CAJAS', 'section' => 'Presentacion', 'location' => 'OFM', 'measure' => null, 'quantity' => 1, 'sam_base' => 0.10],

            // Tiempo módulo - Cuello (cantidad = SAM en minutos)
            ['action' => 'SESGAR', 'garment_part' => 'CUELLO', 'section' => 'Cuello', 'location' => 'COLL', 'measure' => null, 'quantity' => null, 'sam_base' => 0.60],

            // Tiempo módulo - Ensamble (cantidad = SAM en minutos)
            ['action' => 'SESGAR', 'garment_part' => 'SISA X 2', 'section' => 'Ensamble', 'location' => 'COLL', 'measure' => null, 'quantity' => null, 'sam_base' => 1.00],
            ['action' => 'UNIR', 'garment_part' => 'HOMBROS', 'section' => 'Ensamble', 'location' => 'MF4H', 'measure' => null, 'quantity' => null, 'sam_base' => 0.46],
            ['action' => 'UNIR', 'garment_part' => 'HOMBROS FORRO', 'section' => 'Ensamble', 'location' => 'MF4H', 'measure' => null, 'quantity' => null, 'sam_base' => 0.46],
            ['action' => 'EMBONAR', 'garment_part' => 'SISA X 2', 'section' => 'Ensamble', 'location' => 'MF4H', 'measure' => null, 'quantity' => null, 'sam_base' => 1.73],
            ['action' => 'FIJAR', 'garment_part' => 'COSTADOS FORRO X 2', 'section' => 'Ensamble', 'location' => 'MPP', 'measure' => null, 'quantity' => null, 'sam_base' => 0.33],
            ['action' => 'CERRAR', 'garment_part' => 'LADOS', 'section' => 'Ensamble', 'location' => 'MF4H', 'measure' => null, 'quantity' => null, 'sam_base' => 1.43],
            ['action' => 'ATRACAR', 'garment_part' => 'LADOS X 2', 'section' => 'Ensamble', 'location' => 'MPP', 'measure' => null, 'quantity' => null, 'sam_base' => 0.57],
            ['action' => 'DOBLADILLAR', 'garment_part' => 'RUEDO', 'section' => 'Ensamble', 'location' => 'COLL', 'measure' => null, 'quantity' => null, 'sam_base' => 1.00],
            ['action' => 'PRENDER', 'garment_part' => 'MARQUILLA', 'section' => 'Ensamble', 'location' => 'MPP', 'measure' => null, 'quantity' => null, 'sam_base' => 0.30],

            // Tiempo módulo - Terminación (cantidad = SAM en minutos)
            ['action' => 'REVISAR', 'garment_part' => 'BLUSA', 'section' => 'Terminacion', 'location' => 'OFM', 'measure' => null, 'quantity' => null, 'sam_base' => 0.30],
            ['action' => 'PLANCHAR', 'garment_part' => 'BLUSA/CHALECO', 'section' => 'Terminacion', 'location' => 'PLANCH', 'measure' => null, 'quantity' => null, 'sam_base' => 0.50],
            ['action' => 'DOBLAR FINAL', 'garment_part' => 'BLUSA/CHALECO', 'section' => 'Terminacion', 'location' => 'OFM', 'measure' => null, 'quantity' => null, 'sam_base' => 0.60],
            ['action' => 'PREPARAR', 'garment_part' => 'STICKER', 'section' => 'Terminacion', 'location' => 'OFM', 'measure' => null, 'quantity' => null, 'sam_base' => 0.08],
            ['action' => 'TIQUETEAR', 'garment_part' => 'PRENDA', 'section' => 'Terminacion', 'location' => 'OFM', 'measure' => null, 'quantity' => null, 'sam_base' => 0.08],
            ['action' => 'EMBOLSAR', 'garment_part' => 'PRENDA', 'section' => 'Terminacion', 'location' => 'OFM', 'measure' => null, 'quantity' => null, 'sam_base' => 0.14],
            ['action' => 'EMPACAR EN CAJAS', 'garment_part' => 'PRENDA', 'section' => 'Terminacion', 'location' => 'OFM', 'measure' => null, 'quantity' => null, 'sam_base' => 0.10],
        ];

        foreach ($operations as $data) {
            $sectionId = $sections[$data['section']] ?? null;

            $payload = [
                'section_id' => $sectionId,
                'measure' => $data['measure'],
                'quantity' => $data['quantity'],
                'sam_base' => $data['sam_base'],
                'is_active' => true,
            ];

            if ($hasLocationColumn) {
                $payload['location'] = $data['location'];
            } elseif ($hasDescriptionColumn) {
                $payload['description'] = trim(sprintf('Ubicación ref: %s', (string) ($data['location'] ?? 'N/A')));
            }

            Operation::updateOrCreate(
                [
                    'action' => strtoupper(trim($data['action'])),
                    'garment_part' => strtoupper(trim($data['garment_part'])),
                ],
                $payload
            );
        }

        $this->ensureCodigos();
    }

    /**
     * Asegura que existan las secciones operacionales.
     *
     * @return array<string, int> Mapa nombre => id
     */
    private function ensureSections(): array
    {
        $sections = [
            ['name' => 'Delantero', 'type' => 'confeccion'],
            ['name' => 'Trasero', 'type' => 'confeccion'],
            ['name' => 'Pretina', 'type' => 'confeccion'],
            ['name' => 'Cuello', 'type' => 'confeccion'],
            ['name' => 'Ensamble', 'type' => 'confeccion'],
            ['name' => 'Terminacion', 'type' => 'presentacion'],
            ['name' => 'Presentacion', 'type' => 'presentacion'],
        ];

        $map = [];
        foreach ($sections as $s) {
            $section = OperationSection::firstOrCreate(
                ['name' => $s['name']],
                ['type' => $s['type'], 'is_active' => true]
            );
            $map[$s['name']] = $section->id;
        }

        return $map;
    }

    /**
     * Asigna codigo OP-XXXX a operaciones que no lo tengan.
     */
    private function ensureCodigos(): void
    {
        Operation::where(function ($q) {
            $q->whereNull('codigo')->orWhere('codigo', '');
        })->chunkById(100, function ($ops) {
            foreach ($ops as $op) {
                $op->update(['codigo' => 'OP-' . str_pad((string) $op->id, 4, '0', STR_PAD_LEFT)]);
            }
        });
    }
}
