<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\StoreBankAccountRequest;
use App\Http\Requests\Finance\UpdateBankAccountRequest;
use App\Models\Finance\BankAccount;
use App\Models\Finance\JournalEntryLine;
use App\Services\Finance\BankAccountService;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use RuntimeException;

class BankAccountController extends Controller
{
    public function __construct(
        private BankAccountService $bankAccountService,
    ) {
    }

    public function index()
    {
        $bankAccounts = BankAccount::with('accountingAccount')
            ->orderBy('name')
            ->get();

        $accountIds = $bankAccounts->pluck('accounting_account_id')->filter()->values();

        $balances = collect();
        if ($accountIds->isNotEmpty()) {
            $balances = JournalEntryLine::whereIn('journal_entry_lines.account_id', $accountIds)
                ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
                ->where('journal_entries.status', 'posted')
                ->groupBy('journal_entry_lines.account_id')
                ->select([
                    'journal_entry_lines.account_id',
                    DB::raw('COALESCE(SUM(journal_entry_lines.debit), 0) as total_debit'),
                    DB::raw('COALESCE(SUM(journal_entry_lines.credit), 0) as total_credit'),
                ])
                ->get()
                ->keyBy('account_id');
        }

        $bankAccounts = $bankAccounts->map(function (BankAccount $account) use ($balances) {
            $sums = $account->accounting_account_id
                ? ($balances[$account->accounting_account_id] ?? null)
                : null;

            if (!$sums) {
                $account->setAttribute('current_balance', 0.0);
                return $account;
            }

            $totalDebit = (float) $sums->total_debit;
            $totalCredit = (float) $sums->total_credit;

            if ($account->type === BankAccount::TYPE_CREDIT_CARD) {
                $balance = round($totalCredit - $totalDebit, 2);
            } else {
                $balance = round($totalDebit - $totalCredit, 2);
            }

            $account->setAttribute('current_balance', $balance);
            return $account;
        });

        $totalBankCash = $bankAccounts
            ->whereIn('type', [BankAccount::TYPE_BANK, BankAccount::TYPE_CASH])
            ->sum('current_balance');

        $totalCreditCardDebt = $bankAccounts
            ->where('type', BankAccount::TYPE_CREDIT_CARD)
            ->sum('current_balance');

        return Inertia::render('Finance/Banks/Index', [
            'bankAccounts' => $bankAccounts,
            'summary' => [
                'totalBankCash' => round((float) $totalBankCash, 2),
                'totalCreditCardDebt' => round((float) $totalCreditCardDebt, 2),
            ],
        ]);
    }

    public function store(StoreBankAccountRequest $request)
    {
        try {
            $this->bankAccountService->create(
                $request->validated(),
                (int) $request->user()->id,
            );

            return redirect()->back()->with('success', 'Cuenta bancaria creada correctamente.');
        } catch (RuntimeException $e) {
            return redirect()->back()->withErrors(['general' => $e->getMessage()]);
        }
    }

    public function update(UpdateBankAccountRequest $request, BankAccount $bankAccount)
    {
        try {
            $this->bankAccountService->update($bankAccount, $request->validated());

            return redirect()->back()->with('success', 'Cuenta bancaria actualizada correctamente.');
        } catch (RuntimeException $e) {
            return redirect()->back()->withErrors(['general' => $e->getMessage()]);
        }
    }

    public function destroy(BankAccount $bankAccount)
    {
        try {
            $this->bankAccountService->delete($bankAccount);

            return redirect()->back()->with('success', 'Cuenta bancaria desactivada correctamente.');
        } catch (RuntimeException $e) {
            return redirect()->back()->withErrors([
                'general' => 'No se puede eliminar: ' . $e->getMessage(),
            ]);
        }
    }

    public function show(BankAccount $bankAccount)
    {
        $bankAccount->load('accountingAccount');

        $balance = $this->calculateBalance($bankAccount);
        $bankAccount->setAttribute('current_balance', $balance);

        $movements = collect();

        if ($bankAccount->accounting_account_id) {
            $movements = JournalEntryLine::where('account_id', $bankAccount->accounting_account_id)
                ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
                ->where('journal_entries.status', 'posted')
                ->select([
                    'journal_entry_lines.id',
                    'journal_entry_lines.debit',
                    'journal_entry_lines.credit',
                    'journal_entry_lines.description',
                    'journal_entry_lines.line_reference',
                    'journal_entries.entry_number',
                    'journal_entries.entry_date',
                    'journal_entries.entry_type',
                    'journal_entries.description as entry_description',
                    'journal_entries.source_type',
                    'journal_entries.source_id',
                ])
                ->orderByDesc('journal_entries.entry_date')
                ->orderByDesc('journal_entries.entry_number')
                ->get();
        }

        return Inertia::render('Finance/Banks/Show', [
            'bankAccount' => $bankAccount,
            'movements' => $movements,
        ]);
    }

    private function calculateBalance(BankAccount $account): float
    {
        $openingBalance = (float) $account->opening_balance;

        if (!$account->accounting_account_id) {
            return $openingBalance;
        }

        $sums = JournalEntryLine::where('journal_entry_lines.account_id', $account->accounting_account_id)
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->where('journal_entries.status', 'posted')
            ->select([
                DB::raw('COALESCE(SUM(journal_entry_lines.debit), 0) as total_debit'),
                DB::raw('COALESCE(SUM(journal_entry_lines.credit), 0) as total_credit'),
            ])
            ->first();

        $totalDebit = (float) $sums->total_debit;
        $totalCredit = (float) $sums->total_credit;

        // Asset accounts (bank/cash): balance = debits - credits
        // The opening_balance is already included via the opening journal entry
        // so we just return net movement from journal lines
        if ($account->type === BankAccount::TYPE_CREDIT_CARD) {
            // Liability: saldo = credits - debits (positive means debt)
            return round($totalCredit - $totalDebit, 2);
        }

        // Asset: saldo = debits - credits (positive means available funds)
        return round($totalDebit - $totalCredit, 2);
    }
}
