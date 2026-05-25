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

            return response()->json(['message' => 'Correo enviado correctamente.']);
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

        // Solo admin puede aprobar cualquier orden; los demás solo las propias
        $user = $request->user();
        if ($user && !$user->hasRole('admin')) {
            if ((int) $purchaseDocument->buyer_user_id !== (int) $user->id) {
                return response()->json([
                    'message' => 'No tienes permiso para aprobar esta orden.',
                ], 403);
            }
        }

        // Verificar cobertura de cantidades cuando hay solicitud vinculada
        $coverageError = $this->service->checkRequestCoverage($purchaseDocument);
        if ($coverageError) {
            return response()->json(['message' => $coverageError], 422);
        }

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

}
