<?php

namespace App\Models\Inventory;

use App\Models\Inventory\Warehouse;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryRequestItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'inventory_request_id',
        'item_id',
        'item_name',
        'requested_quantity',
        'delivered_quantity',
        'warehouse_id',
    ];

    protected $casts = [
        'requested_quantity' => 'decimal:4',
        'delivered_quantity' => 'decimal:4',
    ];

    public function request()
    {
        return $this->belongsTo(InventoryRequest::class, 'inventory_request_id');
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }
}
