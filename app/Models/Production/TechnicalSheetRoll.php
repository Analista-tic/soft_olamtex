<?php

namespace App\Models\Production;

use Illuminate\Database\Eloquent\Model;

/**
 * Parámetros de rollos de la ficha técnica (trazo/defaultExtra).
 * Las líneas de rollos programados viven en fabric_roll_programming_lines desde la
 * Fase 15 (ver FabricRollProgrammingLine) — este modelo ya no las guarda.
 *
 * @property int $id
 * @property int $technical_sheet_id
 * @property array|null $params
 */
class TechnicalSheetRoll extends Model
{
    protected $table = 'technical_sheet_rolls';

    protected $fillable = ['technical_sheet_id', 'params'];

    protected $casts = [
        'params' => 'array',
    ];

    public function technicalSheet()
    {
        return $this->belongsTo(TechnicalSheet::class);
    }
}
