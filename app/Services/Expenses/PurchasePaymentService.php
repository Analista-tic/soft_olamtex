<?php

namespace App\Services\Expenses;

use App\Enums\PurchaseInvoiceStatus;
use App\Models\Expenses\PurchaseDocument;
use App\Models\Expenses\PurchasePayment;
use App\Services\Textil\FabricRollCreditNoteService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchasePaymentService
{
    public function __construct(
        private PurchaseDocumentInvoiceService $invoiceService,
        private FabricRollCreditNoteService $rollCreditNoteService,
    ) {
    }

    public function registerInvoicePayment(array $payload): PurchasePayment
    {
        $invoiceId = (int) ($payload['purchase_invoice_id'] ?? 0);
        $amount = round((float) ($payload['amount'] ?? 0), 2);

        if ($invoiceId <= 0) {
            throw ValidationException::withMessages([
                'purchase_invoice_id' => 'Debe seleccionar una factura válida para registrar el pago.',
            ]);
        }

        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'El monto del pago debe ser mayor a cero.',
            ]);
        }

        return DB::transaction(function () use ($payload, $invoiceId, $amount) {
            $invoice = PurchaseDocument::query()->whereKey($invoiceId)->lockForUpdate()->first();
            if (!$invoice) {
                throw ValidationException::withMessages([
                    'purchase_invoice_id' => 'La factura indicada no existe.',
                ]);
            }

            $this->ensureIsInvoice($invoice);

            $summary = $this->getInvoicePaymentSummary($invoice);
            $remaining = (float) ($summary['current_balance'] ?? 0);
            if ($remaining <= 0) {
                throw ValidationException::withMessages([
                    'amount' => 'La factura ya no tiene saldo pendiente.',
                ]);
            }

            // Retención por rollos rechazados sin nota crédito aprobada: solo se puede pagar el saldo no disputado.
            $disputed = (float) ($summary['disputed_amount'] ?? 0);
            $payable = (float) ($summary['payable_balance'] ?? $remaining);
            if ($disputed > 0 && $amount > ($payable + 0.0001)) {
                $rolls = (int) ($summary['disputed_rolls_count'] ?? 0);
                throw ValidationException::withMessages([
                    'amount' => sprintf(
                        'El pago supera el saldo pagable; $%s están retenidos por %d %s sin nota crédito aprobada.',
                        number_format($disputed, 2, '.', ','),
                        $rolls,
                        $rolls === 1 ? 'rollo rechazado' : 'rollos rechazados',
                    ),
                ]);
            }

            if ($amount > ($remaining + 0.0001)) {
                throw ValidationException::withMessages([
                    'amount' => 'El pago supera el saldo pendiente de la factura.',
                ]);
            }

            $payment = PurchasePayment::create([
                'voucher_number' => isset($payload['voucher_number']) ? (int) $payload['voucher_number'] : null,
                'purchase_invoice_id' => $invoice->id,
                'payment_date' => $payload['payment_date'] ?? now()->toDateString(),
                'amount' => $amount,
                'reference' => $payload['reference'] ?? null,
                'notes' => $payload['notes'] ?? null,
                'created_by_user_id' => Auth::id(),
            ]);

            return $payment->load([
                'creator:id,name',
            ]);
        });
    }

    public function getInvoicePayments(PurchaseDocument $invoice): Collection
    {
        $this->ensureIsInvoice($invoice);

        return PurchasePayment::query()
            ->where('purchase_invoice_id', $invoice->id)
            ->with(['creator:id,name'])
            ->orderByDesc('payment_date')
            ->orderByDesc('id')
            ->get()
            ->map(function (PurchasePayment $payment) {
                return [
                    'id' => $payment->id,
                    'purchase_invoice_id' => $payment->purchase_invoice_id,
                    'payment_date' => $payment->payment_date?->format('Y-m-d'),
                    'amount' => round((float) ($payment->amount ?? 0), 2),
                    'reference' => $payment->reference,
                    'notes' => $payment->notes,
                    'created_by_user_id' => $payment->created_by_user_id,
                    'created_by_name' => $payment->creator?->name,
                    'created_at' => $payment->created_at?->toDateTimeString(),
                ];
            })
            ->values();
    }

    public function getInvoicePaymentSummary(PurchaseDocument $invoice): array
    {
        $this->ensureIsInvoice($invoice);

        $notesSummary = $this->invoiceService->getInvoiceNotesSummary($invoice);
        $balanceAfterNotes = round((float) ($notesSummary['balance_after_notes'] ?? 0), 2);
        $totalPaid = $this->totalPaidForInvoice((int) $invoice->id);
        $currentBalance = round($balanceAfterNotes - $totalPaid, 2);
        $disputed = $this->rollCreditNoteService->disputedSummary($invoice);

        return [
            'invoice_total' => round((float) ($notesSummary['invoice_total'] ?? 0), 2),
            'credit_notes_total' => round((float) ($notesSummary['credit_total'] ?? 0), 2),
            'debit_notes_total' => round((float) ($notesSummary['debit_total'] ?? 0), 2),
            'balance_after_notes' => $balanceAfterNotes,
            'payments_total' => $totalPaid,
            'current_balance' => $currentBalance,
            // Monto retenido por rollos rechazados sin nota crédito aprobada; solo se puede pagar el resto.
            'disputed_amount' => $disputed['amount'],
            'disputed_rolls_count' => $disputed['rolls'],
            'payable_balance' => max(0.0, round($currentBalance - $disputed['amount'], 2)),
        ];
    }

    public function totalPaidForInvoice(int $invoiceId): float
    {
        $amount = (float) PurchasePayment::query()
            ->where('purchase_invoice_id', $invoiceId)
            ->sum(DB::raw('COALESCE(amount, 0)'));

        return round($amount, 2);
    }

    protected function ensureIsInvoice(PurchaseDocument $invoice): void
    {
        if ($invoice->document_type !== PurchaseDocument::TYPE_INVOICE) {
            throw ValidationException::withMessages([
                'purchase_invoice_id' => 'El pago solo puede aplicarse a facturas de compra.',
            ]);
        }

        if (!in_array((string) $invoice->status, [
            PurchaseInvoiceStatus::PENDING_RECEIPT->value,
            PurchaseInvoiceStatus::RECEIVED->value,
        ], true)) {
            throw ValidationException::withMessages([
                'purchase_invoice_id' => 'La factura no está habilitada para registrar pagos.',
            ]);
        }
    }
}
