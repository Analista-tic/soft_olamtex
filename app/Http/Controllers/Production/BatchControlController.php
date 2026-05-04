<?php

namespace App\Http\Controllers\Production;

use App\Enums\EstatusOperacionOrden;
use App\Helpers\MonthLabel;
use App\Helpers\ScopeHelper;
use App\Http\Controllers\Controller;
use App\Models\Production\BatchControlDelivery;
use App\Models\Production\BatchControlNovelty;
use App\Models\Production\ProductionOrder;
use App\Models\Production\TechnicalDataSheetSupply;
use App\Models\Production\TechnicalSheet;
use App\Models\Production\TechnicalSheetOperation;
use App\Models\Textil\FabricRoll;
use App\Services\Inventory\InventoryMovementService;
use App\Services\Production\FinishedProductCatalogItemService;
use App\Services\Production\OperationCatalogService;
use App\Services\Production\MachineService;
use App\Services\Production\OperationSectionService;
use App\Services\Production\OperationService;
use App\Services\Settings\SettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class BatchControlController extends Controller
{
    private function batchProcessAliases(): array
    {
        return config('product-sheet.batch_control_process_aliases', []);
    }

    private function normalizeBatchProcessName(?string $process): ?string
    {
        $normalized = trim((string) $process);

        if ($normalized === '') {
            return null;
        }

        return $this->batchProcessAliases()[$normalized] ?? $normalized;
    }

    private function normalizeBatchProcessDates(array $dates): array
    {
        $normalized = [];

        foreach ($dates as $process => $date) {
            $resolvedProcess = $this->normalizeBatchProcessName(is_string($process) ? $process : null);
            if ($resolvedProcess === null) {
                continue;
            }

            $normalized[$resolvedProcess] = $date;
        }

        return $normalized;
    }

    private function batchProcessVariants(?string $process): array
    {
        $normalized = $this->normalizeBatchProcessName($process);
        if ($normalized === null) {
            return [];
        }

        $variants = [$normalized];
        foreach ($this->batchProcessAliases() as $legacy => $canonical) {
            if ($canonical === $normalized) {
                $variants[] = $legacy;
            }
        }

        return array_values(array_unique(array_filter(array_map('trim', $variants))));
    }

    private function inferColorFromText(?string $value): ?string
    {
        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }

        if (preg_match('/\(([^)]+)\)\s*$/', $text, $match) === 1) {
            $candidate = trim((string) ($match[1] ?? ''));
            return $candidate !== '' ? $candidate : null;
        }

        $parts = preg_split('/[-|\/]/', $text) ?: [];
        $parts = array_values(array_filter(array_map(static fn ($p) => trim((string) $p), $parts), static fn ($p) => $p !== ''));

        if (count($parts) >= 2) {
            return $parts[count($parts) - 1];
        }

        return null;
    }

    public function __construct(
        private SettingService $settingService,
        private InventoryMovementService $movementService,
        private FinishedProductCatalogItemService $finishedProductCatalogItemService,
        private OperationSectionService $sectionService,
        private OperationService $operationService,
        private OperationCatalogService $operationCatalogService,
        private MachineService $machineService,
    ) {
    }

    public function index(): Response
    {
        $processes = config('product-sheet.batch_control_processes', []);

        return Inertia::render('Production/BatchControl/Index', [
            'processes' => $processes,
        ]);
    }

    /**
     * Procesos de la ficha de producto normalizados al orden de batch_control.
     * Incluye obligatorios (según tipo) y los activos, filtrando por lo permitido al tipo.
     *
     * @param  array<string, bool>|array<int, string>  $rawProcesses  ProductSheet.processes (objeto o array)
     * @param  array<string>  $batchProcesses
     * @param  array<string, string>  $aliases
     * @param  string  $type  'muestra' | 'producto'
     * @return array<string>
     */
    private function productSheetProcessesForFlow(array $rawProcesses, array $batchProcesses, array $aliases, string $type = 'producto'): array
    {
        $mandatoryByType = config('product-sheet.batch_control_mandatory_processes_by_type', []);
        $mandatory = $mandatoryByType[$type] ?? config('product-sheet.batch_control_mandatory_processes', []);

        $allowedByType = config('product-sheet.processes_allowed_by_type', []);
        $allowedForType = isset($allowedByType[$type]) ? array_flip($allowedByType[$type]) : null;

        $active = [];
        if (array_is_list($rawProcesses)) {
            foreach ($rawProcesses as $p) {
                $name = is_string($p) ? trim($p) : null;
                if ($name) {
                    $active[] = $aliases[$name] ?? $name;
                }
            }
        } else {
            foreach ($rawProcesses as $name => $enabled) {
                if ($enabled && is_string($name)) {
                    $normalized = $aliases[trim($name)] ?? trim($name);
                    $active[] = $normalized;
                }
            }
        }

        $activeSet = array_flip(array_unique($active));

        $result = [];
        foreach ($batchProcesses as $proc) {
            // Excluir procesos no permitidos para este tipo
            if ($allowedForType !== null && !isset($allowedForType[$proc])) {
                continue;
            }
            if (in_array($proc, $mandatory, true) || isset($activeSet[$proc])) {
                $result[] = $proc;
            }
        }

        return $result;
    }

    /**
     * Devuelve todas las órdenes con datos para Control de lotes.
     * Incluye la última entrega (observaciones) y fecha estimada.
     */
    public function getData(Request $request): JsonResponse
    {
        $query = ProductionOrder::with([
            'productSheet:id,type,processes,description,sample_reference,product_reference,section_id,group_id',
            'lastDelivery',
        ])
        ->withCount(['pendingNovelties'])
        ->orderBy('batch_priority')
        ->orderByDesc('id');

        ScopeHelper::apply($query, $request->user(), 'ver propios control lotes', 'ver area control lotes', 'ver todos control lotes');

        $orders = $query->get();

        $destinations = $this->settingService->getCatalogOptions('destinations')->keyBy('id');
        $sections = $this->settingService->getCatalogOptions('sections')->keyBy('id');
        $groups = $this->settingService->getCatalogOptions('groups')->keyBy('id');

        $today = Carbon::today();

        $processes = config('product-sheet.batch_control_processes', []);
        $aliases = config('product-sheet.batch_control_process_aliases', []);

        $rows = $orders->map(function (ProductionOrder $p) use ($destinations, $sections, $groups, $today, $processes, $aliases) {
            $ps = $p->productSheet;

            // Flujo real según ProductSheet: procesos activos normalizados al orden de batch_control
            $rawProcesses = $ps?->processes ?? [];
            if (is_string($rawProcesses)) {
                $decoded = json_decode($rawProcesses, true);
                $rawProcesses = is_array($decoded) ? $decoded : [];
            }
            $productSheetProcesses = $this->productSheetProcessesForFlow($rawProcesses, $processes, $aliases, $ps?->type ?? 'producto');

            $displayRef = $ps
                ? (($ps->type ?? '') === 'producto'
                    ? trim((string) ($ps->product_reference ?? ''))
                    : trim((string) ($ps->sample_reference ?? '')))
                : '';
            $sectionId = $ps?->section_id ?? null;
            $groupId = $ps?->group_id ?? null;

            // Fechas estimadas por proceso (JSON)
            $allDates = $this->normalizeBatchProcessDates(is_array($p->process_estimated_dates) ? $p->process_estimated_dates : []);

            // Calcular deadline por proceso (soporta fecha o datetime con hora)
            $deadlines = [];
            foreach ($allDates as $proc => $dateStr) {
                if (!$dateStr) {
                    $deadlines[$proc] = 'none';
                    continue;
                }
                $estDate = Carbon::parse($dateStr);
                // Comparar en días (ignorar hora para determinar urgencia diaria)
                $daysLeft = $today->startOfDay()->diffInDays($estDate->startOfDay(), false);
                if ($daysLeft < 0) {
                    $deadlines[$proc] = 'overdue';
                } elseif ($daysLeft <= 2) {
                    $deadlines[$proc] = 'due_soon';
                } else {
                    $deadlines[$proc] = 'on_time';
                }
            }

            // Última entrega (observaciones del paso anterior)
            $currentProcess = $this->normalizeBatchProcessName($p->current_process);
            $lastDelivery = $p->lastDelivery;

            // Fecha en que llegó al proceso actual (entrega donde to_process = current)
            $arrivalDelivery = null;
            $currentProcessVariants = $this->batchProcessVariants($currentProcess);
            if (!empty($currentProcessVariants)) {
                $arrivalDelivery = BatchControlDelivery::where('production_order_id', $p->id)
                    ->whereIn('to_process', $currentProcessVariants)
                    ->orderByDesc('created_at')
                    ->first();
            }
            $currentProcessArrivedAt = $arrivalDelivery?->created_at?->format('Y-m-d H:i');

            // Estado de plazo para la orden completa (fecha final de entrega)
            $orderDeadlineStatus = 'none';
            if ($p->estimated_delivery_date) {
                $daysLeftOrder = $today->diffInDays($p->estimated_delivery_date, false);
                $orderDeadlineStatus = $daysLeftOrder < 0 ? 'overdue' : ($daysLeftOrder <= 2 ? 'due_soon' : 'on_time');
            }

            return [
                'id' => $p->id,
                'op_code' => $p->op_code,
                'current_process' => $currentProcess,
                'referencia' => $displayRef ?: 'Sin referencia',
                'temporada' => is_numeric($p->season)
                    ? MonthLabel::fromNumber((int) $p->season)
                    : (string) $p->season,
                'prioridad' => $p->priority ?? '-',
                'cant_programadas' => $p->units ?? 0,
                'destino' => $destinations->get($p->destination_id, ['name' => 'N/A'])['name'] ?? 'N/A',
                'seccion' => $sectionId
                    ? ($sections->get($sectionId, ['name' => 'N/A'])['name'] ?? 'N/A')
                    : 'N/A',
                'grupo' => $groupId
                    ? ($groups->get($groupId, ['name' => 'N/A'])['name'] ?? 'N/A')
                    : 'N/A',
                'description' => trim((string) ($ps?->description ?? '')) ?: '-',
                'fecha' => $p->estimated_delivery_date?->format('Y-m-d') ?? '-',
                'product_sheet_type' => $ps?->type ?? 'producto',
                'batch_priority' => $p->batch_priority ?? 0,
                'process_estimated_dates' => $allDates,
                'deadline_statuses' => $deadlines,
                'last_delivery' => $lastDelivery ? [
                    'from_process' => $this->normalizeBatchProcessName($lastDelivery->from_process),
                    'to_process' => $this->normalizeBatchProcessName($lastDelivery->to_process),
                    'observations' => $lastDelivery->observations,
                    'delivered_at' => $lastDelivery->created_at?->toIso8601String(),
                ] : null,
                'has_pending_novelties' => ($p->pending_novelties_count ?? 0) > 0,
                'pending_novelties_count' => $p->pending_novelties_count ?? 0,
                'current_process_arrived_at' => $currentProcessArrivedAt,
                'order_deadline_status' => $orderDeadlineStatus,
                'product_sheet_processes' => $productSheetProcesses,
            ];
        });

        return response()->json([
            'data' => $rows,
        ]);
    }

    /**
     * Entregar una orden al siguiente estado/proceso.
     * Registra la entrega con observaciones y avanza al nuevo proceso.
     */
    public function deliver(Request $request, ProductionOrder $order): JsonResponse
    {
        $validated = $request->validate([
            'to_process' => 'required|string|max:80',
            'observations' => 'nullable|string|max:2000',
        ]);

        $processes = config('product-sheet.batch_control_processes', []);
        $toProcess = $this->normalizeBatchProcessName($validated['to_process']);
        if ($toProcess === null || !in_array($toProcess, $processes, true)) {
            return response()->json(['message' => 'El proceso destino no es válido.'], 422);
        }

        $fromProcess = $this->normalizeBatchProcessName($order->current_process);
        if ($fromProcess === null) {
            return response()->json(['message' => 'La orden no tiene un proceso actual valido.'], 422);
        }

        if ($toProcess === $fromProcess) {
            return response()->json(['message' => 'El proceso destino debe ser diferente al actual.'], 422);
        }

        try {
            DB::transaction(function () use ($order, $fromProcess, $toProcess, $validated, $request) {
                BatchControlDelivery::create([
                    'production_order_id' => $order->id,
                    'from_process' => $fromProcess,
                    'to_process' => $toProcess,
                    'observations' => $validated['observations'] ?? null,
                    'delivered_by' => $request->user()?->id,
                ]);

                $order->update([
                    'current_process' => $toProcess,
                ]);

                if ($this->shouldCreateFinishedProductCatalogItem($fromProcess, $toProcess)) {
                    $this->finishedProductCatalogItemService->ensureForProductionOrder($order);
                }
            });
        } catch (\Throwable $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'message' => "Orden entregada de {$fromProcess} a {$toProcess}.",
            'data' => [
                'id' => $order->id,
                'current_process' => $toProcess,
            ],
        ]);
    }

    private function shouldCreateFinishedProductCatalogItem(string $fromProcess, string $toProcess): bool
    {
        return $fromProcess === 'Inspección y aprobación'
            && $toProcess === 'Bodega';
    }

    /**
     * Actualizar prioridades de órdenes (reordenar).
     */
    public function updatePriorities(Request $request): JsonResponse
    {
        $request->validate([
            'priorities' => 'required|array|min:1',
            'priorities.*.id' => 'required|integer|exists:production_orders,id',
            'priorities.*.batch_priority' => 'required|integer|min:0',
        ]);

        foreach ($request->input('priorities') as $item) {
            ProductionOrder::where('id', $item['id'])
                ->update(['batch_priority' => $item['batch_priority']]);
        }

        return response()->json([
            'message' => 'Prioridades actualizadas.',
        ]);
    }

    /**
     * Actualizar la fecha estimada de un proceso específico.
     * Valida que no supere la fecha final de entrega de la orden.
     */
    public function updateEstimatedDate(Request $request, ProductionOrder $order): JsonResponse
    {
        $request->validate([
            'process' => 'required|string|max:80',
            'estimated_date' => 'nullable|date',
        ]);

        $process = $this->normalizeBatchProcessName($request->input('process'));
        if ($process === null || !in_array($process, config('product-sheet.batch_control_processes', []), true)) {
            return response()->json(['message' => 'El proceso no es valido.'], 422);
        }
        $date = $request->input('estimated_date');

        // Validar que la fecha del proceso no sea anterior a la llegada al proceso
        $arrivalDelivery = null;
        $processVariants = $this->batchProcessVariants($process);
        if (!empty($processVariants)) {
            $arrivalDelivery = BatchControlDelivery::where('production_order_id', $order->id)
                ->whereIn('to_process', $processVariants)
                ->orderByDesc('created_at')
                ->first();
        }
        if ($date && $arrivalDelivery?->created_at) {
            $processDate = Carbon::parse($date);
            $arrivedAt = $arrivalDelivery->created_at;
            if ($processDate->lt($arrivedAt)) {
                return response()->json([
                    'message' => 'La fecha estimada del proceso no puede ser anterior a la hora en que la orden llegó a este proceso (' . $arrivedAt->format('d/m/Y H:i') . ').',
                ], 422);
            }
        }

        // Validar que la fecha del proceso no supere la fecha final de entrega
        if ($date && $order->estimated_delivery_date) {
            $processDate = Carbon::parse($date);
            $finalDate = $order->estimated_delivery_date;

            if ($processDate->gt($finalDate)) {
                return response()->json([
                    'message' => "La fecha estimada del proceso ({$processDate->format('d/m/Y')}) no puede ser posterior a la fecha final de entrega ({$finalDate->format('d/m/Y')}).",
                ], 422);
            }
        }

        $dates = $this->normalizeBatchProcessDates(is_array($order->process_estimated_dates) ? $order->process_estimated_dates : []);

        if ($date) {
            $dates[$process] = $date;
        } else {
            unset($dates[$process]);
        }

        $order->update([
            'process_estimated_dates' => $dates,
        ]);

        return response()->json([
            'message' => 'Fecha estimada actualizada.',
            'data' => [
                'id' => $order->id,
                'process' => $process,
                'estimated_date' => $date,
            ],
        ]);
    }

    /**
     * Historial de entregas de una orden.
     */
    public function deliveryHistory(ProductionOrder $order): JsonResponse
    {
        $deliveries = BatchControlDelivery::where('production_order_id', $order->id)
            ->with('deliveredByUser:id,name')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (BatchControlDelivery $d) => [
                'id' => $d->id,
                'from_process' => $this->normalizeBatchProcessName($d->from_process),
                'to_process' => $this->normalizeBatchProcessName($d->to_process),
                'observations' => $d->observations,
                'delivered_by_name' => $d->deliveredByUser?->name ?? 'N/A',
                'delivered_at' => $d->created_at?->format('d/m/Y H:i'),
                'timestamp' => $d->created_at?->toIso8601String(),
            ]);

        return response()->json([
            'data' => $deliveries,
        ]);
    }

    /**
     * Datos para dialog de consumo de rollos en proceso de corte.
     * Base: rollos programados de la ficha técnica (technical_sheet_rolls.lines).
     * Los largos de trazo y liquidación vienen de la materia prima (raw_materials_uid match).
     */
    public function getCutFabricConsumption(ProductionOrder $order): JsonResponse
    {
        $sheet = $order->technicalSheet;
        $rollLines = [];
        if ($sheet) {
            $snapshot = $sheet->rollsSnapshot()->first();
            $rollLines = is_array($snapshot?->lines) ? array_values($snapshot->lines) : [];
        }

        // Build technical line id -> rollId mapping from snapshot
        $rollIdByTechnicalLineId = [];
        foreach ($rollLines as $line) {
            $technicalLineId = isset($line['id']) ? trim((string) $line['id']) : '';
            $rollId = isset($line['rollId']) ? (int) $line['rollId'] : null;
            if ($technicalLineId !== '' && $rollId) {
                $rollIdByTechnicalLineId[$technicalLineId] = $rollId;
            }
        }

        // Batch-fetch roll metadata from fabric_rolls
        $rollIds = array_values(array_unique(array_filter(array_map(fn (array $l) => isset($l['rollId']) ? (int) $l['rollId'] : null, $rollLines))));
        $rollMetaById = [];
        $rollMetaByNumber = [];
        if (!empty($rollIds)) {
            $rollMetaRows = FabricRoll::query()
                ->select(['id', 'roll_number', 'location', 'internal_color_name', 'internal_color_code'])
                ->whereIn('id', $rollIds)
                ->get();

            foreach ($rollMetaRows as $roll) {
                $meta = [
                    'roll_number' => $roll->roll_number !== null ? (string) $roll->roll_number : null,
                    'location' => $roll->location !== null ? trim((string) $roll->location) : null,
                    'internal_color_name' => $roll->internal_color_name !== null ? trim((string) $roll->internal_color_name) : null,
                    'internal_color_code' => $roll->internal_color_code !== null ? trim((string) $roll->internal_color_code) : null,
                ];

                $rollMetaById[(int) $roll->id] = $meta;

                $rollNumberRaw = trim((string) ($meta['roll_number'] ?? ''));
                if ($rollNumberRaw !== '') {
                    $rollMetaByNumber[$rollNumberRaw] = $meta;
                    $rollMetaByNumber[strtoupper($rollNumberRaw)] = $meta;

                    $normalizedWithoutPrefix = preg_replace('/^RL\s*/i', '', $rollNumberRaw);
                    if (is_string($normalizedWithoutPrefix) && $normalizedWithoutPrefix !== '') {
                        $rollMetaByNumber[$normalizedWithoutPrefix] = $meta;
                        $rollMetaByNumber[strtoupper($normalizedWithoutPrefix)] = $meta;
                    }
                }
            }
        }

        $stored = is_array($order->cut_fabric_consumption) ? $order->cut_fabric_consumption : [];
        $storedLines = is_array($stored['lines'] ?? null) ? array_values($stored['lines']) : [];

        if (!empty($storedLines)) {
            $storedRollCodes = array_values(array_unique(array_filter(array_map(
                static fn (array $line) => trim((string) ($line['roll_code'] ?? '')),
                $storedLines,
            ), static fn ($code) => $code !== '')));

            if (!empty($storedRollCodes)) {
                $normalizedRollCodes = [];
                foreach ($storedRollCodes as $rollCode) {
                    $normalizedRollCodes[] = $rollCode;
                    $withoutPrefix = preg_replace('/^RL\s*/i', '', $rollCode);
                    if (is_string($withoutPrefix) && trim($withoutPrefix) !== '') {
                        $normalizedRollCodes[] = trim($withoutPrefix);
                    }
                }

                $normalizedRollCodes = array_values(array_unique($normalizedRollCodes));

                $rollMetaRowsByNumber = FabricRoll::query()
                    ->select(['id', 'roll_number', 'location', 'internal_color_name', 'internal_color_code'])
                    ->whereIn('roll_number', $normalizedRollCodes)
                    ->get();

                foreach ($rollMetaRowsByNumber as $roll) {
                    $meta = [
                        'roll_number' => $roll->roll_number !== null ? (string) $roll->roll_number : null,
                        'location' => $roll->location !== null ? trim((string) $roll->location) : null,
                        'internal_color_name' => $roll->internal_color_name !== null ? trim((string) $roll->internal_color_name) : null,
                        'internal_color_code' => $roll->internal_color_code !== null ? trim((string) $roll->internal_color_code) : null,
                    ];

                    $rollMetaById[(int) $roll->id] = $meta;

                    $rollNumberRaw = trim((string) ($meta['roll_number'] ?? ''));
                    if ($rollNumberRaw !== '') {
                        $rollMetaByNumber[$rollNumberRaw] = $meta;
                        $rollMetaByNumber[strtoupper($rollNumberRaw)] = $meta;

                        $normalizedWithoutPrefix = preg_replace('/^RL\s*/i', '', $rollNumberRaw);
                        if (is_string($normalizedWithoutPrefix) && $normalizedWithoutPrefix !== '') {
                            $rollMetaByNumber[$normalizedWithoutPrefix] = $meta;
                            $rollMetaByNumber[strtoupper($normalizedWithoutPrefix)] = $meta;
                        }
                    }
                }
            }

            $enrichedStoredLines = array_map(function (array $line) use ($rollIdByTechnicalLineId, $rollMetaById, $rollMetaByNumber): array {
                $technicalRollId = trim((string) ($line['technical_roll_id'] ?? ''));
                $rollCode = trim((string) ($line['roll_code'] ?? ''));

                $rollMeta = null;
                if ($technicalRollId !== '' && isset($rollIdByTechnicalLineId[$technicalRollId])) {
                    $resolvedRollId = (int) $rollIdByTechnicalLineId[$technicalRollId];
                    $rollMeta = $rollMetaById[$resolvedRollId] ?? null;
                }

                if ($rollMeta === null && $rollCode !== '') {
                    $rollMeta = $rollMetaByNumber[$rollCode]
                        ?? $rollMetaByNumber[strtoupper($rollCode)]
                        ?? $rollMetaByNumber[preg_replace('/^RL\s*/i', '', $rollCode) ?: '']
                        ?? null;
                }

                $existingLocation = isset($line['location']) ? trim((string) $line['location']) : null;
                $existingColor = isset($line['color']) ? trim((string) $line['color']) : null;
                $existingColorCode = isset($line['color_code']) ? trim((string) $line['color_code']) : null;

                if (($existingLocation === null || $existingLocation === '') && $rollMeta !== null) {
                    $line['location'] = $rollMeta['location'] ?? null;
                }

                if (($existingColor === null || $existingColor === '')) {
                    $line['color'] = $rollMeta['internal_color_name'] ?? $this->inferColorFromText(isset($line['fabric_reference']) ? (string) $line['fabric_reference'] : null);
                }

                if (($existingColorCode === null || $existingColorCode === '') && $rollMeta !== null) {
                    $line['color_code'] = $rollMeta['internal_color_code'] ?? null;
                }

                if (($rollCode === '' || $rollCode === null) && $rollMeta !== null && !empty($rollMeta['roll_number'])) {
                    $line['roll_code'] = $rollMeta['roll_number'];
                }

                return $line;
            }, $storedLines);

            return response()->json([
                'data' => [
                    'lines'       => $enrichedStoredLines,
                    'curves'      => $stored['curves'] ?? null,
                    'largo_espiga'=> $stored['largo_espiga'] ?? null,
                ],
            ]);
        }

        // Build uid→{largoTrazo, largoLiq} map from raw materials snapshot
        $rawMaterialMap = [];
        if ($sheet) {
            $rawSnapshot = $sheet->rawMaterialsSnapshot()->first();
            if ($rawSnapshot && is_array($rawSnapshot->rows)) {
                foreach ($rawSnapshot->rows as $row) {
                    $uid = $row['uid'] ?? null;
                    if ($uid) {
                        $rawMaterialMap[$uid] = [
                            'largo_trazo' => is_numeric($row['largoTrazo'] ?? null) ? (float) $row['largoTrazo'] : null,
                            'largo_liq'   => is_numeric($row['largoLiq'] ?? null) ? (float) $row['largoLiq'] : null,
                        ];
                    }
                }
            }
        }

        $mappedFromTechnicalData = array_map(function (array $line) use ($rawMaterialMap, $rollMetaById): array {
            $widthCm       = is_numeric($line['widthCm'] ?? null) ? (float) $line['widthCm'] : null;
            $rawType       = strtoupper(trim((string) ($line['type'] ?? '')));
            $rollId        = isset($line['rollId']) ? (int) $line['rollId'] : null;
            $rawMaterialUid = (string) ($line['rawMaterialUid'] ?? '');
            $largos        = $rawMaterialMap[$rawMaterialUid] ?? ['largo_trazo' => null, 'largo_liq' => null];
            $rollMeta      = $rollId ? ($rollMetaById[$rollId] ?? null) : null;

            $fabricReference = trim((string) ($line['fabricName'] ?? ''));
            $lineColor = isset($line['color']) ? trim((string) $line['color']) : null;
            $lineColor = $lineColor !== '' ? $lineColor : null;
            $lineColors = isset($line['colors']) ? trim((string) $line['colors']) : null;
            $lineColors = $lineColors !== '' ? $lineColors : null;
            $lineColorCode = isset($line['colorCode']) ? trim((string) $line['colorCode']) : null;
            $lineColorCode = $lineColorCode !== '' ? $lineColorCode : null;
            $lineLocation = isset($line['location']) ? trim((string) $line['location']) : null;
            $lineLocation = $lineLocation !== '' ? $lineLocation : null;

            return [
                'id'               => (string) Str::uuid(),
                'technical_roll_id'=> isset($line['id']) ? (string) $line['id'] : null,
                'roll_code'        => $rollMeta['roll_number'] ?? null,
                'fabric_reference' => $fabricReference,
                'color'            => $lineColor ?? $lineColors ?? ($rollMeta['internal_color_name'] ?? null) ?? $this->inferColorFromText($fabricReference),
                'color_code'       => $lineColorCode ?? ($rollMeta['internal_color_code'] ?? null),
                'fabric_type'      => in_array($rawType, ['PRINCIPAL', 'SECUNDARIA'], true) ? $rawType : null,
                'location'         => $lineLocation ?? ($rollMeta['location'] ?? null),
                'roll_width_m'     => $widthCm !== null ? round($widthCm / 100, 4) : null,
                'meters_per_roll'  => is_numeric($line['programmedMeters'] ?? null) ? (float) $line['programmedMeters'] : null,
                'largo_trazo'      => $largos['largo_trazo'],
                'largo_liq'        => $largos['largo_liq'],
                'layers_fabric'    => null,
                'layers_liquidation' => null,
                'layers_herringbone' => null,
                'total_consumption'=> null,
                'restante'         => null,
                'ultimo_descargue' => false,
                'retazo_1'         => null,
                'retazo_2'         => null,
                'retazo_3'         => null,
            ];
        }, $rollLines);

        return response()->json([
            'data' => [
                'lines'        => $mappedFromTechnicalData,
                'largo_espiga' => null,
            ],
        ]);
    }

    /**
     * Guardar registro de consumo de telas por orden (proceso corte).
     */
    public function saveCutFabricConsumption(Request $request, ProductionOrder $order): JsonResponse
    {
        $validated = $request->validate([
            'lines' => 'required|array|min:1',
            'lines.*.id' => 'required|string|max:100',
            'lines.*.technical_roll_id' => 'nullable|string|max:100',
            'lines.*.roll_code' => 'nullable|string|max:100',
            'lines.*.fabric_reference' => 'nullable|string|max:200',
            'lines.*.color' => 'nullable|string|max:200',
            'lines.*.color_code' => 'nullable|string|max:100',
            'lines.*.fabric_type' => 'nullable|string|in:PRINCIPAL,SECUNDARIA',
            'lines.*.location' => 'nullable|string|max:200',
            'lines.*.roll_width_m' => 'nullable|numeric|min:0',
            'lines.*.meters_per_roll' => 'nullable|numeric|min:0',
            'lines.*.largo_trazo' => 'nullable|numeric|min:0',
            'lines.*.largo_liq' => 'nullable|numeric|min:0',
            'lines.*.layers_fabric' => 'nullable|integer|min:0',
            'lines.*.layers_liquidation' => 'nullable|integer|min:0',
            'lines.*.layers_herringbone' => 'nullable|integer|min:0',
            'lines.*.total_consumption' => 'nullable|numeric|min:0',
            'lines.*.restante' => 'nullable|numeric',
            'lines.*.ultimo_descargue' => 'nullable|boolean',
            'lines.*.retazo_1' => 'nullable|numeric|min:0',
            'lines.*.retazo_2' => 'nullable|numeric|min:0',
            'lines.*.retazo_3' => 'nullable|numeric|min:0',
            'largo_espiga' => 'nullable|numeric|min:0',
            'curves' => 'nullable|array',
            'curves.principal' => 'nullable|array',
            'curves.liquidacion' => 'nullable|array',
            'curves.espiga' => 'nullable|array',
        ]);

        $payload = [
            'lines'        => array_values($validated['lines']),
            'curves'       => $validated['curves'] ?? null,
            'largo_espiga' => $validated['largo_espiga'] ?? null,
            'updated_at'   => now()->toISOString(),
            'updated_by'   => $request->user()?->id,
        ];

        $order->update([
            'cut_fabric_consumption' => $payload,
        ]);

        return response()->json([
            'message' => 'Registro de consumo de telas guardado.',
            'data' => $payload,
        ]);
    }

    // ─── Novedades / Devoluciones ─────────────────────────

    /**
     * Reportar una novedad al estado anterior.
     */
    public function reportNovelty(Request $request, ProductionOrder $order): JsonResponse
    {
        $validated = $request->validate([
            'to_process' => 'required|string|max:80',
            'description' => 'required|string|max:2000',
        ]);

        $processes = config('product-sheet.batch_control_processes', []);
        $toProcess = $this->normalizeBatchProcessName($validated['to_process']);
        $currentProcess = $this->normalizeBatchProcessName($order->current_process);

        if ($toProcess === null || $currentProcess === null || !in_array($toProcess, $processes, true)) {
            return response()->json(['message' => 'El proceso destino no es válido.'], 422);
        }

        // Validar que to_process sea un estado anterior al actual
        $currentIdx = array_search($currentProcess, $processes, true);
        $targetIdx = array_search($toProcess, $processes, true);

        if ($currentIdx === false || $targetIdx === false || $targetIdx >= $currentIdx) {
            return response()->json([
                'message' => 'Solo se puede reportar una novedad a un estado anterior al actual.',
            ], 422);
        }

        $novelty = BatchControlNovelty::create([
            'production_order_id' => $order->id,
            'from_process' => $currentProcess,
            'to_process' => $toProcess,
            'description' => $validated['description'],
            'status' => 'pending',
            'reported_by' => $request->user()?->id,
        ]);

        // Mover la orden al proceso anterior (desaparece del tab actual, pasa al anterior)
        $order->update([
            'current_process' => $toProcess,
        ]);

        return response()->json([
            'message' => "Novedad reportada a {$toProcess}. La orden ha sido enviada al proceso anterior.",
            'data' => [
                'id' => $novelty->id,
                'from_process' => $novelty->from_process,
                'to_process' => $novelty->to_process,
            ],
        ]);
    }

    /**
     * Resolver una novedad.
     */
    public function resolveNovelty(Request $request, ProductionOrder $order, BatchControlNovelty $novelty): JsonResponse
    {
        if ($novelty->production_order_id !== $order->id) {
            return response()->json(['message' => 'La novedad no pertenece a esta orden.'], 422);
        }

        if ($novelty->isResolved()) {
            return response()->json(['message' => 'La novedad ya fue resuelta.'], 422);
        }

        $request->validate([
            'resolution_notes' => 'nullable|string|max:2000',
        ]);

        $novelty->update([
            'status' => 'resolved',
            'resolved_by' => $request->user()?->id,
            'resolved_at' => now(),
            'resolution_notes' => $request->input('resolution_notes'),
        ]);

        return response()->json([
            'message' => 'Novedad resuelta.',
            'data' => [
                'id' => $novelty->id,
                'status' => 'resolved',
            ],
        ]);
    }

    /**
     * Listar novedades de una orden.
     */
    public function novelties(ProductionOrder $order): JsonResponse
    {
        $novelties = BatchControlNovelty::where('production_order_id', $order->id)
            ->with(['reportedByUser:id,name', 'resolvedByUser:id,name'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (BatchControlNovelty $n) => [
                'id' => $n->id,
                'from_process' => $this->normalizeBatchProcessName($n->from_process),
                'to_process' => $this->normalizeBatchProcessName($n->to_process),
                'description' => $n->description,
                'status' => $n->status,
                'reported_by_name' => $n->reportedByUser?->name ?? 'N/A',
                'reported_at' => $n->created_at?->format('d/m/Y H:i'),
                'timestamp' => $n->created_at?->toIso8601String(),
                'resolved_by_name' => $n->resolvedByUser?->name ?? null,
                'resolved_at' => $n->resolved_at?->format('d/m/Y H:i'),
                'resolution_notes' => $n->resolution_notes,
            ]);

        return response()->json([
            'data' => $novelties,
        ]);
    }

    // ─── Logística: Salida de Insumos ─────────────────────

    /**
     * Insumos de la ficha técnica para proceso de Logística.
     * Devuelve lista de insumos con info del ítem e inventario disponible por bodega.
     */
    public function getLogisticsSupplies(ProductionOrder $order): JsonResponse
    {
        $units = (int) ($order->units ?? 0);

        $supplies = TechnicalDataSheetSupply::query()
            ->where('production_order_id', $order->id)
            ->whereNotNull('item_id')
            ->orderBy('sequence')
            ->orderBy('id')
            ->get();

        // Precarga bodegas con stock para todos los item_ids de esta orden
        $itemIds = $supplies->pluck('item_id')->filter()->unique()->values()->all();

        $warehouseStocks = [];
        if (!empty($itemIds)) {
            $rows = DB::table('warehouse_items')
                ->join('warehouses', 'warehouses.id', '=', 'warehouse_items.warehouse_id')
                ->whereIn('warehouse_items.item_id', $itemIds)
                ->where('warehouse_items.quantity', '>', 0)
                ->select(
                    'warehouse_items.item_id',
                    'warehouse_items.warehouse_id',
                    'warehouse_items.quantity as stock',
                    'warehouses.name as warehouse_name',
                )
                ->get();

            foreach ($rows as $row) {
                $warehouseStocks[$row->item_id][] = [
                    'id'    => (int) $row->warehouse_id,
                    'name'  => $row->warehouse_name,
                    'stock' => (float) $row->stock,
                ];
            }
        }

        // Precarga info de ítems (nombre, referencia, unidad, imagen)
        $items = [];
        if (!empty($itemIds)) {
            $itemModels = \App\Models\MasterData\Item::whereIn('id', $itemIds)
                ->get(['id', 'name', 'reference', 'unit_measure', 'img']);

            foreach ($itemModels as $item) {
                $items[$item->id] = [
                    'name'         => trim((string) $item->name),
                    'reference'    => $item->reference ?? null,
                    'unit_measure' => $item->unit_measure ?? null,
                    'img_url'      => $item->img_url,
                ];
            }
        }

        $lines = $supplies->map(function (TechnicalDataSheetSupply $s) use ($units, $warehouseStocks, $items) {
            $itemId    = $s->item_id;
            $itemInfo  = $itemId ? ($items[$itemId] ?? null) : null;
            $warehouses = $itemId ? ($warehouseStocks[$itemId] ?? []) : [];

            // Ordenar bodegas: mayor stock primero
            usort($warehouses, fn ($a, $b) => $b['stock'] <=> $a['stock']);

            $suggestedQty = $s->qty_per_unit * $units;

            return [
                'uid'              => $s->uid,
                'name'             => $s->name,
                'stage'            => $s->stage,
                'display_label'    => $s->display_label,
                'item_id'          => $itemId,
                'item_name'        => $itemInfo['name'] ?? null,
                'item_reference'   => $itemInfo['reference'] ?? null,
                'item_uom'         => $itemInfo['unit_measure'] ?? null,
                'item_img_url'     => $itemInfo['img_url'] ?? null,
                'uom'              => $s->uom,
                'qty_per_unit'     => (float) $s->qty_per_unit,
                'suggested_qty'    => round($suggestedQty, 4),
                'document_url'     => $s->document_url,
                'warehouses'       => array_values($warehouses),
            ];
        })->values();

        return response()->json([
            'data' => [
                'lines'     => $lines,
                'op_units'  => $units,
                'op_code'   => $order->op_code,
            ],
        ]);
    }

    /**
     * Procesar salidas de inventario de insumos en proceso de Logística.
     */
    public function processLogisticsSupplyExit(Request $request, ProductionOrder $order): JsonResponse
    {
        $validated = $request->validate([
            'lines'                => ['required', 'array', 'min:1'],
            'lines.*.item_id'      => ['required', 'integer', 'exists:items,id'],
            'lines.*.warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'lines.*.quantity'     => ['required', 'numeric', 'min:0.0001'],
            'lines.*.supply_name'  => ['nullable', 'string', 'max:200'],
        ]);

        $user = $request->user();
        $errors = [];
        $processed = 0;

        foreach ($validated['lines'] as $line) {
            try {
                $this->movementService->createMovement([
                    'item_id'             => (int) $line['item_id'],
                    'warehouse_id'        => (int) $line['warehouse_id'],
                    'type'                => 'salida',
                    'quantity'            => (float) $line['quantity'],
                    'date'                => now()->toDateString(),
                    'source_type'         => ProductionOrder::class,
                    'source_id'           => $order->id,
                    'notes'               => 'Salida Logística OP ' . ($order->op_code ?? "#{$order->id}") . ($line['supply_name'] ? ' - ' . $line['supply_name'] : ''),
                    'created_by_user_id'  => $user?->id,
                ]);
                $processed++;
            } catch (\Exception $e) {
                $errors[] = ($line['supply_name'] ?? "Ítem #{$line['item_id']}") . ': ' . $e->getMessage();
            }
        }

        if (!empty($errors) && $processed === 0) {
            return response()->json([
                'message' => 'No se pudo procesar ninguna salida.',
                'errors'  => $errors,
            ], 422);
        }

        $message = "Se registraron {$processed} salida(s) de inventario para la orden {$order->op_code}.";
        if (!empty($errors)) {
            $message .= ' Algunos ítems fallaron: ' . implode('; ', $errors);
        }

        return response()->json([
            'message'   => $message,
            'processed' => $processed,
            'errors'    => $errors,
        ]);
    }

    /**
     * Retorna opciones de catálogo (secciones, operaciones, máquinas, bases de tela)
     * necesarias para el dialog de edición de operaciones en fichas de muestras.
     */
    public function catalogOptions(): JsonResponse
    {
        return response()->json([
            'sections'    => $this->sectionService->options()->map(fn ($s) => [
                'id'   => $s->id,
                'name' => $s->name ?? '',
                'type' => $s->type ?? '',
            ])->values()->all(),
            'operations'  => $this->operationService->options()->map(fn ($o) => [
                'id'           => $o->id,
                'name'         => trim(($o->action ?? '') . ' ' . ($o->garment_part ?? '')),
                'action'       => $o->action ?? '',
                'garment_part' => $o->garment_part ?? '',
                'section_id'   => $o->section_id ?? null,
                'sam_base'     => $o->sam_base !== null ? (float) $o->sam_base : null,
            ])->values()->all(),
            'machines'    => $this->machineService->options()->map(fn ($m) => [
                'id'                => $m->id,
                'code'              => $m->code ?? '',
                'name'              => $m->name ?? '',
                'supplement_factor' => $m->supplement_factor !== null ? (float) $m->supplement_factor : 1.0,
            ])->values()->all(),
            'fabric_bases' => $this->settingService->getFabricBasesWithFactor()->map(fn ($f) => [
                'id'     => $f['id'],
                'name'   => $f['name'] ?? $f['nombre'] ?? $f['codigo'] ?? (string) $f['id'],
                'factor' => $f['factor'] ?? $f['factor_base'] ?? $f['factor_base_tela'] ?? 1.0,
            ])->values()->all(),
            'actions' => $this->operationCatalogService->actionOptions()->values()->all(),
            'garmentParts' => $this->operationCatalogService->garmentPartOptions()->values()->all(),
            'preparation_groups' => $this->settingService->getCatalogOptions('preparation_groups')->pluck('name')->values()->all(),
        ]);
    }

    /**
     * Sincroniza (reemplaza) las operaciones del snapshot TechnicalSheet de una orden.
     * Solo permitido para órdenes de tipo muestra.
     */
    public function syncOperations(Request $request, ProductionOrder $order): JsonResponse
    {
        $validated = $request->validate([
            'operations'                                => ['required', 'array'],
            'operations.*.operation_id'                 => ['nullable', 'integer', 'exists:operations,id'],
            'operations.*.machine_id'                   => ['nullable', 'integer', 'exists:machines,id'],
            'operations.*.section_id'                   => ['nullable', 'integer'],
            'operations.*.sequence'                     => ['required', 'integer', 'min:1'],
            'operations.*.optimal_time'                 => ['required', 'numeric', 'min:0'],
            'operations.*.machine_supplement_factor'    => ['required', 'numeric', 'min:1'],
            'operations.*.operator_efficiency_factor'   => ['required', 'numeric', 'between:1,2'],
            'operations.*.quantity'                     => ['nullable', 'integer', 'min:1'],
            'operations.*.fabric_base_id'               => ['nullable', 'integer'],
        ]);

        $quantityLote = max(1, (int) ($order->units ?? 1));

        DB::transaction(function () use ($order, $validated, $quantityLote) {
            $technicalSheet = TechnicalSheet::firstOrCreate(
                ['production_order_id' => $order->id],
                ['status' => TechnicalSheet::STATUS_DRAFT],
            );

            TechnicalSheetOperation::where('technical_sheet_id', $technicalSheet->id)->forceDelete();

            foreach ($validated['operations'] as $op) {
                $samBase                 = (float) $op['optimal_time'];
                $machineSupplementFactor = (float) $op['machine_supplement_factor'];
                $fabricBaseFactor        = $this->resolveFabricBaseFactor($op['fabric_base_id'] ?? null);
                $operatorEffFactor       = (float) $op['operator_efficiency_factor'];
                $quantity                = (int) ($op['quantity'] ?? 1);

                $samTotalUnitario  = round($samBase * $machineSupplementFactor * $fabricBaseFactor * $operatorEffFactor, 4);
                $samTotalOperacion = round($samTotalUnitario * $quantity, 4);
                $samTotalLote      = round($samTotalOperacion * $quantityLote, 4);

                TechnicalSheetOperation::create([
                    'technical_sheet_id'         => $technicalSheet->id,
                    'product_sheet_operation_id' => null,
                    'sequence'                   => $op['sequence'],
                    'operation_id'               => $op['operation_id'] ?? null,
                    'machine_id'                 => $op['machine_id'] ?? null,
                    'quantity'                   => $quantity,
                    'sam_base'                   => $samBase,
                    'machine_supplement_factor'  => $machineSupplementFactor,
                    'fabric_base_factor'         => $fabricBaseFactor,
                    'operator_efficiency_factor' => $operatorEffFactor,
                    'sam_total_unitario'         => $samTotalUnitario,
                    'sam_total_operacion'        => $samTotalOperacion,
                    'sam_total_lote'             => $samTotalLote,
                    'quantity_progressed'        => 0,
                    'status'                     => EstatusOperacionOrden::PENDIENTE,
                ]);
            }
        });

        return response()->json(['ok' => true]);
    }

    private function resolveFabricBaseFactor(int|string|null $fabricBaseId): float
    {
        if ($fabricBaseId === null || $fabricBaseId === '') {
            return 1.0;
        }

        $option = $this->settingService->getCatalogOptionById('fabric_bases', $fabricBaseId);
        if (! $option) {
            return 1.0;
        }

        $factor = $option['factor'] ?? $option['factor_base'] ?? $option['factor_base_tela'] ?? null;
        $value  = is_numeric($factor) ? (float) $factor : 1.0;

        return $value > 0 ? $value : 1.0;
    }
}
