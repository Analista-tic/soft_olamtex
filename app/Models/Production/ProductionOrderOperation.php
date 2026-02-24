<?php

namespace App\Models\ProductSheet;

use App\Enums\EstatusOperacionOrden;
use App\Models\Concerns\HasAuditLabel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;

/**
 * Snapshot congelado de una operación en el contexto de una orden de producción (Programmer).
 *
 * INMUTABLE: Este modelo representa datos congelados al momento de crear la orden.
 * NO se deben editar campos de operación/máquina/SAM. Solo el progreso (quantity_progressed)
 * y el status de ejecución pueden cambiar.
 *
 * @property int $id
 * @property int $programmer_id
 * @property int|null $product_sheet_operation_id FK a la operación fuente en la plantilla
 * @property int $sequence
 * @property int|null $operation_id
 * @property int|null $machine_id
 * @property int $quantity
 * @property float $sam_base
 * @property float $machine_supplement_factor
 * @property float $fabric_base_factor
 * @property float $operator_efficiency_factor
 * @property float $sam_total_unitario
 * @property float $sam_total_operacion
 * @property float $sam_total_lote
 * @property int $quantity_progressed
 * @property EstatusOperacionOrden $status
 * @property \Carbon\Carbon|null $progressed_at
 */
class ProductionOrderOperation extends Model
{
    use HasFactory, SoftDeletes, HasAuditLabel;

    protected $table = 'production_order_operations';

    protected $fillable = [
        'programmer_id',
        'product_sheet_operation_id',
        'sequence',
        'operation_id',
        'machine_id',
        'quantity',
        'sam_base',
        'machine_supplement_factor',
        'fabric_base_factor',
        'operator_efficiency_factor',
        'sam_total_unitario',
        'sam_total_operacion',
        'sam_total_lote',
        'quantity_progressed',
        'status',
        'progressed_at',
    ];

    protected $casts = [
        'sequence' => 'integer',
        'quantity' => 'integer',
        'sam_base' => 'float',
        'machine_supplement_factor' => 'float',
        'fabric_base_factor' => 'float',
        'operator_efficiency_factor' => 'float',
        'sam_total_unitario' => 'float',
        'sam_total_operacion' => 'float',
        'sam_total_lote' => 'float',
        'quantity_progressed' => 'integer',
        'status' => EstatusOperacionOrden::class,
        'progressed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function programmer()
    {
        return $this->belongsTo(Programmer::class);
    }

    public function operation()
    {
        return $this->belongsTo(Operation::class);
    }

    public function machine()
    {
        return $this->belongsTo(Machine::class);
    }

    /**
     * Operación fuente de la plantilla (ProductSheetOperation) de la que se generó este snapshot.
     */
    public function productSheetOperation()
    {
        return $this->belongsTo(ProductSheetOperation::class, 'product_sheet_operation_id');
    }

    public function scopeByOrder(Builder $query, int $programmerId): Builder
    {
        return $query->where('programmer_id', $programmerId)->orderBy('sequence');
    }

    public function scopeByStatus(Builder $query, EstatusOperacionOrden $status): Builder
    {
        return $query->where('status', $status);
    }

    /**
     * Actualiza SOLO el progreso de la operación. Es la única modificación permitida en un snapshot.
     */
    public function updateProgressOnly(int $quantityProgressed): bool
    {
        $this->quantity_progressed = $quantityProgressed;
        $this->progressed_at = now();

        if ($quantityProgressed >= $this->quantity) {
            $this->status = EstatusOperacionOrden::COMPLETADA;
        } elseif ($quantityProgressed > 0 && $this->status === EstatusOperacionOrden::PENDIENTE) {
            $this->status = EstatusOperacionOrden::EN_EJECUCION;
        }

        return $this->save();
    }

    public function isCompleted(): bool
    {
        return $this->status === EstatusOperacionOrden::COMPLETADA;
    }

    public function calculateProgress(): float
    {
        if ($this->quantity <= 0) {
            return 0.0;
        }

        return round(($this->quantity_progressed / $this->quantity) * 100, 2);
    }

    /**
     * Obtiene la operación fuente de la plantilla original para comparación.
     */
    public function getSourceOperation(): ?ProductSheetOperation
    {
        return $this->productSheetOperation;
    }
}
