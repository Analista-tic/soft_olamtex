<?php

namespace App\Http\Controllers\Expenses;

use App\Http\Controllers\Controller;
use App\Models\Expenses\PurchaseDocument;
use App\Services\Expenses\PurchaseDocumentService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PurchaseRequestController extends Controller
{
    public function __construct(protected PurchaseDocumentService $service) {}

    public function index()
    {
        return Inertia::render('Expenses/PurchaseRequests/Index');
    }

    public function getData(Request $request)
    {
        $filters = $request->only(['search', 'status', 'provider_id', 'date_from', 'date_to']);
        $filters['is_fabric'] = $request->boolean('is_fabric', false);

        $documents = $this->service->paginateDocuments(
            PurchaseDocument::TYPE_REQUEST,
            $filters,
            $request->input('per_page', 20)
        );

        $rows = array_map(function (PurchaseDocument $doc) {
            $estimated = 0;
            if ($doc->relationLoaded('items')) {
                $estimated = $doc->items->sum(fn ($item) => (float) ($item->total ?? 0));
            } else {
                $estimated = (float) $doc->items()->sum('total');
            }

            return [
                'id' => $doc->id,
                'request_number' => $doc->document_number,
                'document_number' => $doc->document_number,
                'date' => $doc->date,
                'description' => $doc->description,
                'estimated_amount' => $estimated ?: null,
                'status' => $doc->status,
                'requester' => $doc->creator ? ['name' => $doc->creator->name] : null,
                'provider' => $doc->supplier
                    ? ['full_name' => $doc->supplier->full_name]
                    : ($doc->provider_name ? ['full_name' => $doc->provider_name] : null),
            ];
        }, $documents->items());

        return response()->json([
            'data' => $rows,
            'total' => $documents->total(),
            'current_page' => $documents->currentPage(),
        ]);
    }

    public function store(Request $request)
    {
        $payload = $this->validateRequestPayload($request);
        $items = $payload['items'] ?? [];
        $document = $this->service->createRequest($payload, $items);

        return response()->json(['data' => $document], 201);
    }

    public function show(PurchaseDocument $purchaseDocument)
    {
        $this->ensureDocumentType($purchaseDocument, PurchaseDocument::TYPE_REQUEST);
        return response()->json(['data' => $this->service->loadWithRelations($purchaseDocument)]);
    }

    public function update(Request $request, PurchaseDocument $purchaseDocument)
    {
        $this->ensureDocumentType($purchaseDocument, PurchaseDocument::TYPE_REQUEST);
        $payload = $this->validateRequestPayload($request, true);
        $items = $request->has('items') ? $payload['items'] ?? [] : null;

        $updated = $this->service->updateDocument($purchaseDocument, $payload, $items);
        return response()->json(['data' => $updated]);
    }

    public function destroy(PurchaseDocument $purchaseDocument)
    {
        $this->ensureDocumentType($purchaseDocument, PurchaseDocument::TYPE_REQUEST);
        $this->service->deleteDocument($purchaseDocument);
        return response()->json(['message' => 'Solicitud eliminada correctamente.']);
    }

    protected function ensureDocumentType(PurchaseDocument $document, string $type): void
    {
        if ($document->document_type !== $type) {
            abort(404);
        }
    }

    protected function validateRequestPayload(Request $request, bool $isUpdate = false): array
    {
        $this->normalizeRequestItems($request);

        $rules = [
            'document_number' => 'nullable|string|max:64',
            'contact_id' => 'nullable|exists:contacts,id',
            'provider_name' => $isUpdate ? 'sometimes|required_without:contact_id|string|max:255' : 'required_without:contact_id|string|max:255',
            'provider_phone' => 'nullable|string|max:50',
            'date' => 'required|date',
            'delivery_date' => 'nullable|date',
            'description' => 'nullable|string',
            'status' => 'nullable|string|max:50',
            'items' => $isUpdate ? 'sometimes|array' : 'sometimes|array',
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
}
