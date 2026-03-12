<?php

namespace App\Services\Finance;

use App\Models\Inventory\InventoryPeriodLock;
use App\Models\Finance\ExpenseVoucher;
use App\Models\Expenses\PurchasePayment;
use App\Services\Expenses\PurchasePaymentService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;

class ExpenseVoucherService
{
    public function __construct(
        private TreasuryVoucherService $treasuryVoucherService,
        private PurchasePaymentService $purchasePaymentService,
        private ExpenseVoucherNumberSequenceService $expenseVoucherNumberSequenceService,
    ) {
    }

    public function paginate(array $filters = [], int $perPage = 10)
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $providerId = isset($filters['provider_id']) ? (int) $filters['provider_id'] : null;
        $status = trim((string) ($filters['status'] ?? 'all'));
        $dateFrom = $filters['date_from'] ?? null;
        $dateTo = $filters['date_to'] ?? null;
        $voucherKeyExpression = 'COALESCE(purchase_payments.voucher_number, purchase_payments.id)';

        $matchingVoucherKeys = PurchasePayment::query()
            ->join('purchase_documents', 'purchase_documents.id', '=', 'purchase_payments.purchase_invoice_id')
            ->leftJoin('finance_expense_vouchers as vouchers', 'vouchers.voucher_number', '=', 'purchase_payments.voucher_number')
            ->leftJoin('contacts', 'contacts.id', '=', 'purchase_documents.contact_id')
            ->selectRaw("{$voucherKeyExpression} AS voucher_key");

        if ($search !== '') {
            $matchingVoucherKeys->where(function ($builder) use ($search) {
                if (ctype_digit($search)) {
                    $builder->where('purchase_payments.voucher_number', (int) $search)
                        ->orWhere('purchase_payments.reference', 'like', "%{$search}%")
                        ->orWhere('vouchers.reference', 'like', "%{$search}%")
                        ->orWhere('purchase_documents.document_number', 'like', "%{$search}%")
                        ->orWhere('contacts.full_name', 'like', "%{$search}%");
                    return;
                }

                $builder->where('purchase_payments.reference', 'like', "%{$search}%")
                    ->orWhere('vouchers.reference', 'like', "%{$search}%")
                    ->orWhere('purchase_documents.document_number', 'like', "%{$search}%")
                    ->orWhere('contacts.full_name', 'like', "%{$search}%");
            });
        }

        if ($providerId && $providerId > 0) {
            $matchingVoucherKeys->whereRaw('COALESCE(vouchers.contact_id, purchase_documents.contact_id) = ?', [$providerId]);
        }

        if ($status === 'recorded') {
            // Fase 1: todos los comprobantes persistidos se consideran registrados.
            $matchingVoucherKeys->whereNotNull('purchase_payments.id');
        }

        if ($dateFrom) {
            $matchingVoucherKeys->whereDate(DB::raw('COALESCE(vouchers.payment_date, purchase_payments.payment_date)'), '>=', $dateFrom);
        }

        if ($dateTo) {
            $matchingVoucherKeys->whereDate(DB::raw('COALESCE(vouchers.payment_date, purchase_payments.payment_date)'), '<=', $dateTo);
        }

        $matchingVoucherKeys->distinct();

        $query = DB::table('purchase_payments')
            ->join('purchase_documents', 'purchase_documents.id', '=', 'purchase_payments.purchase_invoice_id')
            ->leftJoin('finance_expense_vouchers as vouchers', 'vouchers.voucher_number', '=', 'purchase_payments.voucher_number')
            ->leftJoin('contacts', 'contacts.id', '=', 'purchase_documents.contact_id')
            ->leftJoin('users', 'users.id', '=', 'purchase_payments.created_by_user_id')
            ->leftJoin('users as voucher_users', 'voucher_users.id', '=', 'vouchers.created_by_user_id')
            ->whereIn(DB::raw($voucherKeyExpression), $matchingVoucherKeys)
            ->selectRaw("{$voucherKeyExpression} AS voucher_group")
            ->selectRaw('MIN(purchase_payments.id) AS id')
            ->selectRaw('MAX(purchase_payments.voucher_number) AS voucher_number')
            ->selectRaw('COALESCE(MIN(vouchers.payment_date), MIN(purchase_payments.payment_date)) AS payment_date')
            ->selectRaw('COALESCE(MIN(vouchers.created_at), MIN(purchase_payments.created_at)) AS created_at')
            ->selectRaw('COALESCE(MAX(vouchers.contact_id), MAX(purchase_documents.contact_id)) AS provider_id')
            ->selectRaw('MAX(contacts.full_name) AS supplier_name')
            ->selectRaw('COALESCE(MAX(voucher_users.name), MAX(users.name)) AS created_by')
            ->selectRaw('MAX(vouchers.account_id) AS account_id')
            ->selectRaw('MAX(vouchers.payment_method) AS payment_method')
            ->selectRaw('COALESCE(MAX(vouchers.reference), MAX(purchase_payments.reference)) AS reference')
            ->selectRaw('COALESCE(MAX(vouchers.notes), MAX(purchase_payments.notes)) AS notes')
            ->selectRaw('MIN(purchase_payments.purchase_invoice_id) AS invoice_id')
            ->selectRaw('MAX(purchase_documents.document_number) AS invoice_number')
            ->selectRaw('COUNT(*) AS applications_count')
            ->selectRaw('SUM(COALESCE(purchase_payments.amount, 0)) AS total_applied')
            ->groupBy(DB::raw($voucherKeyExpression))
            ->orderByRaw('COALESCE(MIN(vouchers.payment_date), MIN(purchase_payments.payment_date)) DESC')
            ->orderByRaw('MIN(purchase_payments.id) DESC');

        return $query
            ->paginate(max(1, min($perPage, 100)))
            ->through(fn ($row) => $this->mapPaginatedVoucherRow($row));
    }

    public function findOrFail(int $id): array
    {
        $payment = PurchasePayment::query()
            ->with([
                'invoice:id,document_number,contact_id,date',
                'invoice.supplier:id,full_name',
                'creator:id,name',
            ])
            ->whereKey($id)
            ->first();

        if (!$payment) {
            throw (new ModelNotFoundException())->setModel(PurchasePayment::class, [$id]);
        }

        $voucherNumber = $payment->voucher_number;
        $voucherHeader = null;
        if ($voucherNumber !== null) {
            $voucherHeader = ExpenseVoucher::query()
                ->where('voucher_number', (int) $voucherNumber)
                ->first();
        }

        $payments = PurchasePayment::query()
            ->with([
                'invoice:id,document_number,contact_id,date',
                'invoice.supplier:id,full_name',
                'creator:id,name',
            ])
            ->when(
                $voucherNumber !== null,
                fn ($query) => $query->where('voucher_number', $voucherNumber),
                fn ($query) => $query->whereKey($payment->id),
            )
            ->orderBy('id')
            ->get();

        return $this->mapVoucherRecord($payments, $payment, $voucherHeader);
    }

    public function update(int $id, array $payload): array
    {
        $contactId = (int) ($payload['contact_id'] ?? 0);
        $date = (string) ($payload['date'] ?? '');

        if ($contactId <= 0) {
            throw ValidationException::withMessages([
                'contact_id' => 'Debe seleccionar un proveedor válido.',
            ]);
        }

        if (trim($date) === '') {
            throw ValidationException::withMessages([
                'date' => 'La fecha del comprobante es obligatoria.',
            ]);
        }

        $applications = $this->normalizeApplications($payload['applications'] ?? []);
        if ($applications->isEmpty()) {
            throw ValidationException::withMessages([
                'applications' => 'Debe registrar al menos una aplicación con monto mayor a cero.',
            ]);
        }

        $availableDocs = collect($this->treasuryVoucherService->getPendingPayableDocuments($contactId))
            ->keyBy(fn (array $doc) => (int) ($doc['id'] ?? 0));

        return DB::transaction(function () use ($id, $payload, $contactId, $date, $applications, $availableDocs) {
            $basePayment = PurchasePayment::query()
                ->whereKey($id)
                ->lockForUpdate()
                ->first();

            if (!$basePayment) {
                throw (new ModelNotFoundException())->setModel(PurchasePayment::class, [$id]);
            }

            $voucherNumber = $basePayment->voucher_number ?? (int) $basePayment->id;

            $existingPayments = PurchasePayment::query()
                ->when(
                    $basePayment->voucher_number !== null,
                    fn ($query) => $query->where('voucher_number', $basePayment->voucher_number),
                    fn ($query) => $query->whereKey($basePayment->id),
                )
                ->lockForUpdate()
                ->get();

            if ($existingPayments->isEmpty()) {
                throw ValidationException::withMessages([
                    'voucher' => 'No se encontraron líneas del comprobante a actualizar.',
                ]);
            }

            $this->assertVoucherIsEditable($existingPayments);
            $voucherHeader = $this->upsertVoucherHeader(
                voucherNumber: (int) $voucherNumber,
                payload: $payload,
                contactId: $contactId,
                date: $date,
                fallbackCreatorUserId: $basePayment->created_by_user_id ? (int) $basePayment->created_by_user_id : null,
            );

            PurchasePayment::query()
                ->whereIn('id', $existingPayments->pluck('id')->all())
                ->delete();

            $registeredApplications = $applications->values()->map(function (array $line) use ($payload, $date, $availableDocs, $voucherNumber) {
                $documentId = (int) $line['document_id'];
                $amount = (float) $line['amount'];

                $doc = $availableDocs->get($documentId);
                if (!$doc) {
                    throw ValidationException::withMessages([
                        'applications' => "La factura {$documentId} no pertenece al proveedor seleccionado o no tiene saldo pendiente.",
                    ]);
                }

                $pending = (float) ($doc['current_balance'] ?? 0);
                if ($amount > ($pending + 0.0001)) {
                    throw ValidationException::withMessages([
                        'applications' => "El monto aplicado en la factura {$documentId} supera el saldo pendiente.",
                    ]);
                }

                $payment = $this->purchasePaymentService->registerInvoicePayment([
                    'voucher_number' => $voucherNumber,
                    'purchase_invoice_id' => $documentId,
                    'payment_date' => $date,
                    'amount' => $amount,
                    'reference' => $payload['reference'] ?? null,
                    'notes' => $payload['notes'] ?? null,
                ]);

                return [
                    'payment_id' => (int) $payment->id,
                    'document_id' => $documentId,
                    'document_number' => $doc['document_number'] ?? null,
                    'document_date' => $doc['invoice_date'] ?? null,
                    'amount' => round((float) $payment->amount, 2),
                    'payment_date' => $payment->payment_date?->format('Y-m-d'),
                    'reference' => $payment->reference,
                    'voucher_number' => (string) $payment->voucher_number,
                ];
            })->values();

            return [
                'voucher_number' => $voucherNumber,
                'date' => $voucherHeader->payment_date?->format('Y-m-d') ?? $date,
                'contact_id' => $voucherHeader->contact_id ?? $contactId,
                'account_id' => $voucherHeader->account_id,
                'account_label' => $this->resolveAccountLabel($voucherHeader->account_id),
                'payment_method' => $voucherHeader->payment_method,
                'payment_method_label' => $this->resolvePaymentMethodLabel($voucherHeader->payment_method),
                'reference' => $voucherHeader->reference,
                'notes' => $voucherHeader->notes,
                'total_applied' => round((float) $registeredApplications->sum('amount'), 2),
                'applications' => $registeredApplications->all(),
            ];
        });
    }

    public function create(array $payload): array
    {
        $contactId = (int) ($payload['contact_id'] ?? 0);
        $date = (string) ($payload['date'] ?? '');

        if ($contactId <= 0) {
            throw ValidationException::withMessages([
                'contact_id' => 'Debe seleccionar un proveedor válido.',
            ]);
        }

        if (trim($date) === '') {
            throw ValidationException::withMessages([
                'date' => 'La fecha del comprobante es obligatoria.',
            ]);
        }

        $applications = $this->normalizeApplications($payload['applications'] ?? []);
        if ($applications->isEmpty()) {
            throw ValidationException::withMessages([
                'applications' => 'Debe registrar al menos una aplicación con monto mayor a cero.',
            ]);
        }

        $availableDocs = collect($this->treasuryVoucherService->getPendingPayableDocuments($contactId))
            ->keyBy(fn (array $doc) => (int) ($doc['id'] ?? 0));

        return DB::transaction(function () use ($payload, $date, $applications, $availableDocs, $contactId) {
            $voucherNumber = $this->expenseVoucherNumberSequenceService->reserveNextNumber();
            $voucherHeader = $this->upsertVoucherHeader(
                voucherNumber: (int) $voucherNumber,
                payload: $payload,
                contactId: $contactId,
                date: $date,
                fallbackCreatorUserId: null,
            );

            $registeredApplications = $applications->values()->map(function (array $line) use ($payload, $date, $availableDocs, $voucherNumber) {
                $documentId = (int) $line['document_id'];
                $amount = (float) $line['amount'];

                $doc = $availableDocs->get($documentId);
                if (!$doc) {
                    throw ValidationException::withMessages([
                        'applications' => "La factura {$documentId} no pertenece al proveedor seleccionado o no tiene saldo pendiente.",
                    ]);
                }

                $pending = (float) ($doc['current_balance'] ?? 0);
                if ($amount > ($pending + 0.0001)) {
                    throw ValidationException::withMessages([
                        'applications' => "El monto aplicado en la factura {$documentId} supera el saldo pendiente.",
                    ]);
                }

                $payment = $this->purchasePaymentService->registerInvoicePayment([
                    'voucher_number' => $voucherNumber,
                    'purchase_invoice_id' => $documentId,
                    'payment_date' => $date,
                    'amount' => $amount,
                    'reference' => $payload['reference'] ?? null,
                    'notes' => $payload['notes'] ?? null,
                ]);

                return [
                    'payment_id' => (int) $payment->id,
                    'document_id' => $documentId,
                    'document_number' => $doc['document_number'] ?? null,
                    'document_date' => $doc['invoice_date'] ?? null,
                    'amount' => round((float) $payment->amount, 2),
                    'payment_date' => $payment->payment_date?->format('Y-m-d'),
                    'reference' => $payment->reference,
                    'voucher_number' => (string) $payment->voucher_number,
                ];
            })->values();

            return [
                'voucher_number' => $voucherNumber,
                'date' => $voucherHeader->payment_date?->format('Y-m-d') ?? $date,
                'contact_id' => $voucherHeader->contact_id ?? $contactId,
                'account_id' => $voucherHeader->account_id,
                'account_label' => $this->resolveAccountLabel($voucherHeader->account_id),
                'payment_method' => $voucherHeader->payment_method,
                'payment_method_label' => $this->resolvePaymentMethodLabel($voucherHeader->payment_method),
                'reference' => $voucherHeader->reference,
                'notes' => $voucherHeader->notes,
                'total_applied' => round((float) $registeredApplications->sum('amount'), 2),
                'applications' => $registeredApplications->all(),
            ];
        });
    }

    private function normalizeApplications(array $rows): Collection
    {
        return collect($rows)
            ->map(function ($row) {
                $data = is_array($row) ? $row : (array) $row;
                return [
                    'document_id' => (int) ($data['document_id'] ?? 0),
                    'amount' => round((float) ($data['amount'] ?? 0), 2),
                ];
            })
            ->filter(fn (array $line) => $line['document_id'] > 0 && $line['amount'] > 0)
            ->values();
    }

    private function upsertVoucherHeader(
        int $voucherNumber,
        array $payload,
        int $contactId,
        string $date,
        ?int $fallbackCreatorUserId = null,
    ): ExpenseVoucher {
        $accountId = isset($payload['account_id']) && $payload['account_id'] !== null
            ? (int) $payload['account_id']
            : null;

        $paymentMethod = isset($payload['payment_method']) && trim((string) $payload['payment_method']) !== ''
            ? trim((string) $payload['payment_method'])
            : null;

        $reference = isset($payload['reference']) && trim((string) $payload['reference']) !== ''
            ? trim((string) $payload['reference'])
            : null;

        $notes = isset($payload['notes']) && trim((string) $payload['notes']) !== ''
            ? trim((string) $payload['notes'])
            : null;

        $header = ExpenseVoucher::query()
            ->lockForUpdate()
            ->firstOrNew(['voucher_number' => $voucherNumber]);

        $header->fill([
            'contact_id' => $contactId > 0 ? $contactId : null,
            'payment_date' => $date,
            'account_id' => $accountId,
            'payment_method' => $paymentMethod,
            'reference' => $reference,
            'notes' => $notes,
        ]);

        if (!$header->exists) {
            $header->created_by_user_id = Auth::id() ?? $fallbackCreatorUserId;
        }

        $header->save();

        return $header;
    }

    private function resolveAccountLabel(?int $accountId): ?string
    {
        if (!$accountId) {
            return null;
        }

        static $map = null;
        if ($map === null) {
            $map = collect($this->treasuryVoucherService->getAccountOptions())
                ->mapWithKeys(fn (array $option) => [(int) ($option['id'] ?? 0) => (string) ($option['name'] ?? '')])
                ->all();
        }

        $label = $map[$accountId] ?? null;
        return is_string($label) && $label !== '' ? $label : null;
    }

    private function resolvePaymentMethodLabel(?string $paymentMethod): ?string
    {
        if ($paymentMethod === null || trim($paymentMethod) === '') {
            return null;
        }

        static $map = null;
        if ($map === null) {
            $map = collect($this->treasuryVoucherService->getPaymentMethodOptions())
                ->mapWithKeys(fn (array $option) => [(string) ($option['id'] ?? '') => (string) ($option['name'] ?? '')])
                ->all();
        }

        $key = trim($paymentMethod);
        $label = $map[$key] ?? null;
        if (is_string($label) && $label !== '') {
            return $label;
        }

        return $key;
    }

    private function mapPaymentRow(PurchasePayment $payment): array
    {
        $supplierName = $payment->invoice?->supplier?->full_name ?? '-';
        $invoiceNumber = $payment->invoice?->document_number;
        $reference = $payment->reference;
        $notes = $payment->notes;

        $details = collect([
            $invoiceNumber ? "Factura: {$invoiceNumber}" : null,
            $reference ? "Ref: {$reference}" : null,
        ])
            ->filter()
            ->implode(' · ');

        return [
            'id' => (int) $payment->id,
            'voucher_number' => (string) ($payment->voucher_number ?? $payment->id),
            'payment_date' => $payment->payment_date?->format('Y-m-d'),
            'created_at' => $payment->created_at?->format('Y-m-d H:i:s'),
            'provider_id' => $payment->invoice?->contact_id ? (int) $payment->invoice->contact_id : null,
            'supplier_name' => $supplierName,
            'details' => $details !== '' ? $details : '-',
            'invoice_id' => $payment->purchase_invoice_id ? (int) $payment->purchase_invoice_id : null,
            'invoice_number' => $invoiceNumber,
            'account_label' => '-',
            'reconciled' => false,
            'reconciled_label' => 'No conciliado',
            'reconciled_tone' => 'gray',
            'reference' => $reference,
            'notes' => $notes,
            'total_applied' => round((float) $payment->amount, 2),
            'status' => 'recorded',
            'status_label' => 'Registrado',
            'status_tone' => 'green',
            'created_by' => $payment->creator?->name,
        ];
    }

    private function mapPaginatedVoucherRow(object $row): array
    {
        $applicationsCount = (int) ($row->applications_count ?? 0);
        $invoiceNumber = $row->invoice_number ? (string) $row->invoice_number : null;
        $reference = $row->reference ? (string) $row->reference : null;
        $accountId = $row->account_id != null ? (int) $row->account_id : null;
        $paymentMethod = $row->payment_method ? (string) $row->payment_method : null;
        $paymentMethodLabel = $this->resolvePaymentMethodLabel($paymentMethod);
        $accountLabel = $this->resolveAccountLabel($accountId);

        $details = collect([
            $applicationsCount > 1
                ? "Facturas: {$applicationsCount}"
                : ($invoiceNumber ? "Factura: {$invoiceNumber}" : null),
            $reference ? "Ref: {$reference}" : null,
            $paymentMethodLabel ? "Medio: {$paymentMethodLabel}" : null,
        ])
            ->filter()
            ->implode(' · ');

        return [
            'id' => (int) ($row->id ?? 0),
            'voucher_number' => (string) ($row->voucher_number ?? $row->voucher_group ?? ''),
            'payment_date' => $row->payment_date ? (string) $row->payment_date : null,
            'created_at' => $row->created_at ? (string) $row->created_at : null,
            'provider_id' => $row->provider_id != null ? (int) $row->provider_id : null,
            'supplier_name' => $row->supplier_name ? (string) $row->supplier_name : '-',
            'details' => $details !== '' ? $details : '-',
            'invoice_id' => $applicationsCount === 1 && $row->invoice_id != null ? (int) $row->invoice_id : null,
            'invoice_number' => $applicationsCount === 1 ? $invoiceNumber : null,
            'account_id' => $accountId,
            'account_label' => $accountLabel ?? '-',
            'payment_method' => $paymentMethod,
            'payment_method_label' => $paymentMethodLabel,
            'reconciled' => false,
            'reconciled_label' => 'No conciliado',
            'reconciled_tone' => 'gray',
            'reference' => $reference,
            'notes' => $row->notes ? (string) $row->notes : null,
            'total_applied' => round((float) ($row->total_applied ?? 0), 2),
            'status' => 'recorded',
            'status_label' => 'Registrado',
            'status_tone' => 'green',
            'created_by' => $row->created_by ? (string) $row->created_by : null,
        ];
    }

    private function mapVoucherRecord($payments, PurchasePayment $fallbackPayment, ?ExpenseVoucher $voucherHeader = null): array
    {
        $rows = collect($payments);
        $primary = $rows->first() ?: $fallbackPayment;

        $voucherNumber = (int) ($primary->voucher_number ?? $fallbackPayment->id);
        if ($voucherHeader === null && $primary->voucher_number !== null) {
            $voucherHeader = ExpenseVoucher::query()
                ->where('voucher_number', (int) $primary->voucher_number)
                ->first();
        }

        $voucherDate = $voucherHeader?->payment_date?->format('Y-m-d') ?? $primary->payment_date?->format('Y-m-d');
        $contactId = $voucherHeader?->contact_id
            ? (int) $voucherHeader->contact_id
            : ($primary->invoice?->contact_id ? (int) $primary->invoice->contact_id : 0);
        $accountId = $voucherHeader?->account_id ? (int) $voucherHeader->account_id : null;
        $paymentMethod = $voucherHeader?->payment_method;
        $paymentMethodLabel = $this->resolvePaymentMethodLabel($paymentMethod);
        $accountLabel = $this->resolveAccountLabel($accountId);
        $supplierName = $primary->invoice?->supplier?->full_name ?? '-';
        $invoiceNumber = $primary->invoice?->document_number;
        $reference = $voucherHeader?->reference ?? $primary->reference;
        $notes = $voucherHeader?->notes ?? $primary->notes;

        $details = collect([
            $invoiceNumber ? "Factura: {$invoiceNumber}" : null,
            $reference ? "Ref: {$reference}" : null,
            $paymentMethodLabel ? "Medio: {$paymentMethodLabel}" : null,
        ])
            ->filter()
            ->implode(' · ');

        return [
            'id' => (int) $primary->id,
            'voucher_number' => (string) $voucherNumber,
            'date' => $voucherDate,
            'payment_date' => $voucherDate,
            'created_at' => $voucherHeader?->created_at?->format('Y-m-d H:i:s') ?? $primary->created_at?->format('Y-m-d H:i:s'),
            'contact_id' => $contactId,
            'provider_id' => $contactId > 0 ? $contactId : null,
            'supplier_name' => $supplierName,
            'details' => $details !== '' ? $details : '-',
            'invoice_id' => $primary->purchase_invoice_id ? (int) $primary->purchase_invoice_id : null,
            'invoice_number' => $invoiceNumber,
            'account_id' => $accountId,
            'account_label' => $accountLabel ?? '-',
            'payment_method' => $paymentMethod,
            'payment_method_label' => $paymentMethodLabel,
            'reconciled' => false,
            'reconciled_label' => 'No conciliado',
            'reconciled_tone' => 'gray',
            'reference' => $reference,
            'notes' => $notes,
            'total_applied' => round((float) $rows->sum(fn (PurchasePayment $row) => (float) $row->amount), 2),
            'status' => 'recorded',
            'status_label' => 'Registrado',
            'status_tone' => 'green',
            'created_by' => $primary->creator?->name,
            'applications' => $rows->map(fn (PurchasePayment $row) => [
                'payment_id' => (int) $row->id,
                'document_id' => (int) $row->purchase_invoice_id,
                'document_number' => $row->invoice?->document_number,
                'document_date' => $row->invoice?->date?->format('Y-m-d'),
                'amount' => round((float) $row->amount, 2),
                'payment_date' => $row->payment_date?->format('Y-m-d'),
                'reference' => $row->reference,
            ])->values()->all(),
        ];
    }

    private function assertVoucherIsEditable(Collection $voucherPayments): void
    {
        if ($this->hasClosedPeriodPayment($voucherPayments) || $this->hasBlockedStateMarker($voucherPayments)) {
            throw ValidationException::withMessages([
                'voucher' => 'Este comprobante no puede editarse en su estado actual.',
            ]);
        }
    }

    private function hasClosedPeriodPayment(Collection $voucherPayments): bool
    {
        return $voucherPayments->contains(function (PurchasePayment $payment) {
            $paymentDate = $payment->payment_date;
            if (!$paymentDate) {
                return false;
            }

            return InventoryPeriodLock::isPeriodClosed((int) $paymentDate->year, (int) $paymentDate->month);
        });
    }

    private function hasBlockedStateMarker(Collection $voucherPayments): bool
    {
        $blockedStatuses = ['reconciled', 'closed', 'cancelled', 'finalized', 'locked', 'void'];

        if (
            Schema::hasColumn('purchase_payments', 'is_reconciled')
            && $voucherPayments->contains(fn (PurchasePayment $payment) => (bool) $payment->getAttribute('is_reconciled'))
        ) {
            return true;
        }

        if (
            Schema::hasColumn('purchase_payments', 'reconciled_at')
            && $voucherPayments->contains(fn (PurchasePayment $payment) => $payment->getAttribute('reconciled_at') !== null)
        ) {
            return true;
        }

        if (
            Schema::hasColumn('purchase_payments', 'reconciliation_id')
            && $voucherPayments->contains(fn (PurchasePayment $payment) => $payment->getAttribute('reconciliation_id') !== null)
        ) {
            return true;
        }

        if (!Schema::hasColumn('purchase_payments', 'status')) {
            return false;
        }

        return $voucherPayments->contains(function (PurchasePayment $payment) use ($blockedStatuses) {
            $status = strtolower(trim((string) $payment->getAttribute('status')));
            return $status !== '' && in_array($status, $blockedStatuses, true);
        });
    }
}
