<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class WarehouseItemStockController extends Controller
{
    /**
     * Retorna disponibilidad por bodega y precio ponderado (PP) según INVENTARIO_4UTEX.
     * PP vive en warehouse_items.weighted_average_price por bodega.
     */
    public function index(Request $request)
    {
        $request->validate([
            'item_id' => 'required|integer|exists:items,id',
        ]);

        $itemId = (int) $request->input('item_id');

        $selectColumns = [
            'warehouse_items.warehouse_id',
            'warehouse_items.quantity',
            'warehouses.name as warehouse_name',
        ];

        if (Schema::hasColumn('warehouse_items', 'weighted_average_price')) {
            $selectColumns[] = 'warehouse_items.weighted_average_price';
        }

        $rows = DB::table('warehouse_items')
            ->select($selectColumns)
            ->join('warehouses', 'warehouses.id', '=', 'warehouse_items.warehouse_id')
            ->where('warehouse_items.item_id', $itemId)
            ->orderByDesc('warehouse_items.quantity')
            ->get();

        // Total disponible (suma de cantidades por bodega)
        $totalQuantity = $rows->sum(fn ($r) => (float) ($r->quantity ?? 0));

        // PP ponderado global: sum(qty * pp) / totalQty para bodegas con stock (INVENTARIO_4UTEX)
        $weightedAvgPrice = null;
        if (Schema::hasColumn('warehouse_items', 'weighted_average_price')) {
            $weightedSum = 0;
            $qtySum = 0;
            foreach ($rows as $r) {
                $qty = (float) ($r->quantity ?? 0);
                $pp = isset($r->weighted_average_price) ? (float) $r->weighted_average_price : 0;
                if ($qty > 0 && $pp > 0) {
                    $weightedSum += $qty * $pp;
                    $qtySum += $qty;
                }
            }
            $weightedAvgPrice = $qtySum > 0 ? round($weightedSum / $qtySum, 6) : null;
        }

        return response()->json([
            'data' => $rows,
            'total_quantity' => round($totalQuantity, 6),
            'weighted_average_price' => $weightedAvgPrice,
        ]);
    }
}
