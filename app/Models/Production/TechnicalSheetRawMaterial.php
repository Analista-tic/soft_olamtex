<?php

namespace App\Models\Production;

use Illuminate\Database\Eloquent\Model;

/**
 * Snapshot de materia prima de la ficha técnica (rows en JSON).
 *
 * @property int $id
 * @property int $technical_sheet_id
 * @property array|null $rows
 */
class TechnicalSheetRawMaterial extends Model
{
    protected $table = 'technical_sheet_raw_materials';

    protected $fillable = ['technical_sheet_id', 'rows'];

    protected $casts = [
        'rows' => 'array',
    ];

    public function technicalSheet()
    {
        return $this->belongsTo(TechnicalSheet::class);
    }
}
