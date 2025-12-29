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
    protected array $defaultFabricSubgroupNames = ['tela'];

    public function paginateDocuments(string $documentType, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = $this->buildDocumentQuery($documentType);
        $query = $this->applyDocumentFilters($query, $filters);

        return $query->orderByDesc('date')->orderByDesc('id')->paginate($perPage);
    }

    public function buildDocumentQuery(string $documentType): Builder
    {
        return PurchaseDocument::with([
            'items.item',
            'supplier:id,full_name,email',
            'creator:id,name',
            'executor:id,name',
            'rejectedBy:id,name',
            'parent:id,document_number',
            'children:id,parent_document_id',
        ])
        ->where('document_type', $documentType);
    }

    public function applyDocumentFilters(Builder $query, array $filters): Builder
    {
        $search = trim($filters['search'] ?? '');

        $query = $query
            ->when($search !== '', fn (Builder $builder) =>
                $builder->where(function (Builder $sub) use ($search) {
                    $sub->where('document_number', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhereHas('supplier', fn (Builder $supplier) =>
                            $supplier->where('full_name', 'like', "%{$search}%")
                        );
                })
            )
            ->when($this->shouldApplyFilter($filters['status'] ?? null), fn (Builder $builder) =>
                $builder->where('status', $filters['status'])
            )
            ->when($this->shouldApplyFilter($filters['approval_status'] ?? null), fn (Builder $builder) =>
                $builder->where('approval_status', $filters['approval_status'])
            )
            ->when($this->shouldApplyFilter($filters['provider_id'] ?? null), fn (Builder $builder) =>
                $builder->where('contact_id', $filters['provider_id'])
            )
            ->when($this->shouldApplyFilter($filters['subgroup_id'] ?? null), fn (Builder $builder) =>
                $builder->whereHas('items.item', fn (Builder $itemQuery) =>
                    $itemQuery->where('category_sub_group_id', $filters['subgroup_id'])
                )
            )
            ->when(!empty($filters['date_from']), fn (Builder $builder) =>
                $builder->whereDate('date', '>=', $filters['date_from'])
            )
            ->when(!empty($filters['date_to']), fn (Builder $builder) =>
                $builder->whereDate('date', '<=', $filters['date_to'])
            );

        return $this->applyFabricFilter($query, $filters);
    }

    protected function shouldApplyFilter(mixed $value): bool
    {
        if ($value === null) {
            return false;
        }

        if (is_string($value) && $value === 'all') {
            return false;
        }

        return $value !== '';
    }

    protected function applyFabricFilter(Builder $query, array $filters): Builder
    {
        $allowed = $this->normalizeFabricSubgroupNames($filters['fabric_subgroup_names'] ?? $this->defaultFabricSubgroupNames);
        if (empty($allowed)) {
            return $query;
        }

        if (!empty($filters['fabric_only'])) {
            return $query
                ->whereHas('items.item.categorySubGroup', fn (Builder $builder) =>
                    $builder->whereIn(DB::raw('LOWER(name)'), $allowed)
                )
                ->whereDoesntHave('items.item.categorySubGroup', fn (Builder $builder) =>
                    $builder->whereNotIn(DB::raw('LOWER(name)'), $allowed)
                );
        }

        if (!empty($filters['exclude_fabric'])) {
            return $query->whereDoesntHave('items.item.categorySubGroup', fn (Builder $builder) =>
                $builder->whereIn(DB::raw('LOWER(name)'), $allowed)
            );
        }

        return $query;
    }

    protected function normalizeFabricSubgroupNames(array|string $names): array
    {
        $values = is_array($names) ? $names : (is_string($names) ? [$names] : []);
        $normalized = array_map(fn ($name) => Str::lower(trim((string) $name)), $values);
        return array_values(array_unique(array_filter($normalized)));
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
        $this->ensureDocumentCanBeDeleted($document);

        DB::transaction(function () use ($document) {
            $document->items()->delete();
            $document->delete();
            if ($document->isInvoice() && $document->parent_document_id) {
                $parentOrder = PurchaseDocument::find($document->parent_document_id);
                if ($parentOrder && $parentOrder->isOrder()) {
                    $parentOrder->update([
                        'status' => 'pending',
                        'invoiced_at' => null,
                    ]);
                }
            }
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

    protected function ensureDocumentCanBeDeleted(PurchaseDocument $document): void
    {
        if (!$document->isOrder()) {
            return;
        }

        $hasInvoices = $document->children()
            ->where('document_type', PurchaseDocument::TYPE_INVOICE)
            ->exists();

        if ($hasInvoices) {
            throw ValidationException::withMessages([
                'document' => 'No se puede eliminar una orden de compra que ya tiene facturas vinculadas.',
            ]);
        }
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
