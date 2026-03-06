<?php

namespace App\Models\Production;

use Illuminate\Database\Eloquent\Model;

/**
 * Proceso individual de la Ficha Técnica con costo fijo (snapshot).
 *
 * @property int $id
 * @property int $technical_sheet_id
 * @property int $sequence
 * @property string $type CORTE|CONFECCION|TERMINACION|ESTAMPADO|BORDADO|LAVANDERIA|OTRO
 * @property string|null $supplier
 * @property float|null $manual_price
 * @property float $fixed_cost Costo congelado al guardar
 */
class TechnicalSheetProcess extends Model
{
    protected $table = 'technical_sheet_processes';

    protected $fillable = [
        'technical_sheet_id',
        'sequence',
        'type',
        'supplier',
        'manual_price',
        'fixed_cost',
    ];

    protected $casts = [
        'sequence'    => 'integer',
        'manual_price' => 'decimal:4',
        'fixed_cost'   => 'decimal:4',
    ];

    public function technicalSheet()
    {
        return $this->belongsTo(TechnicalSheet::class);
    }
}
