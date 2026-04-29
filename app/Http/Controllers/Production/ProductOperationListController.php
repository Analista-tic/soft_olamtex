<?php

namespace App\Http\Controllers\Production;

use App\Http\Controllers\Controller;
use App\Models\Production\ProductOperationList;
use App\Models\Production\ProductSheetOperationHistory;
use App\Services\Production\ProductOperationListService;
use App\Services\Production\OperationService;
use App\Services\Production\OperationCatalogService;
use App\Services\Production\MachineService;
use App\Services\Production\OperationSectionService;
use App\Services\Production\ProductSheetService;
use App\Services\Settings\SettingService;
use App\Http\Requests\Production\StoreProductOperationListRequest;
use App\Http\Requests\Production\UpdateProductOperationListRequest;
use App\Http\Resources\Production\ProductOperationListResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class ProductOperationListController extends Controller
{
    public function __construct(
        protected ProductOperationListService $service,
        protected OperationService $operationService,
        protected OperationCatalogService $operationCatalogService,
        protected MachineService $machineService,
        protected OperationSectionService $sectionService,
        protected ProductSheetService $sheetService,
        protected SettingService $settingService,
    ) {
    }

    private function actionOptions()
    {
        return $this->operationCatalogService->actionOptions();
    }

    private function garmentPartOptions()
    {
        return $this->operationCatalogService->garmentPartOptions();
    }

    public function options(): JsonResponse
    {
        return response()->json([
            'sections' => $this->sectionService->options(),
            'operations' => $this->operationService->options(),
            'machines' => $this->machineService->options(),
            'productSheets' => $this->sheetService->recentOptions(50),
            'fabric_bases' => $this->settingService->getFabricBasesWithFactor()->values()->all(),
            'actions' => $this->actionOptions(),
            'garmentParts' => $this->garmentPartOptions(),
        ]);
    }

    /**
     * Renderiza la vista principal con los datos necesarios
     * GET /diseno/listados-operacionales
     */
    public function indexView(): Response
    {
        return Inertia::render('Production/ProductOperationList/Index', [
            'sections' => $this->sectionService->options(),
            'operations' => $this->operationService->options(),
            'machines' => $this->machineService->options(),
            'productSheets' => $this->sheetService->recentOptions(50),
            'fabric_bases' => $this->settingService->getFabricBasesWithFactor()->values()->all(),
            'preparation_groups' => $this->settingService->getCatalogOptions('preparation_groups')->pluck('name')->values()->all(),
            'actions' => $this->actionOptions(),
            'garmentParts' => $this->garmentPartOptions(),
            'costParameters' => $this->getCostParameters(),
        ]);
    }

    /**
     * Parámetros de costo para listado operacional (desde parametros).
     * - operation_cost: Costo de operaciones ($/min)
     * - yarn_cost: Costo hilo (fijo por producto)
     */
    private function getCostParameters(): array
    {
        $operationCost = $this->settingService->getNumericParameter('operation_cost', 406.0);
        $yarnCost = $this->settingService->getNumericParameter('yarn_cost', 0);

        return [
            'operation_cost' => round($operationCost, 4),
            'yarn_cost' => round($yarnCost, 4),
        ];
    }

    /**
     * Lista todas las operaciones de una ficha
     * GET /diseno/listados-operacionales/data?product_sheet_id=1
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'product_sheet_id' => ['required', 'integer', 'exists:product_sheets,id'],
        ]);

        $lists = $this->service->listBySheet((int) $request->input('product_sheet_id'));

        return response()->json([
            'data' => ProductOperationListResource::collection($lists)->resolve()
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }

    /**
     * Resumen agregado de listados operacionales por sábana.
     * GET /produccion/listados-operacionales/summaries
     */
    public function summaries(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_sheet_ids' => ['nullable', 'array'],
            'product_sheet_ids.*' => ['integer', 'exists:product_sheets,id'],
        ]);

        $sheetIds = collect($validated['product_sheet_ids'] ?? [])
            ->map(static fn ($id): int => (int) $id)
            ->filter(static fn (int $id): bool => $id > 0)
            ->values()
            ->all();

        $summaries = $this->service->getSheetSummaries($sheetIds);

        return response()->json([
            'data' => $summaries,
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }

    /**
     * Obtiene totales calculados de una ficha
     * GET /diseno/listados-operacionales/totals?product_sheet_id=1
     */
    public function totals(Request $request): JsonResponse
    {
        $request->validate([
            'product_sheet_id' => ['required', 'integer', 'exists:product_sheets,id'],
        ]);

        $totals = $this->service->getSheetTotals((int) $request->input('product_sheet_id'));

        return response()->json(['data' => $totals]);
    }

    /**
     * Obtiene operaciones agrupadas jerárquicamente por sección y grupo_preparacion
     * GET /diseno/listados-operacionales/grouped?product_sheet_id=1
     */
    public function getGroupedBySection(Request $request): JsonResponse
    {
        $request->validate([
            'product_sheet_id' => ['required', 'integer', 'exists:product_sheets,id'],
        ]);

        $productSheetId = (int) $request->input('product_sheet_id');
        $grouped = $this->service->getOperationsHierarchicallyGrouped($productSheetId);
        $samBySection = $this->service->getTotalSamBySection($productSheetId);

        return response()->json([
            'data' => $grouped,
            'totals' => $samBySection,
        ]);
    }

    /**
     * Obtiene grupos de preparación disponibles en una ficha
     * GET /diseno/listados-operacionales/grupos-preparacion?product_sheet_id=1
     */
    public function getPreparacionGroups(Request $request): JsonResponse
    {
        $request->validate([
            'product_sheet_id' => ['required', 'integer', 'exists:product_sheets,id'],
        ]);

        $grupos = $this->service->getPreparacionGroupsInSheet((int) $request->input('product_sheet_id'));

        return response()->json(['data' => $grupos]);
    }

    /**
     * Crea una nueva operación en el listado
     * POST /diseno/listados-operacionales
     */
    public function store(StoreProductOperationListRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            // Validar que grupo_preparacion sea requerido para PREPARACION
            $this->service->validateGrupoPreparacionRequired($validated);
            $item = $this->service->create($validated);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'type' => 'warning',
            ], 422);
        }

        return response()->json([
            'message' => 'Operación agregada correctamente.',
            'data' => new ProductOperationListResource($item)
        ], 201);
    }

    /**
     * Crea múltiples operaciones en lote
     * POST /diseno/listados-operacionales/bulk
     */
    public function storeBulk(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'operations' => ['required', 'array', 'min:1'],
            'operations.*.product_sheet_id' => ['required', 'integer', 'exists:product_sheets,id'],
            'operations.*.operation_id' => ['required', 'integer', 'exists:operations,id'],
            'operations.*.machine_id' => ['nullable', 'integer', 'exists:machines,id'],
            'operations.*.operation_section_id' => ['required', 'integer', 'exists:operation_sections,id'],
            'operations.*.grupo_preparacion' => ['nullable', 'string', 'max:100'],
            'operations.*.sequence' => ['required', 'integer', 'min:1'],
            'operations.*.optimal_time' => ['required', 'numeric', 'min:0'],
            'operations.*.operator_efficiency_factor' => ['required', 'numeric', 'between:0.5,2'],
            'operations.*.machine_supplement_factor' => ['required', 'numeric', 'min:1'],
            'operations.*.fabric_base_id' => ['nullable', 'integer'],
            'operations.*.quantity' => ['nullable', 'integer', 'min:1'],
            'operations.*.actual_time' => ['nullable', 'numeric', 'min:0'],
            'operations.*.notes' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            // Validar grupo_preparacion para cada operación
            foreach ($validated['operations'] as $opData) {
                $this->service->validateGrupoPreparacionRequired($opData);
            }
            $items = $this->service->createBulk($validated['operations']);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'type' => 'warning',
            ], 422);
        }

        return response()->json([
            'message' => count($items) . ' operaciones agregadas correctamente.',
            'data' => $items
        ], 201);
    }

    /**
     * Sincroniza completamente el listado operacional de una sábana.
     * Actualiza filas existentes por id, crea nuevas y elimina solo las removidas del payload.
     */
    public function sync(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_sheet_id' => ['required', 'integer', 'exists:product_sheets,id'],
            'operations' => ['present', 'array'],
            'operations.*.id' => ['nullable', 'integer', 'exists:product_sheet_operations,id'],
            'operations.*.product_sheet_id' => ['nullable', 'integer', 'exists:product_sheets,id'],
            'operations.*.operation_id' => ['required', 'integer', 'exists:operations,id'],
            'operations.*.machine_id' => ['nullable', 'integer', 'exists:machines,id'],
            'operations.*.operation_section_id' => ['required', 'integer', 'exists:operation_sections,id'],
            'operations.*.grupo_preparacion' => ['nullable', 'string', 'max:100'],
            'operations.*.sequence' => ['required', 'integer', 'min:1'],
            'operations.*.optimal_time' => ['required', 'numeric', 'min:0'],
            'operations.*.operator_efficiency_factor' => ['required', 'numeric', 'between:0.5,2'],
            'operations.*.machine_supplement_factor' => ['required', 'numeric', 'min:1'],
            'operations.*.fabric_base_id' => ['nullable', 'integer'],
            'operations.*.quantity' => ['nullable', 'integer', 'min:1'],
            'operations.*.actual_time' => ['nullable', 'numeric', 'min:0'],
            'operations.*.notes' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            foreach ($validated['operations'] as $opData) {
                $this->service->validateGrupoPreparacionRequired($opData);
            }

            $items = $this->service->syncBySheet(
                (int) $validated['product_sheet_id'],
                $validated['operations'],
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'type' => 'warning',
            ], 422);
        }

        return response()->json([
            'message' => 'Listado operacional sincronizado correctamente.',
            'data' => ProductOperationListResource::collection($items)->resolve(),
        ]);
    }

    /**
     * Actualiza una operación existente
     * PUT /diseno/listados-operacionales/{listItem}
     */
    public function update(UpdateProductOperationListRequest $request, ProductOperationList $listItem): JsonResponse
    {
        $validated = $request->validated();
        $motivo = $validated['motivo'] ?? '';
        unset($validated['motivo']);

        try {
            // Validar que grupo_preparacion sea requerido para PREPARACION
            if (isset($validated['operation_section_id']) || isset($validated['grupo_preparacion'])) {
                $sectionId = $validated['operation_section_id'] ?? $listItem->operation_section_id;
                $grupoData = ['operation_section_id' => $sectionId, 'grupo_preparacion' => $validated['grupo_preparacion'] ?? $listItem->grupo_preparacion];
                $this->service->validateGrupoPreparacionRequired($grupoData);
            }
            $item = $this->service->update($listItem, $validated, $motivo);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'type' => 'warning',
            ], 422);
        }

        return response()->json([
            'message' => 'Operación actualizada correctamente.',
            'data' => new ProductOperationListResource($item)
        ]);
    }

    /**
     * Elimina una operación del listado
     * DELETE /diseno/listados-operacionales/{listItem}
     */
    public function destroy(ProductOperationList $listItem): JsonResponse
    {
        $this->service->delete($listItem);

        return response()->json([
            'message' => 'Operación eliminada correctamente.'
        ]);
    }

    /**
     * Elimina múltiples operaciones en lote
     * DELETE /diseno/listados-operacionales/bulk
     */
    public function destroyBulk(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_sheet_id' => ['required', 'integer', 'exists:product_sheets,id'],
            'operation_ids' => ['required', 'array', 'min:1'],
            'operation_ids.*' => ['integer', 'exists:product_sheet_operations,id'],
        ]);

        $count = $this->service->deleteBulk(
            $validated['operation_ids'],
            (int) $validated['product_sheet_id']
        );

        return response()->json([
            'message' => "{$count} operaciones eliminadas correctamente."
        ]);
    }

    /**
     * Copia operaciones de una ficha a otra
     * POST /diseno/listados-operacionales/copy
     */
    public function copyFromProduct(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'source_sheet_id' => ['required', 'integer', 'exists:product_sheets,id'],
            'target_sheet_id' => ['required', 'integer', 'exists:product_sheets,id'],
        ]);

        $copied = $this->service->copyFromProduct(
            (int) $validated['source_sheet_id'],
            (int) $validated['target_sheet_id'],
        );

        return response()->json([
            'message' => count($copied) . ' operaciones copiadas correctamente.',
            'data' => ProductOperationListResource::collection($copied)->resolve(),
        ]);
    }

    /**
     * Reordena las operaciones
     * POST /diseno/listados-operacionales/reorder
     */
    public function reorder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_sheet_id' => ['required', 'integer', 'exists:product_sheets,id'],
            'operation_ids' => ['required', 'array', 'min:1'],
            'operation_ids.*' => ['integer', 'exists:product_sheet_operations,id'],
        ]);

        $this->service->reorder(
            (int) $validated['product_sheet_id'],
            $validated['operation_ids']
        );

        return response()->json([
            'message' => 'Orden actualizado correctamente.'
        ]);
    }

    /**
     * Obtiene el historial de cambios de una operación
     * GET /produccion/listados-operacionales/{listItem}/history
     */
    public function history(ProductOperationList $listItem): JsonResponse
    {
        $history = ProductSheetOperationHistory::where('product_sheet_operation_id', $listItem->id)
            ->with('usuario:id,name')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['data' => $history]);
    }
}
