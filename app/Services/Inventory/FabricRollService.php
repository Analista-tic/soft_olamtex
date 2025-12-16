<?php

namespace App\Services\Inventory;

use App\Models\Inventory\FabricRoll;
use App\Models\Expenses\PurchaseInvoice;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class FabricRollService
{
    public function paginate(array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        $query = FabricRoll::with(['purchaseInvoiceItem.article']);

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('roll_number', 'like', "%{$search}%")
                    ->orWhereHas('purchaseInvoiceItem.article', function ($article) use ($search) {
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
            $fabricRoll->load('purchaseInvoiceItem.article');

            return $fabricRoll;
        });
    }

    public function findOrFail($id): FabricRoll
    {
        return FabricRoll::with(['purchaseInvoiceItem.article'])->findOrFail($id);
    }

    public function update(FabricRoll $fabricRoll, array $data): FabricRoll
    {
        return DB::transaction(function () use ($fabricRoll, $data) {
            $data['partial_output'] = $data['partial_output'] ?? 0;
            $data['actual_output'] = $data['actual_output'] ?? 0;

            $fabricRoll->update($data);
            $fabricRoll->load('purchaseInvoiceItem.article');

            return $fabricRoll;
        });
    }

    public function delete(FabricRoll $fabricRoll): void
    {
        $fabricRoll->delete();
    }

    public function updateOutput(FabricRoll $fabricRoll, array $data): FabricRoll
    {
        $fabricRoll->update($data);
        $fabricRoll->load('purchaseInvoiceItem.article');

        return $fabricRoll;
    }

    public function storeFromReception(array $data): FabricRoll
    {
        return $this->create($data);
    }

    public function getByInvoiceItem($itemId): Collection
    {
        return FabricRoll::where('purchase_invoice_item_id', $itemId)->orderBy('entry_date')->get();
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
