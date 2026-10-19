<?php

namespace App\Models\Production;

use App\Models\Concerns\HasAuditLabel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;

/**
 * Listado operacional por sábana (instancia operativa de una operación base).
 *
 * Cada registro representa cómo se ejecuta una operación dentro de una
 * `product_sheet` específica, conservando snapshots de factores de cálculo
 * para trazabilidad histórica.
 *
 * Campos principales:
 * - product_sheet_id: sábana/ficha técnica a la que pertenece.
 * - operation_id: operación base del catálogo (`Operation`).
 * - grupo_preparacion: agrupador (solo para PREPARACION).
 * - sequence: orden de ejecución en el flujo.
 * - sam_base: snapshot del SAM base utilizado.
 * - sam / optimal_time: tiempo estándar final usado en la fila.
 * - fabric_base_factor: factor de base tela aplicado.
 * - operator_efficiency_factor: factor de eficiencia del operario aplicado.
 * - machine_supplement_factor: suplemento de máquina aplicado.
 * - sam_total_unitario: SAM calculado por unidad.
 * - sam_total_operacion: SAM total por operación (incluyendo cantidad).
 * - production_time / actual_time / variance: métricas de ejecución.
 * - notes: observaciones operativas.
 * - is_active: estado lógico de activación.
 */
class ProductOperationList extends Model
{
    use HasFactory, SoftDeletes, HasAuditLabel;

    protected $table = 'product_sheet_operations';

    protected $fillable = [
        'product_sheet_id',
        'operation_id',
        'grupo_preparacion',
        'sequence',
        'sam_base',
        'sam',
        'fabric_base_factor',
        'sam_total_unitario',
        'sam_total_operacion',
        'is_active',
        'optimal_time',
        'operator_efficiency_factor',
        'machine_supplement_factor',
        'production_time',
        'actual_time',
        'variance',
        'notes',
    ];

    public array $auditIgnore = ['created_at', 'updated_at', 'deleted_at'];

    protected $casts = [
        'product_sheet_id' => 'integer',
        'operation_id' => 'integer',
        'sequence' => 'integer',
        'is_active' => 'boolean',
        'optimal_time' => 'float',
        'sam' => 'float',
        'operator_efficiency_factor' => 'float',
        'machine_supplement_factor' => 'float',
        'production_time' => 'float',
        'actual_time' => 'float',
        'variance' => 'float',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected $with = [];

    protected static function booted(): void
    {
        static::saving(function (self $model) {
            // Validar que grupo_preparacion solo esté en operaciones PREPARACION
            if ($model->operation_id) {
                $model->validateGrupoPreparacionRestriction();
            }
        });
    }

    // Relaciones
    public function productSheet()
    {
        return $this->belongsTo(ProductSheet::class);
    }

    public function operation()
    {
        return $this->belongsTo(Operation::class);
    }

    /**
     * Máquina derivada de la operación base (sin columna propia en el listado).
     */
    public function getMachineAttribute(): ?Machine
    {
        return $this->operation?->machineReference;
    }

    public function history()
    {
        return $this->hasMany(ProductSheetOperationHistory::class, 'product_sheet_operation_id');
    }

    public function calculateSamTotalUnitario(): float
    {
        $samBase = $this->sam_base ?? $this->sam ?? 0;
        $sup = $this->machine_supplement_factor ?? 1.0;
        $fabric = $this->fabric_base_factor ?? 1.0;
        $op = $this->operator_efficiency_factor ?? 1.0;

        return $samBase * $sup * $fabric * $op;
    }

    /** SAM de la operación completa: el SAM unitario por las repeticiones por pieza (`operations.quantity`). */
    public function calculateSamTotalOperacion(): float
    {
        // Sin cargar la relación de forma perezosa: el modo estricto contra N+1 lo convierte en error al guardar en lote.
        $quantity = $this->relationLoaded('operation')
            ? $this->operation?->quantity
            : ($this->operation_id ? Operation::query()->whereKey($this->operation_id)->value('quantity') : null);

        return round($this->calculateSamTotalUnitario() * self::quantityOf($quantity), 4);
    }

    /** Repeticiones por pieza: mínimo 1 (vacío o 0 se tratan como una). */
    public static function quantityOf(int|float|string|null $quantity): int
    {
        return max(1, (int) $quantity);
    }

    /**
     * Alias: sequence_order mapea a sequence para compatibilidad con frontend.
     */
    public function getSequenceOrderAttribute(): int
    {
        return (int) ($this->attributes['sequence'] ?? 0);
    }

    public function setSequenceOrderAttribute($value): void
    {
        $this->attributes['sequence'] = $value;
    }

    /**
     * Determina si esta operación pertenece a la sección PREPARACION.
     */
    public function isPreparationSection(): bool
    {
        return $this->operation?->section?->type === 'PREPARACION';
    }

    /**
     * Determina si esta operación pertenece a la sección ENSAMBLE.
     */
    public function isAssemblySection(): bool
    {
        return $this->operation?->section?->type === 'ENSAMBLE';
    }

    /**
     * Determina si esta operación pertenece a la sección TERMINACION.
     */
    public function isFinishingSection(): bool
    {
        return $this->operation?->section?->type === 'TERMINACION';
    }

    /**
     * Obtiene el tipo de sección (PREPARACION, ENSAMBLE, TERMINACION).
     */
    public function getSectionType(): string
    {
        return $this->operation?->section?->type ?? '';
    }

    /**
     * Valida que grupo_preparacion solo esté presente en operaciones PREPARACION.
     * Lanza excepción si se intenta asignar grupo a una operación no-PREPARACION.
     */
    public function validateGrupoPreparacionRestriction(): void
    {
        // Si es PREPARACION, grupo_preparacion puede tener valor
        // Si NO es PREPARACION, grupo_preparacion DEBE ser NULL
        if (!$this->isPreparationSection() && $this->grupo_preparacion !== null) {
            throw new \InvalidArgumentException(
                "El campo 'grupo_preparacion' solo puede usarse en operaciones de tipo PREPARACION. " .
                "Sección actual: {$this->getSectionType()}"
            );
        }
    }

    // Scopes
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeByGrupoPreparacion(Builder $query, string $grupo): Builder
    {
        return $query->where('grupo_preparacion', $grupo);
    }

    public function scopePreparacionSection(Builder $query): Builder
    {
        return $query->whereHas('operation.section', fn ($q) => $q->where('type', 'PREPARACION'));
    }

    public function scopeEnsambleSection(Builder $query): Builder
    {
        return $query->whereHas('operation.section', fn ($q) => $q->where('type', 'ENSAMBLE'));
    }

    public function scopeTerminacionSection(Builder $query): Builder
    {
        return $query->whereHas('operation.section', fn ($q) => $q->where('type', 'TERMINACION'));
    }

    public function scopeBySheet(Builder $query, int $sheetId): Builder
    {
        return $query->where('product_sheet_id', $sheetId);
    }

    public function scopeBySection(Builder $query, int $sectionId): Builder
    {
        return $query->whereHas('operation', fn ($q) => $q->where('section_id', $sectionId));
    }

    public function scopeByOperation(Builder $query, int $operationId): Builder
    {
        return $query->where('operation_id', $operationId);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sequence');
    }

    public function scopeWithRelations(Builder $query): Builder
    {
        return $query->with(['operation.machineReference', 'operation.section', 'productSheet']);
    }
}