<?php

namespace App\Services\Finance;

use App\Models\Expenses\PurchaseDocument;
use App\Services\Expenses\PurchaseDocumentService;
use App\Services\Settings\SettingService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreditDebitNoteService
{
    /**
     * @var array<int, string>
     */
    private array $allowedTypes = [
        PurchaseDocument::TYPE_CREDIT_NOTE,
        PurchaseDocument::TYPE_DEBIT_NOTE,
    ];

    public function __construct(
        private PurchaseDocumentService $documentService,
        private SettingService $settingService,
    )
    {
    }

    public function paginate(array $filters = []): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? 20);

        $query = PurchaseDocument::query()
            ->with([
                'supplier:id,full_name,id_number,phone,email',
                'parent:id,document_number,document_type,contact_id',
            ])
            ->whereIn('document_type', $this->allowedTypes);

        if (!empty($filters['contact_id'])) {
            $query->where('contact_id', $filters['contact_id']);
        }

        if (!empty($filters['parent_document_id'])) {
            $query->where('parent_document_id', $filters['parent_document_id']);
        }

        if (!empty($filters['date_from'])) {
            $query->whereDate('date', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('date', '<=', $filters['date_to']);
        }

        return $query->orderByDesc('date')->orderByDesc('id')->paginate($perPage);
    }

    public function create(array $payload): PurchaseDocument
    {
        $this->ensureScopeIsExpenses($payload);
        $this->ensureAllowedType($payload['document_type'] ?? null);
        $this->ensureItemsPayload($payload);

        $parentInvoice = $this->resolveParentInvoice((int) ($payload['parent_document_id'] ?? 0));
        $contactId = $payload['contact_id'] ?? $parentInvoice->contact_id;
        $reasonCode = $this->resolveReasonCode($payload);
        $this->ensureReasonCodeIsAllowed($payload['document_type'], $reasonCode);

        if (empty($contactId)) {
            throw ValidationException::withMessages([
                'contact_id' => 'No se pudo resolver el proveedor desde el payload ni desde la factura base.',
            ]);
        }

        return DB::transaction(function () use ($payload, $parentInvoice, $contactId) {
            $note = PurchaseDocument::create([
                'document_number' => $payload['document_number'] ?? null,
                'document_type' => $payload['document_type'],
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

            return $this->documentService->updateDocument($note, [], $payload['items']);
        });
    }

    public function update(PurchaseDocument $note, array $payload): PurchaseDocument
    {
        $this->ensureNoteType($note);
        $this->ensureScopeIsExpenses($payload);
        $this->ensureItemsPayload($payload);

        if (array_key_exists('document_type', $payload)) {
            $this->ensureAllowedType($payload['document_type']);
        }

        if (
            array_key_exists('parent_document_id', $payload)
            && !empty($note->parent_document_id)
            && (int) $payload['parent_document_id'] !== (int) $note->parent_document_id
        ) {
            throw ValidationException::withMessages([
                'parent_document_id' => 'No se permite cambiar la factura base de una nota existente.',
            ]);
        }

        $parentInvoice = $this->resolveParentInvoice((int) ($payload['parent_document_id'] ?? $note->parent_document_id));
        $contactId = $payload['contact_id'] ?? $note->contact_id ?? $parentInvoice->contact_id;
        $documentType = $payload['document_type'] ?? $note->document_type;
        $reasonCode = $this->resolveReasonCode($payload);
        $this->ensureReasonCodeIsAllowed($documentType, $reasonCode);

        if (empty($contactId)) {
            throw ValidationException::withMessages([
                'contact_id' => 'No se pudo resolver el proveedor desde el payload ni desde la factura base.',
            ]);
        }

        return DB::transaction(function () use ($note, $payload, $parentInvoice, $contactId) {
            $note->fill([
                'document_number' => $payload['document_number'] ?? $note->document_number,
                'document_type' => $payload['document_type'] ?? $note->document_type,
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
            return $this->documentService->updateDocument($note, [], $payload['items']);
        });
    }

    public function delete(PurchaseDocument $note): void
    {
        $this->ensureNoteType($note);

        DB::transaction(function () use ($note) {
            $note->items()->delete();
            $note->delete();
        });
    }

    private function ensureScopeIsExpenses(array $payload): void
    {
        if (!array_key_exists('scope', $payload)) {
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
        if (!in_array((string) $documentType, $this->allowedTypes, true)) {
            throw ValidationException::withMessages([
                'document_type' => 'Tipo de nota no permitido.',
            ]);
        }
    }

    private function ensureNoteType(PurchaseDocument $note): void
    {
        if (!in_array($note->document_type, $this->allowedTypes, true)) {
            throw ValidationException::withMessages([
                'note' => 'El documento no corresponde a una nota de crédito/débito de compras.',
            ]);
        }
    }

    private function ensureItemsPayload(array $payload): void
    {
        if (!array_key_exists('items', $payload) || !is_array($payload['items'])) {
            throw ValidationException::withMessages([
                'items' => 'El payload debe incluir items como arreglo.',
            ]);
        }
    }

    private function resolveParentInvoice(int $parentId): PurchaseDocument
    {
        $parent = PurchaseDocument::query()->find($parentId);

        if (!$parent) {
            throw ValidationException::withMessages([
                'parent_document_id' => 'La factura base especificada no existe.',
            ]);
        }

        if ($parent->document_type !== PurchaseDocument::TYPE_INVOICE) {
            throw ValidationException::withMessages([
                'parent_document_id' => 'El documento base debe ser una factura de compra.',
            ]);
        }

        return $parent;
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

        if (!in_array($reasonCode, $allowedCodes, true)) {
            throw ValidationException::withMessages([
                'reason_code' => 'El motivo seleccionado no pertenece al catálogo configurado para este tipo de nota.',
            ]);
        }
    }

}
