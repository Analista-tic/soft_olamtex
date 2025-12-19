<?php

namespace App\Http\Controllers\Expenses;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Expenses\Concerns\ValidatesPurchaseInvoiceRequest;
use App\Models\Expenses\PurchaseDocument;
use App\Services\Expenses\PurchaseDocumentService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PurchaseInvoiceController extends Controller
{
    use ValidatesPurchaseInvoiceRequest;

    public function __construct(protected PurchaseDocumentService $service) {}

    public function index()
    {
        return Inertia::render('Expenses/PurchaseInvoices/Index');
    }

    public function getData(Request $request)
    {
        $filters = $this->buildFilters($request);
        $documents = $this->service->paginateDocuments(
            PurchaseDocument::TYPE_INVOICE,
            $filters,
            $request->input('per_page', 20)
        );

        return response()->json([
            'data' => $documents->items(),
            'total' => $documents->total(),
            'current_page' => $documents->currentPage(),
        ]);
    }

    public function store(Request $request)
    {
        $payload = $this->validateInvoiceRequest($request);
        $items = $payload['items'] ?? [];
        $withholdings = $payload['withholdings'] ?? [];
        $invoice = $this->service->createInvoice($payload, $items, $withholdings);

        return response()->json(['data' => $invoice], 201);
    }

    public function show(PurchaseDocument $purchaseDocument)
    {
        $this->ensureDocumentType($purchaseDocument, PurchaseDocument::TYPE_INVOICE);
        return response()->json(['data' => $this->service->loadWithRelations($purchaseDocument)]);
    }

    public function update(Request $request, PurchaseDocument $purchaseDocument)
    {
        $this->ensureDocumentType($purchaseDocument, PurchaseDocument::TYPE_INVOICE);
        $payload = $this->validateInvoiceRequest($request, true);
        $items = $request->has('items') ? $payload['items'] ?? [] : null;

        $updated = $this->service->updateDocument($purchaseDocument, $payload, $items);
        return response()->json(['data' => $updated]);
    }

    public function destroy(PurchaseDocument $purchaseDocument)
    {
        $this->ensureDocumentType($purchaseDocument, PurchaseDocument::TYPE_INVOICE);
        $this->service->deleteDocument($purchaseDocument);
        return response()->json(['message' => 'Factura eliminada']);
    }

    public function justify(Request $request, PurchaseDocument $purchaseDocument)
    {
        $this->ensureDocumentType($purchaseDocument, PurchaseDocument::TYPE_INVOICE);

        $validated = $request->validate([
            'difference_reason' => 'required|string',
            'difference_evidence_path' => 'nullable|string',
            'difference_notes' => 'nullable|string',
        ]);

        $invoice = $this->service->attachValidationDifference(
            $purchaseDocument,
            $validated['difference_reason'],
            $validated['difference_evidence_path'] ?? null,
            $validated['difference_notes'] ?? null
        );

        return response()->json(['data' => $invoice]);
    }

    protected function ensureDocumentType(PurchaseDocument $document, string $type): void
    {
        if ($document->document_type !== $type) {
            abort(404);
        }
    }

    protected function buildFilters(Request $request): array
    {
        $filters = $request->only(['search', 'date_from', 'date_to', 'status', 'provider_id']);
        $filters['is_fabric'] = $request->boolean('is_fabric', false);
        return $filters;
    }
}
