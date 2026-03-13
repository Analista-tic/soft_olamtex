<?php

namespace App\Services\Finance;

use App\Models\Finance\AccountingAccount;
use App\Models\Finance\JournalEntry;
use App\Models\Finance\JournalEntryLine;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class AccountingAccountMovementService
{
    private const SOURCE_TYPE_LABELS = [
        'expense_voucher' => 'Comprobante de egreso',
        'cash_receipt' => 'Recibo de caja',
        'purchase_invoice' => 'Factura de compra',
        'sales_invoice' => 'Factura de venta',
    ];

    private const ENTRY_TYPE_LABELS = [
        JournalEntry::TYPE_STANDARD => 'Asiento estandar',
        JournalEntry::TYPE_EXPENSE_VOUCHER => 'Comprobante de egreso',
        JournalEntry::TYPE_CASH_RECEIPT => 'Recibo de caja',
        JournalEntry::TYPE_ADJUSTMENT => 'Ajuste',
        JournalEntry::TYPE_OPENING => 'Apertura',
        JournalEntry::TYPE_CLOSING => 'Cierre',
    ];

    public function getMovements(AccountingAccount $account, array $filters): array
    {
        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = min(200, max(1, (int) ($filters['per_page'] ?? 25)));
        $search = trim((string) ($filters['search'] ?? ''));
        $dateFrom = $filters['date_from'] ?? null;
        $dateTo = $filters['date_to'] ?? null;
        $contactId = isset($filters['contact_id']) ? (int) $filters['contact_id'] : null;
        $documentType = $filters['document_type'] ?? null;

        $periodRows = $this->buildBaseQuery($account, $dateFrom, $dateTo, $contactId, $documentType)
            ->get()
            ->map(fn (JournalEntryLine $row) => $this->mapRow($account, $row))
            ->values();

        $openingBalance = $this->calculateOpeningBalance($account, $dateFrom, $contactId, $documentType);
        $rowsWithRunningBalance = $this->attachRunningBalance($account, $periodRows, $openingBalance);

        $displayRows = $search === ''
            ? $rowsWithRunningBalance
            : $rowsWithRunningBalance
                ->filter(fn (array $row) => $this->matchesSearch($row, $search))
                ->values();

        $pageRows = $displayRows->forPage($page, $perPage)->values();
        $periodDebit = (float) $periodRows->sum('debit');
        $periodCredit = (float) $periodRows->sum('credit');
        $netMovement = $this->normalizeBalance($account, $periodDebit - $periodCredit);
        $closingBalance = $rowsWithRunningBalance->last()['running_balance'] ?? $openingBalance;

        return [
            'data' => $pageRows->all(),
            'summary' => [
                'opening_balance' => round($openingBalance, 2),
                'total_debit' => round($periodDebit, 2),
                'total_credit' => round($periodCredit, 2),
                'net_movement' => round($netMovement, 2),
                'closing_balance' => round((float) $closingBalance, 2),
            ],
            'document_type_options' => $this->getDocumentTypeOptions($account),
            'total' => $displayRows->count(),
            'current_page' => $page,
            'per_page' => $perPage,
        ];
    }

    public function getDocumentTypeOptions(AccountingAccount $account): array
    {
        $rows = JournalEntryLine::query()
            ->from('journal_entry_lines as line')
            ->selectRaw('DISTINCT entry.source_type, entry.entry_type')
            ->join('journal_entries as entry', 'entry.id', '=', 'line.journal_entry_id')
            ->where('line.account_id', $account->id)
            ->where('entry.status', JournalEntry::STATUS_POSTED)
            ->orderBy('entry.source_type')
            ->orderBy('entry.entry_type')
            ->get();

        $options = collect($rows)
            ->map(function (JournalEntryLine $row) {
                $documentType = $this->resolveDocumentTypeValue($row->source_type, $row->entry_type);

                return [
                    'id' => $documentType,
                    'name' => $this->resolveDocumentTypeLabel($row->source_type, $row->entry_type),
                ];
            })
            ->filter(fn (array $option) => $option['id'] !== '')
            ->unique('id')
            ->sortBy('name')
            ->values()
            ->all();

        array_unshift($options, ['id' => 'all', 'name' => 'Todos los documentos']);

        return $options;
    }

    private function buildBaseQuery(
        AccountingAccount $account,
        ?string $dateFrom,
        ?string $dateTo,
        ?int $contactId,
        ?string $documentType,
    ): Builder {
        $query = JournalEntryLine::query()
            ->from('journal_entry_lines as line')
            ->select([
                'line.id',
                'line.journal_entry_id',
                'line.contact_id',
                'line.debit',
                'line.credit',
                'line.description as line_description',
                'line.line_reference',
                'entry.entry_date',
                'entry.entry_number',
                'entry.entry_type',
                'entry.description as entry_description',
                'entry.source_type',
                'entry.source_id',
                'contact.full_name as contact_name',
            ])
            ->join('journal_entries as entry', 'entry.id', '=', 'line.journal_entry_id')
            ->leftJoin('contacts as contact', 'contact.id', '=', 'line.contact_id')
            ->where('line.account_id', $account->id)
            ->where('entry.status', JournalEntry::STATUS_POSTED)
            ->orderBy('entry.entry_date')
            ->orderBy('entry.entry_number')
            ->orderBy('line.id');

        if ($dateFrom) {
            $query->whereDate('entry.entry_date', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->whereDate('entry.entry_date', '<=', $dateTo);
        }

        if ($contactId) {
            $query->where('line.contact_id', $contactId);
        }

        if ($documentType && $documentType !== 'all') {
            $this->applyDocumentTypeFilter($query, $documentType);
        }

        return $query;
    }

    private function calculateOpeningBalance(
        AccountingAccount $account,
        ?string $dateFrom,
        ?int $contactId,
        ?string $documentType,
    ): float {
        if (! $dateFrom) {
            return 0.0;
        }

        $query = JournalEntryLine::query()
            ->from('journal_entry_lines as line')
            ->selectRaw('COALESCE(SUM(line.debit), 0) as total_debit, COALESCE(SUM(line.credit), 0) as total_credit')
            ->join('journal_entries as entry', 'entry.id', '=', 'line.journal_entry_id')
            ->where('line.account_id', $account->id)
            ->where('entry.status', JournalEntry::STATUS_POSTED)
            ->whereDate('entry.entry_date', '<', $dateFrom);

        if ($contactId) {
            $query->where('line.contact_id', $contactId);
        }

        if ($documentType && $documentType !== 'all') {
            $this->applyDocumentTypeFilter($query, $documentType);
        }

        $totals = $query->first();
        $signedBalance = (float) ($totals->total_debit ?? 0) - (float) ($totals->total_credit ?? 0);

        return $this->normalizeBalance($account, $signedBalance);
    }

    private function mapRow(AccountingAccount $account, JournalEntryLine $row): array
    {
        $documentType = $this->resolveDocumentTypeValue($row->source_type, $row->entry_type);
        $reference = $row->line_reference
            ?: ($row->source_id ? (string) $row->source_id : null);

        return [
            'id' => (int) $row->id,
            'date' => (string) $row->entry_date,
            'document_type' => $documentType,
            'document_type_label' => $this->resolveDocumentTypeLabel($row->source_type, $row->entry_type),
            'reference' => $reference,
            'entry_number' => (int) $row->entry_number,
            'third_party_id' => $row->contact_id ? (int) $row->contact_id : null,
            'third_party_name' => $row->contact_name ? (string) $row->contact_name : null,
            'debit' => round((float) $row->debit, 2),
            'credit' => round((float) $row->credit, 2),
            'description' => $row->line_description ?: $row->entry_description,
            'source_type' => $row->source_type ? (string) $row->source_type : null,
            'source_id' => $row->source_id ? (int) $row->source_id : null,
            'source_url' => $this->resolveSourceUrl($row->source_type, $row->source_id),
            'can_view_source' => $this->resolveSourceUrl($row->source_type, $row->source_id) !== null,
            'entry_url' => null,
            'account_nature' => $account->isDebitNature() ? 'debit' : 'credit',
        ];
    }

    private function attachRunningBalance(
        AccountingAccount $account,
        Collection $rows,
        float $openingBalance,
    ): Collection {
        $runningBalance = $openingBalance;

        return $rows->map(function (array $row) use ($account, &$runningBalance) {
            $delta = $this->normalizeBalance(
                $account,
                (float) $row['debit'] - (float) $row['credit'],
            );

            $runningBalance += $delta;
            $row['running_balance'] = round($runningBalance, 2);

            return $row;
        });
    }

    private function matchesSearch(array $row, string $search): bool
    {
        $needle = mb_strtolower($search);
        $haystack = mb_strtolower(implode(' ', array_filter([
            (string) ($row['document_type_label'] ?? ''),
            (string) ($row['reference'] ?? ''),
            (string) ($row['entry_number'] ?? ''),
            (string) ($row['third_party_name'] ?? ''),
            (string) ($row['description'] ?? ''),
            (string) ($row['source_id'] ?? ''),
        ])));

        return str_contains($haystack, $needle);
    }

    private function applyDocumentTypeFilter(Builder $query, string $documentType): void
    {
        $query->where(function (Builder $innerQuery) use ($documentType) {
            $innerQuery
                ->where('entry.source_type', $documentType)
                ->orWhere(function (Builder $fallbackQuery) use ($documentType) {
                    $fallbackQuery
                        ->whereNull('entry.source_type')
                        ->where('entry.entry_type', $documentType);
                });
        });
    }

    private function resolveDocumentTypeValue(?string $sourceType, ?string $entryType): string
    {
        return $sourceType ?: (string) $entryType;
    }

    private function resolveDocumentTypeLabel(?string $sourceType, ?string $entryType): string
    {
        if ($sourceType && isset(self::SOURCE_TYPE_LABELS[$sourceType])) {
            return self::SOURCE_TYPE_LABELS[$sourceType];
        }

        if ($entryType && isset(self::ENTRY_TYPE_LABELS[$entryType])) {
            return self::ENTRY_TYPE_LABELS[$entryType];
        }

        return ucfirst(str_replace('_', ' ', (string) ($sourceType ?: $entryType ?: 'Movimiento')));
    }

    private function resolveSourceUrl(?string $sourceType, mixed $sourceId): ?string
    {
        if (! $sourceType || ! $sourceId) {
            return null;
        }

        return match ($sourceType) {
            'expense_voucher' => "/finanzas/comprobantes-egreso/{$sourceId}",
            default => null,
        };
    }

    private function normalizeBalance(AccountingAccount $account, float $signedBalance): float
    {
        return $account->isDebitNature() ? $signedBalance : $signedBalance * -1;
    }
}
