<?php

namespace App\Services\Inventory;

use App\Models\Expenses\PurchaseInvoice;
use App\Models\Inventory\FabricRoll;
use App\Models\Inventory\Item;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class FabricRollService
{
    public function paginate(array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        $query = FabricRoll::with([
            'purchaseDocumentItem.item',
            'purchaseDocumentItem.document.supplier',
            'purchaseDocumentItem.document.creator',
        ]);

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('roll_number', 'like', "%{$search}%")
                    ->orWhereHas('purchaseDocumentItem.item', function ($article) use ($search) {
                        $article->where('description', 'like', "%{$search}%")
                                ->orWhere('reference', 'like', "%{$search}%");
                    });
            });
        }

        if (isset($filters['status']) && $filters['status'] !== '') {
            if ($filters['status'] === 'available') {
                $query->whereRaw('quantity > actual_output');
            } elseif ($filters['status'] === 'depleted') {
                $query->whereRaw('quantity <= actual_output');
            }
        }

        if (!empty($filters['date_from'])) {
            $query->whereDate('entry_date', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('entry_date', '<=', $filters['date_to']);
        }

        return $query->orderBy('entry_date', 'desc')->paginate($perPage);
    }

    public function create(array $data): FabricRoll
    {
        return DB::transaction(function () use ($data) {
            $data['partial_output'] = $data['partial_output'] ?? 0;
            $data['actual_output'] = $data['actual_output'] ?? 0;
            $data['roll_width'] = $data['roll_width'] ?? 0;

            $fabricRoll = FabricRoll::create($data);
            $fabricRoll->load([
                'purchaseDocumentItem.item',
                'purchaseDocumentItem.document.supplier',
                'purchaseDocumentItem.document.creator',
            ]);

            $this->syncItemWarehouseLength($fabricRoll);

            return $fabricRoll;
        });
    }

    public function findOrFail($id): FabricRoll
    {
        return FabricRoll::with([
            'purchaseDocumentItem.item',
            'purchaseDocumentItem.document.supplier',
            'purchaseDocumentItem.document.creator',
        ])->findOrFail($id);
    }

    public function update(FabricRoll $fabricRoll, array $data): FabricRoll
    {
        return DB::transaction(function () use ($fabricRoll, $data) {
            $data['partial_output'] = $data['partial_output'] ?? 0;
            $data['actual_output'] = $data['actual_output'] ?? 0;

            $fabricRoll->update($data);
            $fabricRoll->load([
                'purchaseDocumentItem.item',
                'purchaseDocumentItem.document.supplier',
                'purchaseDocumentItem.document.creator',
            ]);

            $this->syncItemWarehouseLength($fabricRoll);

            return $fabricRoll;
        });
    }

    public function delete(FabricRoll $fabricRoll): void
    {
        $item = $fabricRoll->purchaseDocumentItem->item ?? null;
        $fabricRoll->delete();
        if ($item) {
            $this->syncWarehouseLengthForItem($item);
        }
    }

    public function updateOutput(FabricRoll $fabricRoll, array $data): FabricRoll
    {
        $fabricRoll->update($data);
        $fabricRoll->load([
            'purchaseDocumentItem.item',
            'purchaseDocumentItem.document.supplier',
            'purchaseDocumentItem.document.creator',
        ]);

        $this->syncItemWarehouseLength($fabricRoll);

        return $fabricRoll;
    }

    public function storeFromReception(array $data): FabricRoll
    {
        return $this->create($data);
    }

    protected function syncItemWarehouseLength(FabricRoll $fabricRoll): void
    {
        $item = $fabricRoll->purchaseDocumentItem->item;
        if (!$item) {
            return;
        }

        $this->syncWarehouseLengthForItem($item);
    }

    protected function syncWarehouseLengthForItem(Item $item): void
    {
        $warehouse = $item->warehouses()->first();
        if (!$warehouse) {
            return;
        }

        $totalQuantity = $this->sumRollQuantityForItem($item->id);

        $row = DB::table('warehouse_items')
            ->where('item_id', $item->id)
            ->where('warehouse_id', $warehouse->id)
            ->first();

        $payload = [
            'quantity' => $totalQuantity,
            'updated_at' => now(),
        ];

        if ($row) {
            DB::table('warehouse_items')->where('id', $row->id)->update($payload);
            return;
        }

        $payload['item_id'] = $item->id;
        $payload['warehouse_id'] = $warehouse->id;
        $payload['stock_min'] = 0;
        $payload['stock_max'] = 0;
        $payload['created_at'] = now();

        DB::table('warehouse_items')->insert($payload);
    }

    protected function sumRollQuantityForItem(int $itemId): float
    {
        return (float) FabricRoll::whereHas('purchaseDocumentItem', function ($query) use ($itemId) {
            $query->where('item_id', $itemId);
        })->sum('quantity');
    }

    public function getByInvoiceItem($itemId): Collection
    {
        return FabricRoll::with([
            'purchaseDocumentItem.item',
            'purchaseDocumentItem.document.supplier',
            'purchaseDocumentItem.document.creator',
        ])
            ->where('purchase_document_item_id', $itemId)
            ->orderBy('entry_date')
            ->get();
    }

    public function export(): Collection
    {
        return FabricRoll::with([
            'purchaseDocumentItem.item',
            'purchaseDocumentItem.document.supplier',
            'purchaseDocumentItem.document.creator',
        ])
            ->orderBy('entry_date', 'desc')
            ->get();
    }

    public function available(): Collection
    {
        return FabricRoll::with([
            'purchaseDocumentItem.item',
            'purchaseDocumentItem.document.supplier',
            'purchaseDocumentItem.document.creator',
        ])
            ->whereColumn('quantity', '>', 'actual_output')
            ->orderBy('entry_date', 'desc')
            ->get();
    }

    public function approveReception($invoiceId)
    {
        $invoice = PurchaseInvoice::findOrFail($invoiceId);
        $invoice->update(['status' => 'approved']);
        return $invoice;
    }

    public function rejectReception($invoiceId)
    {
        $invoice = PurchaseInvoice::findOrFail($invoiceId);
        $invoice->update(['status' => 'rejected']);
        return $invoice;
    }
}
