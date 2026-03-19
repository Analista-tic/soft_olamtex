<?php

namespace App\Services\Income;

use App\Enums\Income\PaymentStatus;
use App\Enums\Income\SaleNoteStatus;
use App\Enums\Income\SalesInvoiceStatus;
use App\Enums\Income\SalesOrderStatus;
use App\Models\Income\SaleDocument;
use App\Models\Income\SaleDocumentItem;
use App\Models\Income\SalePayment;
use App\Services\Finance\AccountingPostingService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SalesInvoiceService
{
    public function __construct(
        private readonly AccountingPostingService $accountingPostingService,
    ) {
    }

    public function emit(SaleDocument $invoice, ?int $userId = null): SaleDocument
    {
        if (! $invoice->isInvoice()) {
            throw new \InvalidArgumentException('Solo se pueden emitir facturas de venta.');
        }

        if (! $invoice->canEmitInvoice()) {
            throw ValidationException::withMessages([
                'document' => 'Solo se pueden emitir facturas de venta en estado borrador.',
            ]);
        }

        return DB::transaction(function () use ($invoice, $userId) {
            /** @var SaleDocument $lockedInvoice */
            $lockedInvoice = SaleDocument::query()
                ->with(['items.item.tax', 'payments'])
                ->lockForUpdate()
                ->findOrFail($invoice->id);

            if (! $lockedInvoice->canEmitInvoice()) {
                throw ValidationException::withMessages([
                    'document' => 'La factura de venta ya no se encuentra en estado borrador.',
                ]);
            }

            $lockedInvoice->status = SalesInvoiceStatus::ISSUED->value;
            $lockedInvoice->payment_status = PaymentStatus::UNPAID->value;
            $lockedInvoice->amount_paid = 0;
            $lockedInvoice->invoiced_at = now();
            $lockedInvoice->executed_by_user_id = $lockedInvoice->executed_by_user_id
                ?? $userId
                ?? Auth::id();
            $lockedInvoice->save();

            if ($lockedInvoice->parent_document_id) {
                $parentOrder = SaleDocument::query()
                    ->lockForUpdate()
                    ->find($lockedInvoice->parent_document_id);

                if ($parentOrder && $parentOrder->isSalesOrder()) {
                    $parentOrder->update(['status' => SalesOrderStatus::INVOICED->value]);
                }
            }

            $this->accountingPostingService->postSalesInvoice($lockedInvoice);

            return $this->hydratePaymentState($lockedInvoice->fresh() ?? $lockedInvoice, true);
        });
    }

    public function void(SaleDocument $invoice, string $reason = ''): SaleDocument
    {
        if (! $invoice->isInvoice()) {
            throw new \InvalidArgumentException('Solo se pueden anular facturas de venta.');
        }

        if (! $invoice->canVoidInvoice()) {
            throw ValidationException::withMessages([
                'document' => 'Solo se pueden anular facturas de venta emitidas o parciales.',
            ]);
        }

        return DB::transaction(function () use ($invoice, $reason) {
            /** @var SaleDocument $lockedInvoice */
            $lockedInvoice = SaleDocument::query()
                ->with(['items.item.tax', 'payments'])
                ->lockForUpdate()
                ->findOrFail($invoice->id);

            if (! $lockedInvoice->canVoidInvoice()) {
                throw ValidationException::withMessages([
                    'document' => 'La factura de venta ya no se encuentra en un estado anulable.',
                ]);
            }

            if (SalePayment::query()->where('sale_invoice_id', $lockedInvoice->id)->exists()) {
                throw ValidationException::withMessages([
                    'document' => 'No se puede anular una factura con pagos aplicados.',
                ]);
            }

            $reversalEntry = $this->accountingPostingService->reverseEntry(
                AccountingPostingService::SOURCE_SALES_INVOICE,
                (int) $lockedInvoice->id,
                trim($reason) !== '' ? $reason : 'Anulacion de factura de venta',
            );

            if (! $reversalEntry) {
                throw ValidationException::withMessages([
                    'document' => 'La factura no tiene un asiento contable activo para anular.',
                ]);
            }

            $lockedInvoice->status = SalesInvoiceStatus::VOIDED->value;
            $lockedInvoice->payment_status = PaymentStatus::UNPAID->value;
            $lockedInvoice->amount_paid = 0;
            $lockedInvoice->save();

            if ($lockedInvoice->parent_document_id) {
                $parentOrder = SaleDocument::query()
                    ->lockForUpdate()
                    ->find($lockedInvoice->parent_document_id);

                if ($parentOrder && $parentOrder->isSalesOrder()) {
                    $parentOrder->update(['status' => SalesOrderStatus::CONFIRMED->value]);
                }
            }

            return $this->hydratePaymentState($lockedInvoice->fresh() ?? $lockedInvoice, true);
        });
    }

    public function hydratePaymentState(SaleDocument $invoice, bool $persist = false): SaleDocument
    {
        $invoice->loadMissing([
            'items',
            'payments',
            'client',
            'creator',
            'executor',
            'parent',
            'children',
        ]);

        $paymentsTotal = round(
            (float) $invoice->payments->sum(fn (SalePayment $payment) => (float) ($payment->amount ?? 0)),
            2
        );
        $creditNotesTotal = $invoice->children
            ->filter(fn (SaleDocument $child) => $child->isCreditNote() && $child->status === SaleNoteStatus::ISSUED->value)
            ->reduce(
                fn (float $carry, SaleDocument $child) => $carry + $child->totalNet(),
                0.0
            );
        $amountPaid = round($paymentsTotal + $creditNotesTotal, 2);

        $invoiceTotal = $this->calculateEffectiveTotal($invoice);
        $paymentStatus = $this->resolvePaymentStatus($amountPaid, $invoiceTotal);
        $resolvedStatus = $this->resolveInvoiceStatusFromPayments($invoice->status, $amountPaid, $invoiceTotal);

        $invoice->amount_paid = $amountPaid;
        $invoice->payment_status = $paymentStatus;
        $invoice->status = $resolvedStatus;

        if ($persist && $invoice->exists && $invoice->isDirty(['amount_paid', 'payment_status', 'status'])) {
            $invoice->save();
        }

        if ($invoice->relationLoaded('client') && ! $invoice->relationLoaded('contact')) {
            $invoice->setRelation('contact', $invoice->getRelation('client'));
        }

        return $invoice;
    }

    public function updatePaymentStatus(SaleDocument $invoice, bool $persist = true): SaleDocument
    {
        return $this->hydratePaymentState($invoice, $persist);
    }

    public function calculateEffectiveTotal(SaleDocument $invoice): float
    {
        $invoice->loadMissing('items', 'children.items');

        $baseTotal = $this->calculateInvoiceTotal($invoice);
        $debitNotesTotal = $invoice->children
            ->filter(fn (SaleDocument $child) => $child->isDebitNote() && $child->status === SaleNoteStatus::ISSUED->value)
            ->reduce(
                fn (float $carry, SaleDocument $child) => $carry + $child->totalNet(),
                0.0
            );

        return round($baseTotal + $debitNotesTotal, 2);
    }

    public function calculateOutstandingBalance(SaleDocument $invoice): float
    {
        $effectiveTotal = $this->calculateEffectiveTotal($invoice);
        $amountPaid = round((float) ($invoice->amount_paid ?? 0), 2);

        return round(max($effectiveTotal - $amountPaid, 0), 2);
    }

    private function calculateInvoiceTotal(SaleDocument $invoice): float
    {
        $itemsTotal = round(
            (float) $invoice->items->sum(
                fn (SaleDocumentItem $item) => ((float) ($item->total ?? 0)) + ((float) ($item->total_tax ?? 0))
            ),
            2
        );

        $withholdingsTotal = collect(is_array($invoice->withholdings) ? $invoice->withholdings : [])
            ->reduce(function (float $carry, mixed $withholding) use ($invoice) {
                if (! is_array($withholding)) {
                    return $carry;
                }

                $percentage = round((float) ($withholding['percentage'] ?? 0), 2);
                if ($percentage <= 0) {
                    return $carry;
                }

                $baseAmount = isset($withholding['base_amount']) && is_numeric($withholding['base_amount'])
                    ? round((float) $withholding['base_amount'], 2)
                    : round(
                        (float) $invoice->items->sum(fn (SaleDocumentItem $item) => (float) ($item->total ?? 0)),
                        2
                    );

                return $carry + round(($baseAmount * $percentage) / 100, 2);
            }, 0.0);

        return round(max($itemsTotal - $withholdingsTotal, 0), 2);
    }

    private function resolvePaymentStatus(float $amountPaid, float $invoiceTotal): string
    {
        if ($amountPaid <= 0) {
            return PaymentStatus::UNPAID->value;
        }

        if ($invoiceTotal > 0 && $amountPaid + 0.01 >= $invoiceTotal) {
            return PaymentStatus::PAID->value;
        }

        return PaymentStatus::PARTIAL->value;
    }

    private function resolveInvoiceStatusFromPayments(?string $currentStatus, float $amountPaid, float $invoiceTotal): string
    {
        $status = trim((string) $currentStatus);

        if ($status === SalesInvoiceStatus::VOIDED->value || $status === SalesInvoiceStatus::DRAFT->value) {
            return $status === '' ? SalesInvoiceStatus::DRAFT->value : $status;
        }

        if ($amountPaid <= 0) {
            return SalesInvoiceStatus::ISSUED->value;
        }

        if ($invoiceTotal > 0 && $amountPaid + 0.01 >= $invoiceTotal) {
            return SalesInvoiceStatus::PAID->value;
        }

        return SalesInvoiceStatus::PARTIAL->value;
    }
}
