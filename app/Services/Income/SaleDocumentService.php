<?php

namespace App\Services\Income;

use App\Enums\Income\PaymentStatus;
use App\Enums\Income\QuoteStatus;
use App\Enums\Income\SalesInvoiceStatus;
use App\Enums\Income\SalesOrderStatus;
use App\Helpers\ScopeHelper;
use App\Models\Income\SaleDocument;
use App\Models\Settings\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class SaleDocumentService
{
    private const SORTABLE_COLUMNS = ['date', 'document_number', 'status', 'payment_status'];

    public function createDocument(array $data, array $items = []): SaleDocument
    {
        return DB::transaction(function () use ($data, $items) {
            $documentType = (string) ($data['document_type'] ?? '');

            $document = SaleDocument::create([
                ...$this->buildBaseData($data, $documentType),
                'parent_document_id' => $data['parent_document_id'] ?? null,
                'quote_id' => $data['quote_id'] ?? null,
                'status' => $data['status'] ?? $this->defaultStatusFor($documentType),
                'payment_status' => $data['payment_status'] ?? PaymentStatus::UNPAID->value,
                'amount_paid' => $data['amount_paid'] ?? 0,
            ]);

            $this->syncItems($document, $items);

            return $this->loadWithRelations($document);
        });
    }

    public function paginateDocuments(string $documentType, array $filters = [], int $perPage = 20, ?User $user = null, ?string $ownPermission = null, ?string $areaPermission = null, ?string $allPermission = null): LengthAwarePaginator
    {
        $query = $this->buildDocumentQuery($documentType);
        $query = $this->applyDocumentFilters($query, $filters);
        ScopeHelper::apply($query, $user, $ownPermission, $areaPermission, $allPermission);

        return $this->applyDocumentSort($query, $filters)->paginate($perPage);
    }

    public function paginateDocumentsByTypes(array $documentTypes, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = $this->buildDocumentQueryByTypes($documentTypes);
        $query = $this->applyDocumentFilters($query, $filters);

        return $this->applyDocumentSort($query, $filters)->paginate($perPage);
    }

    protected function applyDocumentSort(Builder $query, array $filters): Builder
    {
        $sortBy = $filters['sort_by'] ?? null;
        $direction = strtolower((string) ($filters['sort_direction'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';

        if (in_array($sortBy, self::SORTABLE_COLUMNS, true)) {
            return $query->orderBy($sortBy, $direction)->orderByDesc('id');
        }

        return $query->orderByDesc('date')->orderByDesc('id');
    }

    public function buildDocumentQuery(string $documentType): Builder
    {
        return $this->buildDocumentQueryByTypes([$documentType]);
    }

    public function buildDocumentQueryByTypes(array $documentTypes): Builder
    {
        return SaleDocument::with([
            'items.item',
            'client:id,full_name,id_number,phone,email',
            'creator:id,name',
            'executor:id,name',
            'parent:id,document_number,document_type,status',
            'children:id,parent_document_id,document_number,document_type,status',
            'payments:id,sale_invoice_id,amount,payment_date',
        ])->whereIn('document_type', $documentTypes);
    }

    public function applyDocumentFilters(Builder $query, array $filters): Builder
    {
        $search = trim($filters['search'] ?? '');
        $contactIdFilter = isset($filters['contact_id']) ? (int) $filters['contact_id'] : null;

        if (! ($contactIdFilter > 0)) {
            $clientId = $filters['client_id'] ?? null;
            $contactIdFilter = is_numeric($clientId) ? (int) $clientId : null;
            if (! ($contactIdFilter > 0)) {
                $contactIdFilter = null;
            }
        }

        return $query
            ->when($search !== '', fn (Builder $builder) => $builder->where(function (Builder $sub) use ($search) {
                $sub->where('document_number', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('customer_order_number', 'like', "%{$search}%")
                    ->orWhereHas('client', fn (Builder $client) => $client->where('full_name', 'like', "%{$search}%"));
            }))
            ->when($this->shouldApplyFilter($filters['status'] ?? null), fn (Builder $builder) => $builder->where('status', $filters['status']))
            ->when($this->shouldApplyFilter($filters['payment_status'] ?? null), fn (Builder $builder) => $builder->where('payment_status', $filters['payment_status']))
            ->when($this->shouldApplyFilter($filters['payment_terms'] ?? null), fn (Builder $builder) => $builder->where('payment_terms', $filters['payment_terms']))
            ->when(($filters['origin'] ?? null) === 'direct', fn (Builder $builder) => $builder->whereNull('parent_document_id'))
            ->when(($filters['origin'] ?? null) === 'from_quote', fn (Builder $builder) => $builder->whereNotNull('parent_document_id'))
            ->when(($filters['origin'] ?? null) === 'from_order', fn (Builder $builder) => $builder->whereNotNull('parent_document_id'))
            ->when($contactIdFilter !== null, fn (Builder $builder) => $builder->where('contact_id', $contactIdFilter))
            ->when(! empty($filters['date_from']), fn (Builder $builder) => $builder->whereDate('date', '>=', $filters['date_from']))
            ->when(! empty($filters['date_to']), fn (Builder $builder) => $builder->whereDate('date', '<=', $filters['date_to']));
    }

    protected function shouldApplyFilter(mixed $value): bool
    {
        if ($value === null) {
            return false;
        }

        if (is_string($value) && $value === 'all') {
            return false;
        }

        return $value !== '';
    }

    public function updateDocument(SaleDocument $document, array $data, ?array $items = null): SaleDocument
    {
        return DB::transaction(function () use ($document, $data, $items) {
            $fillable = array_intersect_key($data, array_flip($this->updateFillableKeys()));

            if (! empty($fillable)) {
                $document->fill($fillable);
            }

            if (array_key_exists('status', $data)) {
                $document->status = $data['status'];
            }

            if (array_key_exists('payment_status', $data)) {
                $document->payment_status = $data['payment_status'];
            }

            $document->save();

            if ($items !== null) {
                $this->syncItems($document, $items);
            }

            return $this->loadWithRelations($document);
        });
    }

    public function deleteDocument(SaleDocument $document): void
    {
        $this->ensureDocumentCanBeDeleted($document);

        DB::transaction(function () use ($document) {
            $document->items()->delete();
            $document->delete();

            if ($document->isSalesOrder() && $document->quote_id) {
                $quote = SaleDocument::find($document->quote_id);
                if ($quote && $quote->isQuote()) {
                    $quote->update(['status' => QuoteStatus::ACCEPTED->value]);
                }
            }

            if ($document->isInvoice() && $document->parent_document_id) {
                $parentOrder = SaleDocument::find($document->parent_document_id);
                if ($parentOrder && $parentOrder->isSalesOrder()) {
                    $parentOrder->update(['status' => SalesOrderStatus::CONFIRMED->value]);
                }
            }
        });
    }

    public function loadWithRelations(SaleDocument $document): SaleDocument
    {
        $document->load([
            'items.item',
            'client',
            'creator',
            'executor',
            'parent',
            'children',
            'payments',
        ]);

        if ($document->relationLoaded('client') && ! $document->relationLoaded('contact')) {
            $document->setRelation('contact', $document->getRelation('client'));
        }

        return $document;
    }

    public function findAccessibleDocumentOrFail(
        SaleDocument $document,
        ?User $user,
        ?string $ownPermission,
        ?string $areaPermission,
        ?string $allPermission,
    ): SaleDocument {
        $query = SaleDocument::query()
            ->whereKey($document->getKey())
            ->where('document_type', $document->document_type);

        ScopeHelper::apply($query, $user, $ownPermission, $areaPermission, $allPermission);

        if (! $query->exists()) {
            throw new AuthorizationException('No tienes permisos para acceder a este documento de ingresos.');
        }

        return $this->loadWithRelations($document);
    }

    protected function syncItems(SaleDocument $document, array $items): void
    {
        $document->items()->delete();

        $payload = [];

        foreach ($items as $item) {
            $normalized = $this->normalizeDocumentItem($item);
            $rawItemId = $item['item_id'] ?? null;
            if (is_string($rawItemId)) {
                $rawItemId = trim($rawItemId);
            }

            $itemName = isset($item['item_name']) ? trim((string) $item['item_name']) : null;

            if ($rawItemId !== null && $rawItemId !== '' && ! is_numeric($rawItemId)) {
                $itemName = $itemName ?: (string) $rawItemId;
                $rawItemId = null;
            }

            $resolvedItemId = is_numeric($rawItemId) ? (int) $rawItemId : null;
            $hasReference = $resolvedItemId !== null || ($itemName !== null && $itemName !== '');

            if (! $hasReference || empty($item['quantity'])) {
                continue;
            }

            $payload[] = [
                'sale_document_id' => $document->id,
                'item_id' => $resolvedItemId,
                'item_name' => $itemName,
                'quantity' => $normalized['quantity'],
                'price' => $normalized['unit_price'],
                'discount' => $normalized['discount'],
                'tax' => $normalized['tax_rate'],
                'total' => $normalized['subtotal'],
                'total_tax' => $normalized['tax_amount'],
                'supplier_code' => $item['supplier_code'] ?? null,
                'supplier_color' => $item['supplier_color'] ?? null,
                'order_width' => $item['order_width'] ?? null,
                'description' => $item['description'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if (! empty($payload)) {
            foreach ($payload as $row) {
                $rowToCreate = $row;
                unset($rowToCreate['sale_document_id']);
                $document->items()->create($rowToCreate);
            }
        }
    }

    protected function ensureDocumentCanBeDeleted(SaleDocument $document): void
    {
        if ($document->isQuote() && ! $document->canDeleteQuote()) {
            throw ValidationException::withMessages([
                'document' => 'Solo se pueden eliminar cotizaciones en estado borrador.',
            ]);
        }

        if (! $document->isSalesOrder()) {
            if (! $document->isInvoice()) {
                if (! $document->isCreditDebitNote()) {
                    return;
                }

                if (! $document->canDeleteNote()) {
                    throw ValidationException::withMessages([
                        'document' => 'Solo se pueden eliminar notas de venta en estado borrador.',
                    ]);
                }

                return;
            }

            if (! $document->canDeleteInvoice()) {
                throw ValidationException::withMessages([
                    'document' => 'Solo se pueden eliminar facturas de venta en estado borrador.',
                ]);
            }

            if ($document->payments()->exists()) {
                throw ValidationException::withMessages([
                    'document' => 'No se puede eliminar una factura de venta con pagos aplicados.',
                ]);
            }

            return;
        }

        if (! $document->canDeleteSalesOrder()) {
            throw ValidationException::withMessages([
                'document' => 'Solo se pueden eliminar pedidos de venta en estado borrador.',
            ]);
        }

        $hasInvoices = $document->children()
            ->where('document_type', SaleDocument::TYPE_INVOICE)
            ->exists();

        if ($hasInvoices) {
            throw ValidationException::withMessages([
                'document' => 'No se puede eliminar un pedido de venta que ya tiene facturas vinculadas.',
            ]);
        }
    }

    protected function updateFillableKeys(): array
    {
        return [
            'document_number',
            'parent_document_id',
            'quote_id',
            'contact_id',
            'date',
            'due_date',
            'delivery_date',
            'description',
            'payment_terms',
            'payment_days',
            'payment_status',
            'amount_paid',
            'customer_order_number',
            'executed_by_user_id',
            'status',
            'withholdings',
        ];
    }

    public function generateDocumentNumberForType(string $documentType): string
    {
        return match ($documentType) {
            SaleDocument::TYPE_QUOTE => $this->generateDocumentNumber('COT', $documentType),
            SaleDocument::TYPE_SALES_ORDER => $this->generateDocumentNumber('PED', $documentType),
            SaleDocument::TYPE_INVOICE => $this->generateDocumentNumber('FV', $documentType),
            SaleDocument::TYPE_CREDIT_NOTE => $this->generateDocumentNumber('NCV', $documentType),
            SaleDocument::TYPE_DEBIT_NOTE => $this->generateDocumentNumber('NDV', $documentType),
            SaleDocument::TYPE_CASH_RECEIPT => $this->generateDocumentNumber('RC', $documentType),
            default => $this->generateDocumentNumber('DOC', $documentType),
        };
    }

    protected function buildBaseData(array $data, string $documentType): array
    {
        return [
            'document_type' => $documentType,
            'document_number' => $this->resolveDocumentNumber($data, $documentType),
            'contact_id' => $data['contact_id'],
            'date' => $data['date'] ?? now()->toDateString(),
            'due_date' => $data['due_date'] ?? null,
            'delivery_date' => $data['delivery_date'] ?? null,
            'description' => $data['description'] ?? null,
            'payment_terms' => $data['payment_terms'] ?? null,
            'payment_days' => $data['payment_days'] ?? null,
            'customer_order_number' => $data['customer_order_number'] ?? null,
            'withholdings' => $data['withholdings'] ?? null,
            'invoiced_at' => $data['invoiced_at'] ?? null,
            'created_by_user_id' => $data['created_by_user_id'] ?? Auth::id(),
            'executed_by_user_id' => $data['executed_by_user_id'] ?? null,
        ];
    }

    protected function resolveDocumentNumber(array $data, string $documentType): ?string
    {
        if (! array_key_exists('document_number', $data)) {
            return null;
        }

        return $data['document_number'] ?: null;
    }

    protected function normalizeDocumentItem(array $item): array
    {
        $quantity = max((float) ($item['quantity'] ?? 0), 0);
        $unitPrice = max((float) ($item['unit_price'] ?? $item['price'] ?? 0), 0);
        $discount = min(max((float) ($item['discount'] ?? 0), 0), 100);
        $taxRate = max((float) ($item['tax_rate'] ?? $item['tax'] ?? 0), 0);

        $lineBase = round($quantity * $unitPrice, 2);
        $discountAmount = round($lineBase * ($discount / 100), 2);
        $subtotal = round($lineBase - $discountAmount, 2);
        $taxAmount = round($subtotal * ($taxRate / 100), 2);

        return [
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'discount' => $discount,
            'tax_rate' => $taxRate,
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
        ];
    }

    protected function defaultStatusFor(string $documentType): string
    {
        return match ($documentType) {
            SaleDocument::TYPE_QUOTE => QuoteStatus::DRAFT->value,
            SaleDocument::TYPE_SALES_ORDER => SalesOrderStatus::CONFIRMED->value,
            default => SalesInvoiceStatus::DRAFT->value,
        };
    }

    protected function generateDocumentNumber(string $prefix, string $documentType): string
    {
        $nextId = $this->nextDocumentSequence($documentType);

        return sprintf('%s-%03d', $prefix, $nextId);
    }

    protected function nextDocumentSequence(string $documentType): int
    {
        return DB::transaction(function () use ($documentType) {
            $sequence = DB::table('finance_document_sequences')
                ->where('document_type', $documentType)
                ->lockForUpdate()
                ->first();

            if (! $sequence) {
                throw new RuntimeException("No existe la secuencia para documentos de venta tipo {$documentType}.");
            }

            $nextValue = (int) $sequence->current_value + 1;

            DB::table('finance_document_sequences')
                ->where('id', $sequence->id)
                ->update([
                    'current_value' => $nextValue,
                    'updated_at' => now(),
                ]);

            return $nextValue;
        });
    }
}
