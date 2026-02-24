<?php

namespace App\Models\Inventory;

use App\Models\MasterData\Item;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Warehouse extends Model implements \App\Contracts\HasAuditLabel
{
    use HasFactory;

    public static function auditLabel(): string
    {
        return 'Bodega';
    }

    protected $fillable = ['name', 'observation'];

    public function items()
    {
        return $this->belongsToMany(Item::class, 'warehouse_items')
            ->withPivot(['quantity', 'stock_min', 'stock_max'])
            ->withTimestamps();
    }
}