<?php

namespace App\Models\Production;

use App\Models\Settings\User;
use App\Models\Textil\FabricRoll;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ManualRollProgramming extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'fabric_roll_id',
        'manual_order_code',
        'production_order_id',
        'programmed_meters',
        'consumed_meters',
        'released_meters',
        'notes',
        'created_by_user_id',
    ];

    protected $casts = [
        'programmed_meters' => 'decimal:6',
        'consumed_meters' => 'decimal:6',
        'released_meters' => 'decimal:6',
    ];

    public function fabricRoll(): BelongsTo
    {
        return $this->belongsTo(FabricRoll::class);
    }

    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /** Reserva que la línea mantiene sobre el rollo: programado − consumido − liberado (rollo terminado). */
    public function getRemainingMetersAttribute(): float
    {
        return max(0.0, round((float) $this->programmed_meters - (float) $this->consumed_meters - (float) $this->released_meters, 6));
    }

    /** Metros descargados por encima de lo programado (sobreconsumo registrado con motivo). */
    public function getOverconsumedMetersAttribute(): float
    {
        return max(0.0, round((float) $this->consumed_meters - (float) $this->programmed_meters, 6));
    }
}
