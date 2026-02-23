<?php

namespace App\Models\Inventory;

use App\Contracts\HasAuditLabel;
use App\Models\Inventory\Warehouse;
use App\Models\Settings\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class InventoryMovement extends Model implements HasAuditLabel
{
    use HasFactory, SoftDeletes;

    protected $table = 'inventory_movements';

    public array $auditIgnore = ['created_at', 'updated_at', 'deleted_at'];

    public static function auditLabel(): string
    {
        return 'Movimiento de inventario';
    }

    protected $fillable = [
        'document_number',
        'item_id',
        'warehouse_id',
        'source_type',
        'source_id',
        'type',
        'date',
        'quantity',
        'unit_price',
        'total_cost',
        'previous_stock',
        'previous_weighted_price',
        'new_stock',
        'new_weighted_price',
        'notes',
        'created_by_user_id',
    ];

    protected $casts = [
        'date' => 'date',
        'quantity' => 'decimal:4',
        'unit_price' => 'decimal:4',
        'total_cost' => 'decimal:4',
        'previous_stock' => 'decimal:4',
        'previous_weighted_price' => 'decimal:4',
        'new_stock' => 'decimal:4',
        'new_weighted_price' => 'decimal:4',
    ];

    /**
     * Relación con el item afectado
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    /**
     * Relación con la bodega
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * Relación polimórfica con el origen del movimiento
     * (PurchaseDocument, InventoryAdjustment, etc.)
     */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Usuario que creó el movimiento
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * Scope: Movimientos de entrada
     */
    public function scopeEntradas($query)
    {
        return $query->where('type', 'entrada');
    }

    /**
     * Scope: Movimientos de salida
     */
    public function scopeSalidas($query)
    {
        return $query->where('type', 'salida');
    }

    /**
     * Scope: Filtrar por item
     */
    public function scopeForItem($query, int $itemId)
    {
        return $query->where('item_id', $itemId);
    }

    /**
     * Scope: Filtrar por bodega
     */
    public function scopeForWarehouse($query, int $warehouseId)
    {
        return $query->where('warehouse_id', $warehouseId);
    }

    /**
     * Scope: Filtrar por rango de fechas
     */
    public function scopeDateRange($query, $from, $to)
    {
        return $query->whereBetween('date', [$from, $to]);
    }
}
