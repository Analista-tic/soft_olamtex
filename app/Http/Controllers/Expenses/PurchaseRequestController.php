<?php

namespace App\Http\Controllers\Expenses;

use App\Models\Expenses\PurchaseDocument;
use App\Services\Expenses\PurchaseDocumentRequestService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PurchaseRequestController extends PurchaseDocumentController
{
    public function __construct(PurchaseDocumentRequestService $service)
    {
        parent::__construct($service);
    }

    public function index()
    {
        return Inertia::render('Expenses/PurchaseRequests/Index');
    }

    public function getData(Request $request)
    {
        $filters = $request->only(['search', 'status', 'provider_id', 'date_from', 'date_to']);

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

}
