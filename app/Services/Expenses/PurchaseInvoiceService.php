<?php

namespace App\Services\Expenses;

use App\Models\Expenses\PurchaseInvoice;
use App\Models\Expenses\PurchaseDocument;
use App\Models\Expenses\PurchaseOrder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PurchaseInvoiceService
{
    public function paginate(array $filters, int $perPage = 20, bool $isFabric = false): LengthAwarePaginator
    {
        $query = PurchaseInvoice::with([
            'contact',
            'purchaseOrder',
            'items.item.warehouses',
            'items.item.categorySubGroup.categoryGroup',
            'items.item.tax',
        ])->where('is_fabric', $isFabric);

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->whereHas('contact', fn($q) =>
                $q->where('full_name', 'like', "%{$search}%")
            );
        }

        if (!empty($filters['date_from'])) {
            $query->where('date', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->where('date', '<=', $filters['date_to']);
        }

        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['provider_id']) && $filters['provider_id'] !== 'all') {
            $query->where('contact_id', $filters['provider_id']);
        }

        return $query->orderByDesc('date')->orderByDesc('id')->paginate($perPage);
    }

    public function findWithRelations(int $id): PurchaseInvoice
    {
        return PurchaseInvoice::with([
            'contact',
            'purchaseOrder',
            'items.item.warehouses',
            'items.item.categorySubGroup.categoryGroup',
            'items.item.tax',
        ])->findOrFail($id);
    }

    public function store(array $data, bool $isFabric = false): PurchaseInvoice
    {
        return DB::transaction(function () use ($data, $isFabric) {
            $payload = $this->normalizePayload($data);
            $payload['is_fabric'] = $isFabric;

            $invoice = PurchaseInvoice::create($payload);
            $this->syncInvoiceItems($invoice, $payload['items'] ?? [], false);
            $this->updatePurchaseOrderStatus($payload['purchase_order_id'] ?? null);

            return $invoice->load('items', 'contact', 'purchaseOrder');
        });
    }

    public function update(PurchaseInvoice $invoice, array $data, bool $isFabric = false): PurchaseInvoice
    {
        return DB::transaction(function () use ($invoice, $data, $isFabric) {
            $payload = $this->normalizePayload($data);
            $payload['is_fabric'] = $isFabric;

            $invoice->update($payload);
            $this->syncInvoiceItems($invoice, $payload['items'] ?? [], true);
            $this->updatePurchaseOrderStatus($payload['purchase_order_id'] ?? null);

            return $invoice->load('items', 'contact', 'purchaseOrder');
        });
    }

    public function delete(PurchaseInvoice $invoice): void
    {
        DB::transaction(function () use ($invoice) {
            $purchaseOrderId = $invoice->purchase_order_id;
            $invoice->items()->delete();
            $invoice->delete();
            $this->handleDeletedPurchaseOrder($purchaseOrderId);
        });
    }

    protected function normalizePayload(array $data): array
    {
        if (array_key_exists('difference_reason', $data)) {
            if (!empty($data['difference_reason'])) {
                $data['reason'] = $data['difference_reason'];
            }
            unset($data['difference_reason']);
        }

        if (array_key_exists('difference_evidence_path', $data)) {
            $data['evidence_path'] = $this->prepareEvidencePath($data['difference_evidence_path']);
            unset($data['difference_evidence_path']);
        } elseif (array_key_exists('evidence_path', $data)) {
            $data['evidence_path'] = $this->prepareEvidencePath($data['evidence_path']);
        }

        $data['withholdings'] = $data['withholdings'] ?? [];
        $data['items'] = $data['items'] ?? [];

        return $data;
    }

    protected function syncInvoiceItems(PurchaseInvoice $invoice, array $items, bool $isUpdate = false): void
    {
        if (!$isUpdate) {
            foreach ($items as $item) {
                $invoice->items()->create($item);
            }
            return;
        }

        $existingItems = $invoice->items()->get()->keyBy('id');
        $incomingItems = collect($items);

        foreach ($incomingItems as $itemData) {
            if (!empty($itemData['id']) && $existingItems->has($itemData['id'])) {
                $existingItems[$itemData['id']]->update($itemData);
            } else {
                $invoice->items()->create($itemData);
            }
        }

        $incomingIds = $incomingItems->pluck('id')->filter()->all();
        foreach ($existingItems as $item) {
            if (!in_array($item->id, $incomingIds) && $item->fabricRolls()->count() == 0) {
                $item->delete();
            }
        }
    }

    protected function updatePurchaseOrderStatus(?int $purchaseOrderId): void
    {
        if (empty($purchaseOrderId)) {
            return;
        }

        $order = PurchaseOrder::find($purchaseOrderId);
        if ($order) {
            $order->status = 'invoiced';
            $order->save();
            $this->updateUnifiedOrderStatus($purchaseOrderId, 'approved');
        }
    }

    protected function handleDeletedPurchaseOrder(?int $purchaseOrderId): void
    {
        if (empty($purchaseOrderId)) {
            return;
        }

        $order = PurchaseOrder::find($purchaseOrderId);
        if (!$order) {
            return;
        }

        $hasActiveInvoices = PurchaseInvoice::withTrashed()
            ->where('purchase_order_id', $purchaseOrderId)
            ->whereNull('deleted_at')
            ->exists();

        if (!$hasActiveInvoices) {
            $order->status = 'rejected';
            $order->save();
            $this->updateUnifiedOrderStatus($purchaseOrderId, 'rejected');
        }
    }

    protected function updateUnifiedOrderStatus(?int $purchaseOrderId, string $status): void
    {
        if (empty($purchaseOrderId)) {
            return;
        }

        $order = PurchaseDocument::find($purchaseOrderId);
        if (!$order || !$order->isOrder()) {
            return;
        }

        $order->update(['status' => $status]);
    }

    protected function prepareEvidencePath(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim($value);
        if ($normalized === '') {
            return null;
        }

        if (str_starts_with($normalized, 'data:image/')) {
            return $this->storeEvidenceFromBase64($normalized);
        }

        return $normalized;
    }

    protected function storeEvidenceFromBase64(string $value): string
    {
        if (!preg_match('/^data:(image\/[a-zA-Z0-9.+-]+);base64,/', $value, $matches)) {
            throw ValidationException::withMessages([
                'difference_evidence_path' => 'La evidencia proporcionada no tiene un formato válido.',
            ]);
        }

        $payload = substr($value, strpos($value, ',') + 1);
        $decoded = base64_decode($payload);
        if ($decoded === false) {
            throw ValidationException::withMessages([
                'difference_evidence_path' => 'No se pudo decodificar la evidencia.',
            ]);
        }

        $extensionMap = [
            'image/jpeg' => 'jpg',
            'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ];

        $extension = $extensionMap[$matches[1]] ?? 'png';
        $path = sprintf('purchase-invoices/evidence/%s/%s.%s', now()->format('Y/m/d'), Str::uuid(), $extension);

        Storage::disk('public')->put($path, $decoded);
        return $path;
    }
}
