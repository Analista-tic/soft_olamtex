<?php

namespace App\Http\Controllers\Expenses;

use App\Http\Controllers\Expenses\Concerns\ValidatesPurchaseInvoiceRequest;
use App\Mail\PurchaseInvoiceMail;
use App\Models\Expenses\PurchaseDocument;
use App\Services\Expenses\PurchaseDocumentInvoiceService;
use Barryvdh\DomPDF\Facade\Pdf as DomPDF;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;

class PurchaseInvoiceController extends PurchaseDocumentController
{
    use ValidatesPurchaseInvoiceRequest;

    protected PurchaseDocumentInvoiceService $invoiceService;

    /** Fechas del recorrido solicitud → orden → recepción → factura, solo lectura. */
    public function traceability(PurchaseDocument $purchaseDocument)
    {
        return $this->traceabilityResponse($purchaseDocument);
    }

    /**
     * Marcar factura como recibida (NO tela)
     */
    public function receive(Request $request, PurchaseDocument $purchaseDocument)
    {
        $this->ensureDocumentType($purchaseDocument, PurchaseDocument::TYPE_INVOICE);
        $purchaseDocument = $this->service->findAccessibleDocumentOrFail($purchaseDocument, $request->user());

        $invoice = $this->invoiceService->receiveInvoice($purchaseDocument, Auth::id());

        return response()->json(['data' => $invoice]);
    }

    /**
     * Rechazar factura (NO tela) y guardar motivo/novedad
     */
    public function reject(Request $request, PurchaseDocument $purchaseDocument)
    {
        $this->ensureDocumentType($purchaseDocument, PurchaseDocument::TYPE_INVOICE);
        $purchaseDocument = $this->service->findAccessibleDocumentOrFail($purchaseDocument, $request->user());
        if (in_array($purchaseDocument->status, ['received', 'approved', 'rejected'], true)) {
            return response()->json(['message' => 'Solo se puede rechazar una factura pendiente de recepción.'], 422);
        }
        $validated = $request->validate([
            'rejection_reason' => 'required|string',
            'rejection_notes' => 'nullable|string',
        ]);
        $purchaseDocument->status = 'rejected';
        $purchaseDocument->rejection_reason = $validated['rejection_reason'];
        $purchaseDocument->rejection_notes = $validated['rejection_notes'] ?? null;
        $purchaseDocument->rejected_by_user_id = Auth::id();
        $purchaseDocument->rejected_at = now();
        $purchaseDocument->save();

        $number = $purchaseDocument->document_number ?? (string) $purchaseDocument->id;
        app(\App\Services\Settings\NotificationDispatcher::class)->dispatch(
            'purchase_invoice_rejected',
            new \App\Notifications\GenericEventNotification(
                'purchase_invoice_rejected',
                'Gastos',
                "Factura #{$number} rechazada",
                "La factura {$number} fue rechazada. Motivo: {$validated['rejection_reason']}.",
                '/gastos/facturas-compra'
            ),
            ['actor' => $request->user(), 'creator' => $purchaseDocument->creator]
        );

        // Una factura rechazada deja de contar como facturado en su orden.
        if ($purchaseDocument->parent_document_id) {
            $parentOrder = PurchaseDocument::find($purchaseDocument->parent_document_id);
            if ($parentOrder) {
                $this->service->recalculateOrderStatus($parentOrder);
            }
        }
        return response()->json(['data' => $purchaseDocument]);
    }
    public function __construct(PurchaseDocumentInvoiceService $service)
    {
        $this->invoiceService = $service;
        parent::__construct($service);
    }

    public function index()
    {
        return $this->indexView('Expenses/PurchaseInvoices/Index');
    }

    public function getData(Request $request)
    {

        $filters = $this->buildFilters($request);
        $filters['exclude_fabric'] = true;
        $documents = $this->service->paginateDocuments(
            PurchaseDocument::TYPE_INVOICE,
            $filters,
            $request->input('per_page', 20),
            $request,
            Auth::user()
        );

        return response()->json([
            'data' => $documents->items(),
            'total' => $documents->total(),
            'current_page' => $documents->currentPage(),
        ]);
    }

    public function creditDebitNotes(PurchaseDocument $purchaseDocument)
    {
        $this->ensureDocumentType($purchaseDocument, PurchaseDocument::TYPE_INVOICE);
        $purchaseDocument = $this->service->findAccessibleDocumentOrFail($purchaseDocument, request()->user());

        return response()->json([
            'data' => $this->invoiceService->getLinkedCreditDebitNotes($purchaseDocument),
        ]);
    }

    public function notesSummary(PurchaseDocument $purchaseDocument)
    {
        $this->ensureDocumentType($purchaseDocument, PurchaseDocument::TYPE_INVOICE);
        $purchaseDocument = $this->service->findAccessibleDocumentOrFail($purchaseDocument, request()->user());

        return response()->json([
            'data' => $this->invoiceService->getInvoiceNotesSummary($purchaseDocument),
        ]);
    }

    public function store(Request $request)
    {
        $payload = $this->validateInvoiceRequest($request);
        $this->validateOrderFabricCompatibility(
            isset($payload['parent_document_id']) ? (int) $payload['parent_document_id'] : null,
            false
        );
        $items = $payload['items'] ?? [];
        $withholdings = $payload['withholdings'] ?? [];
        $invoice = $this->invoiceService->createInvoice($payload, $items, $withholdings);

        return response()->json(['data' => $invoice], 201);
    }

    public function show(PurchaseDocument $purchaseDocument)
    {
        return $this->showGeneric($purchaseDocument, PurchaseDocument::TYPE_INVOICE);
    }

    public function update(Request $request, PurchaseDocument $purchaseDocument)
    {
        $this->ensureDocumentType($purchaseDocument, PurchaseDocument::TYPE_INVOICE);

        if ($purchaseDocument->approval_status === PurchaseDocument::APPROVAL_APPROVED
            || $purchaseDocument->status === 'received') {
            return response()->json([
                'message' => 'No se puede editar una factura aprobada o recibida.'
            ], 422);
        }

        return $this->updateGeneric(
            $request,
            $purchaseDocument,
            PurchaseDocument::TYPE_INVOICE,
            fn($req, $upd) => $this->validateInvoiceRequest($req, $upd)
        );
    }

    public function destroy(PurchaseDocument $purchaseDocument)
    {
        $this->ensureDocumentType($purchaseDocument, PurchaseDocument::TYPE_INVOICE);

        if ($purchaseDocument->approval_status === PurchaseDocument::APPROVAL_APPROVED
            || $purchaseDocument->status === 'received') {
            return response()->json([
                'message' => 'No se puede eliminar una factura aprobada o recibida.'
            ], 422);
        }

        return $this->destroyGeneric(
            $purchaseDocument,
            PurchaseDocument::TYPE_INVOICE,
            'Factura eliminada'
        );
    }

    public function justify(Request $request, PurchaseDocument $purchaseDocument)
    {
        $this->ensureDocumentType($purchaseDocument, PurchaseDocument::TYPE_INVOICE);
        $purchaseDocument = $this->service->findAccessibleDocumentOrFail($purchaseDocument, $request->user());

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

    public function approveDifference(Request $request, PurchaseDocument $purchaseDocument)
    {
        $this->ensureDocumentType($purchaseDocument, PurchaseDocument::TYPE_INVOICE);
        $purchaseDocument = $this->service->findAccessibleDocumentOrFail($purchaseDocument, $request->user());

        $validated = $request->validate([
            'approval_notes' => 'nullable|string|max:1000',
        ]);

        $invoice = $this->service->approveDifference(
            $purchaseDocument,
            $validated['approval_notes'] ?? null,
            Auth::id()
        );

        return response()->json(['data' => $invoice]);
    }

    /**
     * Render printable view (Inertia) for the purchase invoice.
     */
    public function print(PurchaseDocument $purchaseDocument)
    {
        $this->ensureDocumentType($purchaseDocument, PurchaseDocument::TYPE_INVOICE);
        $purchaseDocument = $this->service->findAccessibleDocumentOrFail($purchaseDocument, request()->user());

        $invoice = $this->service->loadWithRelations($purchaseDocument);

        return Inertia::render('Expenses/PurchaseInvoices/PurchaseInvoicePrint', [
            'invoice' => $invoice,
        ]);
    }

    public function sendInvoiceMail(PurchaseDocument $purchaseDocument)
    {
        $this->ensureDocumentType($purchaseDocument, PurchaseDocument::TYPE_INVOICE);
        $purchaseDocument = $this->service->findAccessibleDocumentOrFail($purchaseDocument, request()->user());

        try {
            $invoice = $this->service->loadWithRelations($purchaseDocument);
            $email = $invoice->supplier?->email;

            if (empty($email)) {
                throw new \RuntimeException('El proveedor no tiene correo electrónico registrado.');
            }

            $pdf = DomPDF::loadView('pdf.purchase_invoice', ['invoice' => $invoice]);
            Mail::to($email)->send(new PurchaseInvoiceMail($invoice, $pdf->output()));

            return response()->json(['message' => 'Correo enviado correctamente.']);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            Log::error('Error enviando correo de factura de compra: ' . $e->getMessage());
            return response()->json(['message' => 'Error enviando el correo: ' . $e->getMessage()], 500);
        }
    }

    public function rejectDifference(Request $request, PurchaseDocument $purchaseDocument)
    {
        $this->ensureDocumentType($purchaseDocument, PurchaseDocument::TYPE_INVOICE);
        $purchaseDocument = $this->service->findAccessibleDocumentOrFail($purchaseDocument, $request->user());

        $validated = $request->validate([
            'approval_notes' => 'required|string|max:1000',
        ]);

        $invoice = $this->service->rejectDifference(
            $purchaseDocument,
            $validated['approval_notes'],
            Auth::id()
        );

        return response()->json(['data' => $invoice]);
    }

    protected function buildFilters(Request $request): array
    {
        $filters = $request->only(['search', 'date_from', 'date_to', 'status', 'provider_id', 'contact_id', 'approval_status', 'exclude_difference_rejected']);

        $contactId = (int) ($request->input('contact_id') ?? 0);
        if ($contactId > 0) {
            $filters['contact_id'] = $contactId;
        } else {
            unset($filters['contact_id']);
        }

        return $filters;
    }
}
