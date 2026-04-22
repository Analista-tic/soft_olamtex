<?php

namespace App\Http\Controllers\Income;

use App\Enums\Income\DocumentPrefix;
use App\Enums\Income\QuoteStatus;
use App\Enums\Income\SalesOrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Income\SaleDocument;
use App\Services\Finance\SalesInvoiceNumberSequenceService;
use App\Services\Finance\SalesOrderNumberSequenceService;
use App\Services\Income\SaleDocumentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SalesOrderController extends Controller
{
    public function __construct(
        private readonly SaleDocumentService $service,
        private readonly SalesOrderNumberSequenceService $salesOrderSequenceService,
        private readonly SalesInvoiceNumberSequenceService $salesInvoiceSequenceService,
    ) {
    }

    public function index(Request $request)
    {
        $perPage = (int) $request->integer('per_page', 10);
        $filters = $request->only(['search', 'status', 'contact_id', 'client_id', 'date_from', 'date_to', 'origin']);
        $documents = $this->service->paginateDocuments(
            SaleDocument::TYPE_SALES_ORDER, $filters, $perPage,
            $request->user(), 'ver propios pedidos venta', 'ver area pedidos venta', 'ver todos pedidos venta'
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
        $payload = $this->validateSalesOrderPayload($request);
        $items = $payload['items'] ?? [];
        $originQuote = $this->resolveOriginQuote($payload['parent_document_id'] ?? null);

        $salesOrder = DB::transaction(function () use ($payload, $items, $originQuote) {
            $status = $payload['status'] ?? SalesOrderStatus::DRAFT->value;

            $salesOrder = $this->service->createDocument([
                ...$payload,
                'document_type' => SaleDocument::TYPE_SALES_ORDER,
                'quote_id' => $originQuote?->id,
                'status' => $status,
                'document_number' => $this->resolveSalesOrderDocumentNumber($payload['document_number'] ?? null),
            ], $items);

            if ($originQuote && $originQuote->status !== QuoteStatus::CONVERTED->value) {
                $originQuote->update(['status' => QuoteStatus::CONVERTED->value]);
            }

            return $salesOrder;
        });

        return response()->json([
            'message' => 'Pedido de venta creado correctamente.',
            'data' => $salesOrder,
        ], 201);
    }

    public function show(SaleDocument $saleDocument)
    {
        $salesOrder = $this->resolveSalesOrder($saleDocument);

        return response()->json([
            'data' => $this->service->loadWithRelations($salesOrder),
        ]);
    }

    public function update(Request $request, SaleDocument $saleDocument)
    {
        $salesOrder = $this->resolveSalesOrder($saleDocument);

        if (!$salesOrder->canEditSalesOrder()) {
            throw ValidationException::withMessages([
                'document' => 'Solo se pueden editar pedidos de venta en estado borrador o confirmado.',
            ]);
        }

        $payload = $this->validateSalesOrderPayload($request, true);
        unset($payload['document_number']);
        $originQuote = array_key_exists('parent_document_id', $payload)
            ? $this->resolveOriginQuote($payload['parent_document_id'] ?? null)
            : ($salesOrder->quote_id ? SaleDocument::find($salesOrder->quote_id) : null);
        $items = $request->has('items') ? ($payload['items'] ?? []) : null;

        $updated = DB::transaction(function () use ($salesOrder, $payload, $items, $originQuote) {
            $lockedSalesOrder = SaleDocument::query()
                ->lockForUpdate()
                ->findOrFail($salesOrder->id);

            $updated = $this->service->updateDocument($lockedSalesOrder, [
                ...$payload,
                'quote_id' => array_key_exists('parent_document_id', $payload) ? $originQuote?->id : $salesOrder->quote_id,
            ], $items);

            if ($originQuote && $originQuote->status !== QuoteStatus::CONVERTED->value) {
                $originQuote->update(['status' => QuoteStatus::CONVERTED->value]);
            }

            return $updated;
        });

        return response()->json([
            'message' => 'Pedido de venta actualizado correctamente.',
            'data' => $updated,
        ]);
    }

    public function destroy(SaleDocument $saleDocument)
    {
        $salesOrder = $this->resolveSalesOrder($saleDocument);

        if (!$salesOrder->canDeleteSalesOrder()) {
            throw ValidationException::withMessages([
                'document' => 'Solo se pueden eliminar pedidos de venta en estado borrador.',
            ]);
        }

        $this->service->deleteDocument($salesOrder);

        return response()->json([
            'message' => 'Pedido de venta eliminado correctamente.',
        ]);
    }

    public function convert(SaleDocument $salesOrder)
    {
        $salesInvoice = DB::transaction(function () use ($salesOrder) {
            $salesOrder = SaleDocument::query()
                ->with('items')
                ->whereKey($salesOrder->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->resolveSalesOrder($salesOrder);

            if (!$salesOrder->canConvertSalesOrder()) {
                throw ValidationException::withMessages([
                    'document' => 'Solo se pueden convertir pedidos confirmados o en proceso.',
                ]);
            }

            if ($salesOrder->children()->where('document_type', SaleDocument::TYPE_INVOICE)->exists()) {
                throw ValidationException::withMessages([
                    'document' => 'El pedido de venta ya tiene una factura asociada.',
                ]);
            }

            $salesInvoice = $this->service->createDocument([
                'document_type' => SaleDocument::TYPE_INVOICE,
                'parent_document_id' => $salesOrder->id,
                'contact_id' => $salesOrder->contact_id,
                'date' => $salesOrder->date?->toDateString() ?? now()->toDateString(),
                'due_date' => $salesOrder->due_date?->toDateString(),
                'description' => $salesOrder->description,
                'payment_terms' => $salesOrder->payment_terms,
                'payment_days' => $salesOrder->payment_days,
                'customer_order_number' => $salesOrder->customer_order_number,
                'document_number' => SaleDocument::formatDocumentNumber(
                    DocumentPrefix::SALES_INVOICE->value,
                    $this->salesInvoiceSequenceService->reserveNextNumber()
                ),
            ], $salesOrder->items->map(function ($item) {
                return [
                    'item_id' => $item->item_id,
                    'item_name' => $item->item_name,
                    'quantity' => $item->quantity,
                    'price' => $item->price,
                    'discount' => $item->discount ?? 0,
                    'tax' => $item->tax,
                    'total' => $item->total,
                    'total_tax' => $item->total_tax,
                    'description' => $item->description,
                    'supplier_code' => $item->supplier_code,
                    'supplier_color' => $item->supplier_color,
                    'order_width' => $item->order_width,
                ];
            })->toArray());

            $salesOrder->update(['status' => SalesOrderStatus::INVOICED->value]);

            return $salesInvoice;
        });

        return response()->json([
            'message' => 'Pedido de venta convertido a factura correctamente.',
            'data' => $salesInvoice,
        ]);
    }

    private function resolveSalesOrder(SaleDocument $document): SaleDocument
    {
        if (!$document->isSalesOrder()) {
            abort(404);
        }

        return $document;
    }

    private function resolveOriginQuote(mixed $parentDocumentId): ?SaleDocument
    {
        if (empty($parentDocumentId)) {
            return null;
        }

        $quote = SaleDocument::query()->findOrFail($parentDocumentId);

        if (!$quote->isQuote()) {
            throw ValidationException::withMessages([
                'parent_document_id' => 'El documento de origen debe ser una cotizacion.',
            ]);
        }

        return $quote;
    }

    private function resolveSalesOrderDocumentNumber(?string $currentNumber): ?string
    {
        if (! empty($currentNumber)) {
            return $currentNumber;
        }

        return SaleDocument::formatDocumentNumber(
            DocumentPrefix::SALES_ORDER->value,
            $this->salesOrderSequenceService->reserveNextNumber()
        );
    }

    private function validateSalesOrderPayload(Request $request, bool $isUpdate = false): array
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
            'status' => ['nullable', Rule::in(array_map(static fn (SalesOrderStatus $status) => $status->value, SalesOrderStatus::cases()))],
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
