<?php

namespace App\Models\Production;

use Illuminate\Database\Eloquent\Model;

/**
 * Snapshot de hilos de la ficha técnica (params + lines en JSON).
 *
 * @property int $id
 * @property int $technical_sheet_id
 * @property array|null $params
 * @property array|null $lines
 */
class TechnicalSheetThread extends Model
{
    protected $table = 'technical_sheet_threads';

    protected $fillable = ['technical_sheet_id', 'params', 'lines'];

    protected $casts = [
        'params' => 'array',
        'lines'  => 'array',
    ];

    public function technicalSheet()
    {
        return $this->belongsTo(TechnicalSheet::class);
    }
}
