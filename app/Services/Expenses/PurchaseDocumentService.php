<?php

namespace App\Services\Expenses;

use App\Helpers\ScopeHelper;
use App\Models\Expenses\PurchaseDocument;
use App\Models\Settings\User;
use App\Notifications\PurchaseInvoiceDifferenceNotification;
use App\Support\DocumentSequence;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
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

    public function applyScopeForDocumentType(Builder $query, string $documentType, ?User $user): void
    {
        if ($documentType === PurchaseDocument::TYPE_ORDER) {
            if ($user === null) {
                $query->where('buyer_user_id', -1);
                return;
            }

            try {
                if ($user->hasPermissionTo('ver todos ordenes compra')) {
                    return;
                }
            } catch (\Spatie\Permission\Exceptions\PermissionDoesNotExist) {
                // no permission defined → restrict
            }

            $query->where('buyer_user_id', $user->id);
            return;
        }

        if ($documentType === PurchaseDocument::TYPE_INVOICE) {
            if ($user === null) {
                $query->where('buyer_user_id', -1);
                return;
            }

            try {
                if ($user->hasPermissionTo('ver todos facturas compra')) {
                    if ($user->hasRole('admin')) {
                        return; // admin ve todo
                    }

                    // contabilidad: ve todas excepto las de logística
                    $logisticaUserIds = User::role('logistica')->pluck('id')->toArray();
                    if (!empty($logisticaUserIds)) {
                        $query->where(function (Builder $q) use ($logisticaUserIds) {
                            $q->whereNull('buyer_user_id')
                              ->orWhereNotIn('buyer_user_id', $logisticaUserIds);
                        });
                    }
                    return;
                }
            } catch (\Spatie\Permission\Exceptions\PermissionDoesNotExist) {
                // no permission defined → restrict
            }

            $query->where('buyer_user_id', $user->id);
            return;
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
        $query = PurchaseDocument::with([
            'items.item.categorySubGroup:id,name',
            'supplier:id,full_name,id_number,phone,email',
            'creator:id,name',
            'executor:id,name',
            'rejectedBy:id,name',
            'approvedBy:id,name',
            'parent:id,document_number',
            'children:id,parent_document_id,document_type',
        ])
        ->where('document_type', $documentType);

        if ($documentType === PurchaseDocument::TYPE_ORDER) {
            $query->withCount(['children as invoices_count' => function ($q) {
                $q->where('document_type', PurchaseDocument::TYPE_INVOICE)
                  ->whereNull('deleted_at');
            }]);
        }

        return $query;
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
                if (str_contains($filters['status'], ',')) {
                    $builder->whereIn('status', array_map('trim', explode(',', $filters['status'])));
                } else {
                    $builder->where('status', $filters['status']);
                }
            })
            ->when($this->shouldApplyFilter($filters['approval_status'] ?? null), fn (Builder $builder) =>
                $builder->where('approval_status', $filters['approval_status'])
            )
            ->when($this->shouldApplyFilter($filters['invoice_status'] ?? null), fn (Builder $builder) =>
                $this->applyInvoiceStatusFilter($builder, (string) $filters['invoice_status'])
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

    /**
     * Filtra facturas por el estado único que muestra la lista (4 opciones, mutuamente excluyentes).
     * Precedencia igual a `resolvePurchaseInvoiceStatusKey` del frontend:
     * rechazada (recepción o diferencia) > requiere aprobación > recibida > por recibir.
     */
    protected function applyInvoiceStatusFilter(Builder $query, string $key): Builder
    {
        $received = ['received', 'approved'];
        $differenceStates = [
            PurchaseDocument::APPROVAL_DIFFERENCE_REJECTED,
            PurchaseDocument::APPROVAL_PENDING_DIFFERENCE,
        ];
        $notRejected = fn (Builder $q) => $q->where(fn (Builder $s) => $s->whereNull('status')->orWhere('status', '!=', 'rejected'));
        $noDifference = fn (Builder $q) => $q->where(fn (Builder $s) => $s->whereNull('approval_status')
            ->orWhereNotIn('approval_status', $differenceStates));

        return match ($key) {
            'rejected' => $query->where(fn (Builder $s) => $s->where('status', 'rejected')
                ->orWhere('approval_status', PurchaseDocument::APPROVAL_DIFFERENCE_REJECTED)),
            'pending_difference_approval' => $notRejected($query)->where('approval_status', PurchaseDocument::APPROVAL_PENDING_DIFFERENCE),
            'received' => $noDifference($query->whereIn('status', $received)),
            'pending_receipt' => $noDifference($query->where(fn (Builder $s) => $s->whereNull('status')
                ->orWhereNotIn('status', array_merge(['rejected'], $received)))),
            default => $query,
        };
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
        if ($this->orderHasInvoices($document)) {
            $this->ensureInvoicedOrderValuesUnchanged($document, $data, $items);
            $items = null;
        }

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

            if ($document->isInvoice() && ($items !== null || array_key_exists('withholdings', $data))) {
                $this->syncInvoiceWithholdings($document);
            }

            return $this->loadWithRelations($document);
        });
    }

    /**
     * Guarda cada retención con su tipo, base efectiva y valor calculado según la normativa colombiana
     * (ReteIVA sobre el IVA; ReteFuente/ICA sobre el subtotal). Así todo el sistema (comprobantes de egreso,
     * cuentas por pagar, contabilidad) lee el mismo valor en vez de recalcularlo.
     */
    protected function syncInvoiceWithholdings(PurchaseDocument $invoice): void
    {
        $withholdings = is_array($invoice->withholdings) ? $invoice->withholdings : [];
        if ($withholdings === []) {
            return;
        }

        $items = $invoice->items()->get();
        $subtotal = round((float) $items->sum(fn ($item) => (float) ($item->total ?? 0)), 2);
        $iva = round((float) $items->sum(fn ($item) => (float) ($item->total_tax ?? 0)), 2);

        $invoice->forceFill([
            'withholdings' => app(\App\Support\Finance\PurchaseWithholdingCalculator::class)->normalize($withholdings, $subtotal, $iva),
        ])->save();
    }

    /** Una orden con cualquier factura vinculada (sin importar su estado) ya no admite cambios de valor. */
    protected function orderHasInvoices(PurchaseDocument $document): bool
    {
        return $document->isOrder()
            && $document->children()->where('document_type', PurchaseDocument::TYPE_INVOICE)->exists();
    }

    /**
     * En una orden ya facturada solo se editan datos que no cambian el valor (fecha de entrega, notas,
     * número de orden del proveedor). Ítems, cantidades, precios, proveedor y retenciones quedan bloqueados
     * para todos; para corregir se usa una orden adicional, cerrar la orden o notas crédito/débito.
     */
    protected function ensureInvoicedOrderValuesUnchanged(PurchaseDocument $order, array $data, ?array $items): void
    {
        $changed = false;

        foreach (['contact_id', 'provider_name', 'provider_phone'] as $field) {
            if (array_key_exists($field, $data) && (string) ($data[$field] ?? '') !== (string) ($order->{$field} ?? '')) {
                $changed = true;
            }
        }

        if (array_key_exists('withholdings', $data)
            && json_encode($data['withholdings'] ?? null) !== json_encode($order->withholdings)
            && ! (empty($data['withholdings']) && empty($order->withholdings))) {
            $changed = true;
        }

        if ($items !== null && $this->normalizedItemSignature($items) !== $this->normalizedItemSignature($order->items->toArray())) {
            $changed = true;
        }

        if ($changed) {
            throw ValidationException::withMessages([
                'document' => 'Esta orden ya tiene facturas: no se pueden cambiar sus ítems, cantidades, precios ni proveedor. Para corregir, crea una orden adicional, cierra la orden o usa una nota crédito/débito.',
            ]);
        }
    }

    /** @param  list<array<string, mixed>>  $items */
    private function normalizedItemSignature(array $items): array
    {
        $rows = [];
        foreach ($items as $item) {
            if (empty($item['quantity'])) {
                continue;
            }
            $id = $item['item_id'] ?? null;
            $rows[] = implode('|', [
                is_numeric($id) ? 'id:'.(int) $id : 'name:'.strtolower(trim((string) ($item['item_name'] ?? $id ?? ''))),
                round((float) $item['quantity'], 4),
                round((float) ($item['price'] ?? 0), 4),
                round((float) ($item['tax'] ?? 0), 4),
            ]);
        }
        sort($rows);

        return $rows;
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
                    $this->recalculateOrderStatus($parentOrder);
                }
            }
        });
    }

    /**
     * Única fuente del estado de facturación de una orden. Cuenta solo facturas válidas (no
     * rechazadas ni con diferencia rechazada): aprobada si no hay ninguna; facturada cuando lo
     * facturado cubre lo pedido en cada ítem (facturar de más no lo impide); parcialmente facturada
     * en cualquier otro caso o mientras haya una diferencia pendiente. Un cierre manual no se reabre.
     * También cierra o reabre la solicitud según el resultado.
     */
    public function recalculateOrderStatus(PurchaseDocument $order): PurchaseDocument
    {
        if (! $order->isOrder()) {
            return $order;
        }

        $order->loadMissing('items');
        $current = $order->status instanceof \BackedEnum ? $order->status->value : $order->status;
        $recalculable = [
            \App\Enums\PurchaseOrderStatus::APPROVED->value,
            \App\Enums\PurchaseOrderStatus::PARTIALLY_INVOICED->value,
            \App\Enums\PurchaseOrderStatus::INVOICED->value,
        ];
        if (! in_array($current, $recalculable, true)) {
            return $order;
        }
        if ($current === \App\Enums\PurchaseOrderStatus::INVOICED->value && filled($order->closure_reason)) {
            return $order;
        }

        $invoices = $this->validInvoicesForOrder($order)->get(['id', 'approval_status']);

        if ($invoices->isEmpty()) {
            $new = \App\Enums\PurchaseOrderStatus::APPROVED->value;
        } else {
            $hasPendingDifference = $invoices->contains(fn ($i) => $i->approval_status === PurchaseDocument::APPROVAL_PENDING_DIFFERENCE);
            $new = (! $hasPendingDifference && $this->invoicesCoverOrder($order, $invoices->pluck('id')->all()))
                ? \App\Enums\PurchaseOrderStatus::INVOICED->value
                : \App\Enums\PurchaseOrderStatus::PARTIALLY_INVOICED->value;
        }

        if ($new !== $current) {
            $order->update([
                'status' => $new,
                'invoiced_at' => $new === \App\Enums\PurchaseOrderStatus::INVOICED->value ? ($order->invoiced_at ?? now()) : null,
            ]);
        }

        if ($order->purchase_request_id) {
            if ($new === \App\Enums\PurchaseOrderStatus::INVOICED->value) {
                $this->checkAndMarkRequestAsProcessed((int) $order->purchase_request_id);
            } else {
                $request = \App\Models\Expenses\PurchaseRequest::find($order->purchase_request_id);
                $requestStatus = $request?->status instanceof \BackedEnum ? $request->status->value : $request?->status;
                if ($request && $requestStatus === \App\Enums\PurchaseRequestStatus::PROCESSED->value) {
                    $request->update([
                        'status' => \App\Enums\PurchaseRequestStatus::APPROVED->value,
                        'processed_at' => null,
                        'processed_by_user_id' => null,
                    ]);
                }
            }
        }

        return $order;
    }

    /** Facturas de la orden que cuentan como facturado: no rechazadas ni con diferencia rechazada. */
    protected function validInvoicesForOrder(PurchaseDocument $order): \Illuminate\Database\Eloquent\Builder
    {
        return PurchaseDocument::where('parent_document_id', $order->id)
            ->where('document_type', PurchaseDocument::TYPE_INVOICE)
            ->whereNull('deleted_at')
            ->where(fn ($q) => $q->whereNull('status')->orWhere('status', '!=', 'rejected'))
            ->where(fn ($q) => $q->whereNull('approval_status')->orWhere('approval_status', '!=', PurchaseDocument::APPROVAL_DIFFERENCE_REJECTED));
    }

    /** Cantidad de cada ítem de la orden que ya cubren las facturas dadas. @return array<string,float> */
    protected function invoicedQuantitiesByKey(array $invoiceIds): array
    {
        if (empty($invoiceIds)) {
            return [];
        }

        return \App\Models\Expenses\PurchaseDocumentItem::whereIn('purchase_document_id', $invoiceIds)
            ->get()
            ->reduce(function (array $carry, $item) {
                $key = $item->item_id ? 'id:'.$item->item_id : 'name:'.strtolower(trim((string) ($item->item_name ?? '')));
                $carry[$key] = ($carry[$key] ?? 0) + (float) $item->quantity;

                return $carry;
            }, []);
    }

    /** Marca en cada ítem de la orden cuánto cubren ya sus facturas válidas (misma regla que el pendiente y el estado). */
    public function attachInvoicedQuantities(PurchaseDocument $order): PurchaseDocument
    {
        $order->loadMissing('items');
        $invoiced = $this->invoicedQuantitiesByKey($this->validInvoicesForOrder($order)->pluck('id')->all());

        $order->items->each(function ($item) use ($invoiced) {
            $key = $item->item_id ? 'id:'.$item->item_id : 'name:'.strtolower(trim((string) ($item->item_name ?? '')));
            $item->already_invoiced_qty = round((float) ($invoiced[$key] ?? 0), 2);
        });

        return $order;
    }

    public function hasPendingToInvoice(PurchaseDocument $order): bool
    {
        $order->loadMissing('items');

        return ! $this->invoicesCoverOrder($order, $this->validInvoicesForOrder($order)->pluck('id')->all());
    }

    protected function invoicesCoverOrder(PurchaseDocument $order, array $invoiceIds): bool
    {
        $invoiced = $this->invoicedQuantitiesByKey($invoiceIds);

        foreach ($order->items as $orderItem) {
            $key = $orderItem->item_id ? 'id:'.$orderItem->item_id : 'name:'.strtolower(trim((string) ($orderItem->item_name ?? '')));
            if ((float) $orderItem->quantity > 0 && ($invoiced[$key] ?? 0) < (float) $orderItem->quantity) {
                return false;
            }
        }

        return true;
    }

    /**
     * Cierra la solicitud cuando todas sus órdenes (no rechazadas) ya están facturadas.
     */
    public function checkAndMarkRequestAsProcessed(int $requestId): void
    {
        $purchaseRequest = \App\Models\Expenses\PurchaseRequest::find($requestId);
        if (!$purchaseRequest || !$purchaseRequest->isApproved()) {
            return;
        }

        $orders = PurchaseDocument::where('purchase_request_id', $requestId)
            ->where('document_type', PurchaseDocument::TYPE_ORDER)
            ->whereNotIn('status', ['rejected'])
            ->whereNull('deleted_at')
            ->get(['id', 'status']);

        if ($orders->isEmpty()) {
            return;
        }

        $allInvoiced = $orders->every(fn($o) => $o->status === \App\Enums\PurchaseOrderStatus::INVOICED->value);

        if ($allInvoiced) {
            $purchaseRequest->markAsProcessed(Auth::id());
        }
    }

    public function loadWithRelations(PurchaseDocument $document): PurchaseDocument
    {
        $document->load([
            'items.item.categorySubGroup:id,name',
            'supplier',
            'creator',
            'executor',
            'rejectedBy',
            'approvedBy',
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

        $alreadyPending = $invoice->approval_status === PurchaseDocument::APPROVAL_PENDING_DIFFERENCE;

        $invoice->update([
            'rejection_reason'        => $reason,
            'rejection_evidence_image'=> $this->prepareEvidencePath($evidencePath),
            'rejection_notes'         => $notes,
            'approval_status'         => PurchaseDocument::APPROVAL_PENDING_DIFFERENCE,
            'has_difference'          => true,
            'rejected_by_user_id'     => Auth::id(),
            'rejected_at'             => now(),
        ]);

        // Mientras haya diferencia pendiente la orden queda "parcialmente facturada"
        if ($invoice->parent_document_id) {
            $parentOrder = PurchaseDocument::find($invoice->parent_document_id);
            if ($parentOrder) {
                $this->recalculateOrderStatus($parentOrder);
            }
        }

        // Si ya se creó pendiente (sobrefacturación), el aviso salió una vez: no se repite al justificar.
        if (! $alreadyPending) {
            /** @var \App\Models\Settings\User|null $currentUser */
            $currentUser = Auth::user();
            $this->notifyDifference($invoice, $currentUser);
        }

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
        // Contador atómico por serie (bloqueo de fila): dos usuarios simultáneos nunca reciben el mismo número.
        // El piso es el máximo del prefijo EXACTO (`FAC-0007`, no `FAC-T-0003`) incluyendo borrados: un número
        // eliminado no se reutiliza. Tomar el último por id mezclaba prefijos y generaba FAC-0002 repetido.
        return DocumentSequence::next(
            "purchase_documents:{$documentType}:{$prefix}",
            fn () => DocumentSequence::maxFromNumbers(
                PurchaseDocument::withTrashed()
                    ->where('document_type', $documentType)
                    ->where('document_number', 'like', $prefix . '-%')
                    ->pluck('document_number'),
                $prefix
            )
        );
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
            'approved_by_user_id',
            'approval_notes',
            'closure_reason',
        ];
    }

    protected function notifyDifference(PurchaseDocument $invoice, ?User $requester): void
    {
        app(\App\Services\Settings\NotificationDispatcher::class)->dispatch(
            'invoice_difference',
            new PurchaseInvoiceDifferenceNotification($invoice, $requester?->name),
            ['actor' => $requester]
        );
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

        // Re-evaluar el estado de la orden padre tras aprobar la diferencia
        if ($invoice->parent_document_id) {
            $parentOrder = PurchaseDocument::find($invoice->parent_document_id);
            if ($parentOrder) {
                $this->recalculateOrderStatus($parentOrder);
            }
        }

        $this->notifyDifferenceApproved($invoice);

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

        // La diferencia rechazada deja de contar como facturado: la orden se recalcula
        if ($invoice->parent_document_id) {
            $parentOrder = PurchaseDocument::find($invoice->parent_document_id);
            if ($parentOrder) {
                $this->recalculateOrderStatus($parentOrder);
            }
        }

        // Notificar al creador de la factura para que gestione nota crédito
        $this->notifyDifferenceRejected($invoice);

        return $this->loadWithRelations($invoice);
    }

    protected function notifyDifferenceRejected(PurchaseDocument $invoice): void
    {
        app(\App\Services\Settings\NotificationDispatcher::class)->dispatch(
            'invoice_difference_rejected',
            new \App\Notifications\PurchaseInvoiceDifferenceRejectedNotification($invoice),
            ['actor' => Auth::user(), 'creator' => $invoice->creator]
        );
    }

    protected function notifyDifferenceApproved(PurchaseDocument $invoice): void
    {
        $number = $invoice->document_number ?? (string) $invoice->id;

        app(\App\Services\Settings\NotificationDispatcher::class)->dispatch(
            'invoice_difference_approved',
            new \App\Notifications\GenericEventNotification(
                'invoice_difference_approved',
                'Gastos',
                "Diferencia aprobada — Factura #{$number}",
                "La diferencia registrada en la factura {$number} fue aprobada.",
                '/gastos/facturas-compra'
            ),
            ['actor' => Auth::user(), 'creator' => $invoice->creator]
        );
    }
}
