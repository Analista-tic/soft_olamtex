<?php

namespace App\Http\Controllers\Production;

use App\Helpers\MonthLabel;
use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\ProductSheet\ProductOperationList;
use App\Models\ProductSheet\Programmer;
use App\Services\Production\TechnicalDataSheetRollsStore;
use App\Services\Production\TechnicalDataSheetProcessesStore;
use App\Services\Production\TechnicalDataSheetSuppliesStore;
use App\Services\Production\TechnicalDataSheetThreadsStore;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use App\Services\Settings\SettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class TechnicalDataSheetController extends Controller
{
    private const DEFAULT_COST_CUT = 350.0;
    private const DEFAULT_COST_MINUTE = 0.0;
    private const COST_CUT_KEY = 'cutting_cost';
    private const COST_MINUTE_KEY = 'minute_cost';

    public function __construct(
        private SettingService $settingService,
        private TechnicalDataSheetSuppliesStore $suppliesStore,
        private TechnicalDataSheetRollsStore $rollsStore,
        private TechnicalDataSheetThreadsStore $threadsStore,
        private TechnicalDataSheetProcessesStore $processesStore,
    ) {
    }

    /**
     * Muestra la vista principal de Ficha Técnica.
     */
    public function index(Request $request): Response
    {
        return Inertia::render('Production/TechnicalDataSheet/Index', [
            'initialOpId' => $request->query('op_id'),
        ]);
    }

    /**
     * Retorna las opciones de OP disponibles para el selector.
     * Formato: [{ id, name, groupLabel }]
     */
    public function options(Request $request): JsonResponse
    {
        $search = $request->query('search', '');

        $query = Programmer::query()
            ->with('productSheet:id,sample_reference,product_reference')
            ->whereNotNull('op_code')
            ->orderByDesc('created_at')
            ->limit(50);

        if ($search) {
            $searchTerm = '%' . $search . '%';
            $query->where(function ($q) use ($searchTerm) {
                $q->where('op_code', 'like', $searchTerm)
                    ->orWhereHas('productSheet', function ($ps) use ($searchTerm) {
                        $ps->where('sample_reference', 'like', $searchTerm)
                            ->orWhere('product_reference', 'like', $searchTerm);
                    });
            });
        }

        $programmers = $query->get();

        $options = $programmers->map(function (Programmer $programmer) {
            $opCode = $programmer->op_code ?? '';
            $reference = $this->buildReference($programmer);
            $type = $this->extractOpType($opCode);
            $groupLabel = in_array($type, ['MU', 'MUT']) ? 'Muestras' : 'Productos';
            $priority = $this->nullableText($programmer->priority);

            return [
                'id' => $programmer->id,
                'name' => "{$opCode} - {$reference}",
                'groupLabel' => $groupLabel,
                'op_code' => $opCode,
                'reference_label' => $reference,
                'priority' => $priority,
            ];
        });

        return response()->json($options->values());
    }

    /**
     * Retorna opciones de parámetros de settings por llaves.
     * Formato: { data: { key: [{ id, name }] } }
     */
    public function settingsParameters(Request $request): JsonResponse
    {
        $keys = $request->query('keys', []);
        if (!is_array($keys)) {
            $keys = [$keys];
        }

        $data = [];
        foreach ($keys as $rawKey) {
            if (!is_string($rawKey)) {
                continue;
            }

            $key = trim($rawKey);
            if ($key === '') {
                continue;
            }

            $canonicalKey = $this->settingService->canonicalKey($key);
            if (array_key_exists($canonicalKey, $data)) {
                continue;
            }

            $data[$canonicalKey] = $this->settingService
                ->getCatalogOptions($canonicalKey)
                ->values()
                ->all();
        }

        return response()->json(['data' => $data]);
    }

    /**
     * Retorna la información básica de una OP específica.
     * Reutiliza la misma lógica del módulo Órdenes de Producción.
     */
    public function basicInfo(int $opId): JsonResponse
    {
        $programmer = Programmer::with([
            'productSheet:id,type,sample_reference,product_reference,section_id,group_id,world_id,brand_id,fabric_base_id,yarn_base_id,billing_price,selling_price,sizes,description,status,flat_sketch_path,created_at',
        ])->findOrFail($opId);

        $destinations = $this->settingService->getCatalogOptions('destinations')->keyBy('id');
        $sections = $this->settingService->getCatalogOptions('sections')->keyBy('id');
        $groups = $this->settingService->getCatalogOptions('groups')->keyBy('id');
        $brands = $this->settingService->getCatalogOptions('brands')->keyBy('id');
        $worlds = $this->settingService->getCatalogOptions('worlds')->keyBy('id');

        $destination = $destinations->get($programmer->destination_id);
        $productSheet = $programmer->productSheet;

        $section = null;
        $group = null;
        $brand = null;
        $world = null;

        if ($productSheet) {
            $section = $productSheet->section_id
                ? $sections->get($productSheet->section_id)
                : null;
            $group = $productSheet->group_id
                ? $groups->get($productSheet->group_id)
                : null;
            $brand = $productSheet->brand_id
                ? $brands->get($productSheet->brand_id)
                : null;
            $world = $productSheet->world_id
                ? $worlds->get($productSheet->world_id)
                : null;
        }

        $season = $programmer->season;
        $seasonDisplay = is_numeric($season)
            ? MonthLabel::fromNumber((int) $season)
            : $season;

        $priority = $this->nullableText($programmer->priority);
        $flatDrawingPath = $productSheet?->flat_sketch_path;
        $flatDrawingUrl = null;

        if (is_string($flatDrawingPath) && trim($flatDrawingPath) !== '') {
            $path = trim($flatDrawingPath);
            if (preg_match('/^https?:\/\//i', $path)) {
                $flatDrawingUrl = $path;
            } else {
                $flatDrawingUrl = Storage::url(ltrim($path, '/'));
            }
        }

        return response()->json([
            'id' => $programmer->id,
            'opCode' => $programmer->op_code,
            'reference' => $this->buildReference($programmer),
            'units' => $programmer->units !== null ? (int) $programmer->units : null,
            'date' => $productSheet?->created_at?->toDateString(),
            'flatDrawingUrl' => $flatDrawingUrl,
            'destination' => $destination['name'] ?? null,
            'section' => $section['name'] ?? null,
            'group' => $group['name'] ?? null,
            'priority' => $priority,
            'brand' => $brand['name'] ?? null,
            'world' => $world['name'] ?? null,
            'season' => $seasonDisplay,
        ]);
    }

    /**
     * Retorna el listado operacional asignado a la OP seleccionada.
     */
    public function operationalList(int $opId): JsonResponse
    {
        $programmer = Programmer::with('productSheet:id,operational_list_id')
            ->findOrFail($opId);

        $productSheet = $programmer->productSheet;
        if (!$productSheet || !$productSheet->operational_list_id) {
            return response()->json([
                'hasList' => false,
                'list' => null,
            ]);
        }

        $catalogs = $this->settingService->getCatalogOptions('operational_lists');
        $assignedList = $this->findCatalogById($catalogs, $productSheet->operational_list_id);

        $rows = ProductOperationList::query()
            ->with(['operation:id,action,garment_part', 'operationSection:id,name,type'])
            ->where('product_sheet_id', $productSheet->id)
            ->orderBy('sequence_order')
            ->get();

        return response()->json([
            'hasList' => true,
            'list' => [
                'id' => $productSheet->operational_list_id,
                'name' => $assignedList['name'] ?? null,
                'reference' => isset($assignedList['id']) ? (string) $assignedList['id'] : null,
                'items' => $rows->map(function (ProductOperationList $row) {
                    $action = trim((string) ($row->operation?->action ?? ''));
                    $garmentPart = trim((string) ($row->operation?->garment_part ?? ''));
                    $operationName = trim("{$action} {$garmentPart}");
                    $sectionType = $row->operationSection?->type;
                    $sectionName = $row->operationSection?->name;

                    return [
                        'order' => $row->sequence_order,
                        'process' => $operationName !== '' ? $operationName : null,
                        'description' => $row->notes ?: null,
                        'minutes' => $row->optimal_time !== null ? (float) $row->optimal_time : null,
                        'type' => $sectionType ?: $sectionName,
                    ];
                })->values(),
            ],
        ]);
    }

    /**
     * Retorna opciones de listados operacionales para selección.
     * Formato: [{ value, label }]
     */
    public function operationalListOptions(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));

        $options = $this->settingService
            ->getCatalogOptions('operational_lists')
            ->filter(function (array $option) use ($search) {
                if ($search === '') {
                    return true;
                }

                $id = mb_strtolower((string) ($option['id'] ?? ''));
                $name = mb_strtolower((string) ($option['name'] ?? ''));
                $term = mb_strtolower($search);

                return str_contains($id, $term) || str_contains($name, $term);
            })
            ->take(50)
            ->map(function (array $option) {
                $id = $option['id'] ?? null;
                $name = (string) ($option['name'] ?? '');
                $reference = (string) $id;

                return [
                    'value' => $id,
                    'label' => trim("{$reference} - {$name}"),
                ];
            })
            ->values();

        return response()->json($options);
    }

    /**
     * Asigna un listado operacional existente a la OP.
     */
    public function assignOperationalList(Request $request, int $opId): JsonResponse
    {
        $validated = $request->validate([
            'list_id' => ['required', 'integer'],
        ]);

        $programmer = Programmer::with('productSheet')->findOrFail($opId);
        $productSheet = $programmer->productSheet;

        if (!$productSheet) {
            return response()->json([
                'message' => 'La OP no tiene sábana de producto asociada.',
            ], 422);
        }

        $catalogs = $this->settingService->getCatalogOptions('operational_lists');
        $assignedList = $this->findCatalogById($catalogs, $validated['list_id']);
        if (!$assignedList) {
            return response()->json([
                'message' => 'El listado operacional seleccionado no existe.',
            ], 422);
        }

        $productSheet->update([
            'operational_list_id' => (int) $validated['list_id'],
        ]);

        return response()->json([
            'ok' => true,
        ]);
    }

    /**
     * Retorna los procesos configurados por OP, parámetros y minutos base.
     */
    public function processesSummary(int $opId): JsonResponse
    {
        $programmer = Programmer::with('productSheet:id,operational_list_id')
            ->findOrFail($opId);

        $productSheet = $programmer->productSheet;
        $processes = $this->buildProcessesPayload($opId, $productSheet?->id, (bool) ($productSheet?->operational_list_id));

        return response()->json($processes);
    }

    /**
     * Guarda los procesos configurados por OP.
     */
    public function saveProcesses(Request $request, int $opId): JsonResponse
    {
        $programmer = Programmer::with('productSheet:id,operational_list_id')
            ->findOrFail($opId);

        $validated = $request->validate([
            'processes' => ['required', 'array'],
            'processes.*.id' => ['nullable', 'string', 'max:100'],
            'processes.*.order' => ['required', 'integer', 'min:1'],
            'processes.*.type' => ['required', 'in:CORTE,CONFECCION,TERMINACION,ESTAMPADO,LAVANDERIA,OTRO'],
            'processes.*.supplier' => ['nullable', 'string', 'max:150'],
            'processes.*.manualPrice' => ['nullable', 'numeric', 'min:0'],
        ]);

        $normalized = $this->normalizeProcessesRows($validated['processes']);
        $this->processesStore->write($opId, $normalized);

        $productSheet = $programmer->productSheet;
        $payload = $this->buildProcessesPayload($opId, $productSheet?->id, (bool) ($productSheet?->operational_list_id));

        return response()->json($payload);
    }

    /**
     * Actualiza parámetros de procesos.
     */
    public function updateProcessParameters(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'costCut' => ['required', 'numeric', 'min:0'],
            'costMinute' => ['required', 'numeric', 'min:0'],
        ]);

        $costCut = (float) $validated['costCut'];
        $costMinute = (float) $validated['costMinute'];

        $this->upsertNumericParameter(self::COST_CUT_KEY, 'Costo corte (producción)', $costCut);
        $this->upsertNumericParameter(self::COST_MINUTE_KEY, 'Costo minuto (producción)', $costMinute);

        return response()->json([
            'ok' => true,
            'parameters' => [
                'costCut' => $costCut,
                'costMinute' => $costMinute,
            ],
        ]);
    }

    /**
     * Retorna el resumen consolidado de costos por OP.
     */
    public function costsSummary(int $opId): JsonResponse
    {
        $programmer = Programmer::with('productSheet:id,operational_list_id,billing_price,selling_price')
            ->findOrFail($opId);

        $productSheet = $programmer->productSheet;
        $priceInvoicing = $this->toNullableNumber($productSheet?->billing_price);
        $priceSale = $this->toNullableNumber($productSheet?->selling_price);
        $units = $this->toNullableNumber($programmer->units);

        $processes = $this->calculateProcessesForSheet($opId, $productSheet?->id, (bool) ($productSheet?->operational_list_id));

        $rawMaterial = $this->calculateRollsTotal($opId, $units ?? 0.0)
            + $this->calculateThreadsTotal($opId, $units ?? 0.0);
        $supplies = $this->calculateSuppliesTotal($opId);
        $processesTotal = $processes['total'];
        $total = $rawMaterial + $supplies + $processesTotal;

        $olamtexValue = null;
        $olamtexPercent = null;
        if ($priceInvoicing !== null && $priceInvoicing > 0) {
            $olamtexValue = $priceInvoicing - $total;
            $olamtexPercent = ($olamtexValue / $priceInvoicing) * 100;
        }

        $clientValue = null;
        $clientPercent = null;
        if ($priceSale !== null && $priceSale > 0 && $priceInvoicing !== null) {
            $clientValue = $priceSale - $priceInvoicing;
            $clientPercent = ($clientValue / $priceSale) * 100;
        }

        return response()->json([
            'prices' => [
                'invoicing' => $priceInvoicing,
                'sale' => $priceSale,
                'historicInvoicing' => null,
            ],
            'units' => $units,
            'costs' => [
                'rawMaterial' => round($rawMaterial, 4),
                'supplies' => round($supplies, 4),
                'processes' => round($processesTotal, 4),
                'cut' => round($processes['cut'], 4),
                'confection' => round($processes['confection'], 4),
                'finishing' => round($processes['finishing'], 4),
                'total' => round($total, 4),
            ],
            'profitability' => [
                'olamtex' => [
                    'value' => $olamtexValue !== null ? round($olamtexValue, 4) : null,
                    'percent' => $olamtexPercent !== null ? round($olamtexPercent, 4) : null,
                ],
                'client' => [
                    'value' => $clientValue !== null ? round($clientValue, 4) : null,
                    'percent' => $clientPercent !== null ? round($clientPercent, 4) : null,
                ],
            ],
            'breakdownAvailable' => [
                'rawMaterial' => $this->hasRollsData($opId) || $this->hasThreadsData($opId),
                'supplies' => $this->hasSuppliesData($opId),
                'processes' => $processes['hasOperationalList'],
            ],
        ]);
    }

    /**
     * Construye la referencia a partir del product_sheet.
     */
    private function buildReference(Programmer $programmer): string
    {
        $sampleRef = trim($programmer->productSheet?->sample_reference ?? '');
        $productRef = trim($programmer->productSheet?->product_reference ?? '');

        if ($sampleRef && $productRef) {
            return $sampleRef === $productRef ? $sampleRef : "{$sampleRef} - {$productRef}";
        }
        if ($sampleRef) {
            return $sampleRef;
        }
        if ($productRef) {
            return $productRef;
        }

        return 'Sin referencia';
    }

    /**
     * Extrae el tipo de OP desde el cÃ³digo.
     */
    private function extractOpType(string $opCode): string
    {
        $code = strtoupper(trim($opCode));

        if (str_starts_with($code, 'MUT')) {
            return 'MUT';
        }
        if (str_starts_with($code, 'MU')) {
            return 'MU';
        }
        if (str_starts_with($code, 'MFT')) {
            return 'MFT';
        }
        if (str_starts_with($code, 'MF')) {
            return 'MF';
        }

        return 'MF';
    }

    private function findCatalogById(EloquentCollection|SupportCollection $catalogs, mixed $id): ?array
    {
        return $catalogs->first(function (array $item) use ($id) {
            return (string) ($item['id'] ?? '') === (string) $id;
        });
    }

    private function getNumericParameterValue(string $key, float $default): float
    {
        $setting = Setting::parameters()
            ->where('key', $key)
            ->where('status', true)
            ->first();

        if (!$setting) {
            return $default;
        }

        $raw = $setting->getAttribute('value');
        if (is_array($raw)) {
            $raw = $raw['value'] ?? $raw[0] ?? null;
        }

        if (is_numeric($raw)) {
            return (float) $raw;
        }

        return $default;
    }

    private function upsertNumericParameter(string $key, string $label, float $value): void
    {
        Setting::updateOrCreate(
            [
                'kind' => Setting::KIND_PARAMETER,
                'key' => $key,
            ],
            [
                'label' => $label,
                'description' => null,
                'value' => $value,
                'status' => true,
            ],
        );
    }

    private function resolveProcessBucket(?string $sectionType, ?string $sectionName): ?string
    {
        $type = $this->normalizeText($sectionType ?? '');
        $name = $this->normalizeText($sectionName ?? '');
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
        $normalized = mb_strtolower(trim($value));
        $replacements = [
            'Ã¡' => 'a',
            'Ã©' => 'e',
            'Ã­' => 'i',
            'Ã³' => 'o',
            'Ãº' => 'u',
            'Ã±' => 'n',
        ];

        return strtr($normalized, $replacements);
    }

    private function buildProcessesPayload(int $opId, ?int $productSheetId, bool $hasOperationalList): array
    {
        [$minutesConfeccion, $minutesTerminacion] = $this->resolveOperationalMinutes($productSheetId, $hasOperationalList);

        $params = [
            'cuttingCost' => round($this->getNumericParameterValue(self::COST_CUT_KEY, self::DEFAULT_COST_CUT), 4),
            'minuteCost' => round($this->getNumericParameterValue(self::COST_MINUTE_KEY, self::DEFAULT_COST_MINUTE), 4),
        ];

        $rows = $this->normalizeProcessesRows($this->processesStore->read($opId));

        return [
            'hasOperationalList' => $hasOperationalList,
            'params' => $params,
            'minutes' => [
                'confeccion' => round($minutesConfeccion, 4),
                'terminacion' => round($minutesTerminacion, 4),
            ],
            'processes' => array_values($rows),
        ];
    }

    private function resolveOperationalMinutes(?int $productSheetId, bool $hasOperationalList): array
    {
        $minutesConfeccion = 0.0;
        $minutesTerminacion = 0.0;

        if (!$hasOperationalList || !$productSheetId) {
            return [$minutesConfeccion, $minutesTerminacion];
        }

        $rows = ProductOperationList::query()
            ->with(['operationSection:id,name,type'])
            ->where('product_sheet_id', $productSheetId)
            ->get();

        foreach ($rows as $row) {
            $minutes = (float) ($row->optimal_time ?? 0);
            if ($minutes <= 0) {
                continue;
            }

            $bucket = $this->resolveProcessBucket($row->operationSection?->type, $row->operationSection?->name);
            if ($bucket === 'confection') {
                $minutesConfeccion += $minutes;
            } elseif ($bucket === 'finishing') {
                $minutesTerminacion += $minutes;
            }
        }

        return [$minutesConfeccion, $minutesTerminacion];
    }

    private function normalizeProcessesRows(array $rows): array
    {
        $allowedTypes = ['CORTE', 'CONFECCION', 'TERMINACION', 'ESTAMPADO', 'LAVANDERIA', 'OTRO'];
        $normalized = [];

        foreach ($rows as $index => $row) {
            if (!is_array($row)) {
                continue;
            }

            $type = strtoupper(trim((string) ($row['type'] ?? 'OTRO')));
            if (!in_array($type, $allowedTypes, true)) {
                $type = 'OTRO';
            }

            $supplier = $this->nullableText($row['supplier'] ?? null);
            if (in_array($type, ['CORTE', 'CONFECCION', 'TERMINACION'], true) && $supplier === null) {
                $supplier = 'OLAMTEX';
            }

            $manualPrice = null;
            if (!in_array($type, ['CORTE', 'CONFECCION', 'TERMINACION'], true) && is_numeric($row['manualPrice'] ?? null)) {
                $manualPrice = (float) $row['manualPrice'];
            }

            $normalized[] = [
                'id' => trim((string) ($row['id'] ?? '')) !== '' ? trim((string) $row['id']) : (string) Str::uuid(),
                'order' => is_numeric($row['order'] ?? null) ? (int) $row['order'] : ($index + 1),
                'type' => $type,
                'supplier' => $supplier,
                'manualPrice' => $manualPrice,
            ];
        }

        if (empty($normalized)) {
            $normalized = [
                ['id' => (string) Str::uuid(), 'order' => 1, 'type' => 'CORTE', 'supplier' => 'OLAMTEX', 'manualPrice' => null],
                ['id' => (string) Str::uuid(), 'order' => 2, 'type' => 'CONFECCION', 'supplier' => 'OLAMTEX', 'manualPrice' => null],
                ['id' => (string) Str::uuid(), 'order' => 3, 'type' => 'TERMINACION', 'supplier' => 'OLAMTEX', 'manualPrice' => null],
            ];
        }

        usort($normalized, fn (array $a, array $b) => ((int) $a['order']) <=> ((int) $b['order']));
        foreach ($normalized as $idx => &$row) {
            $row['order'] = $idx + 1;
        }
        unset($row);

        return $normalized;
    }

    private function resolveProcessCost(array $row, array $params, array $minutes, bool $hasOperationalList): float
    {
        $type = strtoupper((string) ($row['type'] ?? ''));
        $cuttingCost = (float) ($params['cuttingCost'] ?? 0);
        $minuteCost = (float) ($params['minuteCost'] ?? 0);

        if ($type === 'CORTE') {
            return $cuttingCost;
        }

        if ($type === 'CONFECCION') {
            $confeccion = $hasOperationalList ? (float) ($minutes['confeccion'] ?? 0) : 0.0;
            return $confeccion * $minuteCost;
        }

        if ($type === 'TERMINACION') {
            $terminacion = $hasOperationalList ? (float) ($minutes['terminacion'] ?? 0) : 0.0;
            return $terminacion * $minuteCost;
        }

        return is_numeric($row['manualPrice'] ?? null) ? (float) $row['manualPrice'] : 0.0;
    }

    private function nullableText(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $text = trim($value);
        return $text !== '' ? $text : null;
    }

    private function calculateProcessesForSheet(int $opId, ?int $productSheetId, bool $hasOperationalList): array
    {
        $payload = $this->buildProcessesPayload($opId, $productSheetId, $hasOperationalList);
        $rows = $payload['processes'] ?? [];

        $cut = 0.0;
        $confection = 0.0;
        $finishing = 0.0;
        $total = 0.0;

        foreach ($rows as $row) {
            $cost = $this->resolveProcessCost($row, $payload['params'], $payload['minutes'], $payload['hasOperationalList']);
            $total += $cost;

            if (($row['type'] ?? '') === 'CORTE') {
                $cut += $cost;
            } elseif (($row['type'] ?? '') === 'CONFECCION') {
                $confection += $cost;
            } elseif (($row['type'] ?? '') === 'TERMINACION') {
                $finishing += $cost;
            }
        }

        return [
            'hasOperationalList' => (bool) ($payload['hasOperationalList'] ?? false),
            'cut' => $cut,
            'confection' => $confection,
            'finishing' => $finishing,
            'total' => $total,
        ];
    }

    private function toNullableNumber(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (float) $value : null;
    }

    private function calculateSuppliesTotal(int $opId): float
    {
        $items = $this->suppliesStore->read($opId);
        if (empty($items)) {
            return 0.0;
        }

        $total = 0.0;
        foreach ($items as $item) {
            $qtyPerUnit = is_numeric($item['qtyPerUnit'] ?? null) ? (float) $item['qtyPerUnit'] : 0.0;
            $unitCost = is_numeric($item['unitCost'] ?? null) ? (float) $item['unitCost'] : 0.0;
            // En Costos se usa el costo prenda directo de la tabla de Insumos: cantidad * precio.
            $total += $qtyPerUnit * $unitCost;
        }

        return $total;
    }

    private function hasSuppliesData(int $opId): bool
    {
        return count($this->suppliesStore->read($opId)) > 0;
    }

    private function calculateRollsTotal(int $opId, float $units): float
    {
        $state = $this->rollsStore->read($opId);
        $params = is_array($state['params'] ?? null) ? $state['params'] : [];
        $trazo = is_numeric($params['trazo'] ?? null) ? (float) $params['trazo'] : 0.8;
        if ($trazo <= 0.01) {
            $trazo = 0.8;
        }

        $lines = is_array($state['lines'] ?? null) ? $state['lines'] : [];
        if (empty($lines) || $units <= 0) {
            return 0.0;
        }

        $total = 0.0;
        foreach ($lines as $line) {
            $base = is_numeric($line['consumptionBase'] ?? null) ? (float) $line['consumptionBase'] : 0.0;
            $extra = is_numeric($line['extraMeters'] ?? null) ? (float) $line['extraMeters'] : 0.0;
            $price = is_numeric($line['pricePerMeter'] ?? null) ? (float) $line['pricePerMeter'] : 0.0;
            $programmedMeters = is_numeric($line['programmedMeters'] ?? null) ? (float) $line['programmedMeters'] : null;

            if ($programmedMeters !== null) {
                $total += $programmedMeters * $price;
                continue;
            }

            $consumoAjustado = ($base / $trazo) + $extra;
            $metros = $consumoAjustado * $units;
            $total += $metros * $price;
        }

        return $total;
    }

    private function hasRollsData(int $opId): bool
    {
        $state = $this->rollsStore->read($opId);
        return count($state['lines'] ?? []) > 0;
    }

    private function calculateThreadsTotal(int $opId, float $units): float
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

    private function hasThreadsData(int $opId): bool
    {
        $state = $this->threadsStore->read($opId);
        return count($state['lines'] ?? []) > 0;
    }
}

