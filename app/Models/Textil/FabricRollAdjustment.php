<?php

namespace App\Models\Textil;

use App\Models\Inventory\InventoryMovement;
use App\Models\Settings\User;
use Illuminate\Database\Eloquent\Model;

class FabricRollAdjustment extends Model
{
    protected $fillable = [
        'fabric_roll_id',
        'user_id',
        'type',
        'quantity',
        'quantity_before',
        'quantity_after',
        'partial_before',
        'partial_after',
        'actual_output_before',
        'actual_output_after',
        'reason',
        'reference',
        'corrects_adjustment_id',
        'inventory_movement_id',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'quantity_before' => 'decimal:2',
        'quantity_after' => 'decimal:2',
        'partial_before' => 'decimal:2',
        'partial_after' => 'decimal:2',
        'actual_output_before' => 'decimal:2',
        'actual_output_after' => 'decimal:2',
    ];

    public function roll()
    {
        return $this->belongsTo(FabricRoll::class, 'fabric_roll_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function inventoryMovement()
    {
        return $this->belongsTo(InventoryMovement::class);
    }

    /** Correcciones aplicadas a este descargue, en orden. */
    public function corrections()
    {
        return $this->hasMany(self::class, 'corrects_adjustment_id')->orderBy('id');
    }

    public function correctedAdjustment()
    {
        return $this->belongsTo(self::class, 'corrects_adjustment_id');
    }
}
