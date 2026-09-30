<?php

namespace App\Services\Finance;

use App\Enums\PurchaseInvoiceStatus;
use App\Models\Expenses\PurchaseDocument;
use App\Models\Expenses\PurchaseDocumentItem;
use App\Support\DocumentSequence;
use App\Services\Expenses\PurchaseDocumentInvoiceService;
use App\Services\Expenses\PurchaseDocumentService;
use App\Services\Settings\SettingService;
use App\Services\Textil\FabricRollCreditNoteService;
use App\Support\MasterData\MaterialClassifier;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreditDebitNoteService
{
    /**
     * @var array<string, string>
     */
    private const NOTE_PREFIX_BY_TYPE = [
        PurchaseDocument::TYPE_CREDIT_NOTE => 'NC',
        PurchaseDocument::TYPE_DEBIT_NOTE => 'ND',
    ];

    private const NOTE_APPROVED_STATUS = 'approved';

    /**
     * @var array<int, string>
     */
    private const ELIGIBLE_PARENT_INVOICE_STATUSES = [
        'received',
        'approved',
    ];

    /**
     * @var array<int, string>
     */
    private array $allowedTypes = [
        PurchaseDocument::TYPE_CREDIT_NOTE,
        PurchaseDocument::TYPE_DEBIT_NOTE,
    ];

    public function __construct(
        private PurchaseDocumentService $documentService,
        private PurchaseDocumentInvoiceService $invoiceService,
        private SettingService $settingService,
        private AccountingPostingService $accountingPostingService,
        private FabricRollCreditNoteService $rollCreditNoteService,
    ) {}

    public function paginate(array $filters = []): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? 20);

        $allowedTypes = $this->resolveAllowedTypesByContext($filters['context'] ?? null);

        $query = PurchaseDocument::query()
            ->with([
                'supplier:id,full_name,id_number,phone,email',
                'parent:id,document_number,document_type,contact_id',
            ])
            ->whereIn('document_type', $allowedTypes);

        if (! empty($filters['document_type']) && in_array($filters['document_type'], $allowedTypes, true)) {
            $query->where('document_type', $filters['document_type']);
        }

        if (! empty($filters['contact_id'])) {
            $query->where('contact_id', $filters['contact_id']);
        }

        if (! empty($filters['parent_document_id'])) {
            $query->where('parent_document_id', $filters['parent_document_id']);
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('date', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('date', '<=', $filters['date_to']);
        }

        $sortBy = in_array($filters['sort_by'] ?? '', ['date', 'document_number', 'status'], true)
            ? $filters['sort_by']
            : null;
        $direction = strtolower((string) ($filters['sort_direction'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';

        if ($sortBy !== null) {
            $query->orderBy($sortBy, $direction);
        } else {
            $query->orderByDesc('date');
        }

        return $query->orderByDesc('id')->paginate($perPage);
    }

    public function getNextDocumentNumberPreview(?string $documentType): string
    {
        $this->ensureAllowedType($documentType);

        return $this->buildNoteDocumentNumber((string) $documentType, false);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listEligiblePurchaseInvoices(?int $contactId = null): array
    {
        $query = PurchaseDocument::query()
            ->with([
                'supplier:id,full_name',
                'items:id,purchase_document_id,item_id,item_name,total,total_tax',
                'items.item:id,name,category_sub_group_id',
                'items.item.categorySubGroup:id,name',
            ])
            ->where('document_type', PurchaseDocument::TYPE_INVOICE)
            ->whereIn('status', self::ELIGIBLE_PARENT_INVOICE_STATUSES);

        if (($contactId ?? 0) > 0) {
            $query->where('contact_id', (int) $contactId);
        }

        return $query
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->get()
            ->unique('id')
            ->map(fn (PurchaseDocument $invoice) => $this->mapEligiblePurchaseInvoice($invoice))
            ->values()
            ->all();
    }

    /**
     * Rollos rechazados sin resolver de la factura base que la nota puede cubrir
     * (los enlazados a OTRA nota quedan fuera; los de la propia nota se conservan).
     *
     * @return array<int, array<string, mixed>>
     */
    public function listCoverableRejectedRolls(int $parentInvoiceId, ?int $noteId = null): array
    {
        $invoice = PurchaseDocument::query()
            ->where('document_type', PurchaseDocument::TYPE_INVOICE)
            ->find($parentInvoiceId);

        if (! $invoice) {
            return [];
        }

        return $this->rollCreditNoteService->pendingRejectedRolls($invoice)
            ->filter(fn (array $roll) => $roll['credit_note_id'] === null || (int) $roll['credit_note_id'] === (int) $noteId)
            ->values()
            ->all();
    }

    /** Rollos enlazados a la nota, para el payload de la nota. */
    public function listLinkedRolls(PurchaseDocument $note): array
    {
        return $this->rollCreditNoteService->linkedRolls($note)->all();
    }

    private function resolveAllowedTypesByContext(?string $context): array
    {
        if ($context === 'sale') {
            return ['sale_credit_note', 'sale_debit_note'];
        }

        // Default: purchase context
        return $this->allowedTypes;
    }

    public function create(array $payload): PurchaseDocument
    {
        $this->ensureScopeIsExpenses($payload);
        $this->ensureAllowedType($payload['document_type'] ?? null);
        $this->ensureItemsPayload($payload);

        $requestedContactId = isset($payload['contact_id']) ? (int) $payload['contact_id'] : null;
        if (($requestedContactId ?? 0) <= 0) {
            $requestedContactId = null;
        }

        $parentInvoice = $this->resolveParentInvoice((int) ($payload['parent_document_id'] ?? 0), $requestedContactId);
        $contactId = isset($payload['contact_id']) ? (int) $payload['contact_id'] : (int) ($parentInvoice->contact_id ?? 0);
        $reasonCode = $this->resolveReasonCode($payload);
        $this->ensureReasonCodeIsAllowed($payload['document_type'], $reasonCode);

        if ($contactId <= 0) {
            throw ValidationException::withMessages([
                'contact_id' => 'No se pudo resolver el proveedor desde el payload ni desde la factura base.',
            ]);
        }

        return DB::transaction(function () use ($payload, $parentInvoice, $contactId, $reasonCode) {
            $documentType = $payload['document_type'];
            $generatedDocumentNumber = $this->generateNoteDocumentNumber($documentType);

            $note = PurchaseDocument::create([
                'document_number' => $generatedDocumentNumber,
                'document_type' => $documentType,
                'parent_document_id' => $parentInvoice->id,
                'contact_id' => (int) $contactId,
                'provider_name' => $payload['provider_name'] ?? $parentInvoice->provider_name,
                'provider_phone' => $payload['provider_phone'] ?? $parentInvoice->provider_phone,
                'date' => $payload['date'] ?? now()->toDateString(),
                'delivery_date' => $payload['delivery_date'] ?? null,
                'description' => $payload['description'] ?? null,
                'status' => $payload['status'] ?? 'pending',
                'approval_status' => $payload['approval_status'] ?? null,
                'created_by_user_id' => $payload['created_by_user_id'] ?? Auth::id(),
                'executed_by_user_id' => $payload['executed_by_user_id'] ?? null,
            ]);

            if ($reasonCode !== null) {
                $note->reason = $reasonCode;
                $note->save();
            }

            $updatedNote = $this->documentService->updateDocument($note, [], $payload['items']);

            if (array_key_exists('fabric_roll_ids', $payload)) {
                $this->rollCreditNoteService->linkRolls($updatedNote, (array) $payload['fabric_roll_ids']);
            }

            // Solo las notas crédito tienen asiento automático (proporcional si es parcial).
            // Las notas débito de compra no tienen contabilización definida: quedan sin asiento.
            if ($documentType === PurchaseDocument::TYPE_CREDIT_NOTE) {
                $this->ensureCreditNoteFitsInvoice($updatedNote, $parentInvoice);
                $this->accountingPostingService->postCreditNote($updatedNote);
            }

            // Una nota que nace aprobada da de baja de inmediato los rollos que cubre.
            if ($updatedNote->status === self::NOTE_APPROVED_STATUS) {
                $this->rollCreditNoteService->resolveOnApproval($updatedNote);
            }

            if ($note->document_type === PurchaseDocument::TYPE_CREDIT_NOTE) {
                $this->ensureCreditNoteBalanceIsNonNegative($parentInvoice);
            }

            return $updatedNote;
        });
    }

    public function update(PurchaseDocument $note, array $payload): PurchaseDocument
    {
        $this->ensureNoteType($note);
        $this->ensureNoteIsNotApproved($note, 'editar');
        $this->ensureScopeIsExpenses($payload);
        $this->ensureItemsPayload($payload);

        if (array_key_exists('document_type', $payload)) {
            $this->ensureAllowedType($payload['document_type']);
        }

        if (
            array_key_exists('parent_document_id', $payload)
            && ! empty($note->parent_document_id)
            && (int) $payload['parent_document_id'] !== (int) $note->parent_document_id
        ) {
            throw ValidationException::withMessages([
                'parent_document_id' => 'No se permite cambiar la factura base de una nota existente.',
            ]);
        }

        $expectedContactId = isset($payload['contact_id']) ? (int) $payload['contact_id'] : (int) ($note->contact_id ?? 0);
        if ($expectedContactId <= 0) {
            $expectedContactId = null;
        }

        $parentInvoice = $this->resolveParentInvoice(
            (int) ($payload['parent_document_id'] ?? $note->parent_document_id),
            $expectedContactId
        );
        $contactId = isset($payload['contact_id'])
            ? (int) $payload['contact_id']
            : (int) ($note->contact_id ?? $parentInvoice->contact_id ?? 0);
        $documentType = $payload['document_type'] ?? $note->document_type;
        $reasonCode = $this->resolveReasonCode($payload);
        $this->ensureReasonCodeIsAllowed($documentType, $reasonCode);

        if ($contactId <= 0) {
            throw ValidationException::withMessages([
                'contact_id' => 'No se pudo resolver el proveedor desde el payload ni desde la factura base.',
            ]);
        }

        return DB::transaction(function () use ($note, $payload, $parentInvoice, $contactId, $reasonCode) {
            $previousStatus = (string) $note->status;
            $previousDocumentType = (string) $note->document_type;
            $targetDocumentType = $payload['document_type'] ?? $note->document_type;
            $currentDocumentNumber = trim((string) $note->document_number);
            $mustGenerateDocumentNumber = $currentDocumentNumber === '' || $targetDocumentType !== $note->document_type;

            $note->fill([
                'document_number' => $mustGenerateDocumentNumber
                    ? $this->generateNoteDocumentNumber($targetDocumentType)
                    : $note->document_number,
                'document_type' => $targetDocumentType,
                'parent_document_id' => $parentInvoice->id,
                'contact_id' => (int) $contactId,
                'provider_name' => $payload['provider_name'] ?? $note->provider_name ?? $parentInvoice->provider_name,
                'provider_phone' => $payload['provider_phone'] ?? $note->provider_phone ?? $parentInvoice->provider_phone,
                'date' => $payload['date'] ?? $note->date?->toDateString(),
                'delivery_date' => $payload['delivery_date'] ?? $note->delivery_date,
                'description' => $payload['description'] ?? $note->description,
                'status' => $payload['status'] ?? $note->status,
                'approval_status' => $payload['approval_status'] ?? $note->approval_status,
                'executed_by_user_id' => $payload['executed_by_user_id'] ?? $note->executed_by_user_id,
            ]);

            if ($reasonCode !== null) {
                $note->reason = $reasonCode;
            }

            $note->save();
            $updatedNote = $this->documentService->updateDocument($note, [], $payload['items']);

            if (array_key_exists('fabric_roll_ids', $payload)) {
                $this->rollCreditNoteService->linkRolls($updatedNote, (array) $payload['fabric_roll_ids']);
            } elseif ($previousDocumentType !== $updatedNote->document_type && $updatedNote->document_type !== PurchaseDocument::TYPE_CREDIT_NOTE) {
                // Si la nota deja de ser crédito ya no puede cubrir rollos.
                $this->rollCreditNoteService->unlinkAll($updatedNote);
            }

            if ($updatedNote->document_type === PurchaseDocument::TYPE_CREDIT_NOTE) {
                // Si el monto cambió, el asiento parcial vigente se revierte y se contabiliza de nuevo.
                $this->ensureCreditNoteFitsInvoice($updatedNote, $parentInvoice);
                $this->accountingPostingService->syncCreditNoteEntry($updatedNote);
                $this->ensureCreditNoteBalanceIsNonNegative($parentInvoice);
            } elseif ($previousDocumentType === PurchaseDocument::TYPE_CREDIT_NOTE) {
                // Dejó de ser crédito: su asiento ya no corresponde.
                $this->accountingPostingService->voidCreditNoteEntry($updatedNote);
            }

            // Transición a aprobada: los rollos enlazados se dan de baja.
            if ($previousStatus !== self::NOTE_APPROVED_STATUS && $updatedNote->status === self::NOTE_APPROVED_STATUS) {
                $this->rollCreditNoteService->resolveOnApproval($updatedNote);
            }

            return $updatedNote;
        });
    }

    /**
     * Consecutivo atómico de la nota. El bloqueo de filas anterior no protegía la primera nota de cada tipo
     * (sin filas no había nada que bloquear) y dos usuarios podían recibir el mismo número.
     */
    private function generateNoteDocumentNumber(string $documentType): string
    {
        $prefix = $this->resolvePrefixByDocumentType($documentType);

        $next = DocumentSequence::next(
            "purchase_documents:{$documentType}:{$prefix}",
            fn () => (int) $this->resolveMaxSequence($documentType, $prefix, false)
        );

        return sprintf('%s-%03d', $prefix, $next);
    }

    private function buildNoteDocumentNumber(string $documentType, bool $lockForUpdate): string
    {
        $prefix = $this->resolvePrefixByDocumentType($documentType);
        $maxSequence = $this->resolveMaxSequence($documentType, $prefix, $lockForUpdate);
        $nextSequence = ((int) $maxSequence) + 1;

        return sprintf('%s-%03d', $prefix, $nextSequence);
    }

    private function resolveMaxSequence(string $documentType, string $prefix, bool $lockForUpdate): ?int
    {
        $prefixedQuery = PurchaseDocument::withTrashed()
            ->where('document_type', $documentType)
            ->whereNotNull('document_number')
            ->where('document_number', 'like', $prefix.'-%');

        if ($lockForUpdate) {
            $prefixedQuery->lockForUpdate();
        }

        $maxSequence = $prefixedQuery
            ->get(['document_number'])
            ->map(function (PurchaseDocument $document) use ($prefix): ?int {
                return $this->extractSequenceFromDocumentNumber((string) $document->document_number, $prefix);
            })
            ->filter(static fn (?int $sequence): bool => $sequence !== null)
            ->max();

        if ($maxSequence === null) {
            $fallbackQuery = PurchaseDocument::withTrashed()
                ->where('document_type', $documentType)
                ->whereNotNull('document_number');

            if ($lockForUpdate) {
                $fallbackQuery->lockForUpdate();
            }

            $maxSequence = $fallbackQuery
                ->get(['document_number'])
                ->map(function (PurchaseDocument $document) use ($prefix): ?int {
                    return $this->extractSequenceFromDocumentNumber((string) $document->document_number, $prefix);
                })
                ->filter(static fn (?int $sequence): bool => $sequence !== null)
                ->max();
        }

        return $maxSequence;
    }

    private function resolvePrefixByDocumentType(string $documentType): string
    {
        $prefix = self::NOTE_PREFIX_BY_TYPE[$documentType] ?? null;
        if ($prefix === null) {
            throw ValidationException::withMessages([
                'document_type' => 'Tipo de nota no permitido para consecutivo.',
            ]);
        }

        return $prefix;
    }

    private function extractSequenceFromDocumentNumber(string $documentNumber, string $prefix): ?int
    {
        $normalized = strtoupper(trim($documentNumber));
        $expectedPrefix = strtoupper($prefix).'-';

        if (! str_starts_with($normalized, $expectedPrefix)) {
            if (! preg_match('/(\d+)$/', $normalized, $matches)) {
                return null;
            }

            return (int) $matches[1];
        }

        $sequencePart = substr($normalized, strlen($expectedPrefix));
        if ($sequencePart === '' || ! ctype_digit($sequencePart)) {
            return null;
        }

        return (int) $sequencePart;
    }

    public function delete(PurchaseDocument $note): void
    {
        $this->ensureNoteType($note);
        $this->ensureNoteIsNotApproved($note, 'eliminar');

        DB::transaction(function () use ($note) {
            // Una nota crédito parcial eliminada anula su asiento (queda el par asiento + reversión).
            if ($note->document_type === PurchaseDocument::TYPE_CREDIT_NOTE) {
                $this->accountingPostingService->voidCreditNoteEntry($note);
            }

            $this->rollCreditNoteService->unlinkAll($note);
            $note->items()->delete();
            $note->delete();
        });
    }

    // Una nota aprobada ya afecta el saldo y la contabilidad de la factura.
    private function ensureNoteIsNotApproved(PurchaseDocument $note, string $action): void
    {
        if ($note->status === self::NOTE_APPROVED_STATUS) {
            throw ValidationException::withMessages([
                'status' => "No se puede {$action} una nota crédito/débito aprobada.",
            ]);
        }
    }

    private function ensureScopeIsExpenses(array $payload): void
    {
        if (! array_key_exists('scope', $payload)) {
            return;
        }

        if (($payload['scope'] ?? null) !== 'expenses') {
            throw ValidationException::withMessages([
                'scope' => 'Actualmente solo se soporta scope=expenses.',
            ]);
        }
    }

    private function ensureAllowedType(?string $documentType): void
    {
        if (! in_array((string) $documentType, $this->allowedTypes, true)) {
            throw ValidationException::withMessages([
                'document_type' => 'Tipo de nota no permitido.',
            ]);
        }
    }

    private function ensureNoteType(PurchaseDocument $note): void
    {
        if (! in_array($note->document_type, $this->allowedTypes, true)) {
            throw ValidationException::withMessages([
                'note' => 'El documento no corresponde a una nota de crédito/débito de compras.',
            ]);
        }
    }

    private function ensureItemsPayload(array $payload): void
    {
        if (! array_key_exists('items', $payload) || ! is_array($payload['items'])) {
            throw ValidationException::withMessages([
                'items' => 'El payload debe incluir items como arreglo.',
            ]);
        }
    }

    private function resolveParentInvoice(int $parentId, ?int $expectedContactId = null): PurchaseDocument
    {
        $parent = PurchaseDocument::query()->find($parentId);

        if (! $parent) {
            throw ValidationException::withMessages([
                'parent_document_id' => 'La factura base especificada no existe.',
            ]);
        }

        if ($parent->document_type !== PurchaseDocument::TYPE_INVOICE) {
            throw ValidationException::withMessages([
                'parent_document_id' => 'El documento base debe ser una factura de compra.',
            ]);
        }

        if (! $this->isEligibleParentInvoiceStatus((string) $parent->status)) {
            throw ValidationException::withMessages([
                'parent_document_id' => 'La factura seleccionada no está en un estado válido para registrar una nota.',
            ]);
        }

        if (($expectedContactId ?? 0) > 0 && (int) ($parent->contact_id ?? 0) !== (int) $expectedContactId) {
            throw ValidationException::withMessages([
                'parent_document_id' => 'La factura seleccionada no pertenece al proveedor indicado.',
            ]);
        }

        return $parent;
    }

    private function isEligibleParentInvoiceStatus(string $status): bool
    {
        return in_array(trim($status), self::ELIGIBLE_PARENT_INVOICE_STATUSES, true);
    }

    /**
     * @return array<string, mixed>
     */
    private function mapEligiblePurchaseInvoice(PurchaseDocument $invoice): array
    {
        $isFabric = $this->isFabricInvoice($invoice);
        $status = trim((string) ($invoice->status ?? ''));
        $total = (float) $invoice->items->sum(function (PurchaseDocumentItem $item) {
            return ((float) ($item->total ?? 0)) + ((float) ($item->total_tax ?? 0));
        });

        return [
            'id' => (int) $invoice->id,
            'invoice_scope' => $isFabric ? 'fabric' : 'general',
            'source_type' => PurchaseDocument::TYPE_INVOICE,
            'document_type' => PurchaseDocument::TYPE_INVOICE,
            'document_number' => $invoice->document_number,
            'provider_id' => (int) ($invoice->contact_id ?? 0),
            'provider_name' => $invoice->supplier?->full_name ?? $invoice->provider_name ?? null,
            'status' => $status,
            'status_label' => $this->resolveInvoiceStatusLabel($status),
            'date' => $invoice->date?->format('Y-m-d'),
            'total' => round($total, 2),
        ];
    }

    private function resolveInvoiceStatusLabel(string $status): string
    {
        $enum = PurchaseInvoiceStatus::tryFrom($status);
        if ($enum !== null) {
            return $enum->label();
        }

        return match ($status) {
            'approved' => 'Aprobada',
            default => $status !== '' ? ucfirst($status) : 'Sin estado',
        };
    }

    private function isFabricInvoice(PurchaseDocument $invoice): bool
    {
        return $invoice->items->contains(function (PurchaseDocumentItem $item): bool {
            if ($item->item?->categorySubGroup) {
                return MaterialClassifier::isFabricPurchaseDocumentItem($item);
            }

            $fallbackName = mb_strtolower(trim((string) ($item->item_name ?? $item->item?->name ?? '')));

            return $fallbackName !== '' && str_contains($fallbackName, 'tela');
        });
    }

    private function resolveReasonCode(array $payload): ?string
    {
        $rawReasonCode = $payload['reason_code'] ?? $payload['reason'] ?? null;
        if ($rawReasonCode === null) {
            return null;
        }

        $normalized = trim((string) $rawReasonCode);

        return $normalized !== '' ? $normalized : null;
    }

    private function ensureReasonCodeIsAllowed(string $documentType, ?string $reasonCode): void
    {
        if ($reasonCode === null) {
            return;
        }

        $parameterKey = $documentType === PurchaseDocument::TYPE_DEBIT_NOTE
            ? 'debit_note_reason'
            : 'credit_note_reason';

        $allowedOptions = $this->settingService->getCatalogOptions($parameterKey);
        if ($allowedOptions->isEmpty()) {
            return;
        }

        $allowedCodes = $allowedOptions
            ->pluck('id')
            ->map(fn ($value) => trim((string) $value))
            ->filter(fn (string $value) => $value !== '')
            ->all();

        if (! in_array($reasonCode, $allowedCodes, true)) {
            throw ValidationException::withMessages([
                'reason_code' => 'El motivo seleccionado no pertenece al catálogo configurado para este tipo de nota.',
            ]);
        }
    }

    /**
     * La suma de las notas crédito con asiento (más esta) no puede superar el total de la factura.
     * Se valida antes de contabilizar; el fallo deshace toda la transacción de la nota.
     */
    private function ensureCreditNoteFitsInvoice(PurchaseDocument $note, PurchaseDocument $invoice): void
    {
        $invoiceTotal = (float) ($this->invoiceService->getInvoiceNotesSummary($invoice)['invoice_total'] ?? 0);
        $noteTotal = round((float) $note->items()->sum(DB::raw('COALESCE(total, 0) + COALESCE(total_tax, 0)')), 2);
        $alreadyCredited = $this->accountingPostingService->creditedTotalForInvoice((int) $invoice->id, (int) $note->id);

        if (round($alreadyCredited + $noteTotal, 2) > round($invoiceTotal, 2) + 0.01) {
            throw ValidationException::withMessages([
                'document_type' => 'La nota crédito supera el saldo disponible de la factura.',
            ]);
        }
    }

    private function ensureCreditNoteBalanceIsNonNegative(PurchaseDocument $invoice): void
    {
        $summary = $this->invoiceService->getInvoiceNotesSummary($invoice);

        if ((float) ($summary['balance_after_notes'] ?? 0) < 0) {
            throw ValidationException::withMessages([
                'document_type' => 'La nota crédito supera el saldo disponible de la factura.',
            ]);
        }
    }
}
