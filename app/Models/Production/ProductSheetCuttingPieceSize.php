<?php

namespace App\Models\Production;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductSheetCuttingPieceSize extends Model
{
    protected $table = 'product_sheet_cutting_piece_sizes';

    protected $fillable = [
        'cutting_piece_id',
        'size',
        'units',
        'curve',
    ];

    protected $casts = [
        'units' => 'integer',
    ];

    /**
     * Relación: pertenece a ProductSheetCuttingPiece
     */
    public function cuttingPiece(): BelongsTo
    {
        return $this->belongsTo(ProductSheetCuttingPiece::class, 'cutting_piece_id');
    }
}
