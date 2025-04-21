<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FabricOrderItem extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'fabric',
        'base_fabric_id',
        'provider_color',
        'provider_color_code',
        'quantity',
        'estimated_width',
        'price',
        'fabric_order_id',
    ];
}
