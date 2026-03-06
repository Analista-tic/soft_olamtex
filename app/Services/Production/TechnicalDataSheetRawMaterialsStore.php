<?php

namespace App\Services\Production;

use App\Models\Production\TechnicalSheet;
use App\Models\Production\TechnicalSheetRawMaterial;

/**
 * Almacenamiento de materia prima de la ficha técnica en BD.
 * El frontend (RawMaterialTab) actualmente usa solo localStorage; al usar este store vía API los datos quedan en BD.
 */
class TechnicalDataSheetRawMaterialsStore
{
    public function read(int $opId): array
    {
        $sheet = TechnicalSheet::where('production_order_id', $opId)->first();
        if (!$sheet) {
            return [];
        }

        $snapshot = $sheet->rawMaterialsSnapshot()->first();
        if (!$snapshot || !is_array($snapshot->rows)) {
            return [];
        }

        return array_values($snapshot->rows);
    }

    public function write(int $opId, array $rows): void
    {
        $sheet = TechnicalSheet::firstOrCreate(
            ['production_order_id' => $opId],
            ['status' => TechnicalSheet::STATUS_DRAFT],
        );
        TechnicalSheetRawMaterial::updateOrCreate(
            ['technical_sheet_id' => $sheet->id],
            ['rows' => array_values($rows)],
        );
    }
}
