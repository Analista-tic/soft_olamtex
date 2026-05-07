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
        // Rules implemented:
        // - Legacy imports -> "Migración"
        // - Bulk/Excel imports -> "Importación"
        // - PurchaseDocument / Adjustment / Transfer / Request / Exit -> return the raw document number
        //   but cleaned (no textual prefixes like "Factura ", "Ajuste ").
        // - Technical codes (BI-bulk_, legacy_, LM-legacy_) are never shown.
        // - Avoid N+1: rely on relationLoaded('source') when reading related model fields.

        $sourceType = (string) ($this->source_type ?? '');
        $doc = is_string($this->document_number) ? trim($this->document_number) : null;
        $srcRef = is_string($this->source_reference) ? trim($this->source_reference) : null;

        // 1) Legacy import explicit
        if ($this->isLegacySource($sourceType) || ($doc !== null && self::isTechnicalCode($doc)) || ($srcRef !== null && self::isTechnicalCode($srcRef))) {
            return 'Migración';
        }

        // 2) Bulk / Excel import
        if ($this->isBulkImportSource($sourceType) || ($doc !== null && stripos($doc, 'BI-bulk_') === 0)) {
            return 'Importación';
        }

        // 3) If related source was eager-loaded, try to resolve a meaningful number
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
                if (isset($src->{$field}) && is_string($src->{$field})) {
                    $value = trim($src->{$field});
                    if ($value === '') {
                        continue;
                    }
                    if (self::isTechnicalCode($value)) {
                        // prefer Importación if it's a bulk technical code
                        if (stripos($value, 'BI-bulk_') === 0) {
                            return 'Importación';
                        }
                        // otherwise consider it legacy/technical and hide
                        return 'Sin documento';
                    }

                    return self::cleanDocumentLabel($value);
                }
            }
        }

        // 4) Fallback to movement.document_number (cleaned), unless it's a technical code
        if ($doc !== null && $doc !== '') {
            if (self::isTechnicalCode($doc)) {
                // If document_number is a technical bulk id, treat as Importación
                if (stripos($doc, 'BI-bulk_') === 0) {
                    return 'Importación';
                }
                return 'Sin documento';
            }
            return self::cleanDocumentLabel($doc);
        }

        // 5) Fallback to source_reference if present and not technical
        if ($srcRef !== null && $srcRef !== '') {
            if (self::isTechnicalCode($srcRef)) {
                if (stripos($srcRef, 'BI-bulk_') === 0) {
                    return 'Importación';
                }
                return 'Sin documento';
            }
            return self::cleanDocumentLabel($srcRef);
        }

        return 'Sin documento';
    }

    private static function isTechnicalCode(string $value): bool
    {
        $value = trim($value);
        if ($value === '') {
            return false;
        }
        $patterns = [
            '/^BI-bulk_/i',
            '/^legacy_/i',
            '/^LM-legacy_/i',
        ];
        foreach ($patterns as $p) {
            if (preg_match($p, $value) === 1) {
                return true;
            }
        }
        return false;
    }

    private function isLegacySource(string $sourceType): bool
    {
        if ($sourceType === '') {
            return false;
        }
        $s = strtolower($sourceType);
        return str_contains($s, 'legacy') || $s === 'legacy_import';
    }

    private function isBulkImportSource(string $sourceType): bool
    {
        if ($sourceType === '') {
            return false;
        }
        $s = strtolower($sourceType);
        return str_contains($s, 'bulk') || str_contains($s, 'import') || str_contains($s, 'excel');
    }

    private static function cleanDocumentLabel(string $value): string
    {
        $v = trim($value);
        // Remove common Spanish prefixes like "Factura ", "Ajuste ", "Salida ", "Traslado ", "Solicitud "
        $v = preg_replace('/^(Factura(?: de compra)?|Ajuste|Salida|Traslado|Solicitud|Solicitud de insumos)\s*[:\-]?\s*/i', '', $v);
        // Also remove words followed by colon like "Factura: FC-0001"
        $v = preg_replace('/^[A-Za-z\s]+:\s*/', '', $v);
        return trim($v);
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
