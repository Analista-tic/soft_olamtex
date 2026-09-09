<?php

namespace App\Http\Controllers\Income;

use App\Enums\Income\DocumentPrefix;
use App\Enums\Income\QuoteStatus;
use App\Enums\Income\SalesOrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Income\SaleDocument;
use App\Services\Finance\QuoteNumberSequenceService;
use App\Services\Finance\SalesOrderNumberSequenceService;
use App\Services\Income\SaleDocumentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class QuoteController extends Controller
{
    public function __construct(
        private readonly SaleDocumentService $service,
        private readonly QuoteNumberSequenceService $quoteSequenceService,
        private readonly SalesOrderNumberSequenceService $salesOrderSequenceService,
    ) {}

    public function index(Request $request)
    {
        $perPage = (int) $request->integer('per_page', 10);
        $filters = $request->only(['search', 'status', 'contact_id', 'client_id', 'date_from', 'date_to', 'sort_by', 'sort_direction']);
        $documents = $this->service->paginateDocuments(
            SaleDocument::TYPE_QUOTE, $filters, $perPage,
            $request->user(), 'ver propios cotizaciones', 'ver area cotizaciones', 'ver todos cotizaciones'
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
        $payload = $this->validateQuotePayload($request);
        $items = $payload['items'] ?? [];

        $quote = DB::transaction(function () use ($payload, $items) {
            $status = $payload['status'] ?? QuoteStatus::DRAFT->value;

            return $this->service->createDocument([
                ...$payload,
                'document_type' => SaleDocument::TYPE_QUOTE,
                'status' => $status,
                'document_number' => $this->resolveQuoteDocumentNumber($payload['document_number'] ?? null),
            ], $items);
        });

        return response()->json([
            'message' => 'Cotización creada correctamente.',
            'data' => $quote,
        ], 201);
    }

    public function show(SaleDocument $saleDocument)
    {
        $quote = $this->resolveAccessibleQuote($saleDocument, request()->user());

        return response()->json([
            'data' => $this->service->loadWithRelations($quote),
        ]);
    }

    public function update(Request $request, SaleDocument $saleDocument)
    {
        $quote = $this->resolveAccessibleQuote($saleDocument, $request->user());

        if (! $quote->canEditQuote()) {
            throw ValidationException::withMessages([
                'document' => 'No se puede editar una cotización aceptada o convertida.',
            ]);
        }

        $payload = $this->validateQuotePayload($request, true);
        unset($payload['document_number']);
        $items = $request->has('items') ? ($payload['items'] ?? []) : null;

        $updated = DB::transaction(function () use ($quote, $payload, $items) {
            $lockedQuote = SaleDocument::query()
                ->lockForUpdate()
                ->findOrFail($quote->id);

            return $this->service->updateDocument($lockedQuote, $payload, $items);
        });

        return response()->json([
            'message' => 'Cotización actualizada correctamente.',
            'data' => $updated,
        ]);
    }

    public function destroy(SaleDocument $saleDocument)
    {
        $quote = $this->resolveAccessibleQuote($saleDocument, request()->user());

        if (! $quote->canDeleteQuote()) {
            throw ValidationException::withMessages([
                'document' => 'Solo se pueden eliminar cotizaciones en estado borrador.',
            ]);
        }

        $this->service->deleteDocument($quote);

        return response()->json([
            'message' => 'Cotización eliminada correctamente.',
        ]);
    }

    public function convert(SaleDocument $quote)
    {
        $this->resolveAccessibleQuote($quote, request()->user());

        $salesOrder = DB::transaction(function () use ($quote) {
            $quote = SaleDocument::query()
                ->with('items')
                ->whereKey($quote->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->resolveQuote($quote);

            if (! $quote->canConvertQuote()) {
                throw ValidationException::withMessages([
                    'document' => 'Solo se pueden convertir cotizaciones enviadas o aceptadas.',
                ]);
            }

            if ($quote->children()->where('document_type', SaleDocument::TYPE_SALES_ORDER)->exists()) {
                throw ValidationException::withMessages([
                    'document' => 'La cotización ya tiene un pedido asociado.',
                ]);
            }

            $salesOrder = $this->service->createDocument([
                'document_type' => SaleDocument::TYPE_SALES_ORDER,
                'parent_document_id' => $quote->id,
                'quote_id' => $quote->id,
                'contact_id' => $quote->contact_id,
                'date' => $quote->date?->toDateString() ?? now()->toDateString(),
                'description' => $quote->description,
                'payment_terms' => $quote->payment_terms,
                'payment_days' => $quote->payment_days,
                'status' => SalesOrderStatus::CONFIRMED->value,
                'document_number' => SaleDocument::formatDocumentNumber(
                    DocumentPrefix::SALES_ORDER->value,
                    $this->salesOrderSequenceService->reserveNextNumber()
                ),
            ], $quote->items->map(function ($item) {
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

            $quote->update(['status' => QuoteStatus::CONVERTED->value]);

            return $salesOrder;
        });

        return response()->json([
            'message' => 'Cotización convertida a pedido correctamente.',
            'data' => $salesOrder,
        ]);
    }

    private function resolveQuote(SaleDocument $document): SaleDocument
    {
        if (! $document->isQuote()) {
            abort(404);
        }

        return $document;
    }

    private function resolveAccessibleQuote(SaleDocument $document, mixed $user): SaleDocument
    {
        return $this->service->findAccessibleDocumentOrFail(
            $this->resolveQuote($document),
            $user,
            'ver propios cotizaciones',
            'ver area cotizaciones',
            'ver todos cotizaciones',
        );
    }

    private function resolveQuoteDocumentNumber(?string $currentNumber): ?string
    {
        if (! empty($currentNumber)) {
            return $currentNumber;
        }

        return SaleDocument::formatDocumentNumber(
            DocumentPrefix::QUOTE->value,
            $this->quoteSequenceService->reserveNextNumber()
        );
    }

    private function validateQuotePayload(Request $request, bool $isUpdate = false): array
    {
        $rules = [
            'document_number' => 'nullable|string',
            'contact_id' => 'required|exists:contacts,id',
            'date' => 'required|date',
            'due_date' => 'nullable|date|after_or_equal:date',
            'description' => 'nullable|string',
            'payment_terms' => 'nullable|in:cash,credit',
            'payment_days' => 'nullable|integer|min:0',
            'customer_order_number' => 'nullable|string',
            'status' => ['nullable', Rule::in(array_map(static fn (QuoteStatus $status) => $status->value, QuoteStatus::cases()))],
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
