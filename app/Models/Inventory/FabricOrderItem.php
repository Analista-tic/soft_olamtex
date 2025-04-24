<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FabricOrderItem extends Model
{
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

    public function baseFabric()
    {
        return $this->belongsTo(BaseFabric::class, 'base_fabric_id');
    }

    public function fabricRolls()
    {
        return $this->hasMany(FabricRoll::class);
    }
}
