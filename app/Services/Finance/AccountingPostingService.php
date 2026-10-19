<?php

namespace App\Services\Finance;

use App\Enums\PurchaseInvoiceStatus;
use App\Models\Finance\AccountingAccount;
use App\Models\Finance\CashReceipt;
use App\Models\Expenses\ExpenseVoucher;
use App\Models\Finance\JournalEntry;
use App\Models\Finance\JournalEntryLine;
use App\Models\Finance\Retention;
use App\Models\Finance\Tax;
use App\Models\Expenses\PurchaseDocument;
use App\Models\Expenses\PurchaseDocumentItem;
use App\Models\Expenses\PurchasePayment;
use App\Models\Income\SaleDocument;
use App\Models\Income\SaleDocumentItem;
use App\Models\Income\SalePayment;
use App\Models\Inventory\InventoryAdjustment;
use App\Models\Inventory\InventoryMovement;
use App\Models\MasterData\Item;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;

class AccountingPostingService
{
    // Tipos de documento fuente para trazabilidad
    public const SOURCE_EXPENSE_VOUCHER = 'expense_voucher';
    public const SOURCE_CASH_RECEIPT = 'cash_receipt';
    public const SOURCE_PURCHASE_INVOICE = 'purchase_invoice';
    public const SOURCE_CREDIT_NOTE = 'credit_note';
    public const SOURCE_INVENTORY_ADJUSTMENT = 'inventory_adjustment';
    public const SOURCE_SALES_INVOICE = 'sales_invoice';
    public const SOURCE_SALE_CREDIT_NOTE = 'sale_credit_note';
    public const SOURCE_SALE_DEBIT_NOTE = 'sale_debit_note';

    /** Estado final de la factura de tela al aprobar su recepción (FabricRollService::approveReception). */
    public const FABRIC_RECEPTION_APPROVED_STATUS = 'approved';

    public function __construct(
        private JournalEntryNumberSequenceService $sequenceService,
    ) {
    }

    /**
     * Contabiliza un comprobante de egreso.
     *
     * Asiento:
     *   Débito: Cuenta por pagar proveedores (disminuye pasivo)
     *   Crédito: Cuenta de banco/caja (disminuye activo)
     */
    public function postExpenseVoucher(ExpenseVoucher $voucher): JournalEntry
    {
        $voucherId = (int) $voucher->id;
        $voucherNumber = (int) $voucher->voucher_number;

        // Obtener el total aplicado desde los pagos
        $totalApplied = PurchasePayment::query()
            ->where('voucher_number', $voucherNumber)
            ->sum('amount');

        if ($totalApplied <= 0) {
            throw new InvalidArgumentException(
                "El comprobante de egreso {$voucherNumber} no tiene aplicaciones válidas para contabilizar."
            );
        }

        // Resolver cuentas contables
        $cashBankAccount = $this->resolveCashBankAccount($voucher->account_id);
        $accountsPayableAccount = $this->resolveAccountsPayableAccount();

        if (!$cashBankAccount) {
            throw new RuntimeException(
                "No se encontró una cuenta contable de banco/caja válida para el comprobante de egreso {$voucherNumber}."
            );
        }

        if (!$accountsPayableAccount) {
            throw new RuntimeException(
                'No se encontró una cuenta contable de cuentas por pagar configurada en el sistema.'
            );
        }

        // Crear el asiento dentro de transacción
        return DB::transaction(function () use ($voucher, $voucherId, $voucherNumber, $totalApplied, $cashBankAccount, $accountsPayableAccount) {
            $activeSourceEntry = $this->resolveActiveSourceEntryForUpdate(
                self::SOURCE_EXPENSE_VOUCHER,
                $voucherId,
            );

            if ($activeSourceEntry) {
                Log::info("AccountingPostingService: Expense voucher {$voucherNumber} already posted as entry {$activeSourceEntry->entry_number}");
                return $activeSourceEntry;
            }

            $entryNumber = $this->sequenceService->reserveNextNumber();

            $entry = JournalEntry::create([
                'entry_number' => $entryNumber,
                'entry_date' => $voucher->payment_date ?? now(),
                'entry_type' => JournalEntry::TYPE_EXPENSE_VOUCHER,
                'status' => JournalEntry::STATUS_POSTED,
                'posted_at' => now(),
                'description' => "Comprobante de egreso #{$voucherNumber}",
                'source_type' => self::SOURCE_EXPENSE_VOUCHER,
                'source_id' => $voucherId,
                'total_debit' => $totalApplied,
                'total_credit' => $totalApplied,
                'created_by_user_id' => Auth::id() ?? $voucher->created_by_user_id,
            ]);

            // Línea 1: Débito a cuentas por pagar (disminuye el pasivo)
            JournalEntryLine::create([
                'journal_entry_id' => $entry->id,
                'account_id' => $accountsPayableAccount->id,
                'contact_id' => $voucher->contact_id,
                'debit' => $totalApplied,
                'credit' => 0,
                'description' => "Pago a proveedor - CE #{$voucherNumber}",
                'line_reference' => "CE-{$voucherNumber}",
            ]);

            // Línea 2: Crédito a banco/caja (disminuye el activo)
            JournalEntryLine::create([
                'journal_entry_id' => $entry->id,
                'account_id' => $cashBankAccount->id,
                'contact_id' => null,
                'debit' => 0,
                'credit' => $totalApplied,
                'description' => "Egreso por pago a proveedor - CE #{$voucherNumber}",
                'line_reference' => "CE-{$voucherNumber}",
            ]);

            // Validar balance
            $entry->recalculateTotals();
            if (!$entry->isBalanced()) {
                throw new RuntimeException(
                    "El asiento contable para el comprobante de egreso {$voucherNumber} no está balanceado."
                );
            }

            $entry->save();

            Log::info("AccountingPostingService: Posted expense voucher {$voucherNumber} as journal entry {$entryNumber}", [
                'journal_entry_id' => $entry->id,
                'total' => $totalApplied,
            ]);

            return $entry;
        });
    }

    public function postCashReceipt(CashReceipt $receipt): JournalEntry
    {
        $receiptId = (int) $receipt->id;
        $receiptNumber = (int) $receipt->receipt_number;

        $totalApplied = SalePayment::query()
            ->where('cash_receipt_id', $receiptId)
            ->sum('amount');

        if ($totalApplied <= 0) {
            throw new InvalidArgumentException(
                "El recibo de caja {$receiptNumber} no tiene aplicaciones validas para contabilizar."
            );
        }

        $cashBankAccount = $this->resolveCashBankAccount($receipt->account_id);
        $accountsReceivableAccount = $this->resolveAccountsReceivableAccount();

        if (! $cashBankAccount) {
            throw new RuntimeException(
                "No se encontro una cuenta contable de banco/caja valida para el recibo de caja {$receiptNumber}."
            );
        }

        if (! $accountsReceivableAccount) {
            throw new RuntimeException(
                'No se encontro una cuenta contable de cuentas por cobrar configurada en el sistema.'
            );
        }

        return DB::transaction(function () use ($receipt, $receiptId, $receiptNumber, $totalApplied, $cashBankAccount, $accountsReceivableAccount) {
            $activeSourceEntry = $this->resolveActiveSourceEntryForUpdate(
                self::SOURCE_CASH_RECEIPT,
                $receiptId,
            );

            if ($activeSourceEntry) {
                Log::info("AccountingPostingService: Cash receipt {$receiptNumber} already posted as entry {$activeSourceEntry->entry_number}");
                return $activeSourceEntry;
            }

            $entryNumber = $this->sequenceService->reserveNextNumber();

            $entry = JournalEntry::create([
                'entry_number' => $entryNumber,
                'entry_date' => $receipt->payment_date ?? now(),
                'entry_type' => JournalEntry::TYPE_CASH_RECEIPT,
                'status' => JournalEntry::STATUS_POSTED,
                'posted_at' => now(),
                'description' => "Recibo de caja #{$receiptNumber}",
                'source_type' => self::SOURCE_CASH_RECEIPT,
                'source_id' => $receiptId,
                'total_debit' => $totalApplied,
                'total_credit' => $totalApplied,
                'created_by_user_id' => Auth::id() ?? $receipt->created_by_user_id,
            ]);

            JournalEntryLine::create([
                'journal_entry_id' => $entry->id,
                'account_id' => $cashBankAccount->id,
                'contact_id' => null,
                'debit' => $totalApplied,
                'credit' => 0,
                'description' => "Cobro de cliente - RC #{$receiptNumber}",
                'line_reference' => "RC-{$receiptNumber}",
            ]);

            JournalEntryLine::create([
                'journal_entry_id' => $entry->id,
                'account_id' => $accountsReceivableAccount->id,
                'contact_id' => $receipt->contact_id,
                'debit' => 0,
                'credit' => $totalApplied,
                'description' => "Cuenta por cobrar cliente - RC #{$receiptNumber}",
                'line_reference' => "RC-{$receiptNumber}",
            ]);

            $entry->recalculateTotals();
            if (! $entry->isBalanced()) {
                throw new RuntimeException(
                    "El asiento contable para el recibo de caja {$receiptNumber} no esta balanceado."
                );
            }

            $entry->save();

            Log::info("AccountingPostingService: Posted cash receipt {$receiptNumber} as journal entry {$entryNumber}", [
                'journal_entry_id' => $entry->id,
                'total' => $totalApplied,
            ]);

            return $entry;
        });
    }

    /**
     * Contabiliza una factura de compra recibida/aprobada solo si nunca tuvo asiento (ni activo ni revertido).
     *
     * Sirve para la factura de tela (recepción aprobada) y para la auto-reparación de facturas de tela
     * aprobadas antes de que su recepción generara asiento. Es idempotente y no reposta una factura cuyo
     * asiento ya fue revertido por una nota crédito. Devuelve null cuando no aplica (no es factura o su
     * estado no es contabilizable); las fallas de configuración contable se propagan como excepción.
     */
    public function postPurchaseInvoiceIfMissing(PurchaseDocument $invoice): ?JournalEntry
    {
        if (! $invoice->isInvoice() || ! in_array($invoice->status ?? null, [
            PurchaseInvoiceStatus::RECEIVED->value,
            self::FABRIC_RECEPTION_APPROVED_STATUS,
        ], true)) {
            return null;
        }

        $existing = JournalEntry::query()
            ->forSource(self::SOURCE_PURCHASE_INVOICE, (int) $invoice->id)
            ->latest('id')
            ->first();

        return $existing ?? $this->postPurchaseInvoice($invoice);
    }

    public function postPurchaseInvoice(PurchaseDocument $invoice): JournalEntry
    {
        $invoiceId = (int) $invoice->id;
        $invoiceNumber = trim((string) $invoice->document_number);

        if (! $invoice->isInvoice()) {
            throw new InvalidArgumentException('El documento recibido no es una factura de compra válida.');
        }

        // 'approved' es el estado final de la factura de tela cuando se aprueba su recepción de rollos.
        if (! in_array($invoice->status ?? null, [
            PurchaseInvoiceStatus::RECEIVED->value,
            self::FABRIC_RECEPTION_APPROVED_STATUS,
        ], true)) {
            throw new InvalidArgumentException(
                "Solo se pueden contabilizar facturas de compra en estado \""
                .PurchaseInvoiceStatus::RECEIVED->value
                .'" o "'.self::FABRIC_RECEPTION_APPROVED_STATUS.'".'
            );
        }

        $invoice->loadMissing(['items.item.tax']);

        if ($invoice->items->isEmpty()) {
            throw new InvalidArgumentException(
                "La factura de compra {$invoiceNumber} no tiene ítems válidos para contabilizar."
            );
        }

        $accountsPayableAccount = $this->resolveAccountsPayableAccount();

        if (! $accountsPayableAccount) {
            throw new RuntimeException(
                'No se encontró una cuenta contable de cuentas por pagar configurada en el sistema.'
            );
        }

        return DB::transaction(function () use ($invoice, $invoiceId, $invoiceNumber, $accountsPayableAccount) {
            $activeSourceEntry = $this->resolveActiveSourceEntryForUpdate(
                self::SOURCE_PURCHASE_INVOICE,
                $invoiceId,
            );

            if ($activeSourceEntry) {
                Log::info("AccountingPostingService: Purchase invoice {$invoiceNumber} already posted as entry {$activeSourceEntry->entry_number}");
                return $activeSourceEntry;
            }

            $entryNumber = $this->sequenceService->reserveNextNumber();

            $entry = JournalEntry::create([
                'entry_number' => $entryNumber,
                'entry_date' => $invoice->date ?? now(),
                'entry_type' => JournalEntry::TYPE_PURCHASE_INVOICE,
                'status' => JournalEntry::STATUS_POSTED,
                'posted_at' => now(),
                'description' => "Factura de compra #{$invoiceNumber}",
                'source_type' => self::SOURCE_PURCHASE_INVOICE,
                'source_id' => $invoiceId,
                'total_debit' => 0,
                'total_credit' => 0,
                'created_by_user_id' => Auth::id() ?? $invoice->created_by_user_id,
            ]);

            $subtotalTotal = '0.00';

            foreach ($invoice->items as $index => $item) {
                $lineSubtotal = round((float) ($item->total ?? 0), 2);

                if ($lineSubtotal <= 0) {
                    continue;
                }

                $itemAccount = $this->resolvePurchaseInvoiceItemAccount($item);

                JournalEntryLine::create([
                    'journal_entry_id' => $entry->id,
                    'account_id' => $itemAccount->id,
                    'contact_id' => $invoice->contact_id,
                    'debit' => $lineSubtotal,
                    'credit' => 0,
                    'description' => "Reconocimiento item factura compra #{$invoiceNumber}",
                    'line_reference' => "FC-{$invoiceNumber}-ITEM-".($index + 1),
                ]);

                $subtotalTotal = bcadd($subtotalTotal, number_format($lineSubtotal, 2, '.', ''), 2);
            }

            $taxLines = $this->resolveInvoiceTaxLines($invoice->items);
            $taxTotal = '0.00';

            foreach ($taxLines as $index => $taxLine) {
                JournalEntryLine::create([
                    'journal_entry_id' => $entry->id,
                    'account_id' => $taxLine['account']->id,
                    'contact_id' => $invoice->contact_id,
                    'debit' => $taxLine['amount'],
                    'credit' => 0,
                    'description' => "IVA descontable factura compra #{$invoiceNumber}",
                    'line_reference' => "FC-{$invoiceNumber}-IVA-".($index + 1),
                ]);

                $taxTotal = bcadd($taxTotal, number_format($taxLine['amount'], 2, '.', ''), 2);
            }

            $withholdingLines = $this->resolveInvoiceWithholdingLines($invoice);
            $withholdingTotal = '0.00';

            foreach ($withholdingLines as $index => $withholdingLine) {
                JournalEntryLine::create([
                    'journal_entry_id' => $entry->id,
                    'account_id' => $withholdingLine['account']->id,
                    'contact_id' => $invoice->contact_id,
                    'debit' => 0,
                    'credit' => $withholdingLine['amount'],
                    'description' => "Retención aplicada factura compra #{$invoiceNumber}",
                    'line_reference' => "FC-{$invoiceNumber}-RET-".($index + 1),
                ]);

                $withholdingTotal = bcadd($withholdingTotal, number_format($withholdingLine['amount'], 2, '.', ''), 2);
            }

            $netPayable = bcsub(
                bcadd($subtotalTotal, $taxTotal, 2),
                $withholdingTotal,
                2
            );

            if (bccomp($netPayable, '0.00', 2) <= 0) {
                throw new RuntimeException(
                    "El neto por pagar de la factura de compra {$invoiceNumber} no es válido para contabilizar."
                );
            }

            JournalEntryLine::create([
                'journal_entry_id' => $entry->id,
                'account_id' => $accountsPayableAccount->id,
                'contact_id' => $invoice->contact_id,
                'debit' => 0,
                'credit' => $netPayable,
                'description' => "Cuenta por pagar proveedor - FC #{$invoiceNumber}",
                'line_reference' => "FC-{$invoiceNumber}",
            ]);

            $entry->recalculateTotals();
            if (! $entry->isBalanced()) {
                throw new RuntimeException(
                    "El asiento contable para la factura de compra {$invoiceNumber} no está balanceado."
                );
            }

            $entry->save();

            Log::info("AccountingPostingService: Posted purchase invoice {$invoiceNumber} as journal entry {$entryNumber}", [
                'journal_entry_id' => $entry->id,
                'total_debit' => $entry->total_debit,
                'total_credit' => $entry->total_credit,
            ]);

            return $entry;
        });
    }

    public function postSalesInvoice(SaleDocument $invoice): JournalEntry
    {
        $invoiceId = (int) $invoice->id;
        $invoiceNumber = trim((string) ($invoice->document_number ?: $invoiceId));

        if (! $invoice->isInvoice()) {
            throw new InvalidArgumentException('El documento recibido no es una factura de venta válida.');
        }

        if (($invoice->status ?? null) !== 'issued') {
            throw new InvalidArgumentException('Solo se pueden contabilizar facturas de venta en estado "issued".');
        }

        $invoice->loadMissing(['items.item.tax']);

        if ($invoice->items->isEmpty()) {
            throw new InvalidArgumentException(
                "La factura de venta {$invoiceNumber} no tiene items validos para contabilizar."
            );
        }

        $accountsReceivableAccount = $this->resolveAccountsReceivableAccount();

        if (! $accountsReceivableAccount) {
            throw new RuntimeException(
                'No se encontro una cuenta contable de cuentas por cobrar configurada en el sistema.'
            );
        }

        return DB::transaction(function () use ($invoice, $invoiceId, $invoiceNumber, $accountsReceivableAccount) {
            $activeSourceEntry = $this->resolveActiveSourceEntryForUpdate(
                self::SOURCE_SALES_INVOICE,
                $invoiceId,
            );

            if ($activeSourceEntry) {
                Log::info("AccountingPostingService: Sales invoice {$invoiceNumber} already posted as entry {$activeSourceEntry->entry_number}");
                return $activeSourceEntry;
            }

            $entryNumber = $this->sequenceService->reserveNextNumber();

            $entry = JournalEntry::create([
                'entry_number' => $entryNumber,
                'entry_date' => $invoice->date ?? now(),
                'entry_type' => JournalEntry::TYPE_SALES_INVOICE,
                'status' => JournalEntry::STATUS_POSTED,
                'posted_at' => now(),
                'description' => "Factura de venta #{$invoiceNumber}",
                'source_type' => self::SOURCE_SALES_INVOICE,
                'source_id' => $invoiceId,
                'total_debit' => 0,
                'total_credit' => 0,
                'created_by_user_id' => Auth::id() ?? $invoice->created_by_user_id,
            ]);

            $subtotalTotal = '0.00';

            foreach ($invoice->items as $index => $item) {
                $lineSubtotal = round((float) ($item->total ?? 0), 2);

                if ($lineSubtotal <= 0) {
                    continue;
                }

                $itemAccount = $this->resolveSalesInvoiceItemAccount($item);

                JournalEntryLine::create([
                    'journal_entry_id' => $entry->id,
                    'account_id' => $itemAccount->id,
                    'contact_id' => $invoice->contact_id,
                    'debit' => 0,
                    'credit' => $lineSubtotal,
                    'description' => "Reconocimiento ingreso factura venta #{$invoiceNumber}",
                    'line_reference' => "FV-{$invoiceNumber}-ITEM-".($index + 1),
                ]);

                $subtotalTotal = bcadd($subtotalTotal, number_format($lineSubtotal, 2, '.', ''), 2);
            }

            $taxLines = $this->resolveSalesInvoiceTaxLines($invoice->items);
            $taxTotal = '0.00';

            foreach ($taxLines as $index => $taxLine) {
                JournalEntryLine::create([
                    'journal_entry_id' => $entry->id,
                    'account_id' => $taxLine['account']->id,
                    'contact_id' => $invoice->contact_id,
                    'debit' => 0,
                    'credit' => $taxLine['amount'],
                    'description' => "IVA por pagar factura venta #{$invoiceNumber}",
                    'line_reference' => "FV-{$invoiceNumber}-IVA-".($index + 1),
                ]);

                $taxTotal = bcadd($taxTotal, number_format($taxLine['amount'], 2, '.', ''), 2);
            }

            $withholdingLines = $this->resolveSalesInvoiceWithholdingLines($invoice);
            $withholdingTotal = '0.00';

            foreach ($withholdingLines as $index => $withholdingLine) {
                JournalEntryLine::create([
                    'journal_entry_id' => $entry->id,
                    'account_id' => $withholdingLine['account']->id,
                    'contact_id' => $invoice->contact_id,
                    'debit' => $withholdingLine['amount'],
                    'credit' => 0,
                    'description' => "Retencion aplicada factura venta #{$invoiceNumber}",
                    'line_reference' => "FV-{$invoiceNumber}-RET-".($index + 1),
                ]);

                $withholdingTotal = bcadd($withholdingTotal, number_format($withholdingLine['amount'], 2, '.', ''), 2);
            }

            $netReceivable = bcsub(
                bcadd($subtotalTotal, $taxTotal, 2),
                $withholdingTotal,
                2
            );

            if (bccomp($netReceivable, '0.00', 2) <= 0) {
                throw new RuntimeException(
                    "El neto por cobrar de la factura de venta {$invoiceNumber} no es valido para contabilizar."
                );
            }

            JournalEntryLine::create([
                'journal_entry_id' => $entry->id,
                'account_id' => $accountsReceivableAccount->id,
                'contact_id' => $invoice->contact_id,
                'debit' => $netReceivable,
                'credit' => 0,
                'description' => "Cuenta por cobrar cliente - FV #{$invoiceNumber}",
                'line_reference' => "FV-{$invoiceNumber}",
            ]);

            $entry->recalculateTotals();
            if (! $entry->isBalanced()) {
                throw new RuntimeException(
                    "El asiento contable para la factura de venta {$invoiceNumber} no esta balanceado."
                );
            }

            $entry->save();

            Log::info("AccountingPostingService: Posted sales invoice {$invoiceNumber} as journal entry {$entryNumber}", [
                'journal_entry_id' => $entry->id,
                'total_debit' => $entry->total_debit,
                'total_credit' => $entry->total_credit,
            ]);

            return $entry;
        });
    }

    public function postSaleCreditNote(SaleDocument $note): JournalEntry
    {
        return $this->postSaleNote(
            $note,
            self::SOURCE_SALE_CREDIT_NOTE,
            JournalEntry::TYPE_CREDIT_NOTE,
            true
        );
    }

    public function postSaleDebitNote(SaleDocument $note): JournalEntry
    {
        return $this->postSaleNote(
            $note,
            self::SOURCE_SALE_DEBIT_NOTE,
            JournalEntry::TYPE_DEBIT_NOTE,
            false
        );
    }

    /**
     * Intenta contabilizar la factura padre sin asiento. Devuelve el mensaje del fallo (ya registrado en log)
     * o null si se contabilizó / no aplicaba.
     */
    private function tryPostMissingParentInvoice(int $parentInvoiceId): ?string
    {
        $parent = PurchaseDocument::query()->find($parentInvoiceId);

        if (! $parent) {
            return null;
        }

        try {
            $this->postPurchaseInvoiceIfMissing($parent);

            return null;
        } catch (\Throwable $e) {
            Log::warning("AccountingPostingService: no se pudo contabilizar automáticamente la factura padre {$parentInvoiceId} antes de la nota crédito", [
                'invoice_id' => $parentInvoiceId,
                'error' => $e->getMessage(),
            ]);

            return $e->getMessage();
        }
    }

    /**
     * Contabiliza una nota crédito de compra contra su factura padre.
     *
     * - Nota por el total de la factura (±0.01): revierte el asiento completo de la factura.
     * - Nota parcial (0 < monto < total): asiento nuevo con las líneas de la factura invertidas y
     *   escaladas por monto_nota / total_factura; el asiento de la factura queda vigente.
     * - Idempotente: si la nota ya tiene asiento activo lo devuelve.
     * - La suma acumulada de notas contabilizadas de la factura no puede superar su total.
     * - Si la factura padre (recibida/aprobada) no tiene asiento, intenta contabilizarla primero.
     */
    public function postCreditNote(PurchaseDocument $note): JournalEntry
    {
        $noteId = (int) $note->id;
        $noteNumber = trim((string) $note->document_number);
        $parentInvoiceId = (int) ($note->parent_document_id ?? 0);

        if ($parentInvoiceId <= 0) {
            throw new RuntimeException('La nota crédito no tiene una factura de compra padre asociada.');
        }

        return DB::transaction(function () use ($note, $noteId, $noteNumber, $parentInvoiceId) {
            $activeNoteEntry = $this->activeCreditNoteEntryForUpdate($noteId);

            if ($activeNoteEntry) {
                Log::info("AccountingPostingService: Credit note {$noteNumber} already posted as entry {$activeNoteEntry->entry_number}");

                return $activeNoteEntry;
            }

            $parentEntry = $this->resolveActiveSourceEntryForUpdate(self::SOURCE_PURCHASE_INVOICE, $parentInvoiceId);
            $repairError = null;

            if (! $parentEntry) {
                // Auto-reparación: factura recibida/aprobada (p.ej. de tela, aprobada antes de contabilizarse
                // al recepcionar) que nunca tuvo asiento. Si no se puede, se deja el error claro de abajo.
                $repairError = $this->tryPostMissingParentInvoice($parentInvoiceId);
                $parentEntry = $this->resolveActiveSourceEntryForUpdate(self::SOURCE_PURCHASE_INVOICE, $parentInvoiceId);
            }

            if (! $parentEntry) {
                $wasReversed = JournalEntry::query()
                    ->forSource(self::SOURCE_PURCHASE_INVOICE, $parentInvoiceId)
                    ->where('status', JournalEntry::STATUS_REVERSED)
                    ->exists();

                throw new RuntimeException($wasReversed
                    ? 'El asiento de la factura de compra padre ya fue revertido por completo por otra nota crédito.'
                    : 'La factura de compra padre no tiene asiento contable registrado. '
                        .'Contabilice primero la factura antes de crear la nota crédito.'
                        .($repairError !== null ? " No se pudo contabilizar automáticamente: {$repairError}" : ''));
            }

            $invoiceTotal = round((float) ($parentEntry->total_credit ?? 0), 2);
            $noteTotal = $this->resolveCreditNoteTotal($note);

            if ($noteTotal <= 0) {
                throw new RuntimeException("La nota crédito {$noteNumber} no tiene un monto válido para contabilizar.");
            }

            $alreadyCredited = $this->creditedTotalForInvoice($parentInvoiceId, $noteId);

            if (round($alreadyCredited + $noteTotal, 2) > $invoiceTotal + 0.01) {
                throw new RuntimeException(
                    "La nota crédito {$noteNumber} ({$noteTotal}) supera el saldo acreditable de la factura padre: "
                    ."total {$invoiceTotal}, ya acreditado {$alreadyCredited}."
                );
            }

            if (abs($noteTotal - $invoiceTotal) <= 0.01) {
                return $this->postFullCreditNote($noteId, $noteNumber, $parentInvoiceId);
            }

            return $this->postPartialCreditNote($note, $parentEntry, $noteTotal, $invoiceTotal);
        });
    }

    /**
     * Sincroniza el asiento de una nota crédito editada: lo crea si no existe y, si el monto cambió,
     * revierte el asiento parcial vigente y contabiliza uno nuevo.
     */
    public function syncCreditNoteEntry(PurchaseDocument $note): JournalEntry
    {
        return DB::transaction(function () use ($note) {
            $noteId = (int) $note->id;
            $existing = $this->activeCreditNoteEntryForUpdate($noteId);

            if (! $existing) {
                return $this->postCreditNote($note);
            }

            if (abs($this->resolveCreditNoteTotal($note) - round((float) $existing->total_debit, 2)) <= 0.01) {
                return $existing;
            }

            if ($existing->reverses_entry_id !== null) {
                throw new RuntimeException(
                    'La nota crédito por el total de la factura ya revirtió su asiento completo; '
                    .'no se puede cambiar su monto. Elimínela o gestione el ajuste manualmente.'
                );
            }

            $this->reverseEntry(
                self::SOURCE_CREDIT_NOTE,
                $noteId,
                'Actualización de la nota crédito #'.trim((string) $note->document_number),
            );

            return $this->postCreditNote($note);
        });
    }

    /**
     * Anula el asiento parcial de una nota crédito eliminada (o que dejó de ser crédito).
     * El asiento de una nota por el total revirtió el de la factura y no se puede anular aquí.
     */
    public function voidCreditNoteEntry(PurchaseDocument $note): ?JournalEntry
    {
        return DB::transaction(function () use ($note) {
            $noteId = (int) $note->id;
            $existing = $this->activeCreditNoteEntryForUpdate($noteId);

            if (! $existing) {
                return null;
            }

            if ($existing->reverses_entry_id !== null) {
                Log::warning("AccountingPostingService: Credit note {$note->document_number} reversed the whole invoice entry; its journal entry was not voided.", [
                    'journal_entry_id' => $existing->id,
                ]);

                return null;
            }

            return $this->reverseEntry(
                self::SOURCE_CREDIT_NOTE,
                $noteId,
                'Anulación de la nota crédito #'.trim((string) $note->document_number),
            );
        });
    }

    /**
     * Total (con impuestos) de las notas crédito vigentes de una factura que ya tienen asiento contabilizado.
     */
    public function creditedTotalForInvoice(int $invoiceId, ?int $exceptNoteId = null): float
    {
        $noteIds = PurchaseDocument::query()
            ->where('parent_document_id', $invoiceId)
            ->where('document_type', PurchaseDocument::TYPE_CREDIT_NOTE)
            ->when($exceptNoteId, fn ($query) => $query->where('id', '!=', $exceptNoteId))
            ->whereIn('id', JournalEntry::query()
                ->select('source_id')
                ->where('source_type', self::SOURCE_CREDIT_NOTE)
                ->where('status', JournalEntry::STATUS_POSTED))
            ->pluck('id');

        if ($noteIds->isEmpty()) {
            return 0.0;
        }

        return round((float) PurchaseDocumentItem::query()
            ->whereIn('purchase_document_id', $noteIds)
            ->sum(DB::raw('COALESCE(total, 0) + COALESCE(total_tax, 0)')), 2);
    }

    private function resolveCreditNoteTotal(PurchaseDocument $note): float
    {
        return round((float) $note->items()->sum(DB::raw('COALESCE(total, 0) + COALESCE(total_tax, 0)')), 2);
    }

    /**
     * Asiento vigente de una nota crédito: contabilizado, no revertido y que no sea el asiento de
     * anulación de otra nota (el de una nota por el total sí es una reversión, pero de la factura).
     */
    private function activeCreditNoteEntryForUpdate(int $noteId): ?JournalEntry
    {
        return JournalEntry::query()
            ->forSource(self::SOURCE_CREDIT_NOTE, $noteId)
            ->where('status', JournalEntry::STATUS_POSTED)
            ->whereNull('reversed_by_entry_id')
            ->where(function ($query) {
                $query->whereNull('reverses_entry_id')
                    ->orWhereIn('reverses_entry_id', JournalEntry::query()
                        ->select('id')
                        ->where('source_type', self::SOURCE_PURCHASE_INVOICE));
            })
            ->lockForUpdate()
            ->latest('id')
            ->first();
    }

    private function postFullCreditNote(int $noteId, string $noteNumber, int $parentInvoiceId): JournalEntry
    {
        $reversalEntry = $this->reverseEntry(
            self::SOURCE_PURCHASE_INVOICE,
            $parentInvoiceId,
            "Nota crédito #{$noteNumber}",
        );

        if (! $reversalEntry) {
            throw new RuntimeException(
                "No se pudo generar la reversión contable para la nota crédito {$noteNumber}."
            );
        }

        $reversalEntry->update([
            'source_type' => self::SOURCE_CREDIT_NOTE,
            'source_id' => $noteId,
            'entry_type' => JournalEntry::TYPE_CREDIT_NOTE,
        ]);

        Log::info("AccountingPostingService: Posted credit note {$noteNumber} as journal entry {$reversalEntry->entry_number}", [
            'journal_entry_id' => $reversalEntry->id,
            'parent_invoice_id' => $parentInvoiceId,
        ]);

        return $reversalEntry->fresh(['lines.account', 'lines.contact']) ?? $reversalEntry;
    }

    /**
     * Asiento proporcional: cada línea de la factura se invierte (debe <-> haber) y se escala por
     * ratio = monto_nota / total_factura, redondeando a centavos. El residuo de redondeo de cada lado
     * se ajusta en su línea de mayor monto para que débitos = créditos = monto de la nota.
     */
    private function postPartialCreditNote(
        PurchaseDocument $note,
        JournalEntry $parentEntry,
        float $noteTotal,
        float $invoiceTotal,
    ): JournalEntry {
        $noteNumber = trim((string) $note->document_number);
        $invoiceNumber = trim((string) ($note->parent?->document_number ?? $note->parent_document_id));
        $ratio = $noteTotal / $invoiceTotal;

        $scaled = [];
        foreach ($parentEntry->lines as $line) {
            $debit = (int) round(((float) $line->credit) * $ratio * 100);
            $credit = (int) round(((float) $line->debit) * $ratio * 100);

            if ($debit === 0 && $credit === 0) {
                continue;
            }

            $scaled[] = ['line' => $line, 'debit' => $debit, 'credit' => $credit];
        }

        if ($scaled === []) {
            throw new RuntimeException("La nota crédito {$noteNumber} es demasiado pequeña para contabilizarse.");
        }

        $targetCents = (int) round($noteTotal * 100);

        foreach (['debit', 'credit'] as $side) {
            $residual = $targetCents - array_sum(array_column($scaled, $side));

            if ($residual === 0) {
                continue;
            }

            $largestIndex = null;
            foreach ($scaled as $index => $row) {
                if ($row[$side] > 0 && ($largestIndex === null || $row[$side] > $scaled[$largestIndex][$side])) {
                    $largestIndex = $index;
                }
            }

            if ($largestIndex === null || $scaled[$largestIndex][$side] + $residual <= 0) {
                throw new RuntimeException("No se pudo balancear el asiento de la nota crédito {$noteNumber}.");
            }

            $scaled[$largestIndex][$side] += $residual;
        }

        $entryNumber = $this->sequenceService->reserveNextNumber();

        $entry = JournalEntry::create([
            'entry_number' => $entryNumber,
            'entry_date' => $note->date ?? now(),
            'entry_type' => JournalEntry::TYPE_CREDIT_NOTE,
            'status' => JournalEntry::STATUS_POSTED,
            'posted_at' => now(),
            'description' => sprintf(
                'Nota crédito #%s parcial (%s%% de la factura #%s)',
                $noteNumber,
                number_format($ratio * 100, 2, '.', ''),
                $invoiceNumber,
            ),
            'source_type' => self::SOURCE_CREDIT_NOTE,
            'source_id' => (int) $note->id,
            'total_debit' => 0,
            'total_credit' => 0,
            'created_by_user_id' => Auth::id() ?? $note->created_by_user_id,
        ]);

        foreach ($scaled as $index => $row) {
            /** @var JournalEntryLine $source */
            $source = $row['line'];

            JournalEntryLine::create([
                'journal_entry_id' => $entry->id,
                'account_id' => $source->account_id,
                'contact_id' => $source->contact_id,
                'debit' => number_format($row['debit'] / 100, 2, '.', ''),
                'credit' => number_format($row['credit'] / 100, 2, '.', ''),
                'description' => "NC {$noteNumber}: {$source->description}",
                'line_reference' => 'NC-'.$noteNumber.'-'.($source->line_reference ?: 'L'.($index + 1)),
            ]);
        }

        $entry->recalculateTotals();

        if (! $entry->isBalanced()) {
            throw new RuntimeException("El asiento contable de la nota crédito {$noteNumber} no está balanceado.");
        }

        $entry->save();

        Log::info("AccountingPostingService: Posted partial credit note {$noteNumber} as journal entry {$entryNumber}", [
            'journal_entry_id' => $entry->id,
            'parent_entry_id' => $parentEntry->id,
            'ratio' => $ratio,
        ]);

        return $entry->fresh(['lines.account', 'lines.contact']) ?? $entry;
    }

    public function postInventoryAdjustmentOutflow(
        InventoryAdjustment $adjustment,
        ?Collection $movements = null,
    ): ?JournalEntry {
        $adjustmentId = (int) $adjustment->id;
        $documentNumber = trim((string) ($adjustment->document_number ?? ''));

        if (($adjustment->type ?? null) !== 'salida') {
            throw new InvalidArgumentException('Solo se pueden contabilizar ajustes de inventario de salida.');
        }

        $movements ??= InventoryMovement::query()
            ->with('item')
            ->where('source_type', InventoryAdjustment::class)
            ->where('source_id', $adjustmentId)
            ->where('type', 'salida')
            ->orderBy('id')
            ->get();

        $movements = $movements
            ->filter(fn (InventoryMovement $movement) => $movement->type === 'salida')
            ->values();

        if ($movements->isEmpty()) {
            throw new InvalidArgumentException(
                "El ajuste de inventario {$documentNumber} no tiene movimientos de salida para contabilizar."
            );
        }

        return DB::transaction(function () use ($adjustment, $adjustmentId, $documentNumber, $movements) {
            $activeSourceEntry = $this->resolveActiveSourceEntryForUpdate(
                self::SOURCE_INVENTORY_ADJUSTMENT,
                $adjustmentId,
            );

            if ($activeSourceEntry) {
                Log::info("AccountingPostingService: Inventory adjustment {$documentNumber} already posted as entry {$activeSourceEntry->entry_number}");
                return $activeSourceEntry;
            }

            $valuationLines = $movements
                ->map(function (InventoryMovement $movement) {
                    $amount = round((float) ($movement->total_cost ?? 0), 2);
                    if ($amount <= 0) {
                        return null;
                    }

                    $item = $movement->relationLoaded('item')
                        ? $movement->item
                        : $movement->item()->first();

                    if (! $item instanceof Item) {
                        throw new RuntimeException(
                            "No se pudo resolver el item maestro del movimiento de inventario {$movement->id}."
                        );
                    }

                    [$inventoryAccount, $outflowAccount] = $this->resolveInventoryOutflowAccounts($item);

                    return [
                        'movement' => $movement,
                        'item' => $item,
                        'amount' => $amount,
                        'inventory_account' => $inventoryAccount,
                        'outflow_account' => $outflowAccount,
                    ];
                })
                ->filter()
                ->values();

            if ($valuationLines->isEmpty()) {
                Log::info("AccountingPostingService: Inventory adjustment {$documentNumber} has zero valuation; no journal entry created.");
                return null;
            }

            $entryNumber = $this->sequenceService->reserveNextNumber();

            $entry = JournalEntry::create([
                'entry_number' => $entryNumber,
                'entry_date' => $adjustment->date ?? now(),
                'entry_type' => JournalEntry::TYPE_ADJUSTMENT,
                'status' => JournalEntry::STATUS_POSTED,
                'posted_at' => now(),
                'description' => "Ajuste de inventario salida #{$documentNumber}",
                'source_type' => self::SOURCE_INVENTORY_ADJUSTMENT,
                'source_id' => $adjustmentId,
                'total_debit' => 0,
                'total_credit' => 0,
                'created_by_user_id' => Auth::id(),
            ]);

            foreach ($valuationLines as $index => $valuationLine) {
                /** @var InventoryMovement $movement */
                $movement = $valuationLine['movement'];
                /** @var Item $item */
                $item = $valuationLine['item'];
                /** @var AccountingAccount $inventoryAccount */
                $inventoryAccount = $valuationLine['inventory_account'];
                /** @var AccountingAccount $outflowAccount */
                $outflowAccount = $valuationLine['outflow_account'];
                $amount = $valuationLine['amount'];
                $reference = 'AI-' . ($documentNumber !== '' ? $documentNumber : $adjustmentId) . '-MOV-' . $movement->id;
                $itemLabel = (string) ($item->name ?? ('Item #' . $item->id));

                JournalEntryLine::create([
                    'journal_entry_id' => $entry->id,
                    'account_id' => $outflowAccount->id,
                    'contact_id' => null,
                    'debit' => $amount,
                    'credit' => 0,
                    'description' => "Salida de inventario por ajuste - {$itemLabel}",
                    'line_reference' => $reference,
                ]);

                JournalEntryLine::create([
                    'journal_entry_id' => $entry->id,
                    'account_id' => $inventoryAccount->id,
                    'contact_id' => null,
                    'debit' => 0,
                    'credit' => $amount,
                    'description' => "Baja de inventario por ajuste - {$itemLabel}",
                    'line_reference' => $reference,
                ]);
            }

            $entry->recalculateTotals();
            if (! $entry->isBalanced()) {
                throw new RuntimeException(
                    "El asiento contable para el ajuste de inventario {$documentNumber} no está balanceado."
                );
            }

            $entry->save();

            Log::info("AccountingPostingService: Posted inventory adjustment {$documentNumber} as journal entry {$entryNumber}", [
                'journal_entry_id' => $entry->id,
                'total_debit' => $entry->total_debit,
                'total_credit' => $entry->total_credit,
            ]);

            return $entry;
        });
    }

    public function postInventoryAdjustmentEntryRegularization(
        InventoryAdjustment $adjustment,
        ?Collection $movements = null,
    ): ?JournalEntry {
        $adjustmentId = (int) $adjustment->id;
        $documentNumber = trim((string) ($adjustment->document_number ?? ''));
        $movementReason = trim((string) ($adjustment->movement_reason ?? ''));

        if (($adjustment->type ?? null) !== 'entrada') {
            throw new InvalidArgumentException('Solo se pueden contabilizar ajustes de inventario de entrada.');
        }

        if ($movementReason !== 'entry_regularization') {
            throw new InvalidArgumentException(
                'Solo las regularizaciones positivas de inventario tienen política contable definida en esta etapa.'
            );
        }

        $movements ??= InventoryMovement::query()
            ->with('item')
            ->where('source_type', InventoryAdjustment::class)
            ->where('source_id', $adjustmentId)
            ->where('type', 'entrada')
            ->orderBy('id')
            ->get();

        $movements = $movements
            ->filter(fn (InventoryMovement $movement) => $movement->type === 'entrada')
            ->values();

        if ($movements->isEmpty()) {
            throw new InvalidArgumentException(
                "El ajuste de inventario {$documentNumber} no tiene movimientos de entrada para contabilizar."
            );
        }

        return DB::transaction(function () use ($adjustment, $adjustmentId, $documentNumber, $movements) {
            $activeSourceEntry = $this->resolveActiveSourceEntryForUpdate(
                self::SOURCE_INVENTORY_ADJUSTMENT,
                $adjustmentId,
            );

            if ($activeSourceEntry) {
                Log::info("AccountingPostingService: Inventory adjustment {$documentNumber} already posted as entry {$activeSourceEntry->entry_number}");
                return $activeSourceEntry;
            }

            $valuationLines = $movements
                ->map(function (InventoryMovement $movement) {
                    $amount = round((float) ($movement->total_cost ?? 0), 2);
                    if ($amount <= 0) {
                        return null;
                    }

                    $item = $movement->relationLoaded('item')
                        ? $movement->item
                        : $movement->item()->first();

                    if (! $item instanceof Item) {
                        throw new RuntimeException(
                            "No se pudo resolver el item maestro del movimiento de inventario {$movement->id}."
                        );
                    }

                    [$inventoryAccount, $regularizationAccount] = $this->resolveInventoryEntryRegularizationAccounts($item);

                    return [
                        'movement' => $movement,
                        'item' => $item,
                        'amount' => $amount,
                        'inventory_account' => $inventoryAccount,
                        'regularization_account' => $regularizationAccount,
                    ];
                })
                ->filter()
                ->values();

            if ($valuationLines->isEmpty()) {
                Log::info("AccountingPostingService: Inventory adjustment {$documentNumber} has zero valuation; no journal entry created.");
                return null;
            }

            $entryNumber = $this->sequenceService->reserveNextNumber();

            $entry = JournalEntry::create([
                'entry_number' => $entryNumber,
                'entry_date' => $adjustment->date ?? now(),
                'entry_type' => JournalEntry::TYPE_ADJUSTMENT,
                'status' => JournalEntry::STATUS_POSTED,
                'posted_at' => now(),
                'description' => "Ajuste de inventario entrada #{$documentNumber}",
                'source_type' => self::SOURCE_INVENTORY_ADJUSTMENT,
                'source_id' => $adjustmentId,
                'total_debit' => 0,
                'total_credit' => 0,
                'created_by_user_id' => Auth::id(),
            ]);

            foreach ($valuationLines as $valuationLine) {
                /** @var InventoryMovement $movement */
                $movement = $valuationLine['movement'];
                /** @var Item $item */
                $item = $valuationLine['item'];
                /** @var AccountingAccount $inventoryAccount */
                $inventoryAccount = $valuationLine['inventory_account'];
                /** @var AccountingAccount $regularizationAccount */
                $regularizationAccount = $valuationLine['regularization_account'];
                $amount = $valuationLine['amount'];
                $reference = 'AI-' . ($documentNumber !== '' ? $documentNumber : $adjustmentId) . '-MOV-' . $movement->id;
                $itemLabel = (string) ($item->name ?? ('Item #' . $item->id));

                JournalEntryLine::create([
                    'journal_entry_id' => $entry->id,
                    'account_id' => $inventoryAccount->id,
                    'contact_id' => null,
                    'debit' => $amount,
                    'credit' => 0,
                    'description' => "Alta de inventario por regularizacion - {$itemLabel}",
                    'line_reference' => $reference,
                ]);

                JournalEntryLine::create([
                    'journal_entry_id' => $entry->id,
                    'account_id' => $regularizationAccount->id,
                    'contact_id' => null,
                    'debit' => 0,
                    'credit' => $amount,
                    'description' => "Contrapartida regularizacion positiva - {$itemLabel}",
                    'line_reference' => $reference,
                ]);
            }

            $entry->recalculateTotals();
            if (! $entry->isBalanced()) {
                throw new RuntimeException(
                    "El asiento contable para el ajuste de inventario {$documentNumber} no esta balanceado."
                );
            }

            $entry->save();

            Log::info("AccountingPostingService: Posted inventory adjustment entry {$documentNumber} as journal entry {$entryNumber}", [
                'journal_entry_id' => $entry->id,
                'total_debit' => $entry->total_debit,
                'total_credit' => $entry->total_credit,
            ]);

            return $entry;
        });
    }

    /**
     * Reversa el asiento contable de un documento fuente.
     */
    public function reverseEntry(string $sourceType, int $sourceId, ?string $reason = null): ?JournalEntry
    {
        return DB::transaction(function () use ($sourceType, $sourceId, $reason) {
            $originalEntry = $this->resolveActiveSourceEntryForUpdate($sourceType, $sourceId);

            if (!$originalEntry) {
                return null;
            }

            $entryNumber = $this->sequenceService->reserveNextNumber();

            // Crear asiento de reversión
            $reversalEntry = JournalEntry::create([
                'entry_number' => $entryNumber,
                'entry_date' => now(),
                'entry_type' => $originalEntry->entry_type,
                'status' => JournalEntry::STATUS_POSTED,
                'posted_at' => now(),
                'description' => "Reversión: {$originalEntry->description}" . ($reason ? " - {$reason}" : ''),
                'reversal_reason' => $reason,
                'source_type' => $originalEntry->source_type,
                'source_id' => $originalEntry->source_id,
                'reverses_entry_id' => $originalEntry->id,
                'total_debit' => $originalEntry->total_credit,
                'total_credit' => $originalEntry->total_debit,
                'created_by_user_id' => Auth::id(),
            ]);

            // Copiar líneas invirtiendo débito/crédito
            foreach ($originalEntry->lines as $line) {
                JournalEntryLine::create([
                    'journal_entry_id' => $reversalEntry->id,
                    'account_id' => $line->account_id,
                    'contact_id' => $line->contact_id,
                    'debit' => $line->credit,
                    'credit' => $line->debit,
                    'description' => "Rev: {$line->description}",
                    'line_reference' => $line->line_reference,
                ]);
            }

            // Marcar el asiento original como reversado
            $originalEntry->update([
                'status' => JournalEntry::STATUS_REVERSED,
                'reversed_by_entry_id' => $reversalEntry->id,
                'reversal_reason' => $reason,
            ]);

            Log::info("AccountingPostingService: Reversed entry {$originalEntry->entry_number} with new entry {$entryNumber}");

            return $reversalEntry;
        });
    }

    private function resolveActiveSourceEntryForUpdate(string $sourceType, int $sourceId): ?JournalEntry
    {
        $baseQuery = JournalEntry::query()
            ->forSource($sourceType, $sourceId)
            ->activePosted()
            ->lockForUpdate();

        $activeCount = (clone $baseQuery)->count();
        if ($activeCount > 1) {
            throw new RuntimeException(
                "Se detectaron {$activeCount} asientos activos para {$sourceType}:{$sourceId}. Se requiere regularización contable."
            );
        }

        return $baseQuery
            ->latest('id')
            ->first();
    }

    /**
     * Verifica si un documento ya fue contabilizado.
     */
    public function isDocumentPosted(string $sourceType, int $sourceId): bool
    {
        return JournalEntry::query()
            ->forSource($sourceType, $sourceId)
            ->posted()
            ->exists();
    }

    /**
     * Obtiene el asiento contable de un documento.
     */
    public function getDocumentEntry(string $sourceType, int $sourceId): ?JournalEntry
    {
        return JournalEntry::query()
            ->forSource($sourceType, $sourceId)
            ->with('lines.account', 'lines.contact')
            ->first();
    }

    private function resolvePurchaseInvoiceItemAccount(PurchaseDocumentItem $item): AccountingAccount
    {
        $lineAccountId = isset($item->account_id) && is_numeric($item->account_id)
            ? (int) $item->account_id
            : null;

        if ($lineAccountId) {
            $lineAccount = AccountingAccount::query()
                ->active()
                ->allowsEntries()
                ->find($lineAccountId);

            if ($lineAccount) {
                return $lineAccount;
            }
        }

        $catalogItem = $item->relationLoaded('item')
            ? $item->item
            : $item->item()->first();

        $tracksInventory = (bool) ($catalogItem?->tracks_inventory ?? false);

        $accountId = $tracksInventory
            ? ($catalogItem?->inventory_account_id ? (int) $catalogItem->inventory_account_id : null)
            : ($catalogItem?->cost_of_sale_account_id ? (int) $catalogItem->cost_of_sale_account_id : null);

        if ($accountId) {
            $account = AccountingAccount::query()
                ->active()
                ->allowsEntries()
                ->find($accountId);

            if ($account) {
                return $account;
            }
        }

        $itemLabel = (string) ($item->item_name ?? 'Sin nombre');

        if ($tracksInventory) {
            throw new RuntimeException(
                'El ítem "' . $itemLabel . '" no tiene cuenta contable configurada. '
                . 'Configure inventory_account_id en el maestro.'
            );
        }

        throw new RuntimeException(
            'El ítem "' . $itemLabel . '" no tiene cuenta contable configurada. '
            . 'Configure cost_of_sale_account_id en el maestro.'
        );
    }

    private function resolveInvoiceTaxLines(Collection $items): array
    {
        $grouped = [];

        foreach ($items as $item) {
            $taxAmount = round((float) ($item->total_tax ?? 0), 2);

            if ($taxAmount <= 0) {
                continue;
            }

            $catalogItem = $item->relationLoaded('item')
                ? $item->item
                : $item->item()->with('tax')->first();

            $tax = $catalogItem?->relationLoaded('tax')
                ? $catalogItem->tax
                : $catalogItem?->tax()->first();

            if (! $tax instanceof Tax) {
                throw new RuntimeException(
                    'No se pudo resolver el impuesto del ítem "'
                    .(string) ($item->item_name ?? 'Sin nombre')
                    .'" para contabilizar el IVA descontable.'
                );
            }

            if (! $tax->debit_account_id) {
                throw new RuntimeException(
                    'El impuesto "'
                    .(string) $tax->name
                    .'" no tiene configurada debit_account_id para contabilizar IVA descontable.'
                );
            }

            $account = AccountingAccount::query()
                ->active()
                ->allowsEntries()
                ->find((int) $tax->debit_account_id);

            if (! $account) {
                throw new RuntimeException(
                    'La cuenta débito configurada para el impuesto "'
                    .(string) $tax->name
                    .'" no existe o no permite movimientos.'
                );
            }

            $key = (string) $tax->id;

            if (! isset($grouped[$key])) {
                $grouped[$key] = [
                    'tax' => $tax,
                    'account' => $account,
                    'amount' => 0.0,
                ];
            }

            $grouped[$key]['amount'] += $taxAmount;
            $grouped[$key]['amount'] = round($grouped[$key]['amount'], 2);
        }

        return array_values($grouped);
    }

    private function resolveInvoiceWithholdingLines(PurchaseDocument $invoice): array
    {
        $invoiceSubtotal = round(
            (float) $invoice->items->sum(fn (PurchaseDocumentItem $item) => (float) ($item->total ?? 0)),
            2
        );

        $invoiceIva = round(
            (float) $invoice->items->sum(fn (PurchaseDocumentItem $item) => (float) ($item->total_tax ?? 0)),
            2
        );

        $withholdings = is_array($invoice->withholdings) ? $invoice->withholdings : [];
        $lines = [];

        foreach ($withholdings as $index => $withholding) {
            if (! is_array($withholding)) {
                continue;
            }

            $percentage = round((float) ($withholding['percentage'] ?? 0), 2);
            if ($percentage <= 0) {
                continue;
            }

            $retentionId = isset($withholding['retention_id']) && is_numeric($withholding['retention_id'])
                ? (int) $withholding['retention_id']
                : null;

            $retentionName = trim((string) ($withholding['name'] ?? ''));

            $retention = $retentionId
                ? Retention::query()->find($retentionId)
                : Retention::query()->where('name', $retentionName)->first();

            if (! $retention instanceof Retention) {
                throw new RuntimeException(
                    'No se pudo resolver la retención "'
                    .($retentionName !== '' ? $retentionName : '#'.($index + 1))
                    .'" para contabilizar la factura de compra.'
                );
            }

            // Base por defecto según normativa: ReteIVA sobre el IVA; ReteFuente/ICA sobre el subtotal.
            $hasCustomBase = isset($withholding['base_amount']) && is_numeric($withholding['base_amount']) && (float) $withholding['base_amount'] > 0;
            $baseAmount = $hasCustomBase
                ? round((float) $withholding['base_amount'], 2)
                : round(\App\Support\Finance\PurchaseWithholdingCalculator::defaultBase($retention->type, $invoiceSubtotal, $invoiceIva), 2);

            $amount = round(($baseAmount * $percentage) / 100, 2);
            if ($amount <= 0) {
                continue;
            }

            if (! $retention->account_id) {
                throw new RuntimeException(
                    'La retención "'
                    .(string) $retention->name
                    .'" no tiene account_id configurada.'
                );
            }

            $account = AccountingAccount::query()
                ->active()
                ->allowsEntries()
                ->find((int) $retention->account_id);

            if (! $account) {
                throw new RuntimeException(
                    'La cuenta configurada para la retención "'
                    .(string) $retention->name
                    .'" no existe o no permite movimientos.'
                );
            }

            $lines[] = [
                'retention' => $retention,
                'account' => $account,
                'amount' => $amount,
            ];
        }

        return $lines;
    }

    /**
     * Resuelve la cuenta de banco/caja basándose en el account_id del comprobante.
     */
    private function resolveCashBankAccount(?int $accountId): ?AccountingAccount
    {
        if ($accountId === null) {
            // Buscar cuenta por defecto de tipo banco
            return AccountingAccount::findBySpecialType(AccountingAccount::SPECIAL_BANK)
                ?? AccountingAccount::findBySpecialType(AccountingAccount::SPECIAL_CASH);
        }

        // Buscar directamente por ID, restringido a cuentas de tesorería (banco/caja).
        $account = AccountingAccount::query()
            ->active()
            ->allowsEntries()
            ->whereIn('special_type', [
                AccountingAccount::SPECIAL_CASH,
                AccountingAccount::SPECIAL_BANK,
            ])
            ->find($accountId);

        if ($account) {
            return $account;
        }

        throw new RuntimeException(
            'No se encontró una cuenta contable configurada como caja o banco. '
            . 'Verifique que exista al menos una cuenta con special_type "cash" o "bank" '
            . 'en el catálogo de cuentas antes de registrar comprobantes.'
        );
    }

    /**
     * Resuelve la cuenta de cuentas por pagar proveedores.
     */
    private function resolveAccountsPayableAccount(): ?AccountingAccount
    {
        return AccountingAccount::findBySpecialType(AccountingAccount::SPECIAL_ACCOUNTS_PAYABLE);
    }

    private function resolveAccountsReceivableAccount(): ?AccountingAccount
    {
        return AccountingAccount::findBySpecialType(AccountingAccount::SPECIAL_ACCOUNTS_RECEIVABLE);
    }

    private function resolveSalesInvoiceItemAccount(SaleDocumentItem $item): AccountingAccount
    {
        $catalogItem = $item->relationLoaded('item')
            ? $item->item
            : $item->item()->first();

        $itemLabel = (string) ($item->item_name ?: ($catalogItem?->name ?? 'Sin nombre'));
        $accountId = $catalogItem?->income_account_id ? (int) $catalogItem->income_account_id : null;

        if ($accountId) {
            return $this->resolveItemAccount(
                $accountId,
                $itemLabel,
                'cuenta de ingreso',
                'reconocimiento del ingreso'
            );
        }

        $fallbackAccount = $this->resolveFallbackIncomeAccount();
        if ($fallbackAccount) {
            return $fallbackAccount;
        }

        throw new RuntimeException(
            'El item "'.$itemLabel.'" no tiene cuenta de ingreso configurada y no existe una cuenta fallback de tipo income.'
        );
    }

    private function resolveSalesInvoiceTaxLines(Collection $items): array
    {
        $grouped = [];

        foreach ($items as $item) {
            $taxAmount = round((float) ($item->total_tax ?? 0), 2);

            if ($taxAmount <= 0) {
                continue;
            }

            $catalogItem = $item->relationLoaded('item')
                ? $item->item
                : $item->item()->with('tax')->first();

            $tax = $catalogItem?->relationLoaded('tax')
                ? $catalogItem->tax
                : $catalogItem?->tax()->first();

            if (! $tax instanceof Tax) {
                throw new RuntimeException(
                    'No se pudo resolver el impuesto del item "'
                    .(string) ($item->item_name ?? 'Sin nombre')
                    .'" para contabilizar la factura de venta.'
                );
            }

            if (! $tax->credit_account_id) {
                throw new RuntimeException(
                    'El impuesto "'
                    .(string) $tax->name
                    .'" no tiene configurada credit_account_id para contabilizar IVA por pagar.'
                );
            }

            $account = AccountingAccount::query()
                ->active()
                ->allowsEntries()
                ->find((int) $tax->credit_account_id);

            if (! $account) {
                throw new RuntimeException(
                    'La cuenta credito configurada para el impuesto "'
                    .(string) $tax->name
                    .'" no existe o no permite movimientos.'
                );
            }

            $key = (string) $tax->id;

            if (! isset($grouped[$key])) {
                $grouped[$key] = [
                    'tax' => $tax,
                    'account' => $account,
                    'amount' => 0.0,
                ];
            }

            $grouped[$key]['amount'] += $taxAmount;
            $grouped[$key]['amount'] = round($grouped[$key]['amount'], 2);
        }

        return array_values($grouped);
    }

    private function resolveSalesInvoiceWithholdingLines(SaleDocument $invoice): array
    {
        $invoiceSubtotal = round(
            (float) $invoice->items->sum(fn (SaleDocumentItem $item) => (float) ($item->total ?? 0)),
            2
        );

        $withholdings = is_array($invoice->withholdings) ? $invoice->withholdings : [];
        $lines = [];

        foreach ($withholdings as $index => $withholding) {
            if (! is_array($withholding)) {
                continue;
            }

            $percentage = round((float) ($withholding['percentage'] ?? 0), 2);
            if ($percentage <= 0) {
                continue;
            }

            $baseAmount = isset($withholding['base_amount']) && is_numeric($withholding['base_amount'])
                ? round((float) $withholding['base_amount'], 2)
                : $invoiceSubtotal;

            $amount = round(($baseAmount * $percentage) / 100, 2);
            if ($amount <= 0) {
                continue;
            }

            $retentionId = isset($withholding['retention_id']) && is_numeric($withholding['retention_id'])
                ? (int) $withholding['retention_id']
                : null;

            $retentionName = trim((string) ($withholding['name'] ?? ''));

            $retention = $retentionId
                ? Retention::query()->find($retentionId)
                : Retention::query()->where('name', $retentionName)->first();

            if (! $retention instanceof Retention) {
                throw new RuntimeException(
                    'No se pudo resolver la retencion "'
                    .($retentionName !== '' ? $retentionName : '#'.($index + 1))
                    .'" para contabilizar la factura de venta.'
                );
            }

            if (! $retention->account_id) {
                throw new RuntimeException(
                    'La retencion "'
                    .(string) $retention->name
                    .'" no tiene account_id configurada.'
                );
            }

            $account = AccountingAccount::query()
                ->active()
                ->allowsEntries()
                ->find((int) $retention->account_id);

            if (! $account) {
                throw new RuntimeException(
                    'La cuenta configurada para la retencion "'
                    .(string) $retention->name
                    .'" no existe o no permite movimientos.'
                );
            }

            $lines[] = [
                'retention' => $retention,
                'account' => $account,
                'amount' => $amount,
            ];
        }

        return $lines;
    }

    private function resolveFallbackIncomeAccount(): ?AccountingAccount
    {
        return AccountingAccount::query()
            ->active()
            ->allowsEntries()
            ->ofType(AccountingAccount::TYPE_INCOME)
            ->orderBy('code')
            ->first();
    }

    private function postSaleNote(
        SaleDocument $note,
        string $sourceType,
        string $entryType,
        bool $isCreditNote,
    ): JournalEntry {
        $noteId = (int) $note->id;
        $noteNumber = trim((string) ($note->document_number ?: $noteId));

        if (! $note->isCreditDebitNote()) {
            throw new InvalidArgumentException('El documento recibido no es una nota de venta válida.');
        }

        if (($note->status ?? null) !== 'issued') {
            throw new InvalidArgumentException('Solo se pueden contabilizar notas de venta en estado "issued".');
        }

        $note->loadMissing(['items.item.tax']);

        if ($note->items->isEmpty()) {
            throw new InvalidArgumentException(
                "La nota de venta {$noteNumber} no tiene items validos para contabilizar."
            );
        }

        $accountsReceivableAccount = $this->resolveAccountsReceivableAccount();

        if (! $accountsReceivableAccount) {
            throw new RuntimeException(
                'No se encontro una cuenta contable de cuentas por cobrar configurada en el sistema.'
            );
        }

        return DB::transaction(function () use ($note, $noteId, $noteNumber, $sourceType, $entryType, $isCreditNote, $accountsReceivableAccount) {
            $activeSourceEntry = $this->resolveActiveSourceEntryForUpdate(
                $sourceType,
                $noteId,
            );

            if ($activeSourceEntry) {
                Log::info("AccountingPostingService: Sale note {$noteNumber} already posted as entry {$activeSourceEntry->entry_number}");
                return $activeSourceEntry;
            }

            $entryNumber = $this->sequenceService->reserveNextNumber();

            $entry = JournalEntry::create([
                'entry_number' => $entryNumber,
                'entry_date' => $note->date ?? now(),
                'entry_type' => $entryType,
                'status' => JournalEntry::STATUS_POSTED,
                'posted_at' => now(),
                'description' => ($isCreditNote ? 'Nota credito venta #' : 'Nota debito venta #').$noteNumber,
                'source_type' => $sourceType,
                'source_id' => $noteId,
                'total_debit' => 0,
                'total_credit' => 0,
                'created_by_user_id' => Auth::id() ?? $note->created_by_user_id,
            ]);

            $subtotalTotal = '0.00';

            foreach ($note->items as $index => $item) {
                $lineSubtotal = round((float) ($item->total ?? 0), 2);

                if ($lineSubtotal <= 0) {
                    continue;
                }

                $itemAccount = $this->resolveSalesInvoiceItemAccount($item);

                JournalEntryLine::create([
                    'journal_entry_id' => $entry->id,
                    'account_id' => $itemAccount->id,
                    'contact_id' => $note->contact_id,
                    'debit' => $isCreditNote ? $lineSubtotal : 0,
                    'credit' => $isCreditNote ? 0 : $lineSubtotal,
                    'description' => ($isCreditNote ? 'Reversion ingreso nota credito venta #' : 'Ingreso adicional nota debito venta #').$noteNumber,
                    'line_reference' => ($isCreditNote ? 'NCV-' : 'NDV-')."{$noteNumber}-ITEM-".($index + 1),
                ]);

                $subtotalTotal = bcadd($subtotalTotal, number_format($lineSubtotal, 2, '.', ''), 2);
            }

            $taxLines = $this->resolveSalesInvoiceTaxLines($note->items);
            $taxTotal = '0.00';

            foreach ($taxLines as $index => $taxLine) {
                JournalEntryLine::create([
                    'journal_entry_id' => $entry->id,
                    'account_id' => $taxLine['account']->id,
                    'contact_id' => $note->contact_id,
                    'debit' => $isCreditNote ? $taxLine['amount'] : 0,
                    'credit' => $isCreditNote ? 0 : $taxLine['amount'],
                    'description' => ($isCreditNote ? 'IVA reversado nota credito venta #' : 'IVA por pagar nota debito venta #').$noteNumber,
                    'line_reference' => ($isCreditNote ? 'NCV-' : 'NDV-')."{$noteNumber}-IVA-".($index + 1),
                ]);

                $taxTotal = bcadd($taxTotal, number_format($taxLine['amount'], 2, '.', ''), 2);
            }

            $netReceivable = bcadd($subtotalTotal, $taxTotal, 2);

            if (bccomp($netReceivable, '0.00', 2) <= 0) {
                throw new RuntimeException(
                    "El neto de la nota de venta {$noteNumber} no es valido para contabilizar."
                );
            }

            JournalEntryLine::create([
                'journal_entry_id' => $entry->id,
                'account_id' => $accountsReceivableAccount->id,
                'contact_id' => $note->contact_id,
                'debit' => $isCreditNote ? 0 : $netReceivable,
                'credit' => $isCreditNote ? $netReceivable : 0,
                'description' => ($isCreditNote ? 'Reduccion cuenta por cobrar cliente - NCV #' : 'Aumento cuenta por cobrar cliente - NDV #').$noteNumber,
                'line_reference' => ($isCreditNote ? 'NCV-' : 'NDV-').$noteNumber,
            ]);

            $entry->recalculateTotals();
            if (! $entry->isBalanced()) {
                throw new RuntimeException(
                    "El asiento contable para la nota de venta {$noteNumber} no esta balanceado."
                );
            }

            $entry->save();

            Log::info(
                'AccountingPostingService: Posted sale note as journal entry',
                [
                    'note_id' => $noteId,
                    'note_number' => $noteNumber,
                    'source_type' => $sourceType,
                    'journal_entry_id' => $entry->id,
                    'entry_number' => $entryNumber,
                    'total_debit' => $entry->total_debit,
                    'total_credit' => $entry->total_credit,
                ]
            );

            return $entry;
        });
    }

    private function resolveInventoryOutflowAccounts(Item $item): array
    {
        $itemLabel = (string) ($item->name ?? ('Item #' . $item->id));
        if (! $item->tracks_inventory) {
            throw new RuntimeException(
                'El item "'.$itemLabel.'" no controla inventario y no puede contabilizarse como salida de inventario.'
            );
        }

        $inventoryAccount = $this->resolveItemAccount(
            $item->inventory_account_id ? (int) $item->inventory_account_id : null,
            $itemLabel,
            'cuenta de inventario',
            'baja de inventario'
        );

        $outflowAccount = $this->resolveItemAccount(
            $item->cost_of_sale_account_id ? (int) $item->cost_of_sale_account_id : null,
            $itemLabel,
            'cuenta de costo/gasto directo',
            'reconocimiento del costo de salida'
        );

        return [$inventoryAccount, $outflowAccount];
    }

    private function resolveInventoryEntryRegularizationAccounts(Item $item): array
    {
        $itemLabel = (string) ($item->name ?? ('Item #' . $item->id));
        if (! $item->tracks_inventory) {
            throw new RuntimeException(
                'El item "'.$itemLabel.'" no controla inventario y no puede contabilizarse como regularizacion positiva de inventario.'
            );
        }

        $inventoryAccount = $this->resolveItemAccount(
            $item->inventory_account_id ? (int) $item->inventory_account_id : null,
            $itemLabel,
            'cuenta de inventario',
            'alta de inventario por regularizacion positiva'
        );

        $regularizationAccount = $this->resolveItemAccount(
            $item->cost_of_sale_account_id ? (int) $item->cost_of_sale_account_id : null,
            $itemLabel,
            'cuenta de costo/gasto directo',
            'contrapartida de regularizacion positiva'
        );

        return [$inventoryAccount, $regularizationAccount];
    }

    private function resolveItemAccount(
        ?int $accountId,
        string $itemLabel,
        string $accountLabel,
        string $purpose,
    ): AccountingAccount {
        if (! $accountId) {
            throw new RuntimeException(
                'El item "'.$itemLabel.'" no tiene '.$accountLabel.' configurada para '.$purpose.'.'
            );
        }

        $account = AccountingAccount::query()
            ->active()
            ->allowsEntries()
            ->find($accountId);

        if (! $account) {
            throw new RuntimeException(
                'La '.$accountLabel.' configurada para el item "'.$itemLabel.'" no existe o no permite movimientos.'
            );
        }

        return $account;
    }
}
