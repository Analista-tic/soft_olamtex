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

    /**
     * @deprecated No manipular stock directamente. Usar InventoryMovementService::createMovement
     *             para todos los movimientos (ajustes, compras, traslados, etc.).
     */
    public function applyStockMovement(Item $item, int $warehouseId, int $quantity, string $type): void
    {
        $quantity = abs($quantity);
        $sign = $type === 'entrada' ? 1 : -1;

        $row = DB::table('warehouse_items')
            ->where('item_id', $item->id)
            ->where('warehouse_id', $warehouseId)
            ->lockForUpdate()
            ->first();

        $currentQty = $row ? (int) $row->quantity : 0;
        $newQty = $currentQty + ($sign * $quantity);

        if ($newQty < 0 && !$item->negative_movement) {
            $item->negative_movement = 1;
            $item->save();
        }

        $quantityToStore = $item->negative_movement ? $newQty : max($newQty, 0);

        $payload = [
            'quantity'   => $quantityToStore,
            'stock_min'  => $row ? $row->stock_min : 0,
            'stock_max'  => $row ? $row->stock_max : 0,
            'updated_at' => now(),
        ];

        if ($row) {
            DB::table('warehouse_items')
                ->where('id', $row->id)
                ->update($payload);
        } else {
            $payload['item_id'] = $item->id;
            $payload['warehouse_id'] = $warehouseId;
            $payload['created_at'] = now();
            DB::table('warehouse_items')->insert($payload);
        }
    }
}
