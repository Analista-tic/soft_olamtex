<?php

namespace App\Models\Textil;

use App\Models\Expenses\PurchaseDocumentItem;
use App\Models\MasterData\Item;
use App\Support\Textil\FabricRollBalance;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FabricRoll extends Model
{
    use HasFactory, SoftDeletes, \App\Models\Concerns\NormalizesAttributes;

    /** Reglas de normalización al guardar. movement_type/approval_status (enums) quedan fuera. */
    protected array $normalizedAttributes = [
        'roll_number' => 'code',
        'batch' => 'code',
        'location' => 'code',
        'internal_color_name' => 'catalogName',
        'internal_color_code' => 'code',
        'description' => 'freeText',
    ];

    public const APPROVAL_APPROVED = 'approved';
    public const APPROVAL_PENDING = 'pending_approval';
    public const APPROVAL_NOT_APPROVED = 'not_approved';

    /** Estado de tolerancia por rollo (independiente de approval_status). */
    public const TOLERANCE_NONE = 'none';
    public const TOLERANCE_PENDING_REVIEW = 'pending_review';
    public const TOLERANCE_APPROVED_WITH_DIFFERENCE = 'approved_with_difference';
    public const TOLERANCE_REJECTED = 'rejected';

    /** Revisión de diferencias de metraje (sobrante/faltante) detectadas al descargar. */
    public const LENGTH_REVIEW_NONE = 'none';
    public const LENGTH_REVIEW_PENDING = 'pending';
    public const LENGTH_REVIEW_REVIEWED = 'reviewed';

    /**
     * price_per_meter persiste desde el precio unitario de PurchaseDocumentItem.
     * Se puede actualizar después también para rollos sin compra vinculada.
     */
    protected $fillable = [
        'legacy_id',
        'roll_number',
        'purchase_document_item_id',
        'item_id',
        'location',
        'quantity',
        'received_quantity',
        'roll_width',
        'price_per_meter',
        'description',
        'internal_color_name',
        'internal_color_code',
        'movement_type',
        'partial_output',
        'actual_output',
        'entry_date',
        'approval_status',
        'batch',
        'tolerance_status',
        'tolerance_reason',
        'resolved_at',
        'written_off_at',
        'length_review_status',
    ];

    protected $casts = [
        'legacy_id'                 => 'integer',
        'purchase_document_item_id' => 'integer',
        'item_id'                   => 'integer',
        'quantity'                  => 'decimal:2',
        'received_quantity'         => 'decimal:2',
        'roll_width'                => 'decimal:2',
        'price_per_meter'           => 'decimal:4',
        'partial_output'            => 'decimal:2',
        'actual_output'             => 'decimal:2',
        'entry_date'                => 'date',
        'resolved_at'               => 'datetime',
        'written_off_at'            => 'datetime',
    ];

    protected static function booted(): void
    {
        // Lo recibido acompaña a quantity mientras la recepción no está aprobada (el rollo
        // todavía se corrige en recepción). Después queda fijo y las diferencias de metraje
        // se registran como eventos en fabric_roll_length_differences.
        static::saving(function (FabricRoll $roll): void {
            $attributes = $roll->getAttributes();

            if (! $roll->exists) {
                $roll->received_quantity ??= $roll->quantity;

                return;
            }

            // Modelo cargado con columnas parciales (select): no se infiere nada.
            foreach (['quantity', 'received_quantity', 'approval_status'] as $column) {
                if (! array_key_exists($column, $attributes)) {
                    return;
                }
            }

            $approvedBefore = $roll->getOriginal('approval_status') === self::APPROVAL_APPROVED;
            if ($roll->received_quantity === null || ! $approvedBefore) {
                $roll->received_quantity = $roll->quantity;
            }
        });
    }

    // Relación con el item de factura de compra
    public function purchaseDocumentItem()
    {
        return $this->belongsTo(PurchaseDocumentItem::class, 'purchase_document_item_id');
    }

    public function item()
    {
        return $this->belongsTo(Item::class, 'item_id');
    }

    // Disponibilidad real: metraje vigente menos lo consumido (sin descontar reservas).
    public function getAvailableQuantityAttribute()
    {
        return FabricRollBalance::real($this);
    }

    // Verificar si el rollo está agotado
    public function getIsDepletedAttribute()
    {
        return $this->available_quantity <= 0;
    }

    public function consumptions()
    {
        return $this->hasMany(\App\Models\Production\ProductionRollConsumption::class, 'fabric_roll_id');
    }

    public function creditNoteLinks()
    {
        return $this->hasMany(FabricRollCreditNoteLink::class, 'fabric_roll_id');
    }

    public function adjustments()
    {
        return $this->hasMany(FabricRollAdjustment::class, 'fabric_roll_id');
    }

    public function lengthDifferences()
    {
        return $this->hasMany(FabricRollLengthDifference::class, 'fabric_roll_id');
    }
}
