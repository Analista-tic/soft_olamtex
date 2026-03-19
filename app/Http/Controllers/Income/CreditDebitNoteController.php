<?php

namespace App\Http\Controllers\Income;

use App\Enums\Income\DocumentPrefix;
use App\Enums\Income\SaleNoteStatus;
use App\Enums\Income\SaleNoteType;
use App\Enums\Income\SalesInvoiceStatus;
use App\Http\Controllers\Controller;
use App\Models\Income\SaleDocument;
use App\Services\Finance\CreditNoteNumberSequenceService;
use App\Services\Finance\DebitNoteNumberSequenceService;
use App\Services\Income\SaleDocumentService;
use App\Services\Income\SaleNoteService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CreditDebitNoteController extends Controller
{
    public function __construct(
        private readonly SaleDocumentService $service,
        private readonly SaleNoteService $saleNoteService,
        private readonly CreditNoteNumberSequenceService $creditNoteSequenceService,
        private readonly DebitNoteNumberSequenceService $debitNoteSequenceService,
    ) {
    }

    public function index(Request $request)
    {
        $perPage = (int) $request->integer('per_page', 10);
        $filters = $request->only(['search', 'status', 'contact_id', 'client_id', 'date_from', 'date_to']);
        $documents = $this->service->paginateDocumentsByTypes(
            $this->resolveRequestedTypes($request->query('type')),
            $filters,
            $perPage
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
        $payload = $this->validateNotePayload($request);
        $documentType = $this->resolveDocumentTypeFromRequest($payload['type'] ?? null);
        $parentInvoice = $this->resolveParentSalesInvoice($payload['parent_document_id'] ?? null);
        $items = $payload['items'] ?? [];

        $note = DB::transaction(function () use ($payload, $documentType, $parentInvoice, $items) {
            return $this->service->createDocument([
                ...$payload,
                'document_type' => $documentType,
                'contact_id' => $payload['contact_id'] ?? $parentInvoice->contact_id,
                'status' => SaleNoteStatus::DRAFT->value,
                'document_number' => ! empty($payload['document_number'])
                    ? $payload['document_number']
                    : $this->resolveNoteDocumentNumber($documentType),
            ], $items);
        });

        return response()->json([
            'message' => 'Nota de venta creada correctamente.',
            'data' => $note,
        ], 201);
    }

    public function show(SaleDocument $saleDocument)
    {
        $note = $this->resolveNote($saleDocument);
        $loaded = $this->service->loadWithRelations($note);
        $loaded->loadMissing('parent.client');

        return response()->json([
            'data' => $loaded,
        ]);
    }

    public function update(Request $request, SaleDocument $saleDocument)
    {
        $note = $this->resolveNote($saleDocument);

        if (! $note->canEditNote()) {
            throw ValidationException::withMessages([
                'document' => 'Solo se pueden editar notas de venta en estado borrador.',
            ]);
        }

        $payload = $this->validateNotePayload($request, true);
        unset($payload['document_number']);
        $parentInvoice = $this->resolveParentSalesInvoice($payload['parent_document_id'] ?? null);
        $items = $request->has('items') ? ($payload['items'] ?? []) : null;

        $updated = $this->service->updateDocument(
            $note,
            [
                ...$payload,
                'contact_id' => $payload['contact_id'] ?? $parentInvoice->contact_id,
            ],
            $items
        );

        return response()->json([
            'message' => 'Nota de venta actualizada correctamente.',
            'data' => $updated,
        ]);
    }

    public function destroy(SaleDocument $saleDocument)
    {
        $note = $this->resolveNote($saleDocument);

        if (! $note->canDeleteNote()) {
            throw ValidationException::withMessages([
                'document' => 'Solo se pueden eliminar notas de venta en estado borrador.',
            ]);
        }

        $this->service->deleteDocument($note);

        return response()->json([
            'message' => 'Nota de venta eliminada correctamente.',
        ]);
    }

    public function emit(SaleDocument $note)
    {
        $resolvedNote = $this->resolveNote($note);
        $emitted = $this->saleNoteService->emit($resolvedNote);

        return response()->json([
            'message' => 'Nota de venta emitida correctamente.',
            'data' => $emitted,
        ]);
    }

    public function void(Request $request, SaleDocument $note)
    {
        $resolvedNote = $this->resolveNote($note);
        $validated = $request->validate([
            'reason' => 'nullable|string',
        ]);

        $voided = $this->saleNoteService->void($resolvedNote, (string) ($validated['reason'] ?? ''));

        return response()->json([
            'message' => 'Nota de venta anulada correctamente.',
            'data' => $voided,
        ]);
    }

    private function resolveRequestedTypes(mixed $type): array
    {
        return match ($type) {
            SaleNoteType::CREDIT->value => [SaleDocument::TYPE_CREDIT_NOTE],
            SaleNoteType::DEBIT->value => [SaleDocument::TYPE_DEBIT_NOTE],
            default => [SaleDocument::TYPE_CREDIT_NOTE, SaleDocument::TYPE_DEBIT_NOTE],
        };
    }

    private function resolveDocumentTypeFromRequest(mixed $type): string
    {
        return match ($type) {
            SaleNoteType::CREDIT->value => SaleNoteType::CREDIT->documentType(),
            SaleNoteType::DEBIT->value => SaleNoteType::DEBIT->documentType(),
            default => throw ValidationException::withMessages([
                'type' => 'El tipo de nota debe ser credito o debito.',
            ]),
        };
    }

    private function resolveNote(SaleDocument $document): SaleDocument
    {
        if (! $document->isCreditDebitNote()) {
            abort(404);
        }

        return $document;
    }

    private function resolveParentSalesInvoice(mixed $parentDocumentId): SaleDocument
    {
        if (empty($parentDocumentId)) {
            throw ValidationException::withMessages([
                'parent_document_id' => 'La factura referenciada es obligatoria.',
            ]);
        }

        $invoice = SaleDocument::query()->findOrFail($parentDocumentId);

        if (! $invoice->isInvoice()) {
            throw ValidationException::withMessages([
                'parent_document_id' => 'La factura referenciada debe ser una factura de venta.',
            ]);
        }

        if (! in_array($invoice->status, [
            SalesInvoiceStatus::ISSUED->value,
            SalesInvoiceStatus::PARTIAL->value,
            SalesInvoiceStatus::PAID->value,
        ], true)) {
            throw ValidationException::withMessages([
                'parent_document_id' => 'Solo se pueden referenciar facturas emitidas, parciales o pagadas.',
            ]);
        }

        return $invoice;
    }

    private function resolveNoteDocumentNumber(string $documentType): string
    {
        $isCreditNote = $documentType === SaleDocument::TYPE_CREDIT_NOTE;

        return SaleDocument::formatDocumentNumber(
            $isCreditNote ? DocumentPrefix::CREDIT_NOTE->value : DocumentPrefix::DEBIT_NOTE->value,
            $isCreditNote
                ? $this->creditNoteSequenceService->reserveNextNumber()
                : $this->debitNoteSequenceService->reserveNextNumber()
        );
    }

    private function validateNotePayload(Request $request, bool $isUpdate = false): array
    {
        return $request->validate([
            'type' => [$isUpdate ? 'sometimes' : 'required', Rule::in(array_map(static fn (SaleNoteType $type) => $type->value, SaleNoteType::cases()))],
            'document_number' => 'nullable|string',
            'parent_document_id' => 'required|exists:sale_documents,id',
            'contact_id' => 'nullable|exists:contacts,id',
            'date' => 'required|date',
            'due_date' => 'nullable|date|after_or_equal:date',
            'description' => 'nullable|string',
            'payment_terms' => 'nullable|in:cash,credit',
            'payment_days' => 'nullable|integer|min:0',
            'customer_order_number' => 'nullable|string',
            'status' => ['nullable', Rule::in([SaleNoteStatus::DRAFT->value])],
            'items' => $isUpdate ? 'sometimes|array|min:1' : 'required|array|min:1',
            'items.*.item_id' => 'required|exists:items,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|min:0|max:100',
            'items.*.tax_rate' => 'nullable|numeric|min:0',
            'items.*.description' => 'nullable|string',
        ]);
    }
}
