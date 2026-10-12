<?php

namespace App\Http\Controllers\Production;

use App\Helpers\MonthLabel;
use App\Helpers\SeasonParser;
use App\Http\Controllers\Controller;
use App\Models\Production\ProductionOrder;
use App\Models\Production\ProductOperationList;
use App\Models\Production\TechnicalSheet;
use App\Models\Production\TechnicalSheetOperation;
use App\Enums\EstatusOperacionOrden;
use App\Services\Textil\FabricRollService;
use App\Services\Production\FabricBaseService;
use App\Services\Production\OperationSectionService;
use App\Services\Production\ProductionOrderService;
use App\Services\Production\TechnicalDataSheetPdfService;
use App\Services\Production\TechnicalDataSheetProcessesStore;
use App\Services\Production\TechnicalDataSheetRawMaterialsStore;
use App\Services\Production\TechnicalDataSheetRollsStore;
use App\Services\Production\TechnicalSheetService;
use App\Services\Settings\SettingService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
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
        private FabricBaseService $fabricBaseService,
        private OperationSectionService $sectionService,
        private ProductionOrderService $productionOrderService,
        private TechnicalSheetService $technicalSheetService,
        private TechnicalDataSheetProcessesStore $processesStore,
        private TechnicalDataSheetRawMaterialsStore $rawMaterialsStore,
        private TechnicalDataSheetRollsStore $rollsStore,
        private TechnicalDataSheetPdfService $pdfService,
        private FabricRollService $fabricRollService,
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
            ->with('productSheet:id,type,woven_product,sample_reference,product_reference')
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
            $sheetType  = Str::lower(trim((string) ($order->productSheet?->type ?? '')));
            $groupLabel = $sheetType === 'muestra' ? 'Muestras' : 'Productos';
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

            $data[$canonicalKey] = $this->settingService->getCatalogOptions($canonicalKey)->values()->all();
        }

        return response()->json(['data' => $data]);
    }

    /**
     * Retorna la información básica de una OP específica.
     */
    public function basicInfo(int $opId): JsonResponse
    {
        $legacyTemporalFallbackEnabled = (bool) config('product-sheet.temporal_legacy_fallback_enabled', true);

        $order = ProductionOrder::with([
            'productSheet:id,type,woven_product,sample_reference,product_reference,section_id,group_id,world_id,brand_id,fabric_base_id,yarn_base_id,billing_price,selling_price,sizes,description,status,flat_sketch_path,created_at',
        ])->findOrFail($opId);

        $destinations = $this->settingService->getCatalogOptions('destinations')->keyBy('id');
        $sections     = $this->settingService->getCatalogOptions('sections')->keyBy('id');
        $groups       = $this->settingService->getCatalogOptions('groups')->keyBy('id');
        $brands       = $this->settingService->getCatalogOptions('brands')->keyBy('id');
        $worlds       = $this->settingService->getCatalogOptions('worlds')->keyBy('id');
        $fabricBases  = $this->fabricBaseService->options()->keyBy('id');
        $yarnBases    = $this->settingService->getCatalogOptions('yarn_bases')->keyBy('id');

        $destination  = $destinations->get($order->destination_id);
        $productSheet = $order->productSheet;

        $section = $group = $brand = $world = $base = $baseYarn = null;
        $fabricBaseId = null;

        if ($productSheet) {
            $section      = $productSheet->section_id   ? $sections->get($productSheet->section_id)       : null;
            $group        = $productSheet->group_id     ? $groups->get($productSheet->group_id)           : null;
            $brand        = $productSheet->brand_id     ? $brands->get($productSheet->brand_id)           : null;
            $world        = $productSheet->world_id     ? $worlds->get($productSheet->world_id)           : null;
            $fabricBaseId = $productSheet->fabric_base_id;
            $base         = $fabricBaseId ? $fabricBases->get($fabricBaseId) : null;
            $baseYarn     = $productSheet->yarn_base_id ? $yarnBases->get($productSheet->yarn_base_id)     : null;
        }

        $season = $order->season;
        if ($order->season_month !== null) {
            $monthName = SeasonParser::monthName((int) $order->season_month)
                ?? MonthLabel::fromNumber((int) $order->season_month);
            $seasonDisplay = $order->season_year !== null
                ? sprintf('%s %d', $monthName, (int) $order->season_year)
                : $monthName;
        } else {
            $seasonDisplay = $legacyTemporalFallbackEnabled && is_numeric($season)
                ? MonthLabel::fromNumber((int) $season)
                : ($legacyTemporalFallbackEnabled ? $season : '-');
        }
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
            'base'             => $base['nombre'] ?? $base['name'] ?? null,
            'fabric_base_id'   => $fabricBaseId,
            'baseYarn'         => $baseYarn['name'] ?? null,
            'season'           => $seasonDisplay,
            'sizes'            => $this->normalizeSizes($productSheet?->sizes),
            'priceBilling'     => $productSheet?->billing_price !== null ? (float) $productSheet->billing_price : null,
            'priceSale'        => $productSheet?->selling_price !== null ? (float) $productSheet->selling_price : null,
            'description'      => $productSheet?->description ?? null,
            'productType'      => $productSheet?->type ?? null,
            'isWovenProduct'   => (bool) ($productSheet?->woven_product ?? false),
            'product_sheet_id' => $order->product_sheet_id,
            'current_process'  => $order->current_process,
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
            $section = $op->operation?->section;
            $resolvedMachine = $op->machine ?? $op->operation?->machineReference;

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
                    'measure'      => $op->operation->measure ?? null,
                    'op_quantity'  => $op->operation->quantity ?? null,
                ] : null,
                'machine' => $resolvedMachine ? [
                    'id'   => $resolvedMachine->id,
                    'code' => $resolvedMachine->code,
                    'name' => $resolvedMachine->name ?? null,
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
            ->with(['operation:id,action,garment_part,section_id', 'operation.section:id,name,type'])
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
                    $sectionType  = $row->operation?->section?->type;
                    $sectionName  = $row->operation?->section?->name;

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

        $order = ProductionOrder::with('productSheet:id,operational_list_id')->findOrFail($opId);
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
     * Sincroniza el listado operacional de la Ficha Técnica (technical_sheet_operations).
     * Solo permitido en estado I&D. Los cambios NO afectan product_sheet_operations.
     */
    public function syncOperationalList(Request $request, int $opId): JsonResponse
    {
        $order = ProductionOrder::with('productSheet')->findOrFail($opId);

        if (strtoupper(trim((string) ($order->current_process ?? ''))) !== 'I&D') {
            return response()->json(['message' => 'El listado operacional solo se puede editar en estado I&D.'], 403);
        }

        $validated = $request->validate([
            'operations'                              => ['required', 'array', 'min:1'],
            'operations.*.id'                         => ['nullable', 'integer'],
            'operations.*.operation_id'               => ['required', 'integer', 'exists:operations,id'],
            'operations.*.sequence'                   => ['required', 'integer', 'min:1'],
            'operations.*.optimal_time'               => ['required', 'numeric', 'min:0'],
            'operations.*.operator_efficiency_factor' => ['required', 'numeric', 'between:0.1,5'],
            'operations.*.machine_supplement_factor'  => ['required', 'numeric', 'min:1'],
            'operations.*.grupo_preparacion'          => ['nullable', 'string', 'max:100'],
            'operations.*.notes'                      => ['nullable', 'string', 'max:1000'],
        ]);

        $technicalSheet = TechnicalSheet::firstOrCreate(
            ['production_order_id' => $opId],
            ['status' => TechnicalSheet::STATUS_DRAFT],
        );

        $quantityLote = max(1, (int) ($order->units ?? 1));

        DB::transaction(function () use ($technicalSheet, $validated, $quantityLote, $order) {
            $existingOps = TechnicalSheetOperation::where('technical_sheet_id', $technicalSheet->id)
                ->get()->keyBy('id');

            $processedIds = [];

            foreach ($validated['operations'] as $data) {
                $operationId       = (int) $data['operation_id'];
                $sequence          = (int) $data['sequence'];
                $samBase           = (float) $data['optimal_time'];
                $opEfficiency      = (float) $data['operator_efficiency_factor'];
                $machineSupplement = (float) $data['machine_supplement_factor'];

                $operation    = \App\Models\Production\Operation::find($operationId);
                $machineId    = $operation?->machine_id_reference ?? null;
                $fabricFactor = $this->resolveFabricFactor($order);

                $opQuantity        = \App\Models\Production\ProductOperationList::quantityOf($operation?->quantity);
                $samTotalUnitario  = round($samBase * $machineSupplement * $fabricFactor * $opEfficiency, 4);
                $samTotalOperacion = round($samTotalUnitario * $opQuantity, 4);
                $samTotalLote      = round($samTotalOperacion * $quantityLote, 4);

                $itemId = isset($data['id']) && $data['id'] !== null ? (int) $data['id'] : null;

                if ($itemId && $existingOps->has($itemId)) {
                    $item = $existingOps->get($itemId);
                    $item->update([
                        'sequence'                   => $sequence,
                        'operation_id'               => $operationId,
                        'machine_id'                 => $machineId,
                        'sam_base'                   => $samBase,
                        'operator_efficiency_factor' => $opEfficiency,
                        'machine_supplement_factor'  => $machineSupplement,
                        'fabric_base_factor'         => $fabricFactor,
                        'quantity'                   => $opQuantity,
                        'sam_total_unitario'         => $samTotalUnitario,
                        'sam_total_operacion'        => $samTotalOperacion,
                        'sam_total_lote'             => $samTotalLote,
                    ]);
                    $processedIds[] = $itemId;
                } else {
                    $newItem = TechnicalSheetOperation::create([
                        'technical_sheet_id'         => $technicalSheet->id,
                        'sequence'                   => $sequence,
                        'operation_id'               => $operationId,
                        'machine_id'                 => $machineId,
                        'quantity'                   => $opQuantity,
                        'sam_base'                   => $samBase,
                        'operator_efficiency_factor' => $opEfficiency,
                        'machine_supplement_factor'  => $machineSupplement,
                        'fabric_base_factor'         => $fabricFactor,
                        'sam_total_unitario'         => $samTotalUnitario,
                        'sam_total_operacion'        => $samTotalOperacion,
                        'sam_total_lote'             => $samTotalLote,
                        'quantity_progressed'        => 0,
                        'status'                     => EstatusOperacionOrden::PENDIENTE,
                    ]);
                    $processedIds[] = (int) $newItem->id;
                }
            }

            // Por instancia (incluye las ya eliminadas, como el forceDelete masivo) para que el
            // AuditObserver registre cada operación eliminada.
            TechnicalSheetOperation::withTrashed()
                ->where('technical_sheet_id', $technicalSheet->id)
                ->whereNotIn('id', $processedIds)
                ->get()
                ->each
                ->forceDelete();
        });

        return response()->json(['message' => 'Listado operacional de ficha técnica sincronizado correctamente.']);
    }

    private function resolveFabricFactor(ProductionOrder $order): float
    {
        $fabricBaseId = $order->productSheet?->fabric_base_id ?? null;
        if ($fabricBaseId === null) return 1.0;

        return $this->fabricBaseService->getFactorById((int) $fabricBaseId);
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

    public function updatePriceOverrides(Request $request, int $opId): JsonResponse
    {
        $validated = $request->validate([
            'billing_price_override' => ['nullable', 'numeric', 'min:0'],
            'selling_price_override' => ['nullable', 'numeric', 'min:0'],
        ]);

        $order = ProductionOrder::query()->findOrFail($opId);

        $toPrice = fn ($v) => ($v !== null && $v !== '') ? (float) $v : null;

        $order->update([
            'billing_price_override' => $toPrice($validated['billing_price_override'] ?? null),
            'selling_price_override' => $toPrice($validated['selling_price_override'] ?? null),
        ]);

        return response()->json($this->technicalSheetService->costsSummary($opId));
    }

    /**
     * GET materia prima de la ficha técnica (desde BD).
     */
    public function rawMaterialsSummary(int $opId): JsonResponse
    {
        ProductionOrder::query()->findOrFail($opId);

        $fabricTypeOptions = $this->settingService
            ->getCatalogOptions('type_of_fabric')
            ->keyBy(fn(array $option) => (string) ($option['id'] ?? ''));

        $normalizeFabricType = function (mixed $value) use ($fabricTypeOptions): array {
            $fabricTypeParameterId = $value;
            if ($fabricTypeParameterId === '') {
                $fabricTypeParameterId = null;
            }

            if (is_numeric($fabricTypeParameterId)) {
                $fabricTypeParameterId = (int) $fabricTypeParameterId;
            }

            $fabricTypeOption = $fabricTypeParameterId !== null
                ? $fabricTypeOptions->get((string) $fabricTypeParameterId)
                : null;

            return [
                'id' => $fabricTypeParameterId,
                'name' => is_array($fabricTypeOption) ? ($fabricTypeOption['name'] ?? null) : null,
            ];
        };

        $rows = array_map(function (array $row) use ($normalizeFabricType) {
            $fabricType = $normalizeFabricType($row['fabric_type_parameter_id'] ?? $row['fabricTypeParameterId'] ?? null);

            $row['fabricTypeParameterId'] = $fabricType['id'];
            $row['fabricTypeParameterName'] = $fabricType['name'];

            unset($row['fabric_type_parameter_id']);

            return $row;
        }, $this->rawMaterialsStore->read($opId));

        $traces = array_map(function (array $trace) use ($normalizeFabricType) {
            $fabricType = $normalizeFabricType($trace['fabric_type_parameter_id'] ?? $trace['fabricTypeParameterId'] ?? null);

            return [
                'uid' => isset($trace['uid']) ? (string) $trace['uid'] : null,
                'trazo' => isset($trace['trazo']) ? (string) $trace['trazo'] : null,
                'promTrazo' => $trace['promTrazo'] ?? null,
                'largoTrazo' => $trace['largoTrazo'] ?? null,
                'largoLiq' => $trace['largoLiq'] ?? null,
                'fabricTypeParameterId' => $fabricType['id'],
                'fabricTypeParameterName' => $fabricType['name'],
            ];
        }, $this->rawMaterialsStore->readTraces($opId));

        return response()->json(['rows' => $rows, 'traces' => $traces]);
    }

    /**
     * PUT materia prima de la ficha técnica (persiste en BD).
     */
    public function saveRawMaterials(Request $request, int $opId): JsonResponse
    {
        ProductionOrder::query()->findOrFail($opId);

        $fabricTypeOptions = $this->settingService
            ->getCatalogOptions('type_of_fabric')
            ->keyBy(fn(array $option) => (string) ($option['id'] ?? ''));

        $resolveFabricTypeParameterId = function (mixed $value) use ($fabricTypeOptions): mixed {
            if ($value === null || $value === '') {
                return null;
            }

            $normalizedInput = mb_strtolower(trim((string) $value));
            if ($normalizedInput === 'principal') {
                return 'PRINCIPAL';
            }
            if ($normalizedInput === 'secundaria' || $normalizedInput === 'secundario') {
                return 'SECUNDARIA';
            }

            if ($fabricTypeOptions->has((string) $value)) {
                return is_numeric($value) ? (int) $value : $value;
            }

            $normalized = $normalizedInput;
            if ($normalized === '') {
                return null;
            }

            $match = $fabricTypeOptions->first(function (array $option) use ($normalized) {
                $name = mb_strtolower(trim((string) ($option['name'] ?? '')));
                if ($name === '') {
                    return false;
                }

                if (str_contains($normalized, 'principal')) {
                    return str_contains($name, 'principal');
                }

                if (str_contains($normalized, 'secundar')) {
                    return str_contains($name, 'secundar');
                }

                return $name === $normalized;
            });

            if (!is_array($match) || !array_key_exists('id', $match)) {
                return in_array($normalized, ['principal', 'secundaria', 'secundario'], true)
                    ? strtoupper($normalized === 'principal' ? 'principal' : 'secundaria')
                    : '__invalid__';
            }

            $resolved = $match['id'];
            return is_numeric($resolved) ? (int) $resolved : $resolved;
        };

        $validated = $request->validate([
            'rows' => ['required', 'array'],
            'rows.*.uid' => ['nullable', 'string', 'max:100'],
            'rows.*.traceUid' => ['nullable', 'string', 'max:100'],
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
            'rows.*.fabricTypeParameterId' => [
                'nullable',
                function (string $attribute, mixed $value, \Closure $fail) use ($resolveFabricTypeParameterId) {
                    if ($value === null || $value === '') {
                        return;
                    }

                    if ($resolveFabricTypeParameterId($value) === '__invalid__') {
                        $fail("El valor seleccionado en {$attribute} no existe en el catálogo de tipo de tela.");
                    }
                },
            ],
            'traces' => ['sometimes', 'array'],
            'traces.*.uid' => ['nullable', 'string', 'max:100'],
            'traces.*.trazo' => ['nullable', 'string', 'max:100'],
            'traces.*.promTrazo' => ['nullable', 'numeric', 'min:0'],
            'traces.*.largoTrazo' => ['nullable', 'numeric', 'min:0'],
            'traces.*.largoLiq' => ['nullable', 'numeric', 'min:0'],
            'traces.*.fabricTypeParameterId' => [
                'nullable',
                function (string $attribute, mixed $value, \Closure $fail) use ($resolveFabricTypeParameterId) {
                    if ($value === null || $value === '') {
                        return;
                    }

                    if ($resolveFabricTypeParameterId($value) === '__invalid__') {
                        $fail("El valor seleccionado en {$attribute} no existe en el catÃ¡logo de tipo de tela.");
                    }
                },
            ],
            'rollState' => ['sometimes', 'array'],
            'rollState.rolls' => ['required_with:rollState', 'array'],
            'rollState.rolls.*.id' => ['nullable', 'string', 'max:100'],
            'rollState.rolls.*.consecutive' => ['nullable', 'string', 'max:120'],
            'rollState.rolls.*.type' => ['required_with:rollState.rolls', 'in:PRINCIPAL,SECUNDARIA'],
            'rollState.rolls.*.fabricName' => ['required_with:rollState.rolls', 'string', 'max:200'],
            'rollState.rolls.*.averageTrace' => ['nullable', 'numeric', 'min:0'],
            'rollState.rolls.*.factor' => ['nullable', 'numeric', 'min:0'],
            'rollState.rolls.*.consumptionBase' => ['required_with:rollState.rolls', 'numeric', 'min:0'],
            'rollState.rolls.*.pricePerMeter' => ['required_with:rollState.rolls', 'numeric', 'min:0'],
            'rollState.rolls.*.programmedMeters' => ['nullable', 'numeric', 'min:0'],
            'rollState.rolls.*.colors' => ['nullable', 'string', 'max:200'],
            'rollState.rolls.*.availableMeters' => ['nullable', 'numeric', 'min:0'],
            'rollState.rolls.*.balanceMeters' => ['nullable', 'numeric'],
            'rollState.rolls.*.color' => ['nullable', 'string', 'max:120'],
            'rollState.rolls.*.colorCode' => ['nullable', 'string', 'max:120'],
            'rollState.rolls.*.widthCm' => ['nullable', 'numeric', 'min:0'],
            'rollState.rolls.*.supplier' => ['nullable', 'string', 'max:200'],
            'rollState.rolls.*.notes' => ['nullable', 'string', 'max:1000'],
            'rollState.rolls.*.rawMaterialUid' => ['nullable', 'string', 'max:100'],
            'rollState.rolls.*.fabricItemId' => ['nullable'],
            'rollState.rolls.*.rollId' => ['nullable'],
            'rollState.rolls.*.location' => ['nullable', 'string', 'max:100'],
            'rollState.deletedIds' => ['nullable', 'array'],
            'rollState.deletedIds.*' => ['string', 'max:100'],
        ]);

        $rowsToPersist = array_map(function (array $row) use ($resolveFabricTypeParameterId) {
            $fabricTypeParameterId = $row['fabricTypeParameterId'] ?? null;
            $fabricTypeParameterId = $resolveFabricTypeParameterId($fabricTypeParameterId);

            unset($row['fabricTypeParameterId'], $row['fabricTypeParameterName']);
            $row['fabric_type_parameter_id'] = $fabricTypeParameterId;

            return $row;
        }, $validated['rows']);

        $tracesSource = $validated['traces'] ?? null;
        if ($tracesSource === null) {
            $traceMap = [];
            foreach ($rowsToPersist as $row) {
                $traceUid = isset($row['traceUid']) && trim((string) $row['traceUid']) !== ''
                    ? trim((string) $row['traceUid'])
                    : null;
                $trazo = isset($row['trazo']) ? trim((string) $row['trazo']) : '';
                $groupKey = $traceUid ?? ($trazo !== '' ? "trazo:{$trazo}" : null);

                if ($groupKey === null || isset($traceMap[$groupKey])) {
                    continue;
                }

                $traceMap[$groupKey] = [
                    'uid' => $traceUid,
                    'trazo' => $row['trazo'] ?? null,
                    'promTrazo' => $row['promTrazo'] ?? null,
                    'largoTrazo' => $row['largoTrazo'] ?? null,
                    'largoLiq' => $row['largoLiq'] ?? null,
                    'fabric_type_parameter_id' => $row['fabric_type_parameter_id'] ?? null,
                ];
            }

            $tracesSource = array_values($traceMap);
        }

        $tracesToPersist = array_map(function (array $trace) use ($resolveFabricTypeParameterId) {
            $fabricTypeParameterId = $trace['fabricTypeParameterId'] ?? $trace['fabric_type_parameter_id'] ?? null;
            $fabricTypeParameterId = $resolveFabricTypeParameterId($fabricTypeParameterId);

            return [
                'uid' => isset($trace['uid']) ? trim((string) $trace['uid']) : null,
                'trazo' => isset($trace['trazo']) ? trim((string) $trace['trazo']) : null,
                'promTrazo' => $trace['promTrazo'] ?? null,
                'largoTrazo' => $trace['largoTrazo'] ?? null,
                'largoLiq' => $trace['largoLiq'] ?? null,
                'fabric_type_parameter_id' => $fabricTypeParameterId,
            ];
        }, is_array($tracesSource) ? $tracesSource : []);

        DB::transaction(function () use ($opId, $rowsToPersist, $tracesToPersist, $validated) {
            $this->rawMaterialsStore->write($opId, $rowsToPersist, $tracesToPersist);

            if (is_array($validated['rollState'] ?? null)) {
                $this->persistRollState($opId, $validated['rollState']);
            }
        });

        return $this->rawMaterialsSummary($opId);
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
        $sheetType = Str::lower(trim((string) ($order->productSheet?->type ?? '')));
        $sampleRef  = trim($order->productSheet?->sample_reference ?? '');
        $productRef = trim($order->productSheet?->product_reference ?? '');

        if ($sheetType === 'muestra' && $sampleRef !== '') {
            return $sampleRef;
        }

        if ($sheetType === 'producto' && $productRef !== '') {
            return $productRef;
        }

        if ($sampleRef && $productRef) {
            return $sampleRef === $productRef ? $sampleRef : "{$sampleRef} - {$productRef}";
        }

        return $sampleRef ?: ($productRef ?: 'Sin referencia');
    }

    private function extractOpType(string $opCode): string
    {
        $code = strtoupper(trim($opCode));

        if (str_starts_with($code, 'MUT')) return 'MU';
        if (str_starts_with($code, 'MU'))  return 'MU';
        if (str_starts_with($code, 'MFT')) return 'MF';

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

    private function persistRollState(int $opId, array $state): void
    {
        $payload = $this->rollsStore->read($opId);
        $existing = is_array($payload['lines'] ?? null) ? $payload['lines'] : [];
        $existingById = [];

        foreach ($existing as $line) {
            $lineId = trim((string) ($line['id'] ?? ''));
            if ($lineId === '') {
                continue;
            }

            $existingById[$lineId] = $line;
        }

        $deletedIds = collect($state['deletedIds'] ?? [])
            ->map(fn($id) => trim((string) $id))
            ->filter(fn($id) => $id !== '')
            ->values()
            ->all();
        $deletedLookup = array_fill_keys($deletedIds, true);

        $now = Carbon::now()->toISOString();
        $nextLines = [];

        foreach (($state['rolls'] ?? []) as $row) {
            $rawId = trim((string) ($row['id'] ?? ''));
            $lineId = $rawId !== '' ? $rawId : (string) Str::uuid();
            if (isset($deletedLookup[$lineId])) {
                continue;
            }

            $previous = $existingById[$lineId] ?? null;
            $nextLines[] = [
                'id' => $lineId,
                'consecutive' => $this->nullableText($row['consecutive'] ?? null),
                'type' => (string) $row['type'],
                'fabricName' => trim((string) $row['fabricName']),
                'averageTrace' => $this->nullableNumber($row['averageTrace'] ?? null),
                'factor' => $this->nullableNumber($row['factor'] ?? null),
                'consumptionBase' => (float) $row['consumptionBase'],
                'pricePerMeter' => (float) $row['pricePerMeter'],
                'programmedMeters' => $this->nullableNumber($row['programmedMeters'] ?? null),
                'colors' => $this->nullableText($row['colors'] ?? null),
                'availableMeters' => $this->nullableNumber($row['availableMeters'] ?? null),
                'balanceMeters' => $this->nullableNumber($row['balanceMeters'] ?? null),
                'color' => $this->nullableText($row['color'] ?? null),
                'colorCode' => $this->nullableText($row['colorCode'] ?? null),
                'widthCm' => $this->nullableNumber($row['widthCm'] ?? null),
                'supplier' => $this->nullableText($row['supplier'] ?? null),
                'extraMeters' => is_numeric($row['extraMeters'] ?? null) ? (float) $row['extraMeters'] : 0.0,
                'notes' => $this->nullableText($row['notes'] ?? null),
                'rawMaterialUid' => $this->nullableText($row['rawMaterialUid'] ?? null),
                'fabricItemId' => $row['fabricItemId'] ?? null,
                'rollId' => $row['rollId'] ?? null,
                'location' => $this->nullableText($row['location'] ?? null),
                'updatedAt' => $now,
                'createdAt' => $previous['createdAt'] ?? $now,
            ];
        }

        // Reserva de rollos: una sola implementación, sobre lo pendiente de cada línea
        // (programado − liberado por el corte). Corre dentro de la transacción del guardado.
        $payload['lines'] = array_values($this->fabricRollService->syncSheetReservations($existing, $nextLines));

        $this->rollsStore->write($opId, $payload);
    }

    private function nullableText(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }
        $text = trim($value);

        return $text !== '' ? $text : null;
    }

    private function nullableNumber(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (float) $value : null;
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
