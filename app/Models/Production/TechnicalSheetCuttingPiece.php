<?php
namespace App\Models\Production;

use Illuminate\Database\Eloquent\Model;

/**
 * ⚠️  DEPRECATED - Eliminada del flujo de negocio en Phase 5
 * 
 * Esta clase se reemplazó con ProductSheetCuttingPiece que se relaciona
 * con ProductSheet en lugar de TechnicalSheet (Phase 3-4).
 * 
 * Migraciones legacy:
 * - 2026_03_19_000001_create_technical_sheet_cutting_pieces_table.php
 * - 2026_03_25_000001_create_technical_sheet_cutting_piece_sizes_table.php
 * 
 * Drop migration creada (Phase 5):
 * - 2026_05_08_000001_drop_technical_sheet_cutting_tables.php
 * 
 * Relaciones removidas:
 * - TechnicalSheet::cuttingPieces() (eliminada en Phase 5)
 * - TechnicalDataSheetAdditionalInfoController::syncCuttingPiecesToDb() (eliminada en Phase 5)
 * 
 * Reemplazos activos:
 * - ProductSheetCuttingPiece ← nueva source of truth
 * - ProductSheetCuttingPieceService ← sincronización
 * - ProductSheetCuttingPieceController ← endpoints REST
 * 
 * Pieza de despiece de trazo de corte vinculada a la ficha técnica (LEGACY).
 *
 * @property int         $id
 * @property int         $technical_sheet_id
 * @property string|null $piece_uid  UUID de frontend para upsert idempotente
 * @property int         $sequence
 * @property string      $name       Nombre de la pieza (Delantero, Manga…)
 * @property string      $piece_type PRINCIPAL | SECUNDARIO
 * @property string|null $fabric_uid UID de la tela en rawMaterials
 * @property string      $side       IZQUIERDA | DERECHA | AMBOS | NO_APLICA
 * @property string      $grain      90 | 180 | SESGO
 * @property int         $quantity
 * @property string|null $notes
 */
class TechnicalSheetCuttingPiece extends Model
{
    protected $table = 'technical_sheet_cutting_pieces';

    protected $fillable = [
        'technical_sheet_id',
        'piece_uid',
        'sequence',
        'name',
        'piece_type',
        'fabric_uid',
        'side',
        'grain',
        'quantity',
        'notes',
    ];

    protected $casts = [
        'sequence' => 'integer',
        'quantity' => 'integer',
    ];

    // ─── Constantes de dominio ────────────────────────────

    public const PIECE_TYPES = ['PRINCIPAL', 'SECUNDARIO'];

    public const SIDES = ['IZQUIERDA', 'DERECHA', 'AMBOS', 'NO_APLICA'];

    public const GRAINS = ['90', '180', 'SESGO'];

    // ─── Relaciones ───────────────────────────────────────

    public function technicalSheet()
    {
        return $this->belongsTo(TechnicalSheet::class);
    }

    /**
     * Relación: tallas/unidades/curva asociadas a la pieza de despiece.
     */
    public function sizes()
    {
        return $this->hasMany(TechnicalSheetCuttingPieceSize::class, 'cutting_piece_id');
    }

    // ─── Helpers ──────────────────────────────────────────

    /**
     * Etiqueta legible para la ubicación en trazo.
     */
    public function getTraceLocationLabelAttribute(): string
    {
        return match ($this->trace_location) {
            'AL_HILO'         => 'Al hilo',
            'AL_DOBLEZ'       => 'Al doblez',
            'APROVECHAMIENTO' => 'Aprovechamiento',
            'SESGO'           => 'Sesgo',
            default           => $this->trace_location ?? '-',
        };
    }

    /**
     * Etiqueta legible para el tipo de tela.
     */
    public function getFabricTypeLabelAttribute(): string
    {
        return match ($this->fabric_type) {
            'PRINCIPAL'  => 'Tela principal',
            'SECUNDARIA' => 'Tela secundaria',
            'ENTRETELA'  => 'Entretela',
            'RETAL'      => 'Retal',
            default      => $this->fabric_type ?? '-',
        };
    }
}
