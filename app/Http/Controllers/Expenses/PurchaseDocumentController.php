<?php

namespace App\Http\Controllers\Expenses;

use App\Http\Controllers\Controller;
use App\Models\Expenses\PurchaseDocument;
use App\Services\Expenses\PurchaseDocumentService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PurchaseDocumentController extends Controller
{
    public function __construct(protected PurchaseDocumentService $service) {}

    protected function ensureDocumentType(PurchaseDocument $document, string $type): void
    {
        if ($document->document_type !== $type) {
            abort(404);
        }
    }

    // Métodos CRUD compartidos
    protected function indexView(string $component)
    {
        return Inertia::render($component);
    }

    protected function getDataGeneric(Request $request, string $documentType, int $perPage = 20)
    {
        $filters = $request->only(['search', 'status', 'provider_id', 'date_from', 'date_to', 'subgroup_id', 'fabric_only', 'exclude_fabric']);
        // Pasar $request como cuarto parámetro solo si se provee (para sort)
        $documents = $this->service->paginateDocuments($documentType, $filters, $perPage, $request, $request->user());

        if ($documentType === PurchaseDocument::TYPE_ORDER) {
            foreach ($documents->items() as $order) {
                $order->setAttribute('has_pending_to_invoice', $order->canInvoiceOrder() && $this->service->hasPendingToInvoice($order));
            }
        }

        return response()->json([
            'data' => $documents->items(),
            'total' => $documents->total(),
            'current_page' => $documents->currentPage(),
        ]);
    }

    protected function showGeneric(PurchaseDocument $purchaseDocument, string $documentType)
    {
        $this->ensureDocumentType($purchaseDocument, $documentType);
        return response()->json(['data' => $this->service->findAccessibleDocumentOrFail($purchaseDocument, request()->user())]);
    }

    protected function updateGeneric(Request $request, PurchaseDocument $purchaseDocument, string $documentType, callable $validator)
    {
        $this->ensureDocumentType($purchaseDocument, $documentType);
        $purchaseDocument = $this->service->findAccessibleDocumentOrFail($purchaseDocument, $request->user());
        $payload = $validator($request, true);
        $items = $request->has('items') ? $payload['items'] ?? [] : null;

        $updated = $this->service->updateDocument($purchaseDocument, $payload, $items);
        return response()->json(['data' => $updated]);
    }

    protected function destroyGeneric(PurchaseDocument $purchaseDocument, string $documentType, string $message = 'Documento eliminado')
    {
        $this->ensureDocumentType($purchaseDocument, $documentType);
        $purchaseDocument = $this->service->findAccessibleDocumentOrFail($purchaseDocument, request()->user());
        $this->service->deleteDocument($purchaseDocument);
        return response()->json(['message' => $message]);
    }

    protected function normalizeRequestItems(Request $request): void
    {
        $items = $request->input('items');
        if (!is_array($items)) {
            return;
        }

        foreach ($items as $index => $item) {
            if (!is_array($item)) {
                continue;
            }

            $rawId = $item['item_id'] ?? null;
            if (is_string($rawId)) {
                $rawId = trim($rawId);
            }
            $manualName = isset($item['item_name']) ? trim((string) $item['item_name']) : null;

            if (is_numeric($rawId)) {
                $items[$index]['item_id'] = (int) $rawId;
            } else {
                if ($rawId !== null && $rawId !== '') {
                    $manualName = $manualName ?: (string) $rawId;
                }
                $items[$index]['item_id'] = null;
            }

            $items[$index]['item_name'] = $manualName ?: null;
        }

        $request->merge(['items' => $items]);
    }

    protected function validateRequestPayload(Request $request, bool $isUpdate = false): array
    {
        $this->normalizeRequestItems($request);

        $rules = [
            // document_number no se valida en request: se genera automáticamente en el backend
            'contact_id' => 'nullable|exists:contacts,id',
            'provider_name' => $isUpdate ? 'sometimes|required_without:contact_id|string|max:255' : 'required_without:contact_id|string|max:255',
            'provider_phone' => 'nullable|string|max:50',
            'date' => 'required|date',
            'delivery_date' => 'nullable|date',
            'description' => $isUpdate ? 'sometimes|nullable|string' : 'required|string',
            'status' => 'sometimes|in:pending',
            'items' => $isUpdate ? 'sometimes|array' : 'required|array|min:1',
            'items.*.item_id' => 'nullable|integer|exists:items,id',
            'items.*.item_name' => 'required_without:items.*.item_id|string|max:255',
            'items.*.price' => 'nullable|numeric|decimal:0,2',
            'items.*.tax' => 'nullable|numeric',
            'items.*.quantity' => 'required_with:items|numeric|decimal:0,2',
            'items.*.description' => 'nullable|string',
            'items.*.supplier_code' => 'nullable|string',
            'items.*.supplier_color' => 'nullable|string',
            'items.*.order_width' => 'nullable|string',
            'items.*.total' => 'nullable|numeric',
            'items.*.total_tax' => 'nullable|numeric',
        ];

        return $request->validate($rules);
    }

    protected function validateOrderPayload(Request $request, bool $isUpdate = false): array
    {
        $rules = [
            // document_number no se valida: se genera automáticamente en el backend
            'contact_id' => 'nullable|exists:contacts,id',
            'provider_name' => $isUpdate ? 'sometimes|required_without:contact_id|string|max:255' : 'required_without:contact_id|string|max:255',
            'provider_phone' => 'nullable|string|max:50',
            'date' => 'required|date',
            'delivery_date' => 'required|date|after_or_equal:date',
            'supplier_order_number' => 'required|string|max:255',
            'description' => 'nullable|string',
            'parent_document_id' => 'nullable|exists:purchase_documents,id',
            'purchase_request_id' => 'nullable|exists:purchase_requests,id',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|exists:items,id',
            'items.*.price' => 'required|numeric|decimal:0,2',
            'items.*.tax' => 'required|numeric',
            'items.*.quantity' => 'required|numeric|decimal:0,2',
            'items.*.description' => 'nullable|string',
            'items.*.supplier_code' => 'nullable|string',
            'items.*.supplier_color' => 'nullable|string',
            'items.*.order_width' => 'nullable|string',
            'items.*.total' => 'required|numeric',
            'items.*.total_tax' => 'nullable|numeric',
        ];

        if ($isUpdate) {
            $rules['items'] = 'sometimes|array|min:1';
        }

        $messages = [
            'items.*.item_id.required' => 'Uno de los ítems no existe en el catálogo. Créalo primero o selecciona uno existente.',
            'items.*.item_id.integer'  => 'Uno de los ítems no existe en el catálogo. Créalo primero o selecciona uno existente.',
            'items.*.item_id.exists'   => 'Uno de los ítems no existe en el catálogo. Créalo primero o selecciona uno existente.',
        ];

        $validated = $request->validate($rules, $messages);

        $this->ensureOrderIsNotMixed($validated['items'] ?? []);
        $this->ensureFabricItemsHaveSupplierData($validated['items'] ?? []);

        return $validated;
    }

    /**
     * En órdenes y facturas de tela, cada ítem de tela debe traer código y color del proveedor y el ancho pedido
     * (los usa la recepción para crear los rollos).
     */
    protected function ensureFabricItemsHaveSupplierData(array $items): void
    {
        $itemIds = collect($items)->pluck('item_id')->filter()->unique()->values();
        if ($itemIds->isEmpty()) {
            return;
        }

        $fabricIds = \App\Models\MasterData\Item::with('categorySubGroup:id,name')
            ->whereIn('id', $itemIds)
            ->get()
            ->filter(fn ($item) => strtolower(trim((string) ($item->categorySubGroup?->name ?? ''))) === 'tela')
            ->pluck('id')
            ->all();

        $errors = [];
        foreach ($items as $index => $item) {
            if (! in_array((int) ($item['item_id'] ?? 0), $fabricIds, true)) {
                continue;
            }
            if (trim((string) ($item['supplier_code'] ?? '')) === '') {
                $errors["items.$index.supplier_code"] = ['El código del proveedor es obligatorio en ítems de tela.'];
            }
            if (trim((string) ($item['supplier_color'] ?? '')) === '') {
                $errors["items.$index.supplier_color"] = ['El color del proveedor es obligatorio en ítems de tela.'];
            }
            if (! is_numeric($item['order_width'] ?? null) || (float) $item['order_width'] <= 0) {
                $errors["items.$index.order_width"] = ['El ancho pedido es obligatorio en ítems de tela y debe ser mayor a 0.'];
            } elseif ((float) $item['order_width'] > 10) {
                $errors["items.$index.order_width"] = ['El ancho pedido se expresa en metros (ej. 1,50) y no puede superar 10 m.'];
            }
        }

        if ($errors) {
            throw \Illuminate\Validation\ValidationException::withMessages($errors);
        }
    }

    /**
     * Una orden es solo de tela o solo de ítems normales: las facturas y la recepción de cada tipo
     * son flujos distintos (rollos vs bodega) y una orden mixta no podría facturarse completa.
     */
    protected function ensureOrderIsNotMixed(array $items): void
    {
        $itemIds = collect($items)->pluck('item_id')->filter()->unique()->values();
        if ($itemIds->count() < 2) {
            return;
        }

        $fabricFlags = \App\Models\MasterData\Item::with('categorySubGroup:id,name')
            ->whereIn('id', $itemIds)
            ->get()
            ->map(fn ($item) => strtolower(trim((string) ($item->categorySubGroup?->name ?? ''))) === 'tela');

        if ($fabricFlags->contains(true) && $fabricFlags->contains(false)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'items' => ['Una orden de compra no puede mezclar telas con ítems normales. Crea una orden solo de telas y otra con los demás ítems.'],
            ]);
        }
    }


    /** Fechas del recorrido solicitud → orden → recepción → factura (compartido por facturas normales y de tela). */
    protected function traceabilityResponse(PurchaseDocument $purchaseDocument)
    {
        $this->ensureDocumentType($purchaseDocument, PurchaseDocument::TYPE_INVOICE);
        $invoice = $this->service->findAccessibleDocumentOrFail($purchaseDocument, request()->user());

        $order = $invoice->parent_document_id
            ? PurchaseDocument::with('purchaseRequest:id,request_number,date,created_at')->find($invoice->parent_document_id)
            : null;
        $purchaseRequest = $order?->purchaseRequest;

        return response()->json(['data' => [
            'request' => $purchaseRequest ? [
                'number' => $purchaseRequest->request_number,
                'date' => $purchaseRequest->date?->toDateString(),
                'created_at' => $purchaseRequest->created_at?->toIso8601String(),
            ] : null,
            'order' => $order ? [
                'number' => $order->document_number,
                'date' => $order->date?->toDateString(),
                'created_at' => $order->created_at?->toIso8601String(),
                'delivery_date' => $order->delivery_date?->toDateString(),
            ] : null,
            'received_at' => $invoice->received_at?->toIso8601String(),
            'invoice' => [
                'date' => $invoice->date?->toDateString(),
                'created_at' => $invoice->created_at?->toIso8601String(),
            ],
            'chain' => app(\App\Services\Expenses\PurchaseDocumentChainService::class)->forDocument($invoice, request()->user()),
        ]]);
    }
}
