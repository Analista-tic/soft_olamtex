<?php

namespace App\Services\Inventory;

use App\Models\Inventory\ItemInventory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ItemInventoryService
{
    public function paginate(array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        $query = ItemInventory::with(['item', 'warehouse']);

        if (!empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->where(function ($q) use ($search) {
                $q->where('item_name', 'like', "%{$search}%")
                    ->orWhere('reference', 'like', "%{$search}%");
            });
        }

        if (!empty($filters['warehouse_id'])) {
            $query->where('warehouse_id', (int) $filters['warehouse_id']);
        }

        if (array_key_exists('status', $filters) && $filters['status'] !== '') {
            $status = filter_var($filters['status'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($status !== null) {
                $query->where('status', $status);
            }
        }

        return $query->orderBy('item_name')->paginate($perPage);
    }
}
