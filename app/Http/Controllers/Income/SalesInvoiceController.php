<?php

namespace App\Http\Controllers\Income;

use App\Enums\Income\DocumentPrefix;
use App\Enums\Income\PaymentStatus;
use App\Enums\Income\SalesInvoiceStatus;
use App\Http\Controllers\Controller;
use App\Models\Income\SaleDocument;
use App\Services\Finance\SalesInvoiceNumberSequenceService;
use App\Services\Income\SaleDocumentService;
use App\Services\Income\SalesInvoiceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SalesInvoiceController extends Controller
{
    public function __construct(
        private readonly SaleDocumentService $service,
        private readonly SalesInvoiceService $salesInvoiceService,
        private readonly SalesInvoiceNumberSequenceService $salesInvoiceSequenceService,
    ) {
    }

    public function index(Request $request)
    {
        $perPage = (int) $request->integer('per_page', 10);
        $filters = $request->only(['search', 'status', 'payment_status', 'contact_id', 'client_id', 'date_from', 'date_to', 'origin']);
        $documents = $this->service->paginateDocuments(
            SaleDocument::TYPE_INVOICE, $filters, $perPage,
            $request->user(), 'ver propios facturas venta', 'ver area facturas venta', 'ver todos facturas venta'
        );

        $documents->setCollection(
            $documents->getCollection()
                ->map(fn (SaleDocument $invoice) => $this->salesInvoiceService->hydratePaymentState($invoice))
                ->values()
        );

        return response()->json([
            'data' => $documents->items(),
            'total' => $documents->total(),
            'current_page' => $documents->currentPage(),
            'per_page' => $documents->perPage(),
            'last_page' => $documents->lastPage(),
        ]);
    }

    public function store(Request $request)
    {
        $payload = $this->validateSalesInvoicePayload($request);
        $items = $payload['items'] ?? [];
        $this->resolveParentSalesOrder($payload['parent_document_id'] ?? null);

        $invoice = DB::transaction(function () use ($payload, $items) {
            return $this->service->createDocument([
                ...$payload,
                'document_type' => SaleDocument::TYPE_INVOICE,
                'status' => SalesInvoiceStatus::DRAFT->value,
                'payment_status' => PaymentStatus::UNPAID->value,
                'amount_paid' => 0,
                'document_number' => ! empty($payload['document_number'])
                    ? $payload['document_number']
                    : SaleDocument::formatDocumentNumber(
                        DocumentPrefix::SALES_INVOICE->value,
                        $this->salesInvoiceSequenceService->reserveNextNumber()
                    ),
            ], $items);
        });

        return response()->json([
            'message' => 'Factura de venta creada correctamente.',
            'data' => $this->salesInvoiceService->hydratePaymentState($invoice),
        ], 201);
    }

    public function show(SaleDocument $saleDocument)
    {
        $invoice = $this->resolveSalesInvoice($saleDocument);

        return response()->json([
            'data' => $this->salesInvoiceService->hydratePaymentState($this->service->loadWithRelations($invoice)),
        ]);
    }

    public function update(Request $request, SaleDocument $saleDocument)
    {
        $invoice = $this->resolveSalesInvoice($saleDocument);

        if (! $invoice->canEditInvoice()) {
            throw ValidationException::withMessages([
                'document' => 'Solo se pueden editar facturas de venta en estado borrador.',
            ]);
        }

        $payload = $this->validateSalesInvoicePayload($request, true);
        unset($payload['document_number']);
        if (array_key_exists('parent_document_id', $payload)) {
            $this->resolveParentSalesOrder($payload['parent_document_id'] ?? null);
        }

        $items = $request->has('items') ? ($payload['items'] ?? []) : null;
        $updated = $this->service->updateDocument($invoice, $payload, $items);

        return response()->json([
            'message' => 'Factura de venta actualizada correctamente.',
            'data' => $this->salesInvoiceService->hydratePaymentState($updated),
        ]);
    }

    public function destroy(SaleDocument $saleDocument)
    {
        $invoice = $this->resolveSalesInvoice($saleDocument);

        if (! $invoice->canDeleteInvoice()) {
            throw ValidationException::withMessages([
                'document' => 'Solo se pueden eliminar facturas de venta en estado borrador.',
            ]);
        }

        $this->service->deleteDocument($invoice);

        return response()->json([
            'message' => 'Factura de venta eliminada correctamente.',
        ]);
    }

    public function emit(SaleDocument $salesInvoice)
    {
        $invoice = $this->resolveSalesInvoice($salesInvoice);
        $emitted = $this->salesInvoiceService->emit($invoice);

        return response()->json([
            'message' => 'Factura de venta emitida correctamente.',
            'data' => $emitted,
        ]);
    }

    public function void(Request $request, SaleDocument $salesInvoice)
    {
        $invoice = $this->resolveSalesInvoice($salesInvoice);
        $validated = $request->validate([
            'reason' => 'nullable|string',
        ]);

        $voided = $this->salesInvoiceService->void($invoice, (string) ($validated['reason'] ?? ''));

        return response()->json([
            'message' => 'Factura de venta anulada correctamente.',
            'data' => $voided,
        ]);
    }

    private function resolveSalesInvoice(SaleDocument $document): SaleDocument
    {
        if (! $document->isInvoice()) {
            abort(404);
        }

        return $document;
    }

    private function resolveParentSalesOrder(mixed $parentDocumentId): ?SaleDocument
    {
        if (empty($parentDocumentId)) {
            return null;
        }

        $salesOrder = SaleDocument::query()->findOrFail($parentDocumentId);

        if (! $salesOrder->isSalesOrder()) {
            throw ValidationException::withMessages([
                'parent_document_id' => 'El documento de origen debe ser un pedido de venta.',
            ]);
        }

        return $salesOrder;
    }

    private function validateSalesInvoicePayload(Request $request, bool $isUpdate = false): array
    {
        $rules = [
            'document_number' => 'nullable|string',
            'parent_document_id' => 'nullable|exists:sale_documents,id',
            'contact_id' => 'required|exists:contacts,id',
            'date' => 'required|date',
            'due_date' => 'nullable|date|after_or_equal:date',
            'description' => 'nullable|string',
            'payment_terms' => 'nullable|in:cash,credit',
            'payment_days' => 'nullable|integer|min:0',
            'customer_order_number' => 'nullable|string',
            'status' => ['nullable', Rule::in([SalesInvoiceStatus::DRAFT->value])],
            'withholdings' => 'nullable|array',
            'withholdings.*.name' => 'required_with:withholdings|string',
            'withholdings.*.percentage' => 'required_with:withholdings|numeric|min:0',
            'withholdings.*.base_amount' => 'nullable|numeric|min:0',
            'items' => $isUpdate ? 'sometimes|array|min:1' : 'required|array|min:1',
            'items.*.item_id' => 'required|exists:items,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|min:0|max:100',
            'items.*.tax_rate' => 'nullable|numeric|min:0',
            'items.*.description' => 'nullable|string',
        ];

        return $request->validate($rules);
    }
}
