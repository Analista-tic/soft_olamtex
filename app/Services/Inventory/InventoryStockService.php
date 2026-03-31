<?php

namespace App\Services\Inventory;

use App\Models\MasterData\Item;
use App\Models\Inventory\Warehouse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryStockService
{
    public function resolveWarehouseId(Item $item, ?int $warehouseId): int
    {
        if ($warehouseId) {
            return $warehouseId;
        }

        $warehouse = $item->warehouses()->first();
        if ($warehouse) {
            return $warehouse->id;
        }

        $fallback = Warehouse::first();
        if ($fallback) {
            $item->warehouses()->attach($fallback->id, [
                'quantity' => 0,
                'stock_min' => 0,
                'stock_max' => 0,
            ]);
            return $fallback->id;
        }

        throw ValidationException::withMessages([
            'lines' => ["No hay bodegas configuradas. Crea una bodega o envía warehouse_id."],
        ]);
    }


}
