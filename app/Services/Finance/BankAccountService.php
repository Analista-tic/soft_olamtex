<?php

namespace App\Services\Finance;

use App\Models\Finance\AccountingAccount;
use App\Models\Finance\BankAccount;
use App\Models\Finance\JournalEntry;
use App\Models\Finance\JournalEntryLine;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class BankAccountService
{
    public function __construct(
        private JournalEntryNumberSequenceService $sequenceService,
    ) {
    }

    public function create(array $data, int $userId): BankAccount
    {
        return DB::transaction(function () use ($data, $userId) {
            $accountNumber = $data['account_number'] ?? null;

            if ($accountNumber !== null) {
                $exists = BankAccount::where('account_number', $accountNumber)->lockForUpdate()->exists();
                if ($exists) {
                    throw new RuntimeException(
                        "Ya existe una cuenta bancaria con el número de cuenta \"{$accountNumber}\"."
                    );
                }
            }

            $type = $data['type'];
            $accountingAccount = $this->createAccountingAccount($type, $data['name']);

            $bankAccount = BankAccount::create([
                'name' => $data['name'],
                'type' => $type,
                'bank_name' => $data['bank_name'] ?? null,
                'account_number' => $data['account_number'] ?? null,
                'currency' => $data['currency'] ?? 'COP',
                'opening_balance' => $data['opening_balance'] ?? 0,
                'opening_balance_date' => $data['opening_balance_date'] ?? null,
                'accounting_account_id' => $accountingAccount->id,
                'is_active' => true,
                'description' => $data['description'] ?? null,
                'created_by_user_id' => $userId,
            ]);

            $openingBalance = (float) ($data['opening_balance'] ?? 0);

            if ($openingBalance > 0) {
                $this->createOpeningBalanceEntry($bankAccount, $accountingAccount, $openingBalance, $userId);
            }

            return $bankAccount;
        });
    }

    public function update(BankAccount $bankAccount, array $data): BankAccount
    {
        $updatable = [];

        if (array_key_exists('name', $data)) {
            $updatable['name'] = $data['name'];
        }
        if (array_key_exists('bank_name', $data)) {
            $updatable['bank_name'] = $data['bank_name'];
        }
        if (array_key_exists('account_number', $data)) {
            $updatable['account_number'] = $data['account_number'];
        }
        if (array_key_exists('description', $data)) {
            $updatable['description'] = $data['description'];
        }
        if (array_key_exists('is_active', $data)) {
            $updatable['is_active'] = $data['is_active'];
        }

        $bankAccount->update($updatable);

        if (isset($updatable['name']) && $bankAccount->accounting_account_id) {
            // Por instancia para que el AuditObserver registre el cambio en la cuenta contable.
            AccountingAccount::where('id', $bankAccount->accounting_account_id)
                ->get()
                ->each
                ->update(['name' => $updatable['name']]);
        }

        return $bankAccount->refresh();
    }

    public function delete(BankAccount $bankAccount): void
    {
        if ($bankAccount->accounting_account_id) {
            $hasMovements = JournalEntryLine::where('account_id', $bankAccount->accounting_account_id)->exists();

            if ($hasMovements) {
                throw new RuntimeException(
                    "No se puede eliminar la cuenta \"{$bankAccount->name}\" porque tiene movimientos contables registrados."
                );
            }
        }

        $bankAccount->update(['is_active' => false]);

        if ($bankAccount->accounting_account_id) {
            // Por instancia para que el AuditObserver registre el cambio en la cuenta contable.
            AccountingAccount::where('id', $bankAccount->accounting_account_id)
                ->get()
                ->each
                ->update(['is_active' => false]);
        }

        Log::info("BankAccountService: Deactivated bank account {$bankAccount->id} ({$bankAccount->name})");
    }

    private function createAccountingAccount(string $type, string $name): AccountingAccount
    {
        switch ($type) {
            case BankAccount::TYPE_CASH:
                $codePrefix = '11050';
                $parentCode = '1105';
                $accountType = AccountingAccount::TYPE_ASSET;
                $specialType = AccountingAccount::SPECIAL_CASH;
                break;

            case BankAccount::TYPE_BANK:
                $codePrefix = '11100';
                $parentCode = '1110';
                $accountType = AccountingAccount::TYPE_ASSET;
                $specialType = AccountingAccount::SPECIAL_BANK;
                break;

            case BankAccount::TYPE_CREDIT_CARD:
                $codePrefix = '22';
                $parentCode = null;
                $accountType = AccountingAccount::TYPE_LIABILITY;
                $specialType = null;
                break;

            default:
                throw new RuntimeException("Tipo de cuenta no soportado: {$type}");
        }

        // Resolve parent account
        $parent = null;
        if ($type === BankAccount::TYPE_CREDIT_CARD) {
            $parent = AccountingAccount::where('code', 'LIKE', '22%')
                ->where('name', 'LIKE', '%tarjeta%')
                ->first();

            if (!$parent) {
                Log::warning("BankAccountService: No se encontró cuenta padre para tarjeta de crédito (code LIKE '22%' AND name LIKE '%tarjeta%'). Se creará sin padre.");
            }
        } else {
            $parent = AccountingAccount::where('code', $parentCode)->first();

            if (!$parent) {
                throw new RuntimeException(
                    "No se encontró la cuenta padre \"{$parentCode}\" en el catálogo contable. "
                    . 'Debe existir antes de crear una cuenta de tipo ' . $type . '.'
                );
            }
        }

        // Generate next available code
        if ($type === BankAccount::TYPE_CREDIT_CARD) {
            $basePrefix = $parent ? $parent->code : '2205';
            $lastCode = AccountingAccount::where('code', 'LIKE', $basePrefix . '%')
                ->where('code', '!=', $basePrefix)
                ->orderByDesc('code')
                ->value('code');

            if ($lastCode) {
                $nextNum = ((int) substr($lastCode, strlen($basePrefix))) + 1;
            } else {
                $nextNum = 1;
            }
            $code = $basePrefix . str_pad((string) $nextNum, 2, '0', STR_PAD_LEFT);
        } else {
            $lastCode = AccountingAccount::where('code', 'LIKE', $codePrefix . '%')
                ->orderByDesc('code')
                ->value('code');

            if ($lastCode) {
                $nextNum = ((int) substr($lastCode, strlen($codePrefix))) + 1;
            } else {
                $nextNum = 1;
            }
            $code = $codePrefix . $nextNum;
        }

        $level = $parent ? ($parent->level + 1) : 1;

        return AccountingAccount::create([
            'code' => $code,
            'name' => $name,
            'account_type' => $accountType,
            'parent_id' => $parent?->id,
            'level' => $level,
            'allows_entries' => true,
            'is_active' => true,
            'special_type' => $specialType,
        ]);
    }

    private function createOpeningBalanceEntry(
        BankAccount $bankAccount,
        AccountingAccount $accountingAccount,
        float $amount,
        int $userId,
    ): JournalEntry {
        $equityAccount = AccountingAccount::where('code', '3105')->active()->first();

        if (!$equityAccount) {
            $equityAccount = AccountingAccount::ofType(AccountingAccount::TYPE_EQUITY)
                ->active()
                ->allowsEntries()
                ->first();
        }

        if (!$equityAccount) {
            throw new RuntimeException(
                'No se encontró una cuenta de patrimonio (3105 o tipo equity) para registrar el saldo de apertura.'
            );
        }

        $entryDate = $bankAccount->opening_balance_date ?? now()->toDateString();
        $entryNumber = $this->sequenceService->reserveNextNumber();

        $entry = JournalEntry::create([
            'entry_number' => $entryNumber,
            'entry_date' => $entryDate,
            'entry_type' => JournalEntry::TYPE_OPENING,
            'status' => JournalEntry::STATUS_POSTED,
            'posted_at' => now(),
            'description' => "Saldo de apertura - {$bankAccount->name}",
            'source_type' => 'bank_account_opening',
            'source_id' => $bankAccount->id,
            'total_debit' => $amount,
            'total_credit' => $amount,
            'created_by_user_id' => $userId,
        ]);

        // For asset accounts (bank/cash): DEBIT bank, CREDIT equity
        // For liability accounts (credit_card): CREDIT liability, DEBIT equity
        if ($bankAccount->type === BankAccount::TYPE_CREDIT_CARD) {
            JournalEntryLine::create([
                'journal_entry_id' => $entry->id,
                'account_id' => $equityAccount->id,
                'contact_id' => null,
                'debit' => $amount,
                'credit' => 0,
                'description' => "Saldo apertura tarjeta de crédito - {$bankAccount->name}",
                'line_reference' => "AP-BA-{$bankAccount->id}",
            ]);

            JournalEntryLine::create([
                'journal_entry_id' => $entry->id,
                'account_id' => $accountingAccount->id,
                'contact_id' => null,
                'debit' => 0,
                'credit' => $amount,
                'description' => "Saldo apertura tarjeta de crédito - {$bankAccount->name}",
                'line_reference' => "AP-BA-{$bankAccount->id}",
            ]);
        } else {
            JournalEntryLine::create([
                'journal_entry_id' => $entry->id,
                'account_id' => $accountingAccount->id,
                'contact_id' => null,
                'debit' => $amount,
                'credit' => 0,
                'description' => "Saldo apertura cuenta - {$bankAccount->name}",
                'line_reference' => "AP-BA-{$bankAccount->id}",
            ]);

            JournalEntryLine::create([
                'journal_entry_id' => $entry->id,
                'account_id' => $equityAccount->id,
                'contact_id' => null,
                'debit' => 0,
                'credit' => $amount,
                'description' => "Capital por apertura cuenta - {$bankAccount->name}",
                'line_reference' => "AP-BA-{$bankAccount->id}",
            ]);
        }

        $entry->recalculateTotals();
        if (!$entry->isBalanced()) {
            throw new RuntimeException(
                "El asiento de apertura para \"{$bankAccount->name}\" no está balanceado."
            );
        }
        $entry->save();

        Log::info("BankAccountService: Created opening balance entry {$entryNumber} for bank account {$bankAccount->id}", [
            'journal_entry_id' => $entry->id,
            'amount' => $amount,
        ]);

        return $entry;
    }
}
