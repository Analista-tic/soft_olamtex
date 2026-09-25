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
        return $this->indexView('Expenses/PurchaseOrders/Index');
    }

    public function create()
    {
        return Inertia::render('Expenses/PurchaseOrders/PurchaseOrderForm', [
            'mode' => 'create',
        ]);
    }

    public function edit(PurchaseDocument $purchaseDocument)
    {
        $this->ensureDocumentType($purchaseDocument, PurchaseDocument::TYPE_ORDER);
        $purchaseDocument = $this->service->findAccessibleDocumentOrFail($purchaseDocument, request()->user());

        $order = $this->service->loadWithRelations($purchaseDocument);

        return Inertia::render('Expenses/PurchaseOrders/PurchaseOrderForm', [
            'mode' => 'edit',
            'form' => $order,
        ]);
    }

    public function getData(Request $request)
    {
        return $this->getDataGeneric($request, PurchaseDocument::TYPE_ORDER, 10);
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
        $order = $this->service->findAccessibleDocumentOrFail($purchaseDocument, request()->user());

        // Calcular cantidades ya facturadas por ítem
        $invoicedQtyByItemId = \App\Models\Expenses\PurchaseDocumentItem::whereIn(
            'purchase_document_id',
            \App\Models\Expenses\PurchaseDocument::where('parent_document_id', $order->id)
                ->where('document_type', \App\Models\Expenses\PurchaseDocument::TYPE_INVOICE)
                ->whereNull('deleted_at')
                ->pluck('id')
        )->get()->groupBy('item_id')->map(fn($g) => $g->sum(fn($i) => (float) $i->quantity));

        $order->items->each(function ($item) use ($invoicedQtyByItemId) {
            $item->already_invoiced_qty = (float) ($invoicedQtyByItemId[$item->item_id] ?? 0);
        });

        return response()->json(['data' => $order]);
    }

    public function update(Request $request, PurchaseDocument $purchaseDocument)
    {
        return $this->updateGeneric(
            $request,
            $purchaseDocument,
            PurchaseDocument::TYPE_ORDER,
            fn($req, $upd) => $this->validateOrderPayload($req, $upd)
        );
    }

    public function destroy(PurchaseDocument $purchaseDocument)
    {
        return $this->destroyGeneric($purchaseDocument, PurchaseDocument::TYPE_ORDER, 'Purchase order deleted.');
    }

    public function sendOrderPdf(PurchaseDocument $purchaseDocument)
    {
        $this->ensureDocumentType($purchaseDocument, PurchaseDocument::TYPE_ORDER);
        $purchaseDocument = $this->service->findAccessibleDocumentOrFail($purchaseDocument, request()->user());

        try {
            $order = $this->service->loadWithRelations($purchaseDocument);
            $email = $order->supplier?->email;

            if (empty($email)) {
                throw new \RuntimeException('El proveedor no tiene correo electrónico registrado.');
            }

            $pdf = DomPDF::loadView('pdf.purchase_order', ['order' => $order]);
            Mail::to($email)->send(new PurchaseOrderMail($order, $pdf->output()));

            $purchaseDocument->forceFill(['sent_at' => now()])->save();

            return response()->json(['message' => 'Correo enviado correctamente.', 'sent_at' => $purchaseDocument->sent_at]);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            Log::error('Error enviando correo de orden de compra: ' . $e->getMessage());
            return response()->json(['message' => 'Error enviando el correo: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Render printable view (Inertia) for the purchase order.
     */
    public function print(PurchaseDocument $purchaseDocument)
    {
        $this->ensureDocumentType($purchaseDocument, PurchaseDocument::TYPE_ORDER);
        $purchaseDocument = $this->service->findAccessibleDocumentOrFail($purchaseDocument, request()->user());

        $order = $this->service->loadWithRelations($purchaseDocument);

        return Inertia::render('Expenses/PurchaseOrders/PurchaseOrderPrint', [
            'order' => $order,
        ]);
    }

    public function convertToInvoice(PurchaseDocument $purchaseDocument)
    {
        $this->ensureDocumentType($purchaseDocument, PurchaseDocument::TYPE_ORDER);
        $purchaseDocument = $this->service->findAccessibleDocumentOrFail($purchaseDocument, request()->user());

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

    public function approve(PurchaseDocument $purchaseDocument)
    {
        $this->ensureDocumentType($purchaseDocument, PurchaseDocument::TYPE_ORDER);
        $purchaseDocument = $this->service->findAccessibleDocumentOrFail($purchaseDocument, request()->user());

        // Validar que la orden pueda ser aprobada
        if (!$purchaseDocument->canApprove()) {
            return response()->json([
                'message' => 'Esta orden no puede ser aprobada desde su estado actual.',
            ], 422);
        }

        // La autorización la da el permiso 'aprobar orden compra' del middleware de la ruta.

        $updated = $this->service->updateDocument($purchaseDocument, [
            'status' => \App\Enums\PurchaseOrderStatus::APPROVED->value,
            'approval_status' => 'approved',
            'approved_by_user_id' => \Illuminate\Support\Facades\Auth::id(),
            'approved_at' => now(),
        ]);

        return response()->json([
            'message' => 'Orden de compra aprobada correctamente.',
            'data' => $updated->toArray(),
        ]);
    }

    public function reject(Request $request, PurchaseDocument $purchaseDocument)
    {
        $this->ensureDocumentType($purchaseDocument, PurchaseDocument::TYPE_ORDER);
        $purchaseDocument = $this->service->findAccessibleDocumentOrFail($purchaseDocument, $request->user());

        if (! $purchaseDocument->canRejectOrder()) {
            return response()->json([
                'message' => 'Solo se pueden rechazar órdenes pendientes de aprobación.',
            ], 422);
        }

        $validated = $request->validate([
            'rejection_reason' => 'required|string|max:1000',
        ]);

        $updated = $this->service->updateDocument($purchaseDocument, [
            'status' => \App\Enums\PurchaseOrderStatus::REJECTED->value,
            'rejection_reason' => $validated['rejection_reason'],
            'rejected_by_user_id' => \Illuminate\Support\Facades\Auth::id(),
            'rejected_at' => now(),
        ]);

        if ($updated->purchase_request_id) {
            $this->service->checkAndMarkRequestAsProcessed((int) $updated->purchase_request_id);
        }

        return response()->json([
            'message' => 'Orden de compra rechazada.',
            'data' => $updated->toArray(),
        ]);
    }

    public function close(Request $request, PurchaseDocument $purchaseDocument)
    {
        $this->ensureDocumentType($purchaseDocument, PurchaseDocument::TYPE_ORDER);
        $purchaseDocument = $this->service->findAccessibleDocumentOrFail($purchaseDocument, $request->user());

        if ($purchaseDocument->status !== \App\Enums\PurchaseOrderStatus::PARTIALLY_INVOICED->value) {
            return response()->json([
                'message' => 'Solo se pueden cerrar órdenes parcialmente facturadas.',
            ], 422);
        }

        $request->validate([
            'closure_reason' => 'required|string|max:1000',
        ]);

        $updated = $this->service->updateDocument($purchaseDocument, [
            'status'         => \App\Enums\PurchaseOrderStatus::INVOICED->value,
            'closure_reason' => $request->input('closure_reason'),
        ]);

        if ($updated->purchase_request_id) {
            $this->service->checkAndMarkRequestAsProcessed((int) $updated->purchase_request_id);
        }

        return response()->json([
            'message' => 'Orden cerrada correctamente.',
            'data' => $updated->toArray(),
        ]);
    }

}
