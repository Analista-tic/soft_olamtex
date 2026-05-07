<?php

namespace App\Models\Inventory;

use App\Contracts\HasAuditLabel;
use App\Models\MasterData\Item;
use App\Models\Settings\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class InventoryMovement extends Model implements HasAuditLabel
{
    use HasFactory;

    protected $table = 'inventory_movements';

    public array $auditIgnore = ['created_at', 'updated_at'];

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
        'source_reference',
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

    protected $appends = [
        'display_document_number',
        'source_group_label',
    ];

    private static function legacyDocumentDisplayPrefix(): string
    {
        return "Migraci\u{00F3}n";
    }

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

    public function getDisplayDocumentNumberAttribute(): ?string
    {
        // Prefer resolver from related source when available (eager-loaded to avoid N+1)
        // 1) legacy imports keep their "Migración N" label
        // 2) if source relation is loaded and has a document-like field, use it
        // 3) fallback to movement.document_number, then to source_reference, then to "Sin documento"

        $documentNumber = is_string($this->document_number) ? trim($this->document_number) : null;

        // Legacy import special casing: keep "Migración N"
        if (
            in_array($this->source_type, ['legacy_import', 'item_excel_import'], true)
            && is_string($documentNumber)
            && preg_match('/^Migraci(?:o|\x{00F3}|\?)n (\d+)-\d{4}$/u', $documentNumber, $matches) === 1
        ) {
            return self::legacyDocumentDisplayPrefix() . ' ' . $matches[1];
        }

        // If the polymorphic relation was eager-loaded, try to read document number from it
        if ($this->relationLoaded('source') && $this->source) {
            $src = $this->source;
            $candidates = [
                'document_number',
                'number',
                'reference',
                'supplier_invoice_number',
                'supplier_order_number',
            ];

            foreach ($candidates as $field) {
                if (isset($src->{$field}) && is_string($src->{$field}) && trim($src->{$field}) !== '') {
                    return trim($src->{$field});
                }
            }
        }

        // Fallbacks
        if (is_string($documentNumber) && $documentNumber !== '') {
            return $documentNumber;
        }

        if (isset($this->source_reference) && is_string($this->source_reference) && trim($this->source_reference) !== '') {
            return trim($this->source_reference);
        }

        return 'Sin documento';
    }

    public function getSourceGroupLabelAttribute(): ?string
    {
        $display = $this->display_document_number;
        $prefix = self::legacyDocumentDisplayPrefix();

        if (is_string($display) && str_starts_with($display, $prefix . ' ')) {
            return $display;
        }

        return null;
    }
}
