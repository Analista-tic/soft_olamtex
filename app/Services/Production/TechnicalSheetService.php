<?php

namespace App\Services\Production;

use App\Models\Production\ProductionOrder;
use App\Models\Production\ProductOperationList;
use App\Models\Production\TechnicalSheet;
use App\Models\Production\TechnicalSheetProcess;
use App\Models\Settings\Setting;
use App\Services\Settings\SettingService;
use Illuminate\Support\Str;

/**
 * Servicio de dominio para la Ficha Técnica (TechnicalSheet).
 *
 * Concentra toda la lógica de costos que antes vivía en TechnicalDataSheetController.
 * Principio: controlador delgado, servicio con lógica de negocio.
 */
class TechnicalSheetService
{
    // ─── Constantes de cálculo ────────────────────────────

    private const DEFAULT_COST_CUT    = 350.0;
    private const DEFAULT_COST_MINUTE = 0.0;
    private const COST_CUT_KEY        = 'cutting_cost';
    private const COST_MINUTE_KEY     = 'minute_cost';
    private const FIXED_COST_RATE     = 0.1458; // 14,58 %
    private const FIXED_PROCESS_TYPES = ['CORTE', 'CONFECCION', 'TERMINACION'];
    private const PROCESS_TYPE_ALIASES = [
        'BORDADO' => 'ESTAMPADO',
    ];
    private const DEFAULT_PROCESS_TYPES = [
        'CORTE', 'CONFECCION', 'TERMINACION',
        'ESTAMPADO', 'LAVANDERIA', 'OTRO',
    ];

    public function __construct(
        private SettingService $settingService,
        private ProductionOrderService $productionOrderService,
        private TechnicalDataSheetRollsStore $rollsStore,
        private TechnicalDataSheetThreadsStore $threadsStore,
        private TechnicalDataSheetSuppliesStore $suppliesStore,
        private TechnicalDataSheetProcessesStore $processesStore,
    ) {}

    // ─── API pública ──────────────────────────────────────

    /**
     * Calcula y retorna el resumen consolidado de costos para una OP.
     *
     * @return array{prices: array, units: ?float, costs: array, profitability: array, breakdownAvailable: array}
     */
    public function costsSummary(int $opId): array
    {
        $order = ProductionOrder::with('productSheet:id,operational_list_id,billing_price,selling_price')
            ->findOrFail($opId);

        $productSheet    = $order->productSheet;
        $priceInvoicing  = $this->toNullableNumber($productSheet?->billing_price);
        $priceSale       = $this->toNullableNumber($productSheet?->selling_price);
        $units           = $this->toNullableNumber($order->units);

        $processes       = $this->calculateProcessesForSheet($opId, $productSheet?->id);

        $rawMaterialTotal          = $this->calculateRollsTotal($opId, $units ?? 0.0)
                                   + $this->calculateThreadsTotal($opId, $units ?? 0.0);
        $rawMaterialPerUnit        = ($units !== null && $units > 0)
            ? ($rawMaterialTotal / $units)
            : 0.0;
        $rawMaterialWeightedFabric = $this->calculateRollsWeightedPrice($opId, $units ?? 0.0);
        $supplies        = $this->calculateSuppliesTotal($opId);
        $processesTotal  = $processes['total'];

        // ── Costo fijo (debe calcularse antes del total) ──
        $fixedCost = $priceInvoicing !== null
            ? $priceInvoicing * self::FIXED_COST_RATE
            : null;

        // total = fixedCost + rawMaterial + supplies + processes
        $total = ($fixedCost ?? 0.0) + $rawMaterialTotal + $supplies + $processesTotal;

        // ── Rentabilidad ──
        $olamtexValue   = null;
        $olamtexPercent = null;
        if ($priceInvoicing !== null) {
            $olamtexValue   = $priceInvoicing - $total;
            $olamtexPercent = $priceInvoicing > 0
                ? ($olamtexValue / $priceInvoicing) * 100
                : 0.0;
        }

        $clientValue   = null;
        $clientPercent = null;
        if ($priceSale !== null && $priceSale > 0 && $priceInvoicing !== null) {
            $clientValue   = $priceSale - $priceInvoicing;
            $clientPercent = ($clientValue / $priceSale) * 100;
        }

        return [
            'prices' => [
                'invoicing'          => $priceInvoicing,
                'fixedCost'          => $fixedCost !== null ? round($fixedCost, 4) : null,
                'sale'               => $priceSale,
                'historicInvoicing'  => null,
            ],
            'units' => $units,
            'costs' => [
                'rawMaterial' => round($rawMaterialTotal, 4),
                'rawMaterialPerUnit' => round($rawMaterialPerUnit, 4),
                'supplies'    => round($supplies, 4),
                'processes'   => round($processesTotal, 4),
                'cut'         => round($processes['cut'], 4),
                'confection'  => round($processes['confection'], 4),
                'finishing'   => round($processes['finishing'], 4),
                'total'       => round($total, 4),
            ],
            'profitability' => [
                'olamtex' => [
                    'value'   => $olamtexValue !== null ? round($olamtexValue, 4) : null,
                    'percent' => $olamtexPercent !== null ? round($olamtexPercent, 4) : null,
                ],
                'client' => [
                    'value'   => $clientValue !== null ? round($clientValue, 4) : null,
                    'percent' => $clientPercent !== null ? round($clientPercent, 4) : null,
                ],
            ],
            'breakdownAvailable' => [
                'rawMaterial' => $this->hasRollsData($opId) || $this->hasThreadsData($opId),
                'supplies'    => $this->hasSuppliesData($opId),
                'processes'   => ($processes['rowsCount'] ?? 0) > 0,
            ],
        ];
    }

    /**
     * Persiste los costos calculados en la TechnicalSheet.
     * Crea la ficha si no existe y actualiza sus campos financieros.
     */
    public function persistCosts(int $opId): TechnicalSheet
    {
        $summary = $this->costsSummary($opId);
        $costs   = $summary['costs'];
        $prices  = $summary['prices'];

        $sheet = TechnicalSheet::firstOrCreate(
            ['production_order_id' => $opId],
            ['status' => TechnicalSheet::STATUS_DRAFT],
        );

        $sheet->update([
            'raw_material_cost' => $costs['rawMaterial'],
            'supplies_cost'     => $costs['supplies'],
            'processes_cost'    => $costs['processes'],
            'cut_cost'          => $costs['cut'],
            'confection_cost'   => $costs['confection'],
            'finishing_cost'    => $costs['finishing'],
            'total_cost'        => $costs['total'],
            'billing_price'     => $prices['invoicing'],
            'selling_price'     => $prices['sale'],
        ]);

        return $sheet->fresh();
    }

    /**
     * Retorna el payload de procesos para una OP.
     * Si no hay procesos en BD, auto-almacena los 3 por defecto (CORTE, CONFECCION, TERMINACION)
     * con sus costos calculados para que queden fijos y no varíen en el tiempo.
     */
    public function buildProcessesPayload(int $opId, ?int $productSheetId): array
    {
        [$costConfeccion, $costTerminacion, $hasOperationalList] = $this->resolveCostsFromOperationalList($opId, $productSheetId);

        $params = [
            'cuttingCost' => round($this->settingService->getNumericParameter(self::COST_CUT_KEY, self::DEFAULT_COST_CUT), 4),
            'minuteCost'  => round($this->settingService->getNumericParameter(self::COST_MINUTE_KEY, self::DEFAULT_COST_MINUTE), 4),
        ];

        $dbRows = $this->readProcessesFromDb($opId);
        if ($dbRows !== []) {
            return [
                'hasOperationalList' => $hasOperationalList,
                'params'             => $params,
                'costConfeccion'     => round($costConfeccion, 4),
                'costTerminacion'    => round($costTerminacion, 4),
                'minutes'            => ['confeccion' => 0, 'terminacion' => 0],
                'processes'          => array_values($dbRows),
                'costsAreFixed'      => true,
            ];
        }

        $legacyRows = $this->processesStore->read($opId);
        $rows       = $this->normalizeProcessesRows($legacyRows, $this->getConfiguredProcessTypes());

        // Auto-almacenar procesos por defecto con costos fijos al primer acceso
        $this->ensureDefaultProcessesStored($opId, $productSheetId, $rows);

        $dbRows = $this->readProcessesFromDb($opId);
        if ($dbRows !== []) {
            return [
                'hasOperationalList' => $hasOperationalList,
                'params'             => $params,
                'costConfeccion'     => round($costConfeccion, 4),
                'costTerminacion'    => round($costTerminacion, 4),
                'minutes'            => ['confeccion' => 0, 'terminacion' => 0],
                'processes'          => array_values($dbRows),
                'costsAreFixed'      => true,
            ];
        }

        return [
            'hasOperationalList' => $hasOperationalList,
            'params'             => $params,
            'costConfeccion'     => round($costConfeccion, 4),
            'costTerminacion'    => round($costTerminacion, 4),
            'minutes'            => ['confeccion' => 0, 'terminacion' => 0],
            'processes'          => array_values($rows),
            'costsAreFixed'      => false,
        ];
    }

    /**
     * Almacena automáticamente los procesos (incl. CORTE, CONFECCION, TERMINACION) con costos fijos
     * si aún no existen en BD. Se ejecuta al primer acceso a la pestaña Procesos.
     */
    public function ensureDefaultProcessesStored(int $opId, ?int $productSheetId, array $rows): void
    {
        $sheet = TechnicalSheet::where('production_order_id', $opId)->first();
        if ($sheet && $sheet->processes()->exists()) {
            return;
        }

        $this->saveProcessesToDb($opId, $rows, $productSheetId);
    }

    /**
     * Upsert de un parámetro numérico de producción.
     */
    public function upsertNumericParameter(string $key, string $label, float $value): void
    {
        Setting::updateOrCreate(
            ['kind' => Setting::KIND_PARAMETER, 'key' => $key],
            ['label' => $label, 'description' => null, 'value' => $value, 'status' => true],
        );
    }

    /**
     * Obtiene los tipos de proceso configurados.
     */
    public function getConfiguredProcessTypes(): array
    {
        $configured = $this->settingService
            ->getCatalogOptions('process_types')
            ->map(fn(array $option) => $this->normalizeProcessTypeName((string) ($option['name'] ?? '')))
            ->filter(fn(string $name) => $name !== '')
            ->values()
            ->all();

        if (empty($configured)) {
            return self::DEFAULT_PROCESS_TYPES;
        }

        $merged = array_merge($configured, self::DEFAULT_PROCESS_TYPES);
        $unique = [];
        foreach ($merged as $type) {
            $normalized = $this->normalizeProcessTypeName((string) $type);
            if ($normalized === '' || in_array($normalized, $unique, true)) {
                continue;
            }
            $unique[] = $normalized;
        }

        return $unique;
    }

    /**
     * Lee procesos desde BD (technical_sheet_processes) con costos fijos.
     *
     * @return array<array{id: string, order: int, type: string, supplier: ?string, manualPrice: ?float, fixedCost: float}>
     */
    public function readProcessesFromDb(int $opId): array
    {
        $sheet = TechnicalSheet::where('production_order_id', $opId)->first();
        if (!$sheet) {
            return [];
        }

        $processes = $sheet->processes()->orderBy('sequence')->get();
        if ($processes->isEmpty()) {
            return [];
        }

        return $processes->map(fn (TechnicalSheetProcess $p) => [
            'id'          => (string) $p->id,
            'order'       => (int) $p->sequence,
            'type'        => $this->normalizeProcessTypeName((string) $p->type),
            'supplier'    => $this->nullableText($p->supplier),
            'manualPrice' => $p->manual_price !== null ? (float) $p->manual_price : null,
            'fixedCost'   => (float) $p->fixed_cost,
        ])->values()->all();
    }

    /**
     * Guarda procesos en BD con costos fijos (snapshot).
     */
    public function saveProcessesToDb(int $opId, array $rows, ?int $productSheetId): void
    {
        $payload = $this->buildProcessesPayloadBeforeSave($opId, $productSheetId);
        $normalized = $this->normalizeProcessesRows($rows, $this->getConfiguredProcessTypes());

        $sheet = TechnicalSheet::firstOrCreate(
            ['production_order_id' => $opId],
            ['status' => TechnicalSheet::STATUS_DRAFT],
        );

        $sheet->processes()->delete();

        foreach ($normalized as $row) {
            $fixedCost = $this->resolveProcessCost($row, $payload);

            TechnicalSheetProcess::create([
                'technical_sheet_id' => $sheet->id,
                'sequence'          => (int) ($row['order'] ?? 0),
                'type'              => $row['type'] ?? 'OTRO',
                'supplier'          => $row['supplier'] ?? null,
                'manual_price'      => $row['manualPrice'] ?? null,
                'fixed_cost'        => round($fixedCost, 4),
            ]);
        }
    }

    /**
     * Construye el payload para calcular costos (sin leer procesos de BD).
     */
    private function buildProcessesPayloadBeforeSave(int $opId, ?int $productSheetId): array
    {
        [$costConfeccion, $costTerminacion, $hasOperationalList] = $this->resolveCostsFromOperationalList($opId, $productSheetId);

        return [
            'hasOperationalList' => $hasOperationalList,
            'params'             => [
                'cuttingCost' => (float) $this->settingService->getNumericParameter(self::COST_CUT_KEY, self::DEFAULT_COST_CUT),
                'minuteCost'  => (float) $this->settingService->getNumericParameter(self::COST_MINUTE_KEY, self::DEFAULT_COST_MINUTE),
            ],
            'costConfeccion'    => $costConfeccion,
            'costTerminacion'   => $costTerminacion,
            'minutes'           => ['confeccion' => 0, 'terminacion' => 0],
        ];
    }

    /**
     * Normaliza las filas de procesos, asegurando tipos válidos y valores por defecto.
     */
    public function normalizeProcessesRows(array $rows, ?array $allowedTypes = null): array
    {
        $normalizedAllowedTypes = collect($allowedTypes ?? $this->getConfiguredProcessTypes())
            ->map(fn($value) => $this->normalizeProcessTypeName((string) $value))
            ->filter(fn(string $value) => $value !== '')
            ->values()
            ->all();

        if (empty($normalizedAllowedTypes)) {
            $normalizedAllowedTypes = self::DEFAULT_PROCESS_TYPES;
        }

        $normalized = [];
        foreach ($rows as $index => $row) {
            if (!is_array($row)) {
                continue;
            }

            $type = $this->normalizeProcessTypeName((string) ($row['type'] ?? 'OTRO'));
            if (!in_array($type, $normalizedAllowedTypes, true)) {
                $type = 'OTRO';
            }

            $supplier = $this->nullableText($row['supplier'] ?? null);
            if (in_array($type, self::FIXED_PROCESS_TYPES, true) && $supplier === null) {
                $supplier = 'OLAMTEX';
            }

            $manualPrice = null;
            if (!in_array($type, self::FIXED_PROCESS_TYPES, true) && is_numeric($row['manualPrice'] ?? null)) {
                $manualPrice = (float) $row['manualPrice'];
            }

            $normalized[] = [
                'id'          => trim((string) ($row['id'] ?? '')) !== '' ? trim((string) $row['id']) : (string) Str::uuid(),
                'order'       => is_numeric($row['order'] ?? null) ? (int) $row['order'] : ($index + 1),
                'type'        => $type,
                'supplier'    => $supplier,
                'manualPrice' => $manualPrice,
            ];
        }

        if (empty($normalized)) {
            $normalized = [
                ['id' => (string) Str::uuid(), 'order' => 1, 'type' => 'CORTE',       'supplier' => 'OLAMTEX', 'manualPrice' => null],
                ['id' => (string) Str::uuid(), 'order' => 2, 'type' => 'CONFECCION',   'supplier' => 'OLAMTEX', 'manualPrice' => null],
                ['id' => (string) Str::uuid(), 'order' => 3, 'type' => 'TERMINACION', 'supplier' => 'OLAMTEX', 'manualPrice' => null],
            ];
        }

        usort($normalized, fn(array $a, array $b) => ((int) $a['order']) <=> ((int) $b['order']));
        foreach ($normalized as $idx => &$row) {
            $row['order'] = $idx + 1;
        }
        unset($row);

        return $normalized;
    }

    /**
     * Calcula el costo individual de un proceso a partir de su tipo y contexto.
     */
    public function resolveProcessCost(array $row, array $payload): float
    {
        $type             = strtoupper((string) ($row['type'] ?? ''));
        $params           = $payload['params'] ?? [];
        $cuttingCost      = (float) ($params['cuttingCost'] ?? 0);
        $minuteCost       = (float) ($params['minuteCost'] ?? 0);
        $hasOperationalList = (bool) ($payload['hasOperationalList'] ?? false);
        $costConfeccion   = isset($payload['costConfeccion']) ? (float) $payload['costConfeccion'] : null;
        $costTerminacion  = isset($payload['costTerminacion']) ? (float) $payload['costTerminacion'] : null;
        $minutes          = $payload['minutes'] ?? ['confeccion' => 0, 'terminacion' => 0];

        if ($type === 'CORTE') {
            return $cuttingCost;
        }

        if ($type === 'CONFECCION') {
            if ($hasOperationalList && $costConfeccion !== null) {
                return $costConfeccion;
            }
            return (float) ($minutes['confeccion'] ?? 0) * $minuteCost;
        }

        if ($type === 'TERMINACION') {
            if ($hasOperationalList && $costTerminacion !== null) {
                return $costTerminacion;
            }
            return (float) ($minutes['terminacion'] ?? 0) * $minuteCost;
        }

        return is_numeric($row['manualPrice'] ?? null) ? (float) $row['manualPrice'] : 0.0;
    }

    // ─── Cálculos internos ────────────────────────────────

    /**
     * Calcula los costos de procesos (corte, confección, terminación) para una hoja.
     * Usa fixedCost cuando está disponible (procesos en BD); si no, recalcula.
     */
    private function normalizeProcessTypeName(string $type): string
    {
        $normalized = strtoupper(trim($type));

        return self::PROCESS_TYPE_ALIASES[$normalized] ?? $normalized;
    }

    public function calculateProcessesForSheet(int $opId, ?int $productSheetId): array
    {
        $payload = $this->buildProcessesPayload($opId, $productSheetId);
        $rows    = $payload['processes'] ?? [];
        $useFixedCost = (bool) ($payload['costsAreFixed'] ?? false);

        $cut       = 0.0;
        $confection = 0.0;
        $finishing  = 0.0;
        $total     = 0.0;

        foreach ($rows as $row) {
            $cost = $useFixedCost && isset($row['fixedCost']) && is_numeric($row['fixedCost'])
                ? (float) $row['fixedCost']
                : $this->resolveProcessCost($row, $payload);

            $total += $cost;

            match (strtoupper((string) ($row['type'] ?? ''))) {
                'CORTE'       => $cut += $cost,
                'CONFECCION'  => $confection += $cost,
                'TERMINACION' => $finishing += $cost,
                default       => null,
            };
        }

        return [
            'hasOperationalList' => (bool) ($payload['hasOperationalList'] ?? false),
            'rowsCount'          => count($rows),
            'cut'                => $cut,
            'confection'         => $confection,
            'finishing'          => $finishing,
            'total'              => $total,
        ];
    }

    public function calculateRollsTotal(int $opId, float $units): float
    {
        $state  = $this->rollsStore->read($opId);
        $params = is_array($state['params'] ?? null) ? $state['params'] : [];
        $trazo  = is_numeric($params['trazo'] ?? null) ? (float) $params['trazo'] : 0.8;
        if ($trazo <= 0.01) {
            $trazo = 0.8;
        }

        $lines = is_array($state['lines'] ?? null) ? $state['lines'] : [];
        if (empty($lines) || $units <= 0) {
            return 0.0;
        }

        $total = 0.0;
        foreach ($lines as $line) {
            $base  = is_numeric($line['consumptionBase'] ?? null) ? (float) $line['consumptionBase'] : 0.0;
            $extra = is_numeric($line['extraMeters'] ?? null) ? (float) $line['extraMeters'] : 0.0;
            $price = is_numeric($line['pricePerMeter'] ?? null) ? (float) $line['pricePerMeter'] : 0.0;
            $programmedMeters = is_numeric($line['programmedMeters'] ?? null) ? (float) $line['programmedMeters'] : null;

            if ($programmedMeters !== null) {
                $total += $programmedMeters * $price;
                continue;
            }

            $consumoAjustado = ($base / $trazo) + $extra;
            $total += $consumoAjustado * $units * $price;
        }

        return $total;
    }

    public function calculateThreadsTotal(int $opId, float $units): float
    {
        $state = $this->threadsStore->read($opId);
        $lines = is_array($state['lines'] ?? null) ? $state['lines'] : [];
        if (empty($lines) || $units <= 0) {
            return 0.0;
        }

        $total = 0.0;
        foreach ($lines as $line) {
            $costPerGarment = is_numeric($line['costPerGarment'] ?? null) ? (float) $line['costPerGarment'] : 0.0;
            $total += $costPerGarment * $units;
        }

        return $total;
    }

    public function calculateRollsWeightedPrice(int $opId, float $units = 0.0): float
    {
        $state  = $this->rollsStore->read($opId);
        $params = is_array($state['params'] ?? null) ? $state['params'] : [];
        $trazo  = is_numeric($params['trazo'] ?? null) ? (float) $params['trazo'] : 0.8;
        if ($trazo <= 0.01) {
            $trazo = 0.8;
        }

        $lines = is_array($state['lines'] ?? null) ? $state['lines'] : [];
        if (empty($lines)) {
            return 0.0;
        }

        $totalMeters = 0.0;
        $totalCost   = 0.0;

        foreach ($lines as $line) {
            $programmedMeters = is_numeric($line['programmedMeters'] ?? null) ? (float) $line['programmedMeters'] : null;
            $base             = is_numeric($line['consumptionBase'] ?? null) ? (float) $line['consumptionBase'] : 0.0;
            $extra            = is_numeric($line['extraMeters'] ?? null) ? (float) $line['extraMeters'] : 0.0;
            $price  = is_numeric($line['pricePerMeter'] ?? null) ? (float) $line['pricePerMeter'] : 0.0;

            $meters = 0.0;
            if ($programmedMeters !== null) {
                $meters = $programmedMeters;
            } elseif ($units > 0) {
                $consumoAjustado = ($base / $trazo) + $extra;
                $meters = $consumoAjustado * $units;
            }

            if ($meters <= 0 || $price <= 0) {
                continue;
            }

            $totalMeters += $meters;
            $totalCost   += $meters * $price;
        }

        return $totalMeters > 0 ? ($totalCost / $totalMeters) : 0.0;
    }

    public function calculateSuppliesTotal(int $opId): float
    {
        $items = $this->suppliesStore->read($opId);
        if (empty($items)) {
            return 0.0;
        }

        $total = 0.0;
        foreach ($items as $item) {
            $qtyPerUnit = is_numeric($item['qtyPerUnit'] ?? null) ? (float) $item['qtyPerUnit'] : 0.0;
            $unitCost   = is_numeric($item['unitCost'] ?? null) ? (float) $item['unitCost'] : 0.0;
            $total += $qtyPerUnit * $unitCost;
        }

        return $total;
    }

    // ─── Helpers internos ─────────────────────────────────

    /**
     * Resuelve costos de Confección y Terminación desde el snapshot operacional.
     *
     * @return array{0: float, 1: float, 2: bool} [costConfeccion, costTerminacion, hasOperationalList]
     */
    private function resolveCostsFromOperationalList(int $opId, ?int $productSheetId): array
    {
        $operations    = $this->productionOrderService->getOperationsByOrder($opId);
        $operationCost = $this->settingService->getNumericParameter('operation_cost', 406.0);
        $yarnCost      = $this->settingService->getNumericParameter('yarn_cost', 0);

        if ($operations->isEmpty()) {
            [$minsConf, $minsTerm] = $this->resolveOperationalMinutes($productSheetId, (bool) $productSheetId);
            $costConf = $minsConf * $operationCost + $yarnCost;
            $costTerm = $minsTerm * $operationCost;

            return [max(0, $costConf), max(0, $costTerm), (bool) $productSheetId];
        }

        $costConfeccion  = 0.0;
        $costTerminacion = 0.0;

        foreach ($operations as $op) {
            $samOp  = (float) ($op->sam_total_operacion ?? 0);
            $costOp = $samOp * $operationCost;

            $section = $op->operation?->section;
            $bucket  = $this->resolveProcessBucket($section?->type, $section?->name);

            if ($bucket === 'confection') {
                $costConfeccion += $costOp;
            } elseif ($bucket === 'finishing') {
                $costTerminacion += $costOp;
            }
        }

        $costConfeccion += $yarnCost;

        return [max(0, $costConfeccion), max(0, $costTerminacion), true];
    }

    private function resolveOperationalMinutes(?int $productSheetId, bool $hasOperationalList): array
    {
        $minutesConfeccion  = 0.0;
        $minutesTerminacion = 0.0;

        if (!$hasOperationalList || !$productSheetId) {
            return [$minutesConfeccion, $minutesTerminacion];
        }

        $rows = ProductOperationList::query()
            ->with(['operation.section:id,name,type'])
            ->where('product_sheet_id', $productSheetId)
            ->get();

        foreach ($rows as $row) {
            $minutes = (float) ($row->optimal_time ?? 0);
            if ($minutes <= 0) {
                continue;
            }

            $bucket = $this->resolveProcessBucket($row->operation?->section?->type, $row->operation?->section?->name);
            if ($bucket === 'confection') {
                $minutesConfeccion += $minutes;
            } elseif ($bucket === 'finishing') {
                $minutesTerminacion += $minutes;
            }
        }

        return [$minutesConfeccion, $minutesTerminacion];
    }

    private function resolveProcessBucket(?string $sectionType, ?string $sectionName): ?string
    {
        $type  = $this->normalizeText($sectionType ?? '');
        $name  = $this->normalizeText($sectionName ?? '');
        $value = trim("{$type} {$name}");

        if (str_contains($value, 'confeccion')) {
            return 'confection';
        }
        if (str_contains($value, 'terminacion') || str_contains($value, 'presentacion')) {
            return 'finishing';
        }

        return null;
    }

    private function normalizeText(string $value): string
    {
        $normalized   = mb_strtolower(trim($value));
        $replacements = [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n',
        ];

        return strtr($normalized, $replacements);
    }

    private function hasRollsData(int $opId): bool
    {
        $state = $this->rollsStore->read($opId);
        return count($state['lines'] ?? []) > 0;
    }

    private function hasThreadsData(int $opId): bool
    {
        $state = $this->threadsStore->read($opId);
        return count($state['lines'] ?? []) > 0;
    }

    private function hasSuppliesData(int $opId): bool
    {
        return count($this->suppliesStore->read($opId)) > 0;
    }

    private function nullableText(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }
        $text = trim($value);
        return $text !== '' ? $text : null;
    }

    private function toNullableNumber(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        return is_numeric($value) ? (float) $value : null;
    }
}
