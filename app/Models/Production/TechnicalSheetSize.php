<?php

namespace App\Models\Production;

use Illuminate\Database\Eloquent\Model;

/**
 * Talla/unidades/curva a nivel de ficha técnica (source of truth para el desglose de tallas en corte).
 *
 * @property int         $id
 * @property int         $technical_sheet_id
 * @property string      $size
 * @property int         $units
 * @property float|null  $curve
 */
class TechnicalSheetSize extends Model
{
    protected $table = 'technical_sheet_sizes';

    protected $fillable = [
        'technical_sheet_id',
        'size',
        'units',
        'curve',
    ];

    protected $casts = [
        'units' => 'integer',
        'curve' => 'decimal:2',
    ];

    public function technicalSheet()
    {
        return $this->belongsTo(TechnicalSheet::class);
    }
}
