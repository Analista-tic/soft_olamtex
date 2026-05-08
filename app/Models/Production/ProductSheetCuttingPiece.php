<?php

namespace App\Models\Production;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductSheetCuttingPiece extends Model
{
    protected $table = 'product_sheet_cutting_pieces';

    protected $fillable = [
        'product_sheet_id',
        'piece_uid',
        'sequence',
        'name',
        'piece_type',
        'trace_location',
        'side',
        'grain',
        'quantity',
        'notes',
    ];

    protected $casts = [
        'sequence' => 'integer',
        'quantity' => 'integer',
    ];

    /**
     * Relación: pertenece a ProductSheet
     */
    public function productSheet(): BelongsTo
    {
        return $this->belongsTo(ProductSheet::class, 'product_sheet_id');
    }

    /**
     * Relación: tiene muchas ProductSheetCuttingPieceSize
     */
    public function sizes(): HasMany
    {
        return $this->hasMany(ProductSheetCuttingPieceSize::class, 'cutting_piece_id');
    }
}
