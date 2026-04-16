<?php

namespace App\Models\Inventory;

use App\Models\MasterData\Item;
use Illuminate\Database\Eloquent\Model;

class WarehouseItem extends Model
{
    protected $table = 'warehouse_items';

    protected $fillable = [
        'warehouse_id',
        'item_id',
        'quantity',
        'stock_min',
        'stock_max',
        'weighted_average_price',
    ];

    protected $casts = [
        'quantity'               => 'float',
        'stock_min'              => 'float',
        'stock_max'              => 'float',
        'weighted_average_price' => 'float',
    ];

    public function item(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function warehouse(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
}
