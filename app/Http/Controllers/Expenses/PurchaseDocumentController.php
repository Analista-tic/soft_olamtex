<?php

namespace App\Http\Controllers\Expenses;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Expenses\Concerns\ValidatesPurchaseInvoiceRequest;
use App\Mail\PurchaseOrderMail;
use App\Models\Expenses\PurchaseDocument;
use App\Services\Expenses\PurchaseDocumentService;
use Barryvdh\DomPDF\Facade\Pdf as DomPDF;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;

class PurchaseDocumentController extends Controller
{
    use ValidatesPurchaseInvoiceRequest;

    public function __construct(protected PurchaseDocumentService $service) {}

    // === REQUESTS ===

    public function requestIndex()
    {
        return Inertia::render('Expenses/PurchaseRequests/Index');
    }

    public function requestData(Request $request)
    {
        $filters = $request->only(['search', 'status', 'provider_id', 'date_from', 'date_to']);
        $documents = $this->service->paginateDocuments(PurchaseDocument::TYPE_REQUEST, $filters, $request->input('per_page', 20));

        $rows = array_map(function (PurchaseDocument $doc) {
            $estimated = 0;
            if ($doc->relationLoaded('items')) {
                $estimated = $doc->items->sum(function ($it) {
                    return (float) ($it->total ?? 0);
                });
            } else {
                // fallback: sum via relation query if items not eager-loaded
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
                // expose `provider` object expected by the frontend (fall back to provider_name)
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

    public function storeRequest(Request $request)
    {
        $payload = $this->validateRequestPayload($request);
        $items = $payload['items'] ?? [];
        $document = $this->service->createRequest($payload, $items);

        return response()->json(['data' => $document], 201);
    }

    public function showRequest(PurchaseDocument $purchaseDocument)
    {
        $this->ensureDocumentType($purchaseDocument, PurchaseDocument::TYPE_REQUEST);
        return response()->json(['data' => $this->service->loadWithRelations($purchaseDocument)]);
    }

    public function updateRequest(Request $request, PurchaseDocument $purchaseDocument)
    {
        $this->ensureDocumentType($purchaseDocument, PurchaseDocument::TYPE_REQUEST);
        $payload = $this->validateRequestPayload($request, true);
        $items = $request->has('items') ? $payload['items'] ?? [] : null;

        $updated = $this->service->updateDocument($purchaseDocument, $payload, $items);
        return response()->json(['data' => $updated]);
    }

    public function destroyRequest(PurchaseDocument $purchaseDocument)
    {
        $this->ensureDocumentType($purchaseDocument, PurchaseDocument::TYPE_REQUEST);
        $this->service->deleteDocument($purchaseDocument);
        return response()->json(['message' => 'Solicitud eliminada correctamente.']);
    }

    // === ORDERS ===

    public function orderIndex()
    {
        return Inertia::render('Expenses/PurchaseOrders/Index');
    }

    public function orderData(Request $request)
    {
        $filters = $request->only(['search', 'status', 'provider_id', 'date_from', 'date_to']);
        $documents = $this->service->paginateDocuments(PurchaseDocument::TYPE_ORDER, $filters, $request->input('per_page', 10));

        return response()->json([
            'data' => $documents->items(),
            'total' => $documents->total(),
            'current_page' => $documents->currentPage(),
        ]);
    }

    public function storeOrder(Request $request)
    {
        $payload = $this->validateOrderPayload($request);
        $items = $payload['items'] ?? [];
        $order = $this->service->createOrder($payload, $items);

        return response()->json([
            'message' => 'Purchase order created.',
            'data' => $order,
        ], 201);
    }

    public function showOrder(PurchaseDocument $purchaseDocument)
    {
        $this->ensureDocumentType($purchaseDocument, PurchaseDocument::TYPE_ORDER);
        return response()->json(['data' => $this->service->loadWithRelations($purchaseDocument)]);
    }

    public function updateOrder(Request $request, PurchaseDocument $purchaseDocument)
    {
        $this->ensureDocumentType($purchaseDocument, PurchaseDocument::TYPE_ORDER);
        $payload = $this->validateOrderPayload($request, true);
        $items = $request->has('items') ? $payload['items'] ?? [] : null;

        $updated = $this->service->updateDocument($purchaseDocument, $payload, $items);
        return response()->json([
            'message' => 'Purchase order updated.',
            'data' => $updated,
        ]);
    }

    public function destroyOrder(PurchaseDocument $purchaseDocument)
    {
        $this->ensureDocumentType($purchaseDocument, PurchaseDocument::TYPE_ORDER);
        $this->service->deleteDocument($purchaseDocument);
        return response()->json(['message' => 'Purchase order deleted.']);
    }

    public function sendOrderPdf(PurchaseDocument $purchaseDocument)
    {
        $this->ensureDocumentType($purchaseDocument, PurchaseDocument::TYPE_ORDER);

        try {
            $order = $this->service->loadWithRelations($purchaseDocument);
            $email = $order->supplier?->email;

            if (empty($email)) {
                throw new \RuntimeException('El proveedor no tiene correo electrónico registrado.');
            }

            $pdf = DomPDF::loadView('pdf.purchase_order', ['order' => $order]);
            Mail::to($email)->send(new PurchaseOrderMail($order, $pdf->output()));

            return response()->json(['message' => 'Correo enviado correctamente.']);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            Log::error('Error enviando correo de orden de compra: ' . $e->getMessage());
            return response()->json(['message' => 'Error enviando el correo: ' . $e->getMessage()], 500);
        }
    }

    public function convertToInvoice(PurchaseDocument $purchaseDocument)
    {
        $this->ensureDocumentType($purchaseDocument, PurchaseDocument::TYPE_ORDER);

        try {
            $invoice = $this->service->convertOrderToInvoice($purchaseDocument);
            return response()->json([
                'message' => 'Purchase order successfully converted to invoice.',
                'data' => $invoice,
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'error_code' => 'ORDER_ALREADY_INVOICED',
            ], 422);
        }
    }

    // === INVOICES ===

    public function invoiceIndex()
    {
        return Inertia::render('Expenses/PurchaseInvoices/Index');
    }

    public function invoiceData(Request $request)
    {
        $filters = $request->only(['search', 'status', 'provider_id', 'date_from', 'date_to']);
        $documents = $this->service->paginateDocuments(PurchaseDocument::TYPE_INVOICE, $filters, $request->input('per_page', 20));

        return response()->json([
            'data' => $documents->items(),
            'total' => $documents->total(),
            'current_page' => $documents->currentPage(),
        ]);
    }

    public function storeInvoice(Request $request)
    {
        $payload = $this->validateInvoiceRequest($request);
        $items = $payload['items'] ?? [];
        $withholdings = $payload['withholdings'] ?? [];
        $invoice = $this->service->createInvoice($payload, $items, $withholdings);

        return response()->json(['data' => $invoice], 201);
    }

    public function showInvoice(PurchaseDocument $purchaseDocument)
    {
        $this->ensureDocumentType($purchaseDocument, PurchaseDocument::TYPE_INVOICE);
        return response()->json(['data' => $this->service->loadWithRelations($purchaseDocument)]);
    }

    public function updateInvoice(Request $request, PurchaseDocument $purchaseDocument)
    {
        $this->ensureDocumentType($purchaseDocument, PurchaseDocument::TYPE_INVOICE);
        $payload = $this->validateInvoiceRequest($request, true);
        $items = $request->has('items') ? $payload['items'] ?? [] : null;

        $updated = $this->service->updateDocument($purchaseDocument, $payload, $items);
        return response()->json(['data' => $updated]);
    }

    public function destroyInvoice(PurchaseDocument $purchaseDocument)
    {
        $this->ensureDocumentType($purchaseDocument, PurchaseDocument::TYPE_INVOICE);
        $this->service->deleteDocument($purchaseDocument);
        return response()->json(['message' => 'Factura eliminada']);
    }

    public function justifyInvoice(Request $request, PurchaseDocument $purchaseDocument)
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

    // === HELPERS ===

    protected function ensureDocumentType(PurchaseDocument $document, string $type): void
    {
        if ($document->document_type !== $type) {
            abort(404);
        }
    }

    protected function validateRequestPayload(Request $request, bool $isUpdate = false): array
    {
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
            // allow manual item names when no `item_id` is provided
            'items.*.item_id' => 'nullable|exists:items,id',
            'items.*.item_name' => 'nullable|string|max:255',
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
            'contact_id' => 'nullable|exists:contacts,id',
            'provider_name' => $isUpdate ? 'sometimes|required_without:contact_id|string|max:255' : 'required_without:contact_id|string|max:255',
            'provider_phone' => 'nullable|string|max:50',
            'date' => 'required|date',
            'delivery_date' => 'required|date',
            'supplier_order_number' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'sometimes|in:pending,invoiced,sent,received',
            'parent_document_id' => 'nullable|exists:purchase_documents,id',
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
