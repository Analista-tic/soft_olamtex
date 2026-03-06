<?php

namespace App\Models\Production;

use Illuminate\Database\Eloquent\Model;

/**
 * Snapshot de insumos de la ficha técnica (items en JSON).
 *
 * @property int $id
 * @property int $technical_sheet_id
 * @property array|null $items
 */
class TechnicalSheetSupply extends Model
{
    protected $table = 'technical_sheet_supplies';

    protected $fillable = ['technical_sheet_id', 'items'];

    protected $casts = [
        'items' => 'array',
    ];

    public function technicalSheet()
    {
        return $this->belongsTo(TechnicalSheet::class);
    }
}
