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
        'quantity_before',
        'quantity_after',
        'partial_before',
        'partial_after',
        'reason',
        'reference',
        'inventory_movement_id',
    ];

    protected $casts = [
        'quantity_before' => 'decimal:2',
        'quantity_after' => 'decimal:2',
        'partial_before' => 'decimal:2',
        'partial_after' => 'decimal:2',
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
}
