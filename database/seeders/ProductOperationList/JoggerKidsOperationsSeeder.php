<?php

namespace Database\Seeders\ProductOperationList;

use App\Models\Production\Operation;
use Illuminate\Database\Seeder;

/**
 * Production operations seeder — Kids Jogger
 * Source: BASE_LIMPIA_OLAMTEX_FINAL.xlsx — sheet: JOGGER NIÑO
 * Operation IDs: 2882 – 2987 (106 unique operations)
 *
 * Depends on: ProductionListingOperationsSeeder (machines & sections already seeded)
 *
 * Section IDs  → PREPARACION=8, ENSAMBLE=5, TERMINACION=6
 * Machine IDs  → OFM=1, MPP=2, MF3H=3, MF5H=4, MULT=5, FUSION=6,
 *                BOTONAD=7, OJALAD=8, PRESIL=9, TROQ=10, PLANC=11,
 *                COLL=12, MP2A=13, MF4H=14, VAPOR=15 (Knitwear only)
 *
 * Usage: php artisan db:seed --class=JoggerKidsOperationsSeeder
 */
class JoggerKidsOperationsSeeder extends Seeder
{
    public function run(): void
    {
        $now      = now();
        $inserted = 0;
        $skipped  = 0;

        // Columns: [id, action, garment_part, section_name, machine_code,
        //           section_id, machine_id_reference, sam_base]
        $batch = [];
        foreach ($this->getData() as [$id, $action, $garmentPart, , , $sectionId, $machineId, $samNeto]) {
            if (Operation::where('action', $action)->where('garment_part', $garmentPart)->exists()) {
                $skipped++;
                continue;
            }

            $batch[] = [
                'action'               => $action,
                'garment_part'         => $garmentPart,
                'section_id'           => $sectionId,
                'machine_id_reference' => $machineId,
                'sam_base'             => $samNeto,
                'is_active'            => 1,
                'created_at'           => $now,
                'updated_at'           => $now,
            ];

            if (count($batch) >= 100) {
                Operation::insert($batch);
                $inserted += count($batch);
                $batch = [];
            }
        }

        if (!empty($batch)) {
            Operation::insert($batch);
            $inserted += count($batch);
        }

        $this->command->info("✓ [Kids Jogger] Inserted: {$inserted}");
        $this->command->info("⊘ [Kids Jogger] Skipped (already exist): {$skipped}");
    }

    /**
     * [id, action, garment_part, section_name, machine_code, section_id, machine_id_reference, sam_neto]
     * IDs 2882–2987
     */
    private function getData(): array
    {
        return [
            ['2882', 'ASENTAR', 'BOLSILLO DELANTERO X 2', 'PREPARACION', 'MPP', 8, 2, 0.24137931],
            ['2883', 'ASENTAR', 'ELASTICO BOTA X 2', 'ENSAMBLE', 'MPP', 5, 2, 0.80172414],
            ['2884', 'ASENTAR', 'CORTE DELANTERO X 2', 'PREPARACION', 'MPP', 8, 2, 0.56896552],
            ['2885', 'ASENTAR', 'CORTE DELANTERO Y TRASERO X2', 'PREPARACION', 'MPP', 8, 2, 1.13793103],
            ['2886', 'ASENTAR', 'CORTE TRASERO X 2', 'PREPARACION', 'MPP', 8, 2, 0.56896552],
            ['2887', 'ASENTAR', 'PRENDIDA DE TAPA X 2', 'PREPARACION', 'MPP', 8, 2, 0.60344828],
            ['2888', 'ASENTAR', 'ELASTICO PRETINA INCORPORADA', 'ENSAMBLE', 'MPP', 5, 2, 0.65517241],
            ['2889', 'ATRACAR', 'EXTREMOS HILADILLA', 'ENSAMBLE', 'MPP', 5, 2, 0.25862069],
            ['2890', 'ASENTAR', 'PRETINA EN 2 AGUJAS', 'PREPARACION', 'MP2A', 8, 13, 1.31034483],
            ['2891', 'CERRAR', 'ALETA X 1', 'PREPARACION', 'MPP', 8, 2, 0.21551724],
            ['2892', 'CERRAR', 'ENTREPIERNA', 'ENSAMBLE', 'MF3H', 5, 3, 0.39655172],
            ['2893', 'CERRAR', 'LATERAL', 'ENSAMBLE', 'MF3H', 5, 3, 0.51724138],
            ['2894', 'CERRAR', 'BOLSILLO DELANTERO X 2', 'PREPARACION', 'MF3H', 8, 3, 0.37068966],
            ['2895', 'CERRAR', 'PRETINA MANUAL', 'PREPARACION', 'MPP', 8, 2, 0.86206897],
            ['2896', 'CERRAR', 'LATERALES INFERIORES', 'ENSAMBLE', 'MPP', 5, 2, 0.75],
            ['2897', 'CERRAR', 'LATERALES SUPERIORES', 'ENSAMBLE', 'MPP', 5, 2, 0.56034483],
            ['2898', 'DOBLADILAR', 'RUEDO', 'ENSAMBLE', 'MPP', 5, 2, 0.6637931],
            ['2899', 'DOBLAR', 'HILADILLA 2 VECES Y ATRACAR', 'ENSAMBLE', 'MPP', 5, 2, 0.51724138],
            ['2900', 'CERRAR', 'PRETINA PESPUNTANDO', 'PREPARACION', 'MULT', 8, 5, 0.67],
            ['2901', 'CERRAR', 'TAPA X 2', 'PREPARACION', 'MPP', 8, 2, 0.43103448],
            ['2902', 'CERRAR', 'TIRA CINTURON X 2', 'PREPARACION', 'MPP', 8, 2, 0.95],
            ['2903', 'CERRAR', 'TIRO DELANTERO', 'PREPARACION', 'MF3H', 8, 3, 0.19827586],
            ['2904', 'CERRAR', 'TIRO DELANTERO JOTA INCLUIDA', 'PREPARACION', 'MF3H', 8, 3, 0.25862069],
            ['2905', 'CERRAR', 'TIRO TRASERO', 'PREPARACION', 'MF3H', 8, 3, 0.19827586],
            ['2906', 'CERRAR', 'TRIANGULOS CRESTA X 3', 'PREPARACION', 'MPP', 8, 2, 0.64655172],
            ['2907', 'CUARTEAR', 'ELASTICO PRETINA X 1', 'PREPARACION', 'OFM', 8, 1, 0.12931034],
            ['2908', 'DOBLADILLAR', 'BOLSILLO PARCHE X 2', 'PREPARACION', 'MPP', 8, 2, 0.25862069],
            ['2909', 'DOBLAR', 'FINAL NIÑA', 'TERMINACION', 'OFM', 6, 1, 0.43103448],
            ['2910', 'EMBOLSAR', '', 'TERMINACION', 'OFM', 6, 1, 0.12068966],
            ['2911', 'DOBLADILLAR', 'BOLSILLO SOBREPUESTO', 'PREPARACION', 'MPP', 8, 2, 0.38793103],
            ['2912', 'ENTALEGAR', 'CORTE OREJITAS', 'PREPARACION', 'MPP', 8, 2, 0.86206897],
            ['2913', 'FIJAR', 'BOLSILLO DELANTERO X 2', 'PREPARACION', 'MPP', 8, 2, 0.3362069],
            ['2914', 'FIJAR', 'ELASTICO EN PRETINA', 'PREPARACION', 'MPP', 8, 2, 0.25862069],
            ['2915', 'DOBLAR', 'Y HACER ATRAQUE X 2', 'ENSAMBLE', 'MPP', 5, 2, 0.73275862],
            ['2916', 'FIJAR', 'Y ASENTAR TIRA CINTURON X 2', 'ENSAMBLE', 'MPP', 5, 2, 0.68965517],
            ['2917', 'EMPACAR', 'EN CAJAS', 'TERMINACION', 'OFM', 6, 1, 0.0862069],
            ['2918', 'HACER', 'MOÑO', 'TERMINACION', 'OFM', 6, 1, 0.51724138],
            ['2919', 'FIJAR', 'TRIANGULOS A LATERAL HACIENDO FIGURA', 'PREPARACION', 'MPP', 8, 2, 0.51724138],
            ['2920', 'FUSIONAR', 'DELANTERO X 2', 'PREPARACION', 'FUSION', 8, 6, 0.29310345],
            ['2921', 'FILETEAR', 'RUEDO X2', 'ENSAMBLE', 'MF3H', 5, 3, 0.39473684],
            ['2922', 'HACER', 'MOÑO CON HILADILLA', 'TERMINACION', 'OFM', 6, 1, 0.19827586],
            ['2923', 'FUSIONAR', 'PARA PRETINA OJAL', 'PREPARACION', 'FUSION', 8, 6, 0.21551724],
            ['2924', 'HACER', 'JOTA EN COLLARIN', 'PREPARACION', 'COLL', 8, 12, 0.72321429],
            ['2925', 'HACER', 'PRENSE X 2', 'PREPARACION', 'MPP', 8, 2, 0.43103448],
            ['2926', 'HACER', 'PRENSES EN DELANTERO 2-2', 'PREPARACION', 'MPP', 8, 2, 1.42241379],
            ['2927', 'HACER', 'FIGURA DECORATIVA RUEDO', 'ENSAMBLE', 'MPP', 5, 2, 0.67241379],
            ['2928', 'HACER', 'RUCHE EN CONTORNO PRETINA', 'ENSAMBLE', 'MF3H', 5, 3, 0.4137931],
            ['2929', 'HACER', 'RUEDO EN COLLARIN', 'ENSAMBLE', 'COLL', 5, 12, 0.88392857],
            ['2930', 'MARCAR', 'PARA PRENDER BOLSILLO PARCHE LATERAL X 2', 'PREPARACION', 'OFM', 8, 1, 0.70689655],
            ['2931', 'MARCAR', 'PARA PRENDER BOLSILLO X 2', 'PREPARACION', 'OFM', 8, 1, 0.17241379],
            ['2932', 'MEDIR', 'Y CORTAR ELASTICO BOTA X 2', 'PREPARACION', 'OFM', 8, 1, 0.21551724],
            ['2933', 'MEDIR', 'Y CORTAR ELASTICO PRETINA X 1', 'PREPARACION', 'OFM', 8, 1, 0.21551724],
            ['2934', 'MEDIR', 'Y CORTAR HILADILLA-CORDON', 'PREPARACION', 'OFM', 8, 1, 0.12931034],
            ['2935', 'MEDIR', 'Y CORTAR TIRAS', 'PREPARACION', 'OFM', 8, 1, 0.12931034],
            ['2936', 'PESPUNTAR', 'TIRO DELANTERO CON SIMULACION JOTA', 'PREPARACION', 'MPP', 8, 2, 0.43103448],
            ['2937', 'PESPUNTAR', 'BOLSILLO DELANTERO X 2', 'PREPARACION', 'MPP', 8, 2, 0.29310345],
            ['2938', 'PESPUNTAR', 'CORTE HORIZONTAL X 2', 'PREPARACION', 'MPP', 8, 2, 0.43103448],
            ['2939', 'PESPUNTAR', 'CORTE OREJITAS', 'PREPARACION', 'MPP', 8, 2, 0.77586207],
            ['2940', 'PESPUNTAR', 'CORTE VERTICAL COMPLETO X 2', 'PREPARACION', 'MPP', 8, 2, 0.56034483],
            ['2941', 'PESPUNTAR', 'CORTE VERTICAL X 2', 'PREPARACION', 'MPP', 8, 2, 0.51724138],
            ['2942', 'PEGAR', 'ELASTICO A RUEDO', 'ENSAMBLE', 'MPP', 5, 2, 0.32758621],
            ['2943', 'PEGAR', 'MARQUILLA', 'ENSAMBLE', 'MPP', 5, 2, 0.0862069],
            ['2944', 'PESPUNTAR', 'PRENSE X 2', 'PREPARACION', 'MPP', 8, 2, 0.51724138],
            ['2945', 'PESPUNTAR', 'PRETINA MANUAL', 'PREPARACION', 'MPP', 8, 2, 0.43103448],
            ['2946', 'PESPUNTAR', 'TAPA X 2', 'PREPARACION', 'MPP', 8, 2, 0.68965517],
            ['2947', 'PEGAR', 'PRETINA', 'ENSAMBLE', 'MF3H', 5, 3, 0.64655172],
            ['2948', 'PRENDER', 'APLIQUE', 'ENSAMBLE', 'MPP', 5, 2, 0.38793103],
            ['2949', 'PRENDER', 'APLIQUE BOLSILLO DELANTERO X 1', 'ENSAMBLE', 'MPP', 5, 2, 0.38793103],
            ['2950', 'PRENDER', 'CINTA EN LATERAL', 'ENSAMBLE', 'MPP', 5, 2, 1.55172414],
            ['2951', 'PRENDER', 'ELASTICO A BOTA X 2', 'ENSAMBLE', 'MPP', 5, 2, 0.51724138],
            ['2952', 'PRENDER', 'ELASTICO A PRETINA INCORPORADA', 'ENSAMBLE', 'MPP', 5, 2, 0.62068966],
            ['2953', 'PRENDER', 'ELASTICO A RUEDO X 2', 'ENSAMBLE', 'MPP', 5, 2, 0.67241379],
            ['2954', 'PESPUNTAR', 'TIRO TRASERO', 'PREPARACION', 'MPP', 8, 2, 0.43103448],
            ['2955', 'INSERTAR', 'HILAD/CORDON A PRETINA', 'TERMINACION', 'OFM', 6, 1, 0.43103448],
            ['2956', 'PICAR', 'Y VOLTEAR TRIANGULOS CRESTA X 3', 'PREPARACION', 'OFM', 8, 1, 0.30172414],
            ['2957', 'OJALAR', 'PRETINA X 1', 'TERMINACION', 'OJALAD', 6, 8, 0.12931034],
            ['2958', 'PIQUETEAR', 'Y VOLTEAR TAPA X 2', 'PREPARACION', 'OFM', 8, 1, 0.17241379],
            ['2959', 'PLANCHAR', 'BOLSILLO DELANTERO SOBREPUESTO', 'PREPARACION', 'PLANC', 8, 11, 0.34482759],
            ['2960', 'PRENDER', 'BOLSILLO DELANTERO X 2', 'PREPARACION', 'MPP', 8, 2, 0.29310345],
            ['2961', 'OJALAR', 'PRETINA X 2', 'TERMINACION', 'OJALAD', 6, 8, 0.21551724],
            ['2962', 'PRENDER', 'BOLSILLO PARCHE X 2', 'PREPARACION', 'MPP', 8, 2, 1.37931034],
            ['2963', 'PRENDER', 'BOLSILLO SOBREPUESTO DELANTERO X 2', 'PREPARACION', 'MPP', 8, 2, 0.77586207],
            ['2964', 'PRENDER', 'TAPA X 2', 'PREPARACION', 'MPP', 8, 2, 0.60344828],
            ['2965', 'RECOGER', 'PRENDIDA DE SESGO', 'PREPARACION', 'MPP', 8, 2, 0.17241379],
            ['2966', 'REVOCAR', 'Y VOLTEAR ALETA X 1', 'PREPARACION', 'OFM', 8, 1, 0.06896552],
            ['2967', 'SESGAR', 'DELANTERO X 2 CURVO + BOLSILLO', 'PREPARACION', 'MPP', 8, 2, 0.51724138],
            ['2968', 'SESGAR', 'TIRAS', 'PREPARACION', 'COLL', 8, 12, 0.21551724],
            ['2969', 'UNIR', 'CORTE HORIZONTAL TRASERO X 2', 'PREPARACION', 'MF3H', 8, 3, 0.43103448],
            ['2970', 'UNIR', 'CORTE HORIZONTAL X 2', 'PREPARACION', 'MF3H', 8, 3, 0.43103448],
            ['2971', 'UNIR', 'CORTE VERTICAL X 2 COMPLETO', 'PREPARACION', 'MF3H', 8, 3, 0.60344828],
            ['2972', 'UNIR', 'CORTE DELANTERO VERTICAL X 2', 'PREPARACION', 'MF3H', 8, 3, 0.51724138],
            ['2973', 'UNIR', 'CORTE DELANTERO Y TRASERO AÑADIENDO TAPA X 2', 'PREPARACION', 'MF3H', 8, 3, 1.29310345],
            ['2974', 'UNIR', 'CORTES INSERTANDO TAPA X 2', 'PREPARACION', 'MF3H', 8, 3, 1.50862069],
            ['2975', 'UNIR', 'ELASTICO PRETINA', 'PREPARACION', 'MPP', 8, 2, 0.0862069],
            ['2976', 'OJALAR', 'X 2', 'TERMINACION', 'OJALAD', 6, 8, 0.24137931],
            ['2977', 'PRENDER', 'PRETINA', 'ENSAMBLE', 'MPP', 5, 2, 0.48275862],
            ['2978', 'UNIR', 'PRETINA', 'PREPARACION', 'MPP', 8, 2, 0.12931034],
            ['2979', 'UNIR', 'SESGO TIRA', 'PREPARACION', 'MPP', 8, 2, 0.12931034],
            ['2980', 'PLANCHAR', 'JOGGER NIÑA', 'TERMINACION', 'PLANC', 6, 11, 0.51724138],
            ['2981', 'PRENDER', 'PRETINA ASENTANDO EN COLLARIN', 'ENSAMBLE', 'COLL', 5, 12, 0.76785714],
            ['2982', 'PRENDER', 'SESGO LATERAL X 2', 'ENSAMBLE', 'MPP', 5, 2, 0.86206897],
            ['2983', 'PREPARAR', 'STICKER', 'TERMINACION', 'OFM', 6, 1, 0.06896552],
            ['2984', 'PRESILLAR', 'CORTE DELANTERO', 'TERMINACION', 'PRESIL', 6, 9, 0.31034483],
            ['2985', 'REVISAR', '', 'TERMINACION', 'OFM', 6, 1, 0.47413793],
            ['2986', 'UNIR', 'SESGO TIRA X 2', 'PREPARACION', 'MPP', 8, 2, 0.25862069],
            ['2987', 'TIQUETEAR', '', 'TERMINACION', 'OFM', 6, 1, 0.06896552],
        ];
    }
}
