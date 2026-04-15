<?php

namespace App\Models\Inventory;

use App\Models\MasterData\Item;
use App\Models\Inventory\WarehouseItem;
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

    public function warehouseItems(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(WarehouseItem::class);
    }

    public function items(): \Illuminate\Database\Eloquent\Relations\HasManyThrough
    {
        return $this->hasManyThrough(
            Item::class,
            WarehouseItem::class,
            'warehouse_id',
            'id',
            'id',
            'item_id'
        );
    }
}
