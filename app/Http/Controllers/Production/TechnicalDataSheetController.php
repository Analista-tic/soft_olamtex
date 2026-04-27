<?php

namespace App\Http\Controllers\Production;

use App\Helpers\MonthLabel;
use App\Http\Controllers\Controller;
use App\Models\Production\ProductionOrder;
use App\Models\Production\ProductOperationList;
use App\Services\Production\OperationSectionService;
use App\Services\Production\ProductionOrderService;
use App\Services\Production\TechnicalDataSheetPdfService;
use App\Services\Production\TechnicalDataSheetProcessesStore;
use App\Services\Production\TechnicalDataSheetRawMaterialsStore;
use App\Services\Production\TechnicalSheetService;
use App\Services\Settings\SettingService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Controlador de Ficha Técnica de Orden de Producción.
 *
 * Delega toda la lógica de costos y procesos a TechnicalSheetService.
 * Mantiene solo responsabilidades HTTP (validación, serialización, respuestas).
 */
class TechnicalDataSheetController extends Controller
{
    private const COST_CUT_KEY    = 'cutting_cost';
    private const COST_MINUTE_KEY = 'minute_cost';
    private const FIXED_PROCESS_TYPES = ['CORTE', 'CONFECCION', 'TERMINACION'];

    public function __construct(
        private SettingService $settingService,
        private OperationSectionService $sectionService,
        private ProductionOrderService $productionOrderService,
        private TechnicalSheetService $technicalSheetService,
        private TechnicalDataSheetProcessesStore $processesStore,
        private TechnicalDataSheetRawMaterialsStore $rawMaterialsStore,
        private TechnicalDataSheetPdfService $pdfService,
    ) {}

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
     */
    public function options(Request $request): JsonResponse
    {
        $search = $request->query('search', '');

        $query = ProductionOrder::query()
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

        $orders = $query->get();

        $options = $orders->map(function (ProductionOrder $order) {
            $opCode     = $order->op_code ?? '';
            $reference  = $this->buildReference($order);
            $type       = $this->extractOpType($opCode);
            $groupLabel = in_array($type, ['MU', 'MUT']) ? 'Muestras' : 'Productos';
            $priority   = $this->nullableText($order->priority);

            return [
                'id'              => $order->id,
                'name'            => "{$opCode} - {$reference}",
                'groupLabel'      => $groupLabel,
                'op_code'         => $opCode,
                'reference_label' => $reference,
                'priority'        => $priority,
            ];
        });

        return response()->json($options->values());
    }

    /**
     * Retorna opciones de parámetros de settings por llaves.
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

            $data[$canonicalKey] = $canonicalKey === 'fabric_colors'
                ? $this->settingService->getCatalogOptionsWithCode($canonicalKey)->values()->all()
                : $this->settingService->getCatalogOptions($canonicalKey)->values()->all();
        }

        return response()->json(['data' => $data]);
    }

    /**
     * Retorna la información básica de una OP específica.
     */
    public function basicInfo(int $opId): JsonResponse
    {
        $order = ProductionOrder::with([
            'productSheet:id,type,sample_reference,product_reference,section_id,group_id,world_id,brand_id,fabric_base_id,yarn_base_id,billing_price,selling_price,sizes,description,status,flat_sketch_path,created_at',
        ])->findOrFail($opId);

        $destinations = $this->settingService->getCatalogOptions('destinations')->keyBy('id');
        $sections     = $this->settingService->getCatalogOptions('sections')->keyBy('id');
        $groups       = $this->settingService->getCatalogOptions('groups')->keyBy('id');
        $brands       = $this->settingService->getCatalogOptions('brands')->keyBy('id');
        $worlds       = $this->settingService->getCatalogOptions('worlds')->keyBy('id');
        $fabricBases  = $this->settingService->getCatalogOptions('fabric_bases')->keyBy('id');
        $yarnBases    = $this->settingService->getCatalogOptions('yarn_bases')->keyBy('id');

        $destination  = $destinations->get($order->destination_id);
        $productSheet = $order->productSheet;

        $section = $group = $brand = $world = $base = $baseYarn = null;

        if ($productSheet) {
            $section  = $productSheet->section_id   ? $sections->get($productSheet->section_id)       : null;
            $group    = $productSheet->group_id     ? $groups->get($productSheet->group_id)           : null;
            $brand    = $productSheet->brand_id     ? $brands->get($productSheet->brand_id)           : null;
            $world    = $productSheet->world_id     ? $worlds->get($productSheet->world_id)           : null;
            $base     = $productSheet->fabric_base_id ? $fabricBases->get($productSheet->fabric_base_id) : null;
            $baseYarn = $productSheet->yarn_base_id ? $yarnBases->get($productSheet->yarn_base_id)     : null;
        }

        $season        = $order->season;
        $seasonDisplay = is_numeric($season) ? MonthLabel::fromNumber((int) $season) : $season;
        $priority      = $this->nullableText($order->priority);
        $rawPath       = $productSheet?->flat_sketch_path;
        $flatDrawingUrl = $this->resolveFlatDrawingUrl($rawPath);

        return response()->json([
            'id'               => $order->id,
            'opCode'           => $order->op_code,
            'reference'        => $this->buildReference($order),
            'units'            => $order->units !== null ? (int) $order->units : null,
            'date'             => $productSheet?->created_at?->toDateString(),
            'flatDrawingUrl'   => $flatDrawingUrl,
            'flat_sketch_path' => $flatDrawingUrl ? null : $rawPath,
            'destination'      => $destination['name'] ?? null,
            'section'          => $section['name'] ?? null,
            'group'            => $group['name'] ?? null,
            'priority'         => $priority,
            'brand'            => $brand['name'] ?? null,
            'world'            => $world['name'] ?? null,
            'base'             => $base['name'] ?? null,
            'baseYarn'         => $baseYarn['name'] ?? null,
            'season'           => $seasonDisplay,
            'sizes'            => $this->normalizeSizes($productSheet?->sizes),
            'priceBilling'     => $productSheet?->billing_price !== null ? (float) $productSheet->billing_price : null,
            'priceSale'        => $productSheet?->selling_price !== null ? (float) $productSheet->selling_price : null,
            'description'      => $productSheet?->description ?? null,
            'product_sheet_id' => $order->product_sheet_id,
        ]);
    }

    /**
     * Retorna el listado operacional detallado (snapshot TechnicalSheetOperation) para la OP.
     */
    public function operationalListDetail(int $opId): JsonResponse
    {
        ProductionOrder::findOrFail($opId);

        $operations         = $this->productionOrderService->getOperationsByOrder($opId);
        $sectionsCollection = $this->sectionService->options();
        $operationCost      = $this->settingService->getNumericParameter('operation_cost', 406.0);
        $yarnCost           = $this->settingService->getNumericParameter('yarn_cost', 0);

        $data = $operations->map(function ($op) {
            $section = $op->productSheetOperation?->operationSection ?? $op->operation?->section;

            return [
                'id'                         => $op->id,
                'sequence'                   => $op->sequence,
                'quantity'                   => $op->quantity ?? 1,
                'sam_base'                   => round((float) ($op->sam_base ?? 0), 4),
                'sam_total_unitario'         => round((float) ($op->sam_total_unitario ?? 0), 4),
                'sam_total_operacion'        => round((float) ($op->sam_total_operacion ?? 0), 4),
                'machine_supplement_factor'  => round((float) ($op->machine_supplement_factor ?? 1), 2),
                'fabric_base_factor'         => round((float) ($op->fabric_base_factor ?? 1), 4),
                'operator_efficiency_factor' => round((float) ($op->operator_efficiency_factor ?? 1), 2),
                'operation'                  => $op->operation ? [
                    'id'           => $op->operation->id,
                    'name'         => $op->operation->name,
                    'action'       => $op->operation->action ?? '',
                    'garment_part' => $op->operation->garment_part ?? '',
                ] : null,
                'machine' => $op->machine ? [
                    'id'   => $op->machine->id,
                    'code' => $op->machine->code,
                    'name' => $op->machine->name ?? null,
                ] : null,
                'operation_section' => $section ? [
                    'id'   => $section->id,
                    'name' => $section->name ?? '',
                    'type' => $section->type ?? '',
                ] : null,
                'operation_section_id' => $section?->id,
            ];
        })->values()->all();

        return response()->json([
            'data'     => $data,
            'sections' => $sectionsCollection->map(fn($s) => ['id' => $s->id, 'name' => $s->name ?? '', 'type' => $s->type ?? ''])->values()->all(),
            'costParameters' => [
                'operation_cost' => round($operationCost, 4),
                'yarn_cost'      => round($yarnCost, 4),
            ],
        ]);
    }

    /**
     * Retorna el listado operacional asignado a la OP seleccionada (formato legacy/catálogo).
     */
    public function operationalList(int $opId): JsonResponse
    {
        $order = ProductionOrder::with('productSheet:id,operational_list_id')
            ->findOrFail($opId);

        $productSheet = $order->productSheet;
        if (!$productSheet || !$productSheet->operational_list_id) {
            return response()->json(['hasList' => false, 'list' => null]);
        }

        $catalogs     = $this->settingService->getCatalogOptions('operational_lists');
        $assignedList = $this->findCatalogById($catalogs, $productSheet->operational_list_id);

        $rows = ProductOperationList::query()
            ->with(['operation:id,action,garment_part', 'operationSection:id,name,type'])
            ->where('product_sheet_id', $productSheet->id)
            ->orderBy('sequence')
            ->get();

        return response()->json([
            'hasList' => true,
            'list' => [
                'id'        => $productSheet->operational_list_id,
                'name'      => $assignedList['name'] ?? null,
                'reference' => isset($assignedList['id']) ? (string) $assignedList['id'] : null,
                'items'     => $rows->map(function (ProductOperationList $row) {
                    $action       = trim((string) ($row->operation?->action ?? ''));
                    $garmentPart  = trim((string) ($row->operation?->garment_part ?? ''));
                    $operationName = trim("{$action} {$garmentPart}");
                    $sectionType  = $row->operationSection?->type;
                    $sectionName  = $row->operationSection?->name;

                    return [
                        'order'       => $row->sequence_order,
                        'process'     => $operationName !== '' ? $operationName : null,
                        'description' => $row->notes ?: null,
                        'minutes'     => $row->optimal_time !== null ? (float) $row->optimal_time : null,
                        'type'        => $sectionType ?: $sectionName,
                    ];
                })->values(),
            ],
        ]);
    }

    /**
     * Retorna opciones de listados operacionales para selección.
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

                $id   = mb_strtolower((string) ($option['id'] ?? ''));
                $name = mb_strtolower((string) ($option['name'] ?? ''));
                $term = mb_strtolower($search);

                return str_contains($id, $term) || str_contains($name, $term);
            })
            ->take(50)
            ->map(function (array $option) {
                $id        = $option['id'] ?? null;
                $name      = (string) ($option['name'] ?? '');
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

        $order        = ProductionOrder::with('productSheet')->findOrFail($opId);
        $productSheet = $order->productSheet;

        if (!$productSheet) {
            return response()->json(['message' => 'La OP no tiene sábana de producto asociada.'], 422);
        }

        $catalogs     = $this->settingService->getCatalogOptions('operational_lists');
        $assignedList = $this->findCatalogById($catalogs, $validated['list_id']);
        if (!$assignedList) {
            return response()->json(['message' => 'El listado operacional seleccionado no existe.'], 422);
        }

        $productSheet->update(['operational_list_id' => (int) $validated['list_id']]);

        return response()->json(['ok' => true]);
    }

    /**
     * Retorna los procesos configurados por OP, parámetros y costos desde listado operacional.
        * Solo usuarios con permiso "editar procesos ficha tecnica" pueden editar procesos por defecto.
     */
    public function processesSummary(Request $request, int $opId): JsonResponse
    {
        $order        = ProductionOrder::with('productSheet:id,operational_list_id')->findOrFail($opId);
        $productSheet = $order->productSheet;
        $payload      = $this->technicalSheetService->buildProcessesPayload($opId, $productSheet?->id);
        $payload['canEditProcesses'] = $this->canEditFixedProcesses($request);

        return response()->json($payload);
    }

    /**
        * Guarda los procesos configurados por OP.
        * Sin permiso especial, protege CORTE/CONFECCION/TERMINACION y solo permite cambios en procesos no fijos.
     */
    public function saveProcesses(Request $request, int $opId): JsonResponse
    {
        $order              = ProductionOrder::with('productSheet:id,operational_list_id')->findOrFail($opId);
        $allowedProcessTypes = $this->technicalSheetService->getConfiguredProcessTypes();
        $canEditFixedProcesses = $this->canEditFixedProcesses($request);

        $validated = $request->validate([
            'processes'              => ['required', 'array'],
            'processes.*.id'         => ['nullable', 'string', 'max:100'],
            'processes.*.order'      => ['required', 'integer', 'min:1'],
            'processes.*.type'       => ['required', Rule::in($allowedProcessTypes)],
            'processes.*.supplier'   => ['nullable', 'string', 'max:150'],
            'processes.*.manualPrice' => ['nullable', 'numeric', 'min:0'],
        ]);

        $rowsToSave = $validated['processes'];

        if (!$canEditFixedProcesses) {
            $rowsToSave = $this->protectFixedProcessesRows(
                $opId,
                $order->productSheet?->id,
                $rowsToSave,
            );
        }

        $this->technicalSheetService->saveProcessesToDb(
            $opId,
            $rowsToSave,
            $order->productSheet?->id,
        );

        $payload      = $this->technicalSheetService->buildProcessesPayload($opId, $order->productSheet?->id);
        $payload['canEditProcesses'] = $canEditFixedProcesses;

        return response()->json($payload);
    }

    private function canEditFixedProcesses(Request $request): bool
    {
        $user = $request->user();

        if (!$user) {
            return false;
        }

        return $user->can('editar procesos ficha tecnica');
    }

    /**
     * Protege filas fijas (CORTE, CONFECCION, TERMINACION) para usuarios sin permiso.
     * Mantiene esas filas desde el estado actual y solo permite cambios en procesos no fijos.
     */
    private function protectFixedProcessesRows(int $opId, ?int $productSheetId, array $submittedRows): array
    {
        $currentPayload = $this->technicalSheetService->buildProcessesPayload($opId, $productSheetId);

        $fixedFromCurrent = collect($currentPayload['processes'] ?? [])
            ->filter(function ($row) {
                $type = strtoupper(trim((string) ($row['type'] ?? '')));
                return in_array($type, self::FIXED_PROCESS_TYPES, true);
            })
            ->map(function ($row) {
                return [
                    'id' => trim((string) ($row['id'] ?? '')) !== '' ? (string) $row['id'] : (string) Str::uuid(),
                    'order' => (int) ($row['order'] ?? 0),
                    'type' => strtoupper(trim((string) ($row['type'] ?? 'OTRO'))),
                    'supplier' => isset($row['supplier']) ? (string) $row['supplier'] : null,
                    'manualPrice' => null,
                ];
            })
            ->values();

        if ($fixedFromCurrent->isEmpty()) {
            $fixedFromCurrent = collect($submittedRows)
                ->filter(function ($row) {
                    $type = strtoupper(trim((string) ($row['type'] ?? '')));
                    return in_array($type, self::FIXED_PROCESS_TYPES, true);
                })
                ->map(function ($row) {
                    return [
                        'id' => trim((string) ($row['id'] ?? '')) !== '' ? (string) $row['id'] : (string) Str::uuid(),
                        'order' => (int) ($row['order'] ?? 0),
                        'type' => strtoupper(trim((string) ($row['type'] ?? 'OTRO'))),
                        'supplier' => isset($row['supplier']) ? (string) $row['supplier'] : null,
                        'manualPrice' => null,
                    ];
                })
                ->values();
        }

        $nonFixedSubmitted = collect($submittedRows)
            ->filter(function ($row) {
                $type = strtoupper(trim((string) ($row['type'] ?? '')));
                return !in_array($type, self::FIXED_PROCESS_TYPES, true);
            })
            ->values();

        return $fixedFromCurrent
            ->concat($nonFixedSubmitted)
            ->values()
            ->map(function ($row, $index) {
                $row['order'] = $index + 1;
                return $row;
            })
            ->all();
    }

    /**
     * Actualiza parámetros de procesos. Delegado a TechnicalSheetService.
     */
    public function updateProcessParameters(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'costCut'    => ['required', 'numeric', 'min:0'],
            'costMinute' => ['required', 'numeric', 'min:0'],
        ]);

        $costCut    = (float) $validated['costCut'];
        $costMinute = (float) $validated['costMinute'];

        $this->technicalSheetService->upsertNumericParameter(self::COST_CUT_KEY, 'Costo corte (producción)', $costCut);
        $this->technicalSheetService->upsertNumericParameter(self::COST_MINUTE_KEY, 'Costo minuto (producción)', $costMinute);

        return response()->json([
            'ok'         => true,
            'parameters' => ['costCut' => $costCut, 'costMinute' => $costMinute],
        ]);
    }

    /**
     * Retorna el resumen consolidado de costos por OP. Delegado a TechnicalSheetService.
     */
    public function costsSummary(int $opId): JsonResponse
    {
        return response()->json($this->technicalSheetService->costsSummary($opId));
    }

    /**
     * GET materia prima de la ficha técnica (desde BD).
     */
    public function rawMaterialsSummary(int $opId): JsonResponse
    {
        ProductionOrder::query()->findOrFail($opId);
        return response()->json(['rows' => $this->rawMaterialsStore->read($opId)]);
    }

    /**
     * PUT materia prima de la ficha técnica (persiste en BD).
     */
    public function saveRawMaterials(Request $request, int $opId): JsonResponse
    {
        ProductionOrder::query()->findOrFail($opId);
        $validated = $request->validate([
            'rows' => ['required', 'array'],
            'rows.*.uid' => ['nullable', 'string', 'max:100'],
            'rows.*.telaItemId' => ['nullable', 'integer'],
            'rows.*.tela' => ['nullable', 'string', 'max:255'],
            'rows.*.trazo' => ['nullable', 'string', 'max:100'],
            'rows.*.supplierId' => ['nullable', 'integer'],
            'rows.*.lastSupplierName' => ['nullable', 'string', 'max:255'],
            'rows.*.lastUnitPriceCop' => ['nullable', 'numeric', 'min:0'],
            'rows.*.ancho' => ['nullable', 'numeric', 'min:0'],
            'rows.*.anchoUnit' => ['nullable', 'in:m,cm'],
            'rows.*.promTrazo' => ['nullable', 'numeric', 'min:0'],
            'rows.*.largoTrazo' => ['nullable', 'numeric', 'min:0'],
            'rows.*.largoLiq' => ['nullable', 'numeric', 'min:0'],
            'rows.*.consumo' => ['nullable', 'numeric', 'min:0'],
        ]);
        $this->rawMaterialsStore->write($opId, $validated['rows']);
        return response()->json(['rows' => $this->rawMaterialsStore->read($opId)]);
    }

    /**
     * Genera el PDF resumen completo de la ficha técnica para una OP.
     */
    public function printSummary(int $opId): \Illuminate\Http\Response
    {
        $basicInfoResponse = $this->basicInfo($opId);
        $basicInfo = json_decode($basicInfoResponse->getContent(), true);

        return $this->pdfService->generate($opId, $basicInfo);
    }

    public function printSummaryH(int $opId): \Illuminate\Http\Response
    {
        $basicInfoResponse = $this->basicInfo($opId);
        $basicInfo = json_decode($basicInfoResponse->getContent(), true);

        return $this->pdfService->generateH($opId, $basicInfo);
    }

    // ─── Helpers privados (solo presentación) ─────────────

    private function buildReference(ProductionOrder $order): string
    {
        $sampleRef  = trim($order->productSheet?->sample_reference ?? '');
        $productRef = trim($order->productSheet?->product_reference ?? '');

        if ($sampleRef && $productRef) {
            return $sampleRef === $productRef ? $sampleRef : "{$sampleRef} - {$productRef}";
        }

        return $sampleRef ?: ($productRef ?: 'Sin referencia');
    }

    private function extractOpType(string $opCode): string
    {
        $code = strtoupper(trim($opCode));

        if (str_starts_with($code, 'MUT')) return 'MUT';
        if (str_starts_with($code, 'MU'))  return 'MU';
        if (str_starts_with($code, 'MFT')) return 'MFT';

        return 'MF';
    }

    private function findCatalogById(EloquentCollection|SupportCollection $catalogs, mixed $id): ?array
    {
        return $catalogs->first(fn(array $item) => (string) ($item['id'] ?? '') === (string) $id);
    }

    private function resolveFlatDrawingUrl(?string $rawPath): ?string
    {
        if (!is_string($rawPath) || trim($rawPath) === '') {
            return null;
        }

        $path = str_replace('\\', '/', trim($rawPath));
        if (preg_match('/^https?:\/\//i', $path) || preg_match('/^(data:|blob:)/i', $path)) {
            return $path;
        }

        $normalized = ltrim($path, '/');
        if (Str::startsWith($normalized, 'storage/')) {
            $normalized = Str::after($normalized, 'storage/');
        }

        $candidates = [$normalized];
        if (Str::startsWith($normalized, 'product-sheets/flat-sketches/')) {
            $candidates[] = Str::replaceFirst('product-sheets/flat-sketches/', 'product_sheets/', $normalized);
        }
        if (Str::startsWith($normalized, 'product_sheets/')) {
            $candidates[] = Str::replaceFirst('product_sheets/', 'product-sheets/flat-sketches/', $normalized);
        }

        foreach ($candidates as $candidate) {
            if ($candidate !== '' && Storage::disk('public')->exists($candidate)) {
                return $this->storageUrl($candidate);
            }
        }

        return $this->storageUrl($normalized);
    }

    private function storageUrl(string $path): string
    {
        $path = ltrim(str_replace('\\', '/', $path), '/');

        return route('storage.file.show', ['path' => $path]);
    }

    private function nullableText(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }
        $text = trim($value);

        return $text !== '' ? $text : null;
    }

    private function normalizeSizes(mixed $sizes): array
    {
        if ($sizes === null) {
            return [];
        }

        $extractFromArray = function (array $value): array {
            // Si es un array asociativo (objeto JSON con keys => valores booleanos)
            if (array_keys($value) !== range(0, count($value) - 1)) {
                return array_values(array_filter(
                    array_map(static fn($key) => trim((string) $key), array_keys($value)),
                    static fn($key) => $key !== '' && !empty($value[$key])
                ));
            }

            // Si es un array secuencial simple
            return array_values(array_filter(
                array_map(static fn($item) => is_string($item) || is_numeric($item) ? trim((string) $item) : '', $value),
                static fn($item) => $item !== ''
            ));
        };

        if (is_array($sizes)) {
            return $extractFromArray($sizes);
        }

        if (is_string($sizes)) {
            $trimmed = trim($sizes);
            if ($trimmed === '') {
                return [];
            }

            $decoded = json_decode($trimmed, true);
            if (is_array($decoded)) {
                return $extractFromArray($decoded);
            }

            return array_values(array_filter(
                array_map('trim', explode(',', $trimmed)),
                static fn($item) => $item !== ''
            ));
        }

        return [];
    }
}
