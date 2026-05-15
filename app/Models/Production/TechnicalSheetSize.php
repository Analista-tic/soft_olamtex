<?php

namespace App\Models\Production;

use Illuminate\Database\Eloquent\Model;

/**
 * Curva de tallas de la ficha técnica: unidades por talla desglosadas por tipo de trazo.
 *
 * @property int    $id
 * @property int    $technical_sheet_id
 * @property string $size
 * @property int    $trazo_principal
 * @property int    $liquidacion
 * @property int    $espiga
 */
class TechnicalSheetSize extends Model
{
    protected $table = 'technical_sheet_sizes';

    protected $fillable = [
        'technical_sheet_id',
        'size',
        'trazo_principal',
        'liquidacion',
        'espiga',
        'total_unidades',
    ];

    protected $casts = [
        'trazo_principal' => 'integer',
        'liquidacion'     => 'integer',
        'espiga'          => 'integer',
        'total_unidades'  => 'integer',
    ];

    public function technicalSheet()
    {
        return $this->belongsTo(TechnicalSheet::class);
    }
}
