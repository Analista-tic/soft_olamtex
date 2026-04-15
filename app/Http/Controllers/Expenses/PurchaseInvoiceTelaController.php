<?php

namespace App\Http\Controllers\Expenses;

use App\Http\Controllers\Expenses\Concerns\ValidatesPurchaseInvoiceRequest;
use App\Models\Expenses\PurchaseDocument;
use App\Services\Expenses\PurchaseDocumentInvoiceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class PurchaseInvoiceTelaController extends PurchaseDocumentController
{
    use ValidatesPurchaseInvoiceRequest;

    public function __construct(PurchaseDocumentInvoiceService $service)
    {
        parent::__construct($service);
    }

    private function invoiceService(): PurchaseDocumentInvoiceService
    {
        return $this->service;
    }

    public function indexFabric()
    {
        return Inertia::render('Expenses/PurchaseInvoices/IndexFabric');
    }

    public function show(PurchaseDocument $purchaseInvoice)
    {
        $this->ensureDocumentType($purchaseInvoice, PurchaseDocument::TYPE_INVOICE);
        return response()->json(['data' => $this->service->loadWithRelations($purchaseInvoice)]);
    }

    public function store(Request $request)
    {
        $payload = $this->validateInvoiceRequest($request);
        $this->validateOrderFabricCompatibility(
            isset($payload['parent_document_id']) ? (int) $payload['parent_document_id'] : null,
            true
        );
        $items = $payload['items'] ?? [];
        $withholdings = $payload['withholdings'] ?? [];

        if (empty($payload['document_number'])) {
            $payload['document_number'] = $this->invoiceService()->generateFabricInvoiceNumber();
        }

        $invoice = $this->invoiceService()->createInvoice($payload, $items, $withholdings);

        return response()->json(['data' => $invoice], 201);
    }

    public function update(Request $request, PurchaseDocument $purchaseInvoice)
    {
        $this->ensureDocumentType($purchaseInvoice, PurchaseDocument::TYPE_INVOICE);
        $payload = $this->validateInvoiceRequest($request, true);
        $items = $request->has('items') ? $payload['items'] ?? [] : null;

        $updated = $this->invoiceService()->updateDocument($purchaseInvoice, $payload, $items);
        return response()->json(['data' => $updated]);
    }

    public function destroy(PurchaseDocument $purchaseInvoice)
    {
        $this->ensureDocumentType($purchaseInvoice, PurchaseDocument::TYPE_INVOICE);
        $this->service->deleteDocument($purchaseInvoice);
        return response()->json(['message' => 'Factura eliminada']);
    }

    public function getData(Request $request)
    {

        $filters = $this->buildFilters($request);
        $filters['fabric_only'] = true;
        $perPage = $request->input('per_page', 20);

        $documents = $this->service->paginateDocuments(
            PurchaseDocument::TYPE_INVOICE,
            $filters,
            $perPage,
            $request
        );

        return response()->json([
            'data' => $documents->items(),
            'total' => $documents->total(),
            'current_page' => $documents->currentPage(),
        ]);
    }

    protected function buildFilters(Request $request): array
    {
        return $request->only(['search', 'date_from', 'date_to', 'status', 'provider_id', 'subgroup_id', 'approval_status']);
    }

    public function justify(Request $request, PurchaseDocument $purchaseInvoice)
    {
        $this->ensureDocumentType($purchaseInvoice, PurchaseDocument::TYPE_INVOICE);

        $validated = $request->validate([
            'difference_reason' => 'required|string',
            'difference_evidence_path' => 'nullable|string',
            'difference_notes' => 'nullable|string',
        ]);

        $invoice = $this->invoiceService()->attachValidationDifference(
            $purchaseInvoice,
            $validated['difference_reason'],
            $validated['difference_evidence_path'] ?? null,
            $validated['difference_notes'] ?? null
        );

        return response()->json(['data' => $invoice]);
    }

    public function approveDifference(Request $request, PurchaseDocument $purchaseInvoice)
    {
        $this->ensureDocumentType($purchaseInvoice, PurchaseDocument::TYPE_INVOICE);

        $validated = $request->validate([
            'approval_notes' => 'nullable|string|max:1000',
        ]);

        $invoice = $this->invoiceService()->approveDifference(
            $purchaseInvoice,
            $validated['approval_notes'] ?? null,
            Auth::id()
        );

        return response()->json(['data' => $invoice]);
    }

    public function rejectDifference(Request $request, PurchaseDocument $purchaseInvoice)
    {
        $this->ensureDocumentType($purchaseInvoice, PurchaseDocument::TYPE_INVOICE);

        $validated = $request->validate([
            'approval_notes' => 'required|string|max:1000',
        ]);

        $invoice = $this->invoiceService()->rejectDifference(
            $purchaseInvoice,
            $validated['approval_notes'],
            Auth::id()
        );

        return response()->json(['data' => $invoice]);
    }
}
