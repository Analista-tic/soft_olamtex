<?php

namespace App\Services\Expenses;

use App\Helpers\ScopeHelper;
use App\Models\Expenses\PurchaseDocument;
use App\Models\Expenses\PurchaseDocumentItem;
use App\Models\Settings\User;
use App\Notifications\PurchaseInvoiceDifferenceNotification;
use Illuminate\Auth\Access\AuthorizationException;
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

    public function paginateDocuments(string $documentType, array $filters = [], int $perPage = 20, $request = null, ?User $user = null): LengthAwarePaginator
    {
        $query = $this->buildDocumentQuery($documentType);
        $query = $this->applyDocumentFilters($query, $filters);
        $this->applyScopeForDocumentType($query, $documentType, $user);

        // Soporte de ordenamiento dinámico solo si $request está presente
        if ($request) {
            $allowedSorts = ['date', 'delivery_date', 'status', 'total'];
            $sortBy  = in_array($request->get('sort_by'), $allowedSorts)
                ? $request->get('sort_by')
                : 'date';
            $sortDir = $request->get('sort_dir') === 'asc' ? 'asc' : 'desc';

            // Soporte especial para sort por total (para órdenes, solicitudes y facturas)
            $typesWithTotalSort = [
                \App\Models\Expenses\PurchaseDocument::TYPE_ORDER,
                \App\Models\Expenses\PurchaseDocument::TYPE_REQUEST,
                \App\Models\Expenses\PurchaseDocument::TYPE_INVOICE,
                'purchase_invoice_fabric', // Si existe un tipo específico para facturas de tela
            ];
            if ($sortBy === 'total' && in_array($documentType, $typesWithTotalSort, true)) {
                $query = $query->addSelect(['total_sum' => function($q) {
                    $q->selectRaw('COALESCE(SUM(total + COALESCE(total_tax,0)),0)')
                        ->from('purchase_document_items')
                        ->whereColumn('purchase_document_items.purchase_document_id', 'purchase_documents.id');
                }]);
                return $query->orderBy('total_sum', $sortDir)->orderByDesc('id')->paginate($perPage);
            }

            return $query->orderBy($sortBy, $sortDir)->orderByDesc('id')->paginate($perPage);
        }
        return $query->orderByDesc('date')->orderByDesc('id')->paginate($perPage);
    }

    private function applyScopeForDocumentType(Builder $query, string $documentType, ?User $user): void
    {
        $typeMap = [
            PurchaseDocument::TYPE_ORDER   => ['ver propios ordenes compra',   'ver area ordenes compra',   'ver todos ordenes compra'],
            PurchaseDocument::TYPE_INVOICE => ['ver propios facturas compra',  'ver area facturas compra',  'ver todos facturas compra'],
        ];

        $permissions = $typeMap[$documentType] ?? null;
        if ($permissions) {
            ScopeHelper::apply($query, $user, $permissions[0], $permissions[1], $permissions[2]);
        }
    }

    public function findAccessibleDocumentOrFail(PurchaseDocument $document, ?User $user): PurchaseDocument
    {
        $query = PurchaseDocument::query()
            ->whereKey($document->getKey())
            ->where('document_type', $document->document_type);

        $this->applyScopeForDocumentType($query, $document->document_type, $user);

        if (!$query->exists()) {
            throw new AuthorizationException('No tienes permisos para acceder a este documento.');
        }

        return $this->loadWithRelations($document);
    }

    public function buildDocumentQuery(string $documentType): Builder
    {
        return PurchaseDocument::with([
            'items.item.categorySubGroup:id,name',
            'supplier:id,full_name,id_number,phone,email',
            'creator:id,name',
            'executor:id,name',
            'rejectedBy:id,name',
            'parent:id,document_number',
            'children:id,parent_document_id,document_type',
        ])
        ->where('document_type', $documentType);
    }

    public function applyDocumentFilters(Builder $query, array $filters): Builder
    {
        $search = trim($filters['search'] ?? '');
        $contactIdFilter = isset($filters['contact_id']) ? (int) $filters['contact_id'] : null;
        if (!($contactIdFilter > 0)) {
            $providerId = $filters['provider_id'] ?? null;
            $contactIdFilter = is_numeric($providerId) ? (int) $providerId : null;
            if (!($contactIdFilter > 0)) {
                $contactIdFilter = null;
            }
        }

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
            ->when($this->shouldApplyFilter($filters['status'] ?? null), function (Builder $builder) use ($filters) {
                if ($filters['status'] === 'processed') {
                    $builder->where('status', 'approved')->whereNotNull('purchase_request_id');
                } else {
                    $builder->where('status', $filters['status']);
                }
            })
            ->when($this->shouldApplyFilter($filters['approval_status'] ?? null), fn (Builder $builder) =>
                $builder->where('approval_status', $filters['approval_status'])
            )
            ->when(!empty($filters['exclude_difference_rejected']), fn (Builder $builder) =>
                $builder->where(function (Builder $sub) {
                    $sub->whereNull('approval_status')
                        ->orWhere('approval_status', '!=', PurchaseDocument::APPROVAL_DIFFERENCE_REJECTED);
                })
            )
            ->when($contactIdFilter !== null, fn (Builder $builder) =>
                $builder->where('contact_id', $contactIdFilter)
            )
            ->when($this->shouldApplyFilter($filters['subgroup_id'] ?? null), fn (Builder $builder) =>
                $builder->whereHas('items.item', fn (Builder $itemQuery) =>
                    $itemQuery->where('category_sub_group_id', $filters['subgroup_id'])
                )
            )
            ->when(
                !empty($filters['date_from']) && !empty($filters['date_to']) && $filters['date_from'] === $filters['date_to'],
                fn (Builder $builder) =>
                    $builder->where(function (Builder $sub) use ($filters) {
                        $sub->whereDate('date', $filters['date_from'])
                            ->orWhereDate('delivery_date', $filters['date_from']);
                    })
            )
            ->when(
                !empty($filters['date_from']) && (empty($filters['date_to']) || $filters['date_from'] !== $filters['date_to']),
                fn (Builder $builder) =>
                    $builder->whereDate('date', '>=', $filters['date_from'])
            )
            ->when(
                !empty($filters['date_to']) && (empty($filters['date_from']) || $filters['date_from'] !== $filters['date_to']),
                fn (Builder $builder) =>
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
                ->whereDoesntHave('items', fn (Builder $builder) =>
                    $builder->whereHas('item.categorySubGroup', fn (Builder $subBuilder) =>
                        $subBuilder->whereNotIn(DB::raw('LOWER(name)'), $allowed)
                    )->orWhereDoesntHave('item.categorySubGroup')
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

            // Si es una orden de compra, restaurar la solicitud si no tiene más órdenes activas
            if ($document->isOrder() && $document->purchase_request_id) {
                $purchaseRequest = \App\Models\Expenses\PurchaseRequest::find($document->purchase_request_id);
                if ($purchaseRequest) {
                    $activeOrders = $purchaseRequest->orders()->where('document_type', PurchaseDocument::TYPE_ORDER)->whereNull('deleted_at')->count();
                    if ($activeOrders === 0) {
                        $purchaseRequest->update([
                            'status' => \App\Enums\PurchaseRequestStatus::APPROVED->value,
                            'processed_at' => null,
                            'processed_by_user_id' => null,
                        ]);
                    }
                }
            }

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
        $document->load([
            'items.item.categorySubGroup:id,name',
            'supplier',
            'creator',
            'executor',
            'rejectedBy',
            'parent',
            'children',
        ]);
        // Exponer también como "contact" para compatibilidad con el frontend (Reception, etc.)
        if ($document->relationLoaded('supplier') && ! $document->relationLoaded('contact')) {
            $document->setRelation('contact', $document->getRelation('supplier'));
        }
        return $document;
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
            'approval_status'         => PurchaseDocument::APPROVAL_PENDING_DIFFERENCE,
            'has_difference'          => true,
            'rejected_by_user_id'     => Auth::id(),
            'rejected_at'             => now(),
        ]);

        /** @var \App\Models\Settings\User|null $currentUser */
        $currentUser = Auth::user();
        $this->notifyDifference($invoice, $currentUser);

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

    public function generateFabricInvoiceNumber(): string
    {
        return $this->generateDocumentNumber('FAC-T', PurchaseDocument::TYPE_INVOICE);
    }

    protected function generateDocumentNumber(string $prefix, string $documentType): string
    {
        $nextId = $this->nextDocumentSequence($documentType, $prefix);
        return sprintf('%s-%04d', $prefix, $nextId);
    }

    protected function nextDocumentSequence(string $documentType, string $prefix): int
    {
        $last = PurchaseDocument::withTrashed()
            ->where('document_type', $documentType)
            ->where('document_number', 'like', $prefix . '-%')
            ->orderByDesc('id')
            ->value('document_number');

        if (!$last) {
            return 1;
        }

        $parts = explode('-', $last);
        $number = (int) end($parts);
        return $number + 1;
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
            'approval_notes',
        ];
    }

    /**
     * Notificar a usuarios con permiso 'aprobar facturas compra' sobre una diferencia registrada.
     */
    protected function notifyDifference(PurchaseDocument $invoice, ?User $requester): void
    {
        $requesterName = $requester?->name;
        $notification = new PurchaseInvoiceDifferenceNotification($invoice, $requesterName);

        $approvers = User::permission('aprobar facturas compra')->get();
        foreach ($approvers as $approver) {
            // No notificar al mismo usuario que registró la diferencia
            if ($approver->id !== $requester?->id) {
                $approver->notify($notification);
            }
        }
    }

    /**
     * Aprobar la diferencia de una factura.
     */
    public function approveDifference(
        PurchaseDocument $invoice,
        ?string $notes,
        int $approverId
    ): PurchaseDocument {
        if (!$invoice->isInvoice()) {
            throw new \InvalidArgumentException(
                'Solo las facturas pueden ser aprobadas.'
            );
        }

        if ($invoice->approval_status !== PurchaseDocument::APPROVAL_PENDING_DIFFERENCE) {
            throw ValidationException::withMessages([
                'approval_status' => 'La factura no está pendiente de aprobación de diferencia.',
            ]);
        }

        $invoice->update([
            'approval_status'     => PurchaseDocument::APPROVAL_APPROVED,
            'difference_status'   => 'approved',
            'approval_notes'      => $notes,
            'approved_by_user_id' => $approverId,
            'approved_at'         => now(),
        ]);

        return $this->loadWithRelations($invoice);
    }

    /**
     * Rechazar la diferencia de una factura (requiere notas).
     */
    public function rejectDifference(
        PurchaseDocument $invoice,
        string $notes,
        int $rejectorId
    ): PurchaseDocument {
        if (!$invoice->isInvoice()) {
            throw new \InvalidArgumentException(
                'Solo las facturas pueden ser rechazadas.'
            );
        }

        if ($invoice->approval_status !== PurchaseDocument::APPROVAL_PENDING_DIFFERENCE) {
            throw ValidationException::withMessages([
                'approval_status' => 'La factura no está pendiente de aprobación de diferencia.',
            ]);
        }

        $invoice->update([
            'approval_status'     => PurchaseDocument::APPROVAL_DIFFERENCE_REJECTED,
            'difference_status'   => 'rejected',
            'approval_notes'      => $notes,
            'approved_by_user_id' => $rejectorId,
            'approved_at'         => now(),
        ]);

        // Notificar al creador de la factura para que gestione nota crédito
        $this->notifyDifferenceRejected($invoice);

        return $this->loadWithRelations($invoice);
    }

    protected function notifyDifferenceRejected(PurchaseDocument $invoice): void
    {
        $creator = $invoice->creator;
        if (!$creator || !$creator->email) {
            return;
        }

        try {
            \Illuminate\Support\Facades\Mail::send(
                [],
                [],
                function ($message) use ($invoice, $creator) {
                    $message->to($creator->email, $creator->name)
                        ->subject('Diferencia rechazada — ' . $invoice->document_number)
                        ->html(
                            '<p>Estimado/a <strong>' . e($creator->name) . '</strong>,</p>' .
                            '<p>La diferencia registrada en la factura <strong>' .
                            e($invoice->document_number) . '</strong> ha sido <strong>rechazada</strong>.</p>' .
                            '<p><strong>Motivo de rechazo:</strong> ' . e($invoice->approval_notes) . '</p>' .
                            '<p>Por favor gestiona la nota crédito correspondiente.</p>'
                        );
                }
            );
        } catch (\Throwable $e) {
            // No interrumpir el flujo si el correo falla
            \Illuminate\Support\Facades\Log::warning(
                'No se pudo enviar email de rechazo de diferencia: ' . $e->getMessage(),
                ['invoice_id' => $invoice->id]
            );
        }
    }
}
