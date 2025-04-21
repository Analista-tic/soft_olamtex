<?php

namespace App\Models\ProductSheet;

use App\Models\Inventory\FabricOrder;
use Illuminate\Database\Eloquent\Model;

class Destination extends Model
{
    protected $fillable = ['name'];

    public function product()
    {
        return $this->hasMany(ProductSheet::class);
    }

    public function fabricOrders()
    {
        return $this->hasMany(FabricOrder::class);
    }
}
