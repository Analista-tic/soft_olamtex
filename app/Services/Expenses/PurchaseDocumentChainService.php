<?php

namespace App\Services\Expenses;

use App\Models\Expenses\PurchaseDocument;
use App\Models\Expenses\PurchaseRequest;
use App\Models\Settings\User;
use App\Support\MasterData\MaterialClassifier;
use Illuminate\Support\Collection;

/**
 * Cadena solicitud → órdenes → facturas (con su recepción) de cualquier documento de compra, con
 * estados y fechas. Cada eslabón indica si el usuario puede abrirlo (permiso de ruta + alcance).
 */
class PurchaseDocumentChainService
{
    public function __construct(
        protected PurchaseDocumentService $documents,
        protected PurchaseRequestService $requests,
    ) {
    }

    public function forRequest(PurchaseRequest $request, ?User $user): array
    {
        $orders = PurchaseDocument::where('purchase_request_id', $request->id)
            ->where('document_type', PurchaseDocument::TYPE_ORDER)
            ->orderBy('id')
            ->get();

        return $this->build($request, $orders, 'request', $request->id, $user);
    }

    public function forDocument(PurchaseDocument $document, ?User $user): array
    {
        $order = $document->isOrder()
            ? $document
            : ($document->parent_document_id ? PurchaseDocument::find($document->parent_document_id) : null);

        $request = $order?->purchase_request_id ? PurchaseRequest::find($order->purchase_request_id) : null;
        $orders = $order ? collect([$order]) : collect();
        $current = $document->isOrder() ? 'order' : 'invoice';

        // Factura sin orden: la cadena es solo ella misma.
        $extraInvoices = $order ? collect() : collect([$document]);

        return $this->build($request, $orders, $current, $document->id, $user, $extraInvoices);
    }

    protected function build(?PurchaseRequest $request, Collection $orders, string $currentType, int $currentId, ?User $user, ?Collection $extraInvoices = null): array
    {
        $invoices = $orders->isEmpty()
            ? collect()
            : PurchaseDocument::with('items.item.categorySubGroup')
                ->whereIn('parent_document_id', $orders->pluck('id'))
                ->where('document_type', PurchaseDocument::TYPE_INVOICE)
                ->orderBy('id')
                ->get();
        $invoices = $invoices->merge($extraInvoices ?? collect())->unique('id')->values();

        $visibleOrders = $this->visibleIds($orders, PurchaseDocument::TYPE_ORDER, 'ver ordenes compra', $user);
        $visibleInvoices = $this->visibleIds($invoices, PurchaseDocument::TYPE_INVOICE, 'ver facturas compra', $user);

        return [
            'current' => ['type' => $currentType, 'id' => $currentId],
            'request' => $request ? [
                'id' => $request->id,
                'number' => $request->request_number,
                'status' => $request->status instanceof \BackedEnum ? $request->status->value : $request->status,
                'date' => $request->date?->toDateString(),
                'created_at' => $request->created_at?->toIso8601String(),
                'url' => $this->canSeeRequest($request, $user) ? "/gastos/solicitudes-compra?ver={$request->id}" : null,
            ] : null,
            'orders' => $orders->map(fn (PurchaseDocument $o) => [
                'id' => $o->id,
                'number' => $o->document_number,
                'status' => $o->status,
                'date' => $o->date?->toDateString(),
                'delivery_date' => $o->delivery_date?->toDateString(),
                'created_at' => $o->created_at?->toIso8601String(),
                'url' => in_array($o->id, $visibleOrders, true) ? "/gastos/ordenes-compra?ver={$o->id}" : null,
            ])->values()->all(),
            'invoices' => $invoices->map(fn (PurchaseDocument $i) => [
                'id' => $i->id,
                'order_id' => $i->parent_document_id,
                'number' => $i->document_number,
                'status' => $i->status,
                'approval_status' => $i->approval_status,
                'has_difference' => (bool) $i->has_difference,
                'date' => $i->date?->toDateString(),
                'created_at' => $i->created_at?->toIso8601String(),
                'received_at' => $i->received_at?->toIso8601String(),
                'url' => in_array($i->id, $visibleInvoices, true)
                    ? ($this->isFabricInvoice($i) ? '/gastos/facturas-compra-tela' : '/gastos/facturas-compra')."?ver={$i->id}"
                    : null,
            ])->values()->all(),
        ];
    }

    /** @return int[] */
    protected function visibleIds(Collection $docs, string $type, string $routePermission, ?User $user): array
    {
        if ($docs->isEmpty() || ! $this->can($user, $routePermission)) {
            return [];
        }

        $query = PurchaseDocument::whereIn('id', $docs->pluck('id'))->where('document_type', $type);
        $this->documents->applyScopeForDocumentType($query, $type, $user);

        return $query->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    protected function canSeeRequest(PurchaseRequest $request, ?User $user): bool
    {
        $canRoute = $this->can($user, 'ver propios solicitudes compra')
            || $this->can($user, 'ver area solicitudes compra')
            || $this->can($user, 'ver todos solicitudes compra');
        if (! $canRoute) {
            return false;
        }

        $query = PurchaseRequest::query()->whereKey($request->id);
        $this->requests->applyVisibility($query, $user);

        return $query->exists();
    }

    protected function can(?User $user, string $permission): bool
    {
        try {
            return (bool) $user?->hasPermissionTo($permission);
        } catch (\Spatie\Permission\Exceptions\PermissionDoesNotExist) {
            return false;
        }
    }

    protected function isFabricInvoice(PurchaseDocument $invoice): bool
    {
        if (str_starts_with((string) $invoice->document_number, 'FAC-T')) {
            return true;
        }
        $invoice->loadMissing('items.item.categorySubGroup');

        return $invoice->items->contains(fn ($line) => MaterialClassifier::isFabricPurchaseDocumentItem($line));
    }
}
