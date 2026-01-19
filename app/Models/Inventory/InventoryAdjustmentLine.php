<?php

namespace App\Models\Inventory;

use App\Models\Inventory\Item;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryAdjustmentLine extends Model
{
    use HasFactory;

    protected $table = 'inventory_adjustment_lines';

    protected $fillable = [
        'inventory_adjustment_id',
        'item_id',
        'warehouse_id',
        'price',
        'tax',
        'quantity',
        'description',
        'line_total',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'tax' => 'decimal:2',
        'quantity' => 'decimal:4',
        'line_total' => 'decimal:2',
    ];

    public function adjustment()
    {
        return $this->belongsTo(InventoryAdjustment::class, 'inventory_adjustment_id');
    }

    public function warehouse()
    {
        return $this->belongsTo(\App\Models\Common\Warehouse::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }
}
