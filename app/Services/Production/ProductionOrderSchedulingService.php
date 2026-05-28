<?php

namespace App\Services\Production;

use App\Models\Production\ProductSheet;
use App\Models\Production\ProductionOrder;
use App\Services\Settings\SettingService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class ProductionOrderSchedulingService
{
    public function __construct(private SettingService $settingService)
    {
    }

    private const PRIORITIES = ['A', 'B', 'C', 'D'];

    private const TARGET_PERCENTAGES = [
        'A' => 30.0,
        'B' => 30.0,
        'C' => 30.0,
        'D' => 10.0,
    ];

    private const TOLERANCE = 5.0;

    private const MONTH_MAP = [
        'ENERO' => 1,
        'FEBRERO' => 2,
        'MARZO' => 3,
        'ABRIL' => 4,
        'MAYO' => 5,
        'JUNIO' => 6,
        'JULIO' => 7,
        'AGOSTO' => 8,
        'SEPTIEMBRE' => 9,
        'OCTUBRE' => 10,
        'NOVIEMBRE' => 11,
        'DICIEMBRE' => 12,
    ];

    public function resolveStandardDate(string $season, string $priority, ?Carbon $baseDate = null): string
    {
        $priority = strtoupper(trim($priority));
        if (!in_array($priority, self::PRIORITIES, true)) {
            throw new InvalidArgumentException('Prioridad no válida para calcular fecha estimada.');
        }

        $month = $this->monthNumber($season);
        $referenceDate = $baseDate ?? now();
        $explicitYear = $this->yearNumber($season);

        if ($explicitYear !== null) {
            $year = $explicitYear;
        } else {
            $year = $referenceDate->year;
            if ($month < $referenceDate->month) {
                $year++;
            }
        }

        return match ($priority) {
            'A' => Carbon::create($year, $month, 1)->subMonth()->setDay(20)->toDateString(),
            'B' => Carbon::create($year, $month, 1)->subMonth()->setDay(27)->toDateString(),
            'C' => Carbon::create($year, $month, 3)->toDateString(),
            'D' => Carbon::create($year, $month, 10)->toDateString(),
            default => throw new InvalidArgumentException('No se pudo calcular fecha estimada.'),
        };
    }

    public function resolvePriorityForNewOrder(
        ProductSheet $productSheet,
        string $season,
        int $units,
        ?string $dispatchMonth = null,
        ?string $priorityOverride = null,
    ): string {
        if ($priorityOverride !== null && $priorityOverride !== '') {
            $priority = strtoupper(trim($priorityOverride));
            if (in_array($priority, self::PRIORITIES, true)) {
                return $priority;
            }
        }

        $distribution = $this->calculateDistributionForSeason($season);

        $groupOption = $this->settingService->getCatalogOptionById('groups', (int) ($productSheet->group_id ?? 0));
        $groupName = strtoupper(trim((string) ($groupOption['name'] ?? '')));

        if ($groupName === 'MODA') {
            $aCurrent = (float) ($distribution['breakdown']['A']['percentage'] ?? 0);
            if ($aCurrent < self::TARGET_PERCENTAGES['A']) {
                return 'A';
            }
        }

        $candidates = collect(self::TARGET_PERCENTAGES)
            ->map(function (float $target, string $priority) use ($distribution) {
                $current = (float) ($distribution['breakdown'][$priority]['percentage'] ?? 0);
                return [
                    'priority' => $priority,
                    'gap' => $target - $current,
                ];
            })
            ->sortByDesc('gap')
            ->values();

        $available = $candidates->firstWhere('gap', '>', 0);

        return (string) ($available['priority'] ?? 'A');
    }

    public function calculateDashboardFromQuery(Builder $query): array
    {
        $rows = (clone $query)
            ->select(['priority', 'units'])
            ->get();

        return $this->calculateDashboardFromRows($rows);
    }

    /**
     * @param Collection<int, array|object> $rows
     */
    public function calculateDashboardFromRows(Collection $rows): array
    {
        $totals = [
            'A' => 0,
            'B' => 0,
            'C' => 0,
            'D' => 0,
        ];

        foreach ($rows as $row) {
            $priority = strtoupper(trim((string) data_get($row, 'priority', '')));
            $units = (int) data_get($row, 'units', 0);

            if (!in_array($priority, self::PRIORITIES, true) || $units <= 0) {
                continue;
            }

            $totals[$priority] += $units;
        }

        $totalUnits = array_sum($totals);
        $breakdown = [];
        $isBalanced = true;

        foreach (self::TARGET_PERCENTAGES as $priority => $target) {
            $percentage = $totalUnits > 0 ? round(($totals[$priority] / $totalUnits) * 100, 2) : 0.0;
            $isInRange = abs($percentage - $target) <= self::TOLERANCE;
            $breakdown[$priority] = [
                'units' => $totals[$priority],
                'percentage' => $percentage,
                'target' => $target,
                'delta' => round($percentage - $target, 2),
                'status' => $isInRange ? 'bien' : 'mal',
            ];
            $isBalanced = $isBalanced && $isInRange;
        }

        return [
            'total_units' => $totalUnits,
            'balanced' => $isBalanced,
            'tolerance' => self::TOLERANCE,
            'breakdown' => $breakdown,
        ];
    }

    public function calculateDistributionForSeason(string $season): array
    {
        $rows = ProductionOrder::query()
            ->where('season', $season)
            ->whereNull('deleted_at')
            ->select(['priority', 'units'])
            ->get();

        return $this->calculateDashboardFromRows($rows);
    }

    private function monthNumber(string $season): int
    {
        $value = strtoupper(trim($season));

        foreach (self::MONTH_MAP as $label => $monthNumber) {
            if (preg_match('/\b' . preg_quote($label, '/') . '\b/u', $value)) {
                return $monthNumber;
            }
        }

        if (isset(self::MONTH_MAP[$value])) {
            return self::MONTH_MAP[$value];
        }

        if (preg_match('/\b(1[0-2]|0?[1-9])\b/', $value, $match) === 1) {
            $month = (int) $match[1];
            if ($month >= 1 && $month <= 12) {
                return $month;
            }
        }

        if (is_numeric($value)) {
            $month = (int) $value;
            if ($month >= 1 && $month <= 12) {
                return $month;
            }
        }

        throw new InvalidArgumentException('Temporada inválida para cálculo de fechas.');
    }

    private function yearNumber(string $season): ?int
    {
        $value = strtoupper(trim($season));

        if (preg_match('/\b(19|20)\d{2}\b/', $value, $match) === 1) {
            return (int) $match[0];
        }

        return null;
    }
}
