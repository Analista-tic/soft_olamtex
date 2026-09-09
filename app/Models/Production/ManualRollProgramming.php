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
        'notes',
        'created_by_user_id',
    ];

    protected $casts = [
        'programmed_meters' => 'decimal:6',
        'consumed_meters' => 'decimal:6',
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

    public function getRemainingMetersAttribute(): float
    {
        return max(0.0, (float) $this->programmed_meters - (float) $this->consumed_meters);
    }
}
