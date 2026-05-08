<?php

namespace App\Models\Production;

use App\Models\Concerns\HasAuditLabel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Ficha Técnica de Orden de Producción.
 *
 * Aggregate secundario: concentra operaciones, costos y metadatos técnicos
 * de una ProductionOrder. Los costos se persisten aquí una vez calculados
 * para evitar recalculaciones constantes y tener trazabilidad financiera.
 *
 * @property int $id
 * @property int $production_order_id
 * @property string $status  draft|in_progress|completed
 * @property float|null $raw_material_cost   Costo de telas/rollos
 * @property float|null $supplies_cost       Costo de avíos/insumos
 * @property float|null $processes_cost      Costo de procesos (corte, confección, acabado)
 * @property float|null $cut_cost            Costo de corte
 * @property float|null $confection_cost     Costo de confección
 * @property float|null $finishing_cost      Costo de acabado
 * @property float|null $total_cost          Costo total
 * @property float|null $billing_price       Precio de facturación
 * @property float|null $selling_price       Precio de venta
 * @property float|null $rendimiento         Rendimiento (kg/prenda)
 * @property float|null $costo_minuto        Costo por minuto
 *
 * @see \App\Services\Production\TechnicalSheetService
 */
class TechnicalSheet extends Model
{
    use SoftDeletes, HasAuditLabel;

    protected $table = 'technical_sheets';

    protected $fillable = [
        'production_order_id',
        'status',
        'raw_material_cost',
        'supplies_cost',
        'processes_cost',
        'cut_cost',
        'confection_cost',
        'finishing_cost',
        'total_cost',
        'billing_price',
        'selling_price',
        'rendimiento',
        'costo_minuto',
    ];

    protected $casts = [
        'raw_material_cost'  => 'decimal:4',
        'supplies_cost'      => 'decimal:4',
        'processes_cost'     => 'decimal:4',
        'cut_cost'           => 'decimal:4',
        'confection_cost'    => 'decimal:4',
        'finishing_cost'     => 'decimal:4',
        'total_cost'         => 'decimal:4',
        'billing_price'      => 'decimal:4',
        'selling_price'      => 'decimal:4',
        'rendimiento'        => 'decimal:4',
        'costo_minuto'       => 'decimal:4',
    ];

    // ─── Constantes de dominio ────────────────────────────

    public const STATUS_DRAFT       = 'draft';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED   = 'completed';

    // ─── Relaciones ───────────────────────────────────────

    /**
     * Orden de producción a la que pertenece esta ficha técnica.
     */
    public function productionOrder()
    {
        return $this->belongsTo(ProductionOrder::class);
    }

    /**
     * Operaciones congeladas (snapshot) vinculadas a esta ficha.
     */
    public function operations()
    {
        return $this->hasMany(TechnicalSheetOperation::class);
    }

    /**
     * Procesos con costos fijos (CORTE, CONFECCION, TERMINACION, etc.).
     */
    public function processes()
    {
        return $this->hasMany(TechnicalSheetProcess::class)->orderBy('sequence');
    }

    /** Snapshot de rollos (params + lines). */
    public function rollsSnapshot()
    {
        return $this->hasOne(TechnicalSheetRoll::class);
    }

    /** Snapshot de hilos (params + lines). */
    public function threadsSnapshot()
    {
        return $this->hasOne(TechnicalSheetThread::class);
    }

    /** Snapshot de insumos (items). */
    public function suppliesSnapshot()
    {
        return $this->hasOne(TechnicalSheetSupply::class);
    }

    /** Snapshot de materia prima (rows). */
    public function rawMaterialsSnapshot()
    {
        return $this->hasOne(TechnicalSheetRawMaterial::class);
    }

    /**
     * Tallas/unidades/curva de la ficha (source of truth para el desglose de corte).
     */
    public function sizes()
    {
        return $this->hasMany(TechnicalSheetSize::class)->orderBy('size');
    }

    /**
     * Imágenes de referencia de estampado/lavandería (source of truth para imágenes del diálogo).
     */
    public function stampingImages()
    {
        return $this->hasMany(TechnicalSheetStampingImage::class);
    }

    // ─── Helpers de dominio ───────────────────────────────

    /**
     * ¿La ficha está borrador?
     */
    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    /**
     * ¿La ficha está en progreso?
     */
    public function isInProgress(): bool
    {
        return $this->status === self::STATUS_IN_PROGRESS;
    }

    /**
     * ¿La ficha está completada?
     */
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * ¿Tiene costos persitidos?
     */
    public function hasCosts(): bool
    {
        return $this->total_cost !== null;
    }

    /**
     * Recalcular costo total a partir de sus componentes.
     */
    public function recalculateTotalCost(): static
    {
        $this->total_cost = collect([
            $this->raw_material_cost,
            $this->supplies_cost,
            $this->processes_cost,
        ])->sum();

        return $this;
    }
}
