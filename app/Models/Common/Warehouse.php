<?php

namespace App\Models\Common;

use App\Models\Inventory\Item;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Warehouse extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'observation'];

    public function items()
    {
        return $this->belongsToMany(Item::class, 'warehouse_article')
            ->withPivot(['quantity', 'stock_min', 'stock_max'])
            ->withTimestamps();
    }
}
