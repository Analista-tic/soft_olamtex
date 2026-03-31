<?php

namespace App\Models\Production;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TechnicalSheetCuttingPieceSize extends Model
{
    use HasFactory;

    protected $table = 'technical_sheet_cutting_piece_sizes';

    protected $fillable = [
        'cutting_piece_id',
        'size',
        'units',
        'curve',
    ];

    protected $casts = [
        'units' => 'integer',
    ];

    public function cuttingPiece()
    {
        return $this->belongsTo(TechnicalSheetCuttingPiece::class, 'cutting_piece_id');
    }
}
