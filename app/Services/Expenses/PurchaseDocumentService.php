<?php

namespace App\Services\Expenses;

use App\Models\Expenses\PurchaseDocument;
use App\Models\Expenses\PurchaseDocumentItem;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PurchaseDocumentService
{
    public function paginateDocuments(string $documentType, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = $this->buildDocumentQuery($documentType);
        $this->applyDocumentFilters($query, $filters);

        return $query->orderByDesc('date')->orderByDesc('id')->paginate($perPage);
    }

    public function buildDocumentQuery(string $documentType): Builder
    {
        return PurchaseDocument::with([
            'items.item',
            'supplier',
            'creator',
            'executor',
            'rejectedBy',
            'parent',
            'children',
        ])->where('document_type', $documentType);
    }

    public function applyDocumentFilters(Builder $query, array $filters): void
    {
        if (!empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->where(function (Builder $sub) use ($search) {
                $sub->where('document_number', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhereHas('supplier', fn ($supplier) =>
                        $supplier->where('full_name', 'like', "%{$search}%")
                    );
            });
        }

        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['approval_status']) && $filters['approval_status'] !== 'all') {
            $query->where('approval_status', $filters['approval_status']);
        }

        if (!empty($filters['provider_id']) && $filters['provider_id'] !== 'all') {
            $query->where('contact_id', $filters['provider_id']);
        }

        if (!empty($filters['date_from'])) {
            $query->whereDate('date', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('date', '<=', $filters['date_to']);
        }
    }

    /**
     * Crear una SOLICITUD de compra (purchase_request)
     */
    public function createRequest(array $data, array $items = []): PurchaseDocument
    {
        return DB::transaction(function () use ($data, $items) {
            if (empty($data['document_number'])) {
                $data['document_number'] = $this->generateRequestNumber();
            }

            $base = $this->buildBaseData($data, PurchaseDocument::TYPE_REQUEST);

            $document = PurchaseDocument::create([
                ...$base,
                'status'          => $data['status'] ?? 'pending',
                'approval_status' => $data['approval_status'] ?? null,
                'requested_at'    => $data['requested_at'] ?? now(),
            ]);

            $this->syncItems($document, $items);

            return $this->loadWithRelations($document);
        });
    }

    public function createOrder(array $data, array $items = []): PurchaseDocument
    {
        return DB::transaction(function () use ($data, $items) {
            if (empty($data['document_number'])) {
                $data['document_number'] = $this->generateOrderNumber();
            }
            $base = $this->buildBaseData($data, PurchaseDocument::TYPE_ORDER);

            $order = PurchaseDocument::create([
                ...$base,
                'parent_document_id'    => $data['parent_document_id'] ?? null,
                'status'                => $data['status'] ?? 'pending',
                'ordered_at'            => $data['ordered_at'] ?? now(),
                'supplier_order_number' => $data['supplier_order_number'] ?? null,
            ]);

            $this->syncItems($order, $items);

            return $this->loadWithRelations($order);
        });
    }

    public function createInvoice(array $data, array $items = [], ?array $withholdings = null): PurchaseDocument
    {
        $data = $this->normalizeInvoicePayload($data);

        return DB::transaction(function () use ($data, $items, $withholdings) {
            if (empty($data['document_number'])) {
                $data['document_number'] = $this->generateInvoiceNumber();
            }
            $base = $this->buildBaseData($data, PurchaseDocument::TYPE_INVOICE);

            $parentId = $data['parent_document_id'] ?? $data['purchase_order_id'] ?? null;

            $invoice = PurchaseDocument::create([
                ...$base,
                'parent_document_id'        => $parentId,
                'status'                    => $data['status'] ?? 'pending',
                'approval_status'           => $data['approval_status'] ?? 'pending',
                'supplier_invoice_number'   => $data['supplier_invoice_number'] ?? null,
                'withholdings'              => $withholdings ?? $data['withholdings'] ?? [],
                'invoiced_at'               => $data['invoiced_at'] ?? now(),
                'rejection_reason'          => $data['rejection_reason'] ?? null,
                'rejection_evidence_image'  => $data['rejection_evidence_image'] ?? null,
                'rejection_notes'           => $data['rejection_notes'] ?? null,
                'rejected_by_user_id'       => $data['rejected_by_user_id'] ?? null,
                'rejected_at'               => $data['rejected_at'] ?? null,
            ]);

            $this->syncItems($invoice, $items);

            $this->updateParentOrderStatus(
                $parentId,
                $data['order_status'] ?? 'approved',
                $data['linked_invoiced_at'] ?? $invoice->invoiced_at
            );

            return $this->loadWithRelations($invoice);
        });
    }

    public function convertRequestToOrder(
        PurchaseDocument $request,
        array $orderData = [],
        ?array $orderItems = null
    ): PurchaseDocument {
        return DB::transaction(function () use ($request, $orderData, $orderItems) {
            $request->update([
                'status'      => $orderData['request_status'] ?? 'approved',
                'approved_at' => $orderData['approved_at'] ?? now(),
            ]);

            if (empty($orderData['document_number'])) {
                $orderData['document_number'] = $this->generateOrderNumber();
            }
            $base = $this->buildBaseData($orderData, PurchaseDocument::TYPE_ORDER);

            $order = PurchaseDocument::create([
                ...$base,
                'parent_document_id'    => $request->id,
                'status'                => $orderData['status'] ?? 'pending',
                'ordered_at'            => $orderData['ordered_at'] ?? now(),
                'supplier_order_number' => $orderData['supplier_order_number'] ?? null,
            ]);

            $items = $orderItems ?? $request->items
                ->map(fn (PurchaseDocumentItem $item) => [
                    'item_id'        => $item->item_id,
                    'quantity'       => $item->quantity,
                    'price'          => $item->price,
                    'tax'            => $item->tax,
                    'total'          => $item->total,
                    'total_tax'      => $item->total_tax,
                    'supplier_code'  => $item->supplier_code,
                    'supplier_color' => $item->supplier_color,
                    'order_width'    => $item->order_width,
                    'description'    => $item->description,
                ])
                ->toArray();

            $this->syncItems($order, $items);

            return $this->loadWithRelations($order);
        });
    }

    public function convertOrderToInvoice(
        PurchaseDocument $order,
        array $invoiceData = [],
        ?array $invoiceItems = null,
        ?array $withholdings = null
    ): PurchaseDocument {
        return DB::transaction(function () use ($order, $invoiceData, $invoiceItems, $withholdings) {
            $data = $this->normalizeInvoicePayload($invoiceData);

            $order->update([
                'status'      => $data['order_status'] ?? 'sent',
                'invoiced_at' => $data['linked_invoiced_at'] ?? now(),
            ]);

            if (empty($data['document_number'])) {
                $data['document_number'] = $this->generateInvoiceNumber();
            }
            $base = $this->buildBaseData($data, PurchaseDocument::TYPE_INVOICE);

            $invoice = PurchaseDocument::create([
                ...$base,
                'parent_document_id'        => $order->id,
                'status'                    => $data['status'] ?? 'pending',
                'approval_status'           => $data['approval_status'] ?? 'pending',
                'invoiced_at'               => $data['invoiced_at'] ?? now(),
                'supplier_invoice_number'   => $data['supplier_invoice_number'] ?? null,
                'withholdings'              => $withholdings ?? $data['withholdings'] ?? [],
                'rejection_reason'          => $data['rejection_reason'] ?? null,
                'rejection_evidence_image'  => $data['rejection_evidence_image'] ?? null,
                'rejection_notes'           => $data['rejection_notes'] ?? null,
                'rejected_by_user_id'       => $data['rejected_by_user_id'] ?? null,
                'rejected_at'               => $data['rejected_at'] ?? null,
            ]);

            $items = $invoiceItems ?? $order->items
                ->map(fn (PurchaseDocumentItem $item) => [
                    'item_id'        => $item->item_id,
                    'quantity'       => $item->quantity,
                    'price'          => $item->price,
                    'tax'            => $item->tax,
                    'total'          => $item->total,
                    'total_tax'      => $item->total_tax,
                    'supplier_code'  => $item->supplier_code,
                    'supplier_color' => $item->supplier_color,
                    'order_width'    => $item->order_width,
                    'description'    => $item->description,
                ])
                ->toArray();

            $this->syncItems($invoice, $items);

            $this->updateParentOrderStatus(
                $order->id,
                $data['order_status'] ?? 'approved',
                $data['linked_invoiced_at'] ?? $invoice->invoiced_at
            );

            return $this->loadWithRelations($invoice);
        });
    }

    public function updateDocument(
        PurchaseDocument $document,
        array $data,
        ?array $items = null
    ): PurchaseDocument {
        return DB::transaction(function () use ($document, $data, $items) {
            $fillable = array_intersect_key($data, array_flip($this->updateFillableKeys()));

            if (!empty($fillable)) {
                $document->fill($fillable);
            }

            if (array_key_exists('status', $data)) {
                $document->status = $data['status'];
            }

            if (array_key_exists('approval_status', $data)) {
                $document->approval_status = $data['approval_status'];
            }

            $document->save();

            if ($items !== null) {
                $this->syncItems($document, $items);
            }

            return $this->loadWithRelations($document);
        });
    }

    public function deleteDocument(PurchaseDocument $document): void
    {
        DB::transaction(function () use ($document) {
            $document->items()->delete();
            $document->delete();
        });
    }

    public function loadWithRelations(PurchaseDocument $document): PurchaseDocument
    {
        return $document->load([
            'items.item',
            'supplier',
            'creator',
            'executor',
            'rejectedBy',
            'parent',
            'children',
        ]);
    }

    public function attachValidationDifference(
        PurchaseDocument $invoice,
        string $reason,
        ?string $evidencePath,
        ?string $notes = null
    ): PurchaseDocument {
        if (! $invoice->isInvoice()) {
            throw new \InvalidArgumentException('Solo las facturas pueden tener justificación de diferencias.');
        }

        $invoice->update([
            'rejection_reason'        => $reason,
            'rejection_evidence_image'=> $this->prepareEvidencePath($evidencePath),
            'rejection_notes'         => $notes,
            'approval_status'         => $invoice->approval_status ?? 'pending',
            'rejected_by_user_id'     => Auth::id(),
            'rejected_at'             => now(),
        ]);

        return $this->loadWithRelations($invoice);
    }

    protected function buildBaseData(array $data, string $documentType): array
    {
        return [
            'document_type'       => $documentType,
            'document_number'     => $data['document_number'] ?? null,
            'contact_id'          => $data['contact_id'] ?? null,
            'provider_name'       => $data['provider_name'] ?? null,
            'provider_phone'      => $data['provider_phone'] ?? null,
            'date'                => $data['date'] ?? now()->toDateString(),
            'delivery_date'       => $data['delivery_date'] ?? null,
            'description'         => $data['description'] ?? null,
            'created_by_user_id'  => $data['created_by_user_id'] ?? Auth::id(),
            'executed_by_user_id' => $data['executed_by_user_id'] ?? null,
        ];
    }

    protected function syncItems(PurchaseDocument $document, array $items): void
    {
        $document->items()->delete();

        $payload = [];

        foreach ($items as $item) {
            $rawItemId = $item['item_id'] ?? null;
            if (is_string($rawItemId)) {
                $rawItemId = trim($rawItemId);
            }
            $itemName = isset($item['item_name']) ? trim((string) $item['item_name']) : null;

            if ($rawItemId !== null && $rawItemId !== '' && !is_numeric($rawItemId)) {
                // treat non-numeric values as manual names instead of forcing them into the ID column
                $itemName = $itemName ?: (string) $rawItemId;
                $rawItemId = null;
            }

            $resolvedItemId = is_numeric($rawItemId) ? (int) $rawItemId : null;
            $hasReference = $resolvedItemId !== null || ($itemName !== null && $itemName !== '');

            if (!$hasReference || empty($item['quantity'])) {
                continue;
            }

            $payload[] = [
                'purchase_document_id' => $document->id,
                'item_id'              => $resolvedItemId,
                'item_name'            => $itemName,
                'quantity'             => $item['quantity'],
                'price'                => $item['price']      ?? 0,
                'tax'                  => $item['tax']        ?? 0,
                'total'                => $item['total']      ?? 0,
                'total_tax'            => $item['total_tax']  ?? 0,
                'supplier_code'        => $item['supplier_code']  ?? null,
                'supplier_color'       => $item['supplier_color'] ?? null,
                'order_width'          => $item['order_width']    ?? null,
                'description'          => $item['description']    ?? null,
                'created_at'           => now(),
                'updated_at'           => now(),
            ];
        }

        if (!empty($payload)) {
            // Use Eloquent create to ensure model events, casts and timestamps are applied
            foreach ($payload as $row) {
                $rowToCreate = $row;
                unset($rowToCreate['purchase_document_id']);
                $document->items()->create($rowToCreate);
            }
        }
    }

    protected function generateRequestNumber(): string
    {
        return $this->generateDocumentNumber('SOL', PurchaseDocument::TYPE_REQUEST);
    }

    protected function generateOrderNumber(): string
    {
        return $this->generateDocumentNumber('ORD', PurchaseDocument::TYPE_ORDER);
    }

    protected function generateInvoiceNumber(): string
    {
        return $this->generateDocumentNumber('FAC', PurchaseDocument::TYPE_INVOICE);
    }

    protected function generateDocumentNumber(string $prefix, string $documentType): string
    {
        $nextId = $this->nextDocumentSequence($documentType);
        return sprintf('%s-%03d', $prefix, $nextId);
    }

    protected function nextDocumentSequence(string $documentType): int
    {
        return (int) PurchaseDocument::withTrashed()
            ->where('document_type', $documentType)
            ->max('id') + 1;
    }

    protected function normalizeInvoicePayload(array $data): array
    {
        if (array_key_exists('difference_reason', $data)) {
            $data['rejection_reason'] = $data['difference_reason'] ?: null;
            unset($data['difference_reason']);
        }

        $evidence = null;
        if (array_key_exists('difference_evidence_path', $data)) {
            $evidence = $data['difference_evidence_path'];
            unset($data['difference_evidence_path']);
        }

        if (array_key_exists('evidence_path', $data)) {
            $evidence = $data['evidence_path'];
            unset($data['evidence_path']);
        }

        if ($evidence !== null) {
            $data['rejection_evidence_image'] = $this->prepareEvidencePath($evidence);
        }

        $data['withholdings'] = $data['withholdings'] ?? [];
        $data['items'] = $data['items'] ?? [];

        return $data;
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

    protected function updateParentOrderStatus(?int $parentId, string $status = 'pending', ?string $invoicedAt = null): void
    {
        if (empty($parentId)) {
            return;
        }

        $order = PurchaseDocument::find($parentId);
        if (!$order || !$order->isOrder()) {
            return;
        }

        $order->update([
            'status'      => $status,
            'invoiced_at' => $invoicedAt ?? now(),
        ]);
    }

    protected function updateFillableKeys(): array
    {
        return [
            'document_number',
            'provider_name',
            'provider_phone',
            'contact_id',
            'date',
            'delivery_date',
            'description',
            'executed_by_user_id',
            'status',
            'approval_status',
            'supplier_order_number',
            'supplier_invoice_number',
            'withholdings',
            'rejection_reason',
            'rejection_evidence_image',
            'rejection_notes',
            'rejected_by_user_id',
            'rejected_at',
            'requested_at',
            'ordered_at',
            'invoiced_at',
            'approved_at',
        ];
    }
}
