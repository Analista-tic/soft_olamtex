<?php

namespace App\Services\Finance;

use App\Models\Finance\AccountingAccount;
use App\Models\Finance\ExpenseVoucher;
use App\Models\Finance\JournalEntry;
use App\Models\Finance\JournalEntryLine;
use App\Models\Expenses\PurchasePayment;
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
    public const SOURCE_SALES_INVOICE = 'sales_invoice';

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

        // Fallback temporal solo para IDs heredados del front anterior.
        if ($accountId === 1) {
            return AccountingAccount::findBySpecialType(AccountingAccount::SPECIAL_CASH);
        }

        if ($accountId === 2) {
            return AccountingAccount::findBySpecialType(AccountingAccount::SPECIAL_BANK);
        }

        return null;
    }

    /**
     * Resuelve la cuenta de cuentas por pagar proveedores.
     */
    private function resolveAccountsPayableAccount(): ?AccountingAccount
    {
        return AccountingAccount::findBySpecialType(AccountingAccount::SPECIAL_ACCOUNTS_PAYABLE);
    }
}
