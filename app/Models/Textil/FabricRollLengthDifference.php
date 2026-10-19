<?php

namespace App\Models\Textil;

use App\Models\Inventory\InventoryMovement;
use App\Models\Production\ManualRollProgramming;
use App\Models\Production\ProductionOrder;
use App\Models\Settings\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Diferencia de metraje de un rollo detectada al descargar: sobrante (dio más de lo
 * registrado) o faltante (se acabó antes). Evento inmutable: no se edita ni se borra,
 * solo se marca como revisado. Un error se corrige con un evento inverso (reverses_id).
 */
class FabricRollLengthDifference extends Model
{
    public const TYPE_SURPLUS = 'sobrante';
    public const TYPE_SHORTAGE = 'faltante';

    /** Campos que la revisión puede completar; todo lo demás queda fijo al crear el evento. */
    private const REVIEW_FIELDS = ['reviewed_by_user_id', 'reviewed_at', 'review_notes', 'updated_at'];

    protected $fillable = [
        'fabric_roll_id',
        'type',
        'meters',
        'quantity_before',
        'quantity_after',
        'reason',
        'source_adjustment_id',
        'manual_roll_programming_id',
        'production_order_id',
        'inventory_movement_id',
        'released_reservations',
        'reverses_id',
        'created_by_user_id',
        'reviewed_by_user_id',
        'reviewed_at',
        'review_notes',
    ];

    protected $casts = [
        'meters' => 'decimal:2',
        'quantity_before' => 'decimal:2',
        'quantity_after' => 'decimal:2',
        'released_reservations' => 'array',
        'reviewed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (FabricRollLengthDifference $difference): void {
            $changed = array_diff(array_keys($difference->getDirty()), self::REVIEW_FIELDS);
            if ($changed !== []) {
                throw new LogicException('Una diferencia de metraje no se modifica: registre un evento inverso.');
            }
        });

        static::deleting(function (): void {
            throw new LogicException('Una diferencia de metraje no se elimina: registre un evento inverso.');
        });
    }

    public function roll(): BelongsTo
    {
        return $this->belongsTo(FabricRoll::class, 'fabric_roll_id');
    }

    public function sourceAdjustment(): BelongsTo
    {
        return $this->belongsTo(FabricRollAdjustment::class, 'source_adjustment_id');
    }

    public function manualRollProgramming(): BelongsTo
    {
        return $this->belongsTo(ManualRollProgramming::class)->withTrashed();
    }

    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class);
    }

    public function inventoryMovement(): BelongsTo
    {
        return $this->belongsTo(InventoryMovement::class);
    }

    public function reverses(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reverses_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    /** Efecto sobre el metraje vigente del rollo: + sobrante, − faltante. */
    public function signedMeters(): float
    {
        return $this->type === self::TYPE_SURPLUS ? (float) $this->meters : -(float) $this->meters;
    }
}
