<?php

namespace App\Services\Inventory;

use App\Models\Expenses\PurchaseDocument;
use App\Models\Inventory\Item;
use App\Models\Inventory\ItemInventory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ItemInventoryService
{
    protected bool $inventorySynced = false;

    public function paginate(array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        $this->ensureInventorySynced();

        $query = $this->buildAggregatedQuery($filters);

        return $query->orderBy('item_name')->paginate($perPage);
    }

    protected function buildAggregatedQuery(array $filters)
    {
        $query = Item::query()
            ->select([
                DB::raw('MIN(item_inventories.id) as id'),
                DB::raw('MIN(items.id) as item_id'),
                DB::raw('MIN(items.name) as item_name'),
                'items.reference',
                'items.status',
                'category_sub_groups.id as category_sub_group_id',
                'category_sub_groups.category_group_id as category_group_id',
                DB::raw('SUM(item_inventories.quantity) as quantity'),
                DB::raw('CASE WHEN SUM(item_inventories.quantity) > 0 THEN SUM(item_inventories.average_cost * item_inventories.quantity) / SUM(item_inventories.quantity) ELSE 0 END as average_cost'),
                DB::raw('SUM(item_inventories.total) as total'),
            ])
            ->join('item_inventories', 'item_inventories.item_id', '=', 'items.id')
            ->leftJoin('category_sub_groups', 'items.category_sub_group_id', '=', 'category_sub_groups.id');

        if (!empty($filters['warehouse_id'])) {
            $query->where('item_inventories.warehouse_id', (int) $filters['warehouse_id']);
        }

        $this->applyItemFilters($query, $filters);

        $query->groupBy(
            'category_sub_groups.category_group_id',
            'category_sub_groups.id',
            'items.reference',
            'items.status'
        );

        return $query;
    }

    protected function applyItemFilters($query, array $filters): void
    {
        if (!empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->where(function ($q) use ($search) {
                $q->where('items.name', 'like', "%{$search}%")
                    ->orWhere('items.reference', 'like', "%{$search}%");
            });
        }

        $this->applyDateStatusFilters($query, $filters, 'items.status');
    }

    protected function applyDateStatusFilters($query, array $filters, string $statusColumn): void
    {
        if (array_key_exists('status', $filters) && $filters['status'] !== '') {
            $status = filter_var($filters['status'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($status !== null) {
                $query->where($statusColumn, $status);
            }
        }

        if (!empty($filters['date_from'])) {
            $query->whereDate('item_inventories.created_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('item_inventories.created_at', '<=', $filters['date_to']);
        }
    }

    protected function ensureInventorySynced(): void
    {
        if ($this->inventorySynced) {
            return;
        }

        $this->syncInventoryRecords();
        $this->inventorySynced = true;
    }

    protected function syncInventoryRecords(): void
    {
        DB::transaction(function () {
            $warehouseRows = DB::table('warehouse_items')->get();

            if ($warehouseRows->isEmpty()) {
                ItemInventory::query()->delete();
                return;
            }

            $itemIds = $warehouseRows
                ->pluck('item_id')
                ->filter()
                ->unique()
                ->values()
                ->all();

            $items = Item::whereIn('id', $itemIds)->get()->keyBy('id');
            $averageCosts = $this->calculateAverageCosts();
            $syncedIds = [];

            foreach ($warehouseRows as $row) {
                if (empty($row->item_id) || empty($row->warehouse_id)) {
                    continue;
                }

                $item = $items->get($row->item_id);
                if (!$item) {
                    continue;
                }

                $averageCost = $averageCosts[$row->item_id]['average_cost'] ?? 0;
                $quantity = (float) ($row->quantity ?? 0);
                $totalValue = round($quantity * $averageCost, 2);

                $inventory = ItemInventory::updateOrCreate(
                    ['item_id' => $row->item_id, 'warehouse_id' => $row->warehouse_id],
                    [
                        'item_name'    => $item->name,
                        'reference'    => $item->reference,
                        'quantity'     => $quantity,
                        'status'       => (bool) $item->status,
                        'average_cost' => $averageCost,
                        'total'        => $totalValue,
                    ]
                );

                $syncedIds[] = $inventory->id;
            }

            if (!empty($syncedIds)) {
                ItemInventory::whereNotIn('id', $syncedIds)->delete();
            } else {
                ItemInventory::query()->delete();
            }
        });
    }

    protected function calculateAverageCosts(): array
    {
        $purchases = DB::table('purchase_document_items')
            ->select(
                'purchase_document_items.item_id',
                DB::raw('SUM(purchase_document_items.quantity) as total_quantity'),
                DB::raw('SUM(purchase_document_items.quantity * purchase_document_items.price) as total_cost')
            )
            ->join('purchase_documents', function ($join) {
                $join->on('purchase_document_items.purchase_document_id', '=', 'purchase_documents.id')
                    ->where('purchase_documents.document_type', PurchaseDocument::TYPE_INVOICE)
                    ->whereNotNull('purchase_document_items.item_id')
                    ->where(function ($query) {
                        $query->where('purchase_documents.status', 'approved')
                            ->orWhere('purchase_documents.approval_status', 'approved');
                    });
            })
            ->groupBy('purchase_document_items.item_id')
            ->get();

        $adjustments = DB::table('inventory_adjustment_lines')
            ->select(
                'inventory_adjustment_lines.item_id',
                DB::raw('SUM(inventory_adjustment_lines.quantity) as total_quantity'),
                DB::raw('SUM(inventory_adjustment_lines.quantity * inventory_adjustment_lines.price) as total_cost')
            )
            ->join('inventory_adjustments', function ($join) {
                $join->on('inventory_adjustment_lines.inventory_adjustment_id', '=', 'inventory_adjustments.id')
                    ->where('inventory_adjustments.type', 'entrada');
            })
            ->groupBy('inventory_adjustment_lines.item_id')
            ->get();

        $totals = [];

        foreach ($purchases as $row) {
            if (!$row->item_id) {
                continue;
            }

            $itemId = (int) $row->item_id;
            $totals[$itemId]['quantity'] = ($totals[$itemId]['quantity'] ?? 0) + (float) $row->total_quantity;
            $totals[$itemId]['cost'] = ($totals[$itemId]['cost'] ?? 0) + (float) $row->total_cost;
        }

        foreach ($adjustments as $row) {
            if (!$row->item_id) {
                continue;
            }

            $itemId = (int) $row->item_id;
            $totals[$itemId]['quantity'] = ($totals[$itemId]['quantity'] ?? 0) + (float) $row->total_quantity;
            $totals[$itemId]['cost'] = ($totals[$itemId]['cost'] ?? 0) + (float) $row->total_cost;
        }

        $averages = [];
        foreach ($totals as $itemId => $values) {
            $quantity = $values['quantity'] ?? 0;
            $cost = $values['cost'] ?? 0;
            $average = $quantity > 0 ? $cost / $quantity : 0;
            $averages[$itemId] = [
                'average_cost' => round($average, 4),
            ];
        }

        return $averages;
    }
}
