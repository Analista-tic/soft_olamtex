<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WarehouseItemStockController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'item_id' => 'required|integer|exists:items,id',
        ]);

        $itemId = (int) $request->input('item_id');

        $rows = DB::table('warehouse_items')
            ->select([
                'warehouse_items.warehouse_id',
                'warehouse_items.quantity',
                'warehouses.name as warehouse_name',
            ])
            ->join('warehouses', 'warehouses.id', '=', 'warehouse_items.warehouse_id')
            ->where('warehouse_items.item_id', $itemId)
            ->where('warehouse_items.quantity', '>', 0)
            ->orderByDesc('warehouse_items.quantity')
            ->get();

        return response()->json([
            'data' => $rows,
        ]);
    }
}
