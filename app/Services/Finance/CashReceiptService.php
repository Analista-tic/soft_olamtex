<?php

namespace App\Services\Finance;

use App\Helpers\ScopeHelper;
use App\Models\Finance\CashReceipt;
use App\Models\Finance\JournalEntry;
use App\Models\Income\SaleDocument;
use App\Models\Income\SalePayment;
use App\Models\Settings\User;
use App\Services\Income\SalesInvoiceService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CashReceiptService
{
    public function __construct(
        private readonly AccountingPostingService $accountingPostingService,
        private readonly CashReceiptNumberSequenceService $sequenceService,
        private readonly SalesInvoiceService $salesInvoiceService,
    ) {
    }

    public function paginate(array $filters = [], int $perPage = 10, ?User $user = null): LengthAwarePaginator
    {
        $query = CashReceipt::query()
            ->with([
                'contact',
                'account',
                'creator',
                'salePayments.invoice.client',
            ])
            ->withSum('salePayments as total', 'amount')
            ->orderByDesc('id');

        ScopeHelper::apply($query, $user, 'ver propios recibos caja', null, 'ver todos recibos caja');

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('receipt_number', 'like', "%{$search}%")
                    ->orWhere('reference', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%")
                    ->orWhereHas('contact', function ($contactQuery) use ($search) {
                        $contactQuery
                            ->where('full_name', 'like', "%{$search}%")
                            ->orWhere('id_number', 'like', "%{$search}%");
                    });
            });
        }

        if (! empty($filters['contact_id'])) {
            $query->where('contact_id', (int) $filters['contact_id']);
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('payment_date', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('payment_date', '<=', $filters['date_to']);
        }

        return $query->paginate(max(1, min($perPage, 100)));
    }

    public function findOrFail(int $id): CashReceipt
    {
        $receipt = CashReceipt::query()->findOrFail($id);

        return $this->loadWithRelations($receipt);
    }

    public function create(array $data, array $applications): CashReceipt
    {
        $normalizedApplications = $this->normalizeApplications($applications);

        if ($normalizedApplications->isEmpty()) {
            throw ValidationException::withMessages([
                'applications' => 'Debes registrar al menos una aplicacion para el recibo.',
            ]);
        }

        return DB::transaction(function () use ($data, $normalizedApplications) {
            $receiptNumber = $this->sequenceService->reserveNextNumber();

            $receipt = CashReceipt::query()->create([
                'receipt_number' => $receiptNumber,
                'contact_id' => $data['contact_id'] ?? null,
                'payment_date' => $data['payment_date'] ?? null,
                'account_id' => $data['account_id'] ?? null,
                'payment_method' => $data['payment_method'] ?? null,
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by_user_id' => Auth::id(),
            ]);

            $affectedInvoiceIds = [];

            foreach ($normalizedApplications as $application) {
                $invoice = SaleDocument::query()
                    ->lockForUpdate()
                    ->findOrFail($application['sale_invoice_id']);

                if (! $invoice->isInvoice()) {
                    throw ValidationException::withMessages([
                        'applications' => 'Solo se pueden aplicar cobros sobre facturas de venta.',
                    ]);
                }

                $invoice = $this->salesInvoiceService->hydratePaymentState($invoice);

                if (! in_array($invoice->status, ['issued', 'partial'], true)) {
                    throw ValidationException::withMessages([
                        'applications' => 'Solo se pueden cobrar facturas emitidas o parciales.',
                    ]);
                }

                if ((int) $invoice->contact_id !== (int) $receipt->contact_id) {
                    throw ValidationException::withMessages([
                        'applications' => 'Todas las facturas aplicadas deben pertenecer al mismo cliente del recibo.',
                    ]);
                }

                $currentBalance = $this->salesInvoiceService->calculateOutstandingBalance($invoice);
                if (($application['amount'] - $currentBalance) > 0.01) {
                    throw ValidationException::withMessages([
                        'applications' => 'No se puede aplicar un valor mayor al saldo pendiente de la factura.',
                    ]);
                }

                SalePayment::query()->create([
                    'cash_receipt_id' => $receipt->id,
                    'cash_receipt_number' => $receipt->receipt_number,
                    'sale_invoice_id' => $invoice->id,
                    'payment_date' => $receipt->payment_date,
                    'amount' => $application['amount'],
                    'reference' => $receipt->reference,
                    'notes' => $receipt->notes,
                    'created_by_user_id' => Auth::id(),
                ]);

                $this->salesInvoiceService->updatePaymentStatus($invoice->fresh());
                $affectedInvoiceIds[] = (int) $invoice->id;
            }

            if (empty($affectedInvoiceIds)) {
                throw ValidationException::withMessages([
                    'applications' => 'El recibo no tiene aplicaciones validas para registrar.',
                ]);
            }

            $this->accountingPostingService->postCashReceipt($receipt);

            return $this->loadWithRelations($receipt->fresh());
        });
    }

    public function void(CashReceipt $receipt, string $reason = ''): CashReceipt
    {
        return DB::transaction(function () use ($receipt, $reason) {
            /** @var CashReceipt $lockedReceipt */
            $lockedReceipt = CashReceipt::query()
                ->with('salePayments')
                ->lockForUpdate()
                ->findOrFail($receipt->id);

            $payments = $lockedReceipt->salePayments()->get();
            $invoiceIds = $payments
                ->pluck('sale_invoice_id')
                ->filter()
                ->map(fn ($value) => (int) $value)
                ->unique()
                ->values();

            $lockedInvoices = SaleDocument::query()
                ->whereIn('id', $invoiceIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            SalePayment::query()
                ->where('cash_receipt_id', $lockedReceipt->id)
                ->delete();

            $this->accountingPostingService->reverseEntry(
                AccountingPostingService::SOURCE_CASH_RECEIPT,
                (int) $lockedReceipt->id,
                $reason
            );

            foreach ($invoiceIds as $invoiceId) {
                /** @var SaleDocument|null $invoice */
                $invoice = $lockedInvoices->get($invoiceId);
                if (! $invoice instanceof SaleDocument) {
                    continue;
                }

                $this->salesInvoiceService->updatePaymentStatus($invoice->fresh());
            }

            $snapshot = $this->loadWithRelations($lockedReceipt);
            $lockedReceipt->delete();

            return $snapshot;
        });
    }

    public function getPendingReceivableDocuments(int $contactId): array
    {
        if ($contactId <= 0) {
            return [];
        }

        return SaleDocument::query()
            ->where('document_type', SaleDocument::TYPE_INVOICE)
            ->where('contact_id', $contactId)
            ->whereIn('status', ['issued', 'partial'])
            ->with([
                'items.item',
                'payments',
                'children.items',
            ])
            ->orderBy('date')
            ->get()
            ->map(function (SaleDocument $invoice) {
                $hydrated = $this->salesInvoiceService->hydratePaymentState($invoice);
                $balance = round($this->salesInvoiceService->calculateOutstandingBalance($hydrated), 2);

                if ($balance <= 0) {
                    return null;
                }

                $itemsTotal = round(
                    $hydrated->items->sum(fn ($item) => (float) ($item->total ?? 0) + (float) ($item->total_tax ?? 0)),
                    2
                );

                return [
                    'id' => (int) $hydrated->id,
                    'document_number' => $hydrated->document_number,
                    'date' => $hydrated->date?->format('Y-m-d') ?? $hydrated->date,
                    'due_date' => $hydrated->due_date?->format('Y-m-d') ?? $hydrated->due_date,
                    'total' => round($this->salesInvoiceService->calculateEffectiveTotal($hydrated), 2),
                    'amount_paid' => round((float) ($hydrated->amount_paid ?? 0), 2),
                    'current_balance' => $balance,
                    'items_total' => $itemsTotal,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    public function getJournalEntry(CashReceipt $receipt): ?JournalEntry
    {
        return $this->accountingPostingService->getDocumentEntry(
            AccountingPostingService::SOURCE_CASH_RECEIPT,
            (int) $receipt->id
        );
    }

    public function loadWithRelations(CashReceipt $receipt): CashReceipt
    {
        return $receipt->load([
            'contact',
            'account',
            'creator',
            'salePayments.invoice.client',
            'salePayments.invoice.contact',
            'salePayments.creator',
        ]);
    }

    private function normalizeApplications(array $applications): Collection
    {
        $seenInvoices = [];

        return collect($applications)
            ->map(function ($application) use (&$seenInvoices) {
                $invoiceId = (int) ($application['sale_invoice_id'] ?? 0);
                $amount = round((float) ($application['amount'] ?? 0), 2);

                if ($invoiceId <= 0 || $amount <= 0) {
                    return null;
                }

                if (in_array($invoiceId, $seenInvoices, true)) {
                    throw ValidationException::withMessages([
                        'applications' => 'No se puede aplicar la misma factura mas de una vez en el mismo recibo.',
                    ]);
                }

                $seenInvoices[] = $invoiceId;

                return [
                    'sale_invoice_id' => $invoiceId,
                    'amount' => $amount,
                ];
            })
            ->filter()
            ->values();
    }
}
