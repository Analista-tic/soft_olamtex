<?php

namespace App\Http\Controllers\Expenses;

use App\Http\Controllers\Controller;
use App\Mail\PurchaseOrderMail;
use App\Models\Expenses\PurchaseDocument;
use App\Services\Expenses\PurchaseDocumentService;
use Barryvdh\DomPDF\Facade\Pdf as DomPDF;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;

class PurchaseOrderController extends Controller
{
    public function __construct(protected PurchaseDocumentService $service) {}

    public function index()
    {
        return Inertia::render('Expenses/PurchaseOrders/Index');
    }

    public function getData(Request $request)
    {
        $filters = $request->only(['search', 'status', 'provider_id', 'date_from', 'date_to']);
        $documents = $this->service->paginateDocuments(
            PurchaseDocument::TYPE_ORDER,
            $filters,
            $request->input('per_page', 10)
        );

        return response()->json([
            'data' => $documents->items(),
            'total' => $documents->total(),
            'current_page' => $documents->currentPage(),
        ]);
    }

    public function store(Request $request)
    {
        $payload = $this->validateOrderPayload($request);
        $items = $payload['items'] ?? [];
        $order = $this->service->createOrder($payload, $items);

        return response()->json([
            'message' => 'Purchase order created.',
            'data' => $order,
        ], 201);
    }

    public function show(PurchaseDocument $purchaseDocument)
    {
        $this->ensureDocumentType($purchaseDocument, PurchaseDocument::TYPE_ORDER);
        return response()->json(['data' => $this->service->loadWithRelations($purchaseDocument)]);
    }

    public function update(Request $request, PurchaseDocument $purchaseDocument)
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

    public function destroy(PurchaseDocument $purchaseDocument)
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

    protected function ensureDocumentType(PurchaseDocument $document, string $type): void
    {
        if ($document->document_type !== $type) {
            abort(404);
        }
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
