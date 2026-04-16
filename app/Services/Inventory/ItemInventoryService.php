<?php

namespace App\Services\Inventory;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ItemInventoryService
{
    public function paginate(array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        $allowedSorts = ['quantity', 'average_cost', 'total'];
        $sortBy = in_array($filters['sort_by'] ?? null, $allowedSorts, true)
            ? (string) $filters['sort_by']
            : 'total';
        $sortDir = ($filters['sort_dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        return $this->buildQuery($filters)
            ->orderBy($sortBy, $sortDir)
            ->orderBy('items.name')
            ->paginate($perPage);
    }

    protected function buildQuery(array $filters)
    {
        $query = DB::table('warehouse_items')
            ->join('items', 'items.id', '=', 'warehouse_items.item_id')
            ->leftJoin('category_sub_groups',
                       'category_sub_groups.id', '=',
                       'items.category_sub_group_id')
            ->select([
                DB::raw('MIN(warehouse_items.id) as id'),
                'items.id as item_id',
                'items.name as item_name',
                'items.reference',
                'items.status',
                'category_sub_groups.id as category_sub_group_id',
                'category_sub_groups.category_group_id',
                DB::raw('SUM(warehouse_items.quantity) as quantity'),
                DB::raw('
                    CASE
                        WHEN SUM(warehouse_items.quantity) > 0
                        THEN SUM(
                            COALESCE(warehouse_items.weighted_average_price, 0)
                            * warehouse_items.quantity
                        ) / SUM(warehouse_items.quantity)
                        ELSE 0
                    END as average_cost
                '),
                DB::raw('
                    SUM(
                        warehouse_items.quantity
                        * COALESCE(warehouse_items.weighted_average_price, 0)
                    ) as total
                '),
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

        $query->groupBy(
            'items.id',
            'items.name',
            'items.reference',
            'items.status',
            'category_sub_groups.id',
            'category_sub_groups.category_group_id'
        );

        return $query;
    }
}
