<?php

namespace App\Services\Inventory;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ItemInventoryService
{
    public function paginate(array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        $allowedSorts = ['quantity', 'average_cost', 'last_unit_cost', 'total_cost'];
        $sortBy = in_array($filters['sort_by'] ?? null, $allowedSorts, true)
            ? (string) $filters['sort_by']
            : 'total_cost';
        $sortDir = ($filters['sort_dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        return $this->buildQuery($filters)
            ->orderBy($sortBy, $sortDir)
            ->orderBy('warehouses.name')
            ->orderBy('items.name')
            ->paginate($perPage);
    }

    protected function buildQuery(array $filters)
    {
        $query = DB::table('warehouse_items')
            ->join('items', 'items.id', '=', 'warehouse_items.item_id')
            ->join('warehouses', 'warehouses.id', '=', 'warehouse_items.warehouse_id')
            ->leftJoin('category_sub_groups',
                       'category_sub_groups.id', '=',
                       'items.category_sub_group_id')
            ->select([
                'warehouse_items.id',
                'items.id as item_id',
                'items.name as item_name',
                'items.reference',
                'items.status',
                'warehouses.id as warehouse_id',
                'warehouses.name as warehouse_name',
                'category_sub_groups.id as category_sub_group_id',
                'category_sub_groups.category_group_id',
                'warehouse_items.quantity',
                DB::raw('COALESCE(warehouse_items.weighted_average_price, 0) as average_cost'),
                DB::raw('COALESCE(warehouse_items.last_unit_cost, warehouse_items.weighted_average_price, 0) as last_unit_cost'),
                DB::raw('COALESCE(warehouse_items.total_cost, warehouse_items.quantity * COALESCE(warehouse_items.weighted_average_price, 0), 0) as total_cost'),
                'warehouse_items.stock_min as min_quantity',
                'warehouse_items.stock_max as max_quantity',
                'warehouse_items.source_type',
                'warehouse_items.source_reference',
            ]);

        if (!empty($filters['warehouse_id'])) {
            $query->where('warehouse_items.warehouse_id',
                          (int) $filters['warehouse_id']);
        }

        if (!empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->where(function ($q) use ($search) {
                $q->where('items.name', 'like', "%{$search}%")
                  ->orWhere('items.reference', 'like', "%{$search}%");
            });
        }

        if (array_key_exists('status', $filters) && $filters['status'] !== '') {
            $status = filter_var(
                $filters['status'],
                FILTER_VALIDATE_BOOLEAN,
                FILTER_NULL_ON_FAILURE
            );
            if ($status !== null) {
                $query->where('items.status', $status);
            }
        }

        $query->where('items.deleted_at', null);
        $query->where('warehouse_items.quantity', '<>', 0);

        return $query;
    }
}
