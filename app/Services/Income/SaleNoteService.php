<?php

namespace App\Services\Income;

use App\Enums\Income\SaleNoteStatus;
use App\Enums\Income\SalesInvoiceStatus;
use App\Models\Income\SaleDocument;
use App\Services\Finance\AccountingPostingService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaleNoteService
{
    public function __construct(
        private readonly AccountingPostingService $accountingPostingService,
        private readonly SalesInvoiceService $salesInvoiceService,
        private readonly SaleDocumentService $saleDocumentService,
    ) {
    }

    public function emit(SaleDocument $note, ?int $userId = null): SaleDocument
    {
        $this->ensureNoteDocument($note);

        if (! $note->canEmitNote()) {
            throw ValidationException::withMessages([
                'document' => 'Solo se pueden emitir notas de venta en estado borrador.',
            ]);
        }

        return DB::transaction(function () use ($note, $userId) {
            /** @var SaleDocument $lockedNote */
            $lockedNote = SaleDocument::query()
                ->with(['items.item.tax', 'parent.items', 'parent.children.items', 'parent.payments'])
                ->lockForUpdate()
                ->findOrFail($note->id);

            $this->ensureNoteDocument($lockedNote);

            if (! $lockedNote->canEmitNote()) {
                throw ValidationException::withMessages([
                    'document' => 'La nota ya no se encuentra en estado borrador.',
                ]);
            }

            /** @var SaleDocument $lockedInvoice */
            $lockedInvoice = SaleDocument::query()
                ->with(['items', 'children.items', 'payments'])
                ->lockForUpdate()
                ->findOrFail((int) $lockedNote->parent_document_id);

            $this->ensureValidParentInvoice($lockedInvoice);

            $noteNetTotal = round($lockedNote->totalNet(), 2);
            if ($noteNetTotal <= 0) {
                throw ValidationException::withMessages([
                    'items' => 'La nota debe tener un total neto mayor a cero.',
                ]);
            }

            if ($lockedNote->isCreditNote()) {
                $pendingBalance = $this->salesInvoiceService->calculateOutstandingBalance($lockedInvoice);

                if ($noteNetTotal - $pendingBalance > 0.01) {
                    throw ValidationException::withMessages([
                        'document' => 'La nota de credito no puede superar el saldo pendiente de la factura.',
                    ]);
                }
            }

            $lockedNote->status = SaleNoteStatus::ISSUED->value;
            $lockedNote->invoiced_at = now();
            $lockedNote->executed_by_user_id = $lockedNote->executed_by_user_id
                ?? $userId
                ?? Auth::id();
            $lockedNote->save();

            if ($lockedNote->isCreditNote()) {
                $this->accountingPostingService->postSaleCreditNote($lockedNote);
                $lockedInvoice->amount_paid = round((float) $lockedInvoice->amount_paid + $noteNetTotal, 2);
                $lockedInvoice->save();
            } else {
                $this->accountingPostingService->postSaleDebitNote($lockedNote);
            }

            $refreshedInvoice = SaleDocument::query()
                ->with(['items', 'children.items', 'payments'])
                ->findOrFail($lockedInvoice->id);

            $this->salesInvoiceService->updatePaymentStatus($refreshedInvoice, true);

            return $this->saleDocumentService->loadWithRelations($lockedNote->fresh() ?? $lockedNote);
        });
    }

    public function void(SaleDocument $note, string $reason = ''): SaleDocument
    {
        $this->ensureNoteDocument($note);

        if (! $note->canVoidNote()) {
            throw ValidationException::withMessages([
                'document' => 'Solo se pueden anular notas de venta emitidas.',
            ]);
        }

        return DB::transaction(function () use ($note, $reason) {
            /** @var SaleDocument $lockedNote */
            $lockedNote = SaleDocument::query()
                ->with(['items.item.tax', 'parent.items', 'parent.children.items', 'parent.payments'])
                ->lockForUpdate()
                ->findOrFail($note->id);

            $this->ensureNoteDocument($lockedNote);

            if (! $lockedNote->canVoidNote()) {
                throw ValidationException::withMessages([
                    'document' => 'La nota ya no se encuentra en un estado anulable.',
                ]);
            }

            /** @var SaleDocument $lockedInvoice */
            $lockedInvoice = SaleDocument::query()
                ->with(['items', 'children.items', 'payments'])
                ->lockForUpdate()
                ->findOrFail((int) $lockedNote->parent_document_id);

            $this->ensureValidParentInvoice($lockedInvoice);

            $sourceType = $lockedNote->isCreditNote()
                ? AccountingPostingService::SOURCE_SALE_CREDIT_NOTE
                : AccountingPostingService::SOURCE_SALE_DEBIT_NOTE;

            $reversalEntry = $this->accountingPostingService->reverseEntry(
                $sourceType,
                (int) $lockedNote->id,
                trim($reason) !== '' ? $reason : 'Anulacion de nota de venta',
            );

            if (! $reversalEntry) {
                throw ValidationException::withMessages([
                    'document' => 'La nota no tiene un asiento contable activo para anular.',
                ]);
            }

            if ($lockedNote->isCreditNote()) {
                $lockedInvoice->amount_paid = round(
                    max((float) $lockedInvoice->amount_paid - $lockedNote->totalNet(), 0),
                    2
                );
                $lockedInvoice->save();
            }

            $lockedNote->status = SaleNoteStatus::VOIDED->value;
            $lockedNote->save();

            $refreshedInvoice = SaleDocument::query()
                ->with(['items', 'children.items', 'payments'])
                ->findOrFail($lockedInvoice->id);

            $this->salesInvoiceService->updatePaymentStatus($refreshedInvoice, true);

            return $this->saleDocumentService->loadWithRelations($lockedNote->fresh() ?? $lockedNote);
        });
    }

    private function ensureNoteDocument(SaleDocument $note): void
    {
        if (! $note->isCreditDebitNote()) {
            throw new \InvalidArgumentException('Solo se pueden operar notas de credito o debito de venta.');
        }
    }

    private function ensureValidParentInvoice(SaleDocument $invoice): void
    {
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
    }
}
