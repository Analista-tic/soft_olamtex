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

        return response()->json([
            'data' => $documents->items(),
            'total' => $documents->total(),
            'current_page' => $documents->currentPage(),
        ]);
    }

    protected function showGeneric(PurchaseDocument $purchaseDocument, string $documentType)
    {
        $this->ensureDocumentType($purchaseDocument, $documentType);
        return response()->json(['data' => $this->service->loadWithRelations($purchaseDocument)]);
    }

    protected function updateGeneric(Request $request, PurchaseDocument $purchaseDocument, string $documentType, callable $validator)
    {
        $this->ensureDocumentType($purchaseDocument, $documentType);
        $payload = $validator($request, true);
        $items = $request->has('items') ? $payload['items'] ?? [] : null;

        $updated = $this->service->updateDocument($purchaseDocument, $payload, $items);
        return response()->json(['data' => $updated]);
    }

    protected function destroyGeneric(PurchaseDocument $purchaseDocument, string $documentType, string $message = 'Documento eliminado')
    {
        $this->ensureDocumentType($purchaseDocument, $documentType);
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
            'status' => 'sometimes|in:pending,processed',
            'items' => $isUpdate ? 'sometimes|array' : 'required|array|min:1',
            'items.*.item_id' => 'nullable|integer|exists:items,id',
            'items.*.item_name' => 'required_without:items.*.item_id|string|max:255',
            'items.*.price' => 'nullable|numeric',
            'items.*.tax' => 'nullable|numeric',
            'items.*.quantity' => 'required_with:items|numeric',
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
            'delivery_date' => 'required|date',
            'supplier_order_number' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'sometimes|in:pending,approved,sent,received,invoiced,cancelled',
            'parent_document_id' => 'nullable|exists:purchase_documents,id',
            'purchase_request_id' => 'nullable|exists:purchase_requests,id',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|exists:items,id',
            'items.*.price' => 'required|numeric',
            'items.*.tax' => 'required|numeric',
            'items.*.quantity' => 'required|numeric',
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

        return $request->validate($rules);
    }

}
