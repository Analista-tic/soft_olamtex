<?php

namespace App\Models\Inventory;

use App\Models\MasterData\Item;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemInventory extends Model
{
    use HasFactory;

    protected $fillable = [
        'item_id',
        'warehouse_id',
        'item_name',
        'reference',
        'quantity',
        'status',
        'average_cost',
        'total',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'status' => 'boolean',
        'average_cost' => 'decimal:4',
        'total' => 'decimal:2',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
}
