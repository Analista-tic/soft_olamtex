<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;

class FabricRoll extends Model
{
    protected $fillable = [
        'location',
        'fabric_color_id',
        'fabric_order_item_id',
        'received_quantity',
        'width',
        'quantity',
    ];
}
