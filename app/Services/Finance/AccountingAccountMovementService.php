<?php

namespace App\Services\Finance;

use App\Models\Finance\AccountingAccount;
use App\Models\Finance\JournalEntry;
use App\Models\Finance\JournalEntryLine;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class AccountingAccountMovementService
{
    private const SOURCE_TYPE_LABELS = [
        'expense_voucher' => 'Comprobante de egreso',
        'cash_receipt' => 'Recibo de caja',
        'purchase_invoice' => 'Factura de compra',
        'inventory_adjustment' => 'Ajuste de inventario',
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
        $entryReference = trim((string) ($filters['entry_reference'] ?? ''));
        $dateFrom = $filters['date_from'] ?? null;
        $dateTo = $filters['date_to'] ?? null;
        $tableDate = $filters['table_date'] ?? null;
        $tableAccountId = isset($filters['table_account_id']) ? (int) $filters['table_account_id'] : null;
        $contactId = isset($filters['contact_id']) ? (int) $filters['contact_id'] : null;
        $documentType = $filters['document_type'] ?? null;
        $subtreeAccountIds = $this->resolveSubtreeAccountIds($account);

        $allPeriodRows = $this->buildBaseQuery($subtreeAccountIds, $dateFrom, $dateTo)
            ->get()
            ->map(fn (JournalEntryLine $row) => $this->mapRow($account, $row))
            ->values();

        $openingBalance = $this->calculateOpeningBalance($account, $subtreeAccountIds, $dateFrom);
        $rowsWithRunningBalance = $this->attachRunningBalance($account, $allPeriodRows, $openingBalance);
        $rowsWithRunningBalanceById = $rowsWithRunningBalance->keyBy('id');

        $filteredQuery = $this->buildBaseQuery($subtreeAccountIds, $dateFrom, $dateTo)
            ->when($contactId, fn (Builder $query) => $query->where('line.contact_id', $contactId))
            ->when($tableDate, fn (Builder $query) => $query->whereDate('entry.entry_date', $tableDate))
            ->when($tableAccountId, fn (Builder $query) => $query->where('line.account_id', $tableAccountId))
            ->when($documentType && $documentType !== 'all', function (Builder $query) use ($documentType) {
                $query->where(function (Builder $subQuery) use ($documentType) {
                    $subQuery->where('entry.source_type', $documentType)
                        ->orWhere('entry.entry_type', $documentType);
                });
            });

        $filteredRows = $filteredQuery
            ->get()
            ->map(function (JournalEntryLine $row) use ($account, $rowsWithRunningBalanceById) {
                $mappedRow = $this->mapRow($account, $row);
                $runningBalanceRow = $rowsWithRunningBalanceById->get($mappedRow['id']);

                if ($runningBalanceRow) {
                    $mappedRow['running_balance'] = $runningBalanceRow['running_balance'] ?? null;
                }

                return $mappedRow;
            })
            ->values();

        if ($search !== '') {
            $filteredRows = $filteredRows
                ->filter(fn (array $row) => $this->matchesSearch($row, $search))
                ->values();
        }

        if ($entryReference !== '') {
            $filteredRows = $filteredRows
                ->filter(fn (array $row) => $this->matchesEntryReference($row, $entryReference))
                ->values();
        }

        $pageRows = $filteredRows->forPage($page, $perPage)->values();
        $periodDebit = (float) $allPeriodRows->sum('debit');
        $periodCredit = (float) $allPeriodRows->sum('credit');
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
            'chart' => $this->buildComparisonChart($account, $subtreeAccountIds, $dateFrom, $dateTo),
            'document_type_options' => $this->getDocumentTypeOptions($account, $subtreeAccountIds),
            'total' => $filteredRows->count(),
            'current_page' => $page,
            'per_page' => $perPage,
        ];
    }

    public function getDocumentTypeOptions(AccountingAccount $account, ?array $subtreeAccountIds = null): array
    {
        $subtreeAccountIds ??= $this->resolveSubtreeAccountIds($account);

        $rows = JournalEntryLine::query()
            ->from('journal_entry_lines as line')
            ->selectRaw('DISTINCT entry.source_type, entry.entry_type')
            ->join('journal_entries as entry', 'entry.id', '=', 'line.journal_entry_id')
            ->whereIn('line.account_id', $subtreeAccountIds)
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

    private function buildBaseQuery(array $accountIds, ?string $dateFrom, ?string $dateTo): Builder
    {
        $query = JournalEntryLine::query()
            ->from('journal_entry_lines as line')
            ->select([
                'line.id',
                'line.journal_entry_id',
                'line.account_id',
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
                'account.code as account_code',
                'account.name as account_name',
            ])
            ->join('journal_entries as entry', 'entry.id', '=', 'line.journal_entry_id')
            ->leftJoin('contacts as contact', 'contact.id', '=', 'line.contact_id')
            ->join('accounting_accounts as account', 'account.id', '=', 'line.account_id')
            ->whereIn('line.account_id', $accountIds)
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

        return $query;
    }

    private function calculateOpeningBalance(
        AccountingAccount $account,
        array $accountIds,
        ?string $dateFrom,
    ): float {
        if (! $dateFrom) {
            return 0.0;
        }

        $query = JournalEntryLine::query()
            ->from('journal_entry_lines as line')
            ->selectRaw('COALESCE(SUM(line.debit), 0) as total_debit, COALESCE(SUM(line.credit), 0) as total_credit')
            ->join('journal_entries as entry', 'entry.id', '=', 'line.journal_entry_id')
            ->whereIn('line.account_id', $accountIds)
            ->where('entry.status', JournalEntry::STATUS_POSTED)
            ->whereDate('entry.entry_date', '<', $dateFrom);

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
            'account_id' => (int) $row->account_id,
            'account_code' => (string) $row->account_code,
            'account_name' => (string) $row->account_name,
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

    private function buildComparisonChart(
        AccountingAccount $account,
        array $accountIds,
        ?string $dateFrom,
        ?string $dateTo,
    ): array {
        if (! $dateFrom || ! $dateTo) {
            return $this->emptyChartPayload();
        }

        $currentStart = CarbonImmutable::parse($dateFrom)->startOfDay();
        $currentEnd = CarbonImmutable::parse($dateTo)->startOfDay();
        $rangeDays = $currentStart->diffInDays($currentEnd) + 1;

        $previousEnd = $currentStart->subDay();
        $previousStart = $previousEnd->subDays($rangeDays - 1);
        $granularity = $this->resolveChartGranularity($rangeDays);

        $currentRows = $this->fetchRowsForRange($account, $accountIds, $currentStart->toDateString(), $currentEnd->toDateString());
        $previousRows = $this->fetchRowsForRange($account, $accountIds, $previousStart->toDateString(), $previousEnd->toDateString());

        $currentBuckets = $this->buildChartBuckets($currentStart, $currentEnd, $granularity);
        $previousBuckets = $this->buildComparableBuckets($previousStart, $currentBuckets);
        $currentOpeningBalance = $this->calculateOpeningBalance($account, $accountIds, $currentStart->toDateString());
        $previousOpeningBalance = $this->calculateOpeningBalance($account, $accountIds, $previousStart->toDateString());

        $currentSeries = $this->buildRunningBalanceChartSeries($account, $currentRows, $currentBuckets, $currentOpeningBalance);
        $previousSeries = $this->buildRunningBalanceChartSeries($account, $previousRows, $previousBuckets, $previousOpeningBalance);

        return [
            'has_query' => true,
            'has_data' => $currentSeries['has_data'],
            'granularity' => $granularity,
            'labels' => array_map(fn (array $bucket) => $bucket['label'], $currentBuckets),
            'current_period_label' => $this->formatPeriodLabel($currentStart, $currentEnd),
            'previous_period_label' => $previousSeries['has_data']
                ? $this->formatPeriodLabel($previousStart, $previousEnd)
                : null,
            'series' => [
                [
                    'key' => 'current',
                    'name' => 'Periodo actual',
                    'color' => 'primary',
                    'values' => $currentSeries['values'],
                    'has_data' => $currentSeries['has_data'],
                ],
                [
                    'key' => 'previous',
                    'name' => 'Periodo anterior',
                    'color' => 'muted',
                    'values' => $previousSeries['values'],
                    'has_data' => $previousSeries['has_data'],
                ],
            ],
        ];
    }

    private function emptyChartPayload(): array
    {
        return [
            'has_query' => false,
            'has_data' => false,
            'granularity' => 'day',
            'labels' => [],
            'current_period_label' => null,
            'previous_period_label' => null,
            'series' => [],
        ];
    }

    private function fetchRowsForRange(
        AccountingAccount $account,
        array $accountIds,
        string $dateFrom,
        string $dateTo,
    ): Collection {
        return $this->buildBaseQuery($accountIds, $dateFrom, $dateTo)
            ->get()
            ->map(fn (JournalEntryLine $row) => $this->mapRow($account, $row))
            ->values();
    }

    private function resolveSubtreeAccountIds(AccountingAccount $account): array
    {
        $accounts = AccountingAccount::query()
            ->select(['id', 'parent_id'])
            ->orderBy('code')
            ->get();

        $childrenByParent = [];
        foreach ($accounts as $candidate) {
            $childrenByParent[$candidate->parent_id ?? 0][] = (int) $candidate->id;
        }

        $subtreeIds = [];
        $stack = [$account->id];
        $visited = [];

        while ($stack !== []) {
            $currentId = array_pop($stack);
            if (isset($visited[$currentId])) {
                continue;
            }

            $visited[$currentId] = true;
            $subtreeIds[] = $currentId;

            foreach ($childrenByParent[$currentId] ?? [] as $childId) {
                $stack[] = $childId;
            }
        }

        return $subtreeIds;
    }

    private function matchesEntryReference(array $row, string $entryReference): bool
    {
        $needle = mb_strtolower($entryReference);
        $haystack = mb_strtolower(implode(' ', array_filter([
            (string) ($row['reference'] ?? ''),
            (string) ($row['entry_number'] ?? ''),
            (string) ($row['source_id'] ?? ''),
        ])));

        return str_contains($haystack, $needle);
    }

    private function resolveChartGranularity(int $rangeDays): string
    {
        if ($rangeDays <= 45) {
            return 'day';
        }

        if ($rangeDays <= 180) {
            return 'week';
        }

        return 'month';
    }

    private function buildChartBuckets(
        CarbonImmutable $start,
        CarbonImmutable $end,
        string $granularity,
    ): array {
        $buckets = [];
        $cursor = $start;

        while ($cursor->lessThanOrEqualTo($end)) {
            if ($granularity === 'day') {
                $bucketEnd = $cursor;
                $label = $cursor->translatedFormat('d M');
            } elseif ($granularity === 'week') {
                $bucketEnd = $cursor->addDays(6);
                if ($bucketEnd->greaterThan($end)) {
                    $bucketEnd = $end;
                }
                $label = sprintf('%s - %s', $cursor->translatedFormat('d M'), $bucketEnd->translatedFormat('d M'));
            } else {
                $bucketEnd = $cursor->endOfMonth();
                if ($bucketEnd->greaterThan($end)) {
                    $bucketEnd = $end;
                }
                $label = $cursor->translatedFormat('M Y');
            }

            $buckets[] = [
                'start' => $cursor,
                'end' => $bucketEnd,
                'label' => $label,
                'days' => $cursor->diffInDays($bucketEnd) + 1,
            ];

            $cursor = $bucketEnd->addDay();
        }

        return $buckets;
    }

    private function buildComparableBuckets(CarbonImmutable $start, array $referenceBuckets): array
    {
        $buckets = [];
        $cursor = $start;

        foreach ($referenceBuckets as $bucket) {
            $bucketEnd = $cursor->addDays(((int) $bucket['days']) - 1);

            $buckets[] = [
                'start' => $cursor,
                'end' => $bucketEnd,
                'label' => $bucket['label'],
                'days' => (int) $bucket['days'],
            ];

            $cursor = $bucketEnd->addDay();
        }

        return $buckets;
    }

    private function buildRunningBalanceChartSeries(
        AccountingAccount $account,
        Collection $rows,
        array $buckets,
        float $openingBalance,
    ): array
    {
        $values = [];
        $runningBalance = $openingBalance;
        $hasData = $rows->isNotEmpty() || round($openingBalance, 2) !== 0.0;

        foreach ($buckets as $bucket) {
            $bucketNetMovement = (float) $rows
                ->filter(function (array $row) use ($bucket) {
                    $date = CarbonImmutable::parse($row['date'])->startOfDay();

                    return $date->betweenIncluded($bucket['start'], $bucket['end']);
                })
                ->sum(fn (array $row) => $this->normalizeBalance(
                    $account,
                    (float) $row['debit'] - (float) $row['credit'],
                ));

            $runningBalance += $bucketNetMovement;
            $values[] = round($runningBalance, 2);
        }

        return [
            'values' => $values,
            'has_data' => $hasData,
        ];
    }

    private function formatPeriodLabel(CarbonImmutable $start, CarbonImmutable $end): string
    {
        return sprintf('%s - %s', $start->translatedFormat('d M Y'), $end->translatedFormat('d M Y'));
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
            'inventory_adjustment' => '/inventario/ajuste-inventario',
            default => null,
        };
    }

    private function normalizeBalance(AccountingAccount $account, float $signedBalance): float
    {
        return $account->isDebitNature() ? $signedBalance : $signedBalance * -1;
    }
}
