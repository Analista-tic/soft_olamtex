<?php

namespace App\Http\Controllers\Expenses;

use App\Mail\PurchaseOrderMail;
use App\Models\Expenses\PurchaseDocument;
use App\Services\Expenses\PurchaseDocumentOrderService;
use Barryvdh\DomPDF\Facade\Pdf as DomPDF;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;

class PurchaseOrderController extends PurchaseDocumentController
{
    public function __construct(PurchaseDocumentOrderService $service)
    {
        parent::__construct($service);
    }

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

        if ($purchaseDocument->children()
            ->where('document_type', PurchaseDocument::TYPE_INVOICE)
            ->exists()
        ) {
            throw new \RuntimeException('La orden de compra ya tiene una factura vinculada.');
        }

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

}
