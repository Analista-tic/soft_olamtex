<?php

namespace App\Models\Production;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * ⚠️  DEPRECATED - Eliminada del flujo de negocio en Phase 5
 * 
 * Siendo reemplazada por ProductSheetCuttingPieceSize que se relaciona con
 * ProductSheetCuttingPiece. Relación eliminada en Phase 5.
 * 
 * Migraciones legacy (a eliminar):
 * - 2026_03_25_000001_create_technical_sheet_cutting_piece_sizes_table.php
 * 
 * Drop migration:
 * - 2026_05_08_000001_drop_technical_sheet_cutting_tables.php
 * 
 * Reemplazo activo: ProductSheetCuttingPieceSize
 */
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
