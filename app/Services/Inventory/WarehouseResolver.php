<?php

namespace App\Services\Inventory;

use App\Models\Inventory\Warehouse;
use App\Models\Inventory\WarehouseItem;
use App\Models\MasterData\Item;
use Illuminate\Validation\ValidationException;

class WarehouseResolver
{
    public function resolveWarehouseId(Item $item, ?int $warehouseId): int
    {
        if ($warehouseId) {
            return $warehouseId;
        }

        $existing = WarehouseItem::where('item_id', $item->id)->first();
        if ($existing) {
            return $existing->warehouse_id;
        }

        $fallback = Warehouse::first();
        if ($fallback) {
            WarehouseItem::create([
                'item_id'      => $item->id,
                'warehouse_id' => $fallback->id,
                'quantity'     => 0,
                'stock_min'    => 0,
                'stock_max'    => 0,
            ]);
            return $fallback->id;
        }

        throw ValidationException::withMessages([
            'lines' => ['No hay bodegas configuradas. Crea una bodega o envía warehouse_id.'],
        ]);
    }
}
