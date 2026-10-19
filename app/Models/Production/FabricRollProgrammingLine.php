<?php

namespace App\Models\Production;

use App\Models\MasterData\Item;
use App\Models\Textil\FabricRoll;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FabricRollProgrammingLine extends Model
{
    protected $table = 'fabric_roll_programming_lines';

    protected $fillable = [
        'technical_sheet_id',
        'legacy_uuid',
        'consecutive',
        'type',
        'fabric_roll_id',
        'fabric_item_id',
        'raw_material_uid',
        'fabric_name',
        'color',
        'color_code',
        'supplier',
        'location',
        'notes',
        'average_trace',
        'largo_trazo',
        'largo_liq',
        'factor',
        'consumption_base',
        'price_per_meter',
        'extra_meters',
        'programmed_meters',
        'released_meters',
        'available_meters',
        'balance_meters',
        'width_cm',
    ];

    protected $casts = [
        'average_trace' => 'float',
        'largo_trazo' => 'float',
        'largo_liq' => 'float',
        'factor' => 'float',
        'consumption_base' => 'float',
        'price_per_meter' => 'float',
        'extra_meters' => 'float',
        'programmed_meters' => 'float',
        'released_meters' => 'float',
        'available_meters' => 'float',
        'balance_meters' => 'float',
        'width_cm' => 'float',
    ];

    public function technicalSheet(): BelongsTo
    {
        return $this->belongsTo(TechnicalSheet::class);
    }

    public function fabricRoll(): BelongsTo
    {
        return $this->belongsTo(FabricRoll::class);
    }

    public function fabricItem(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'fabric_item_id');
    }

    /** Reserva que la línea todavía mantiene sobre el rollo: programado menos lo liberado por el corte. */
    public function getPendingMetersAttribute(): float
    {
        return max(0.0, round((float) $this->programmed_meters - (float) $this->released_meters, 2));
    }
}
