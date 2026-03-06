<?php

namespace App\Models\Production;

use App\Models\Textil\FabricRoll;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionRollConsumption extends Model
{
    protected $table = 'production_roll_consumptions';

    protected $fillable = [
        'production_order_id',
        'fabric_roll_id',
        'meters_consumed',
    ];

    protected $casts = [
        'meters_consumed' => 'decimal:6',
    ];

    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class);
    }

    public function fabricRoll(): BelongsTo
    {
        return $this->belongsTo(FabricRoll::class);
    }
}
