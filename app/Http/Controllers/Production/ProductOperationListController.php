<?php

namespace App\Http\Controllers\Production;

use App\Http\Controllers\Controller;
use App\Models\Production\ProductOperationList;
use App\Services\ProductSheet\ProductOperationListService;
use App\Services\ProductSheet\OperationService;
use App\Services\ProductSheet\MachineService;
use App\Services\ProductSheet\OperationSectionService;
use App\Services\ProductSheet\ProductSheetService;
use App\Http\Requests\Production\StoreProductOperationListRequest;
use App\Http\Requests\Production\UpdateProductOperationListRequest;
use App\Http\Resources\Production\ProductOperationListResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Facades\Auth;

class ProductOperationListController extends Controller
{
    public function __construct(
        protected ProductOperationListService $service,
        protected OperationService $operationService,
        protected MachineService $machineService,
        protected OperationSectionService $sectionService,
        protected ProductSheetService $sheetService
    ) {
    }

    public function options(): JsonResponse
    {
        return response()->json([
            'sections' => $this->sectionService->options(),
            'operations' => $this->operationService->options(),
            'machines' => $this->machineService->options(),
            'productSheets' => $this->sheetService->recentOptions(50),
            'actions' => $this->operationService->getUniqueActions(),
            'garmentParts' => $this->operationService->getUniqueGarmentParts(),
        ]);
    }

    /**
     * Renderiza la vista principal con los datos necesarios
     * GET /diseno/listados-operacionales
     */
    public function indexView(): Response
    {
        return Inertia::render('ProductOperationList/Index', [
            'sections' => $this->sectionService->options(),
            'operations' => $this->operationService->options(),
            'machines' => $this->machineService->options(),
            'productSheets' => $this->sheetService->recentOptions(50),
            'actions' => $this->operationService->getUniqueActions(),
            'garmentParts' => $this->operationService->getUniqueGarmentParts(),
        ]);
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
        ]);
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
     * Crea una nueva operación en el listado
     * POST /diseno/listados-operacionales
     */
    public function store(StoreProductOperationListRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $validated['created_by'] = Auth::id();

        $item = $this->service->create($validated);

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
            'operations.*.machine_id' => ['required', 'integer', 'exists:machines,id'],
            'operations.*.operation_section_id' => ['required', 'integer', 'exists:operation_sections,id'],
            'operations.*.sequence_order' => ['required', 'integer', 'min:1'],
            'operations.*.optimal_time' => ['required', 'numeric', 'min:0'],
            'operations.*.operator_efficiency_factor' => ['required', 'numeric', 'between:1,2'],
            'operations.*.machine_supplement_factor' => ['required', 'numeric', 'min:1'],
            'operations.*.actual_time' => ['nullable', 'numeric', 'min:0'],
            'operations.*.notes' => ['nullable', 'string', 'max:1000'],
        ]);

        // Añadir created_by a cada operación
        $operations = collect($validated['operations'])->map(function ($op) {
            $op['created_by'] = Auth::id();
            return $op;
        })->toArray();

        $items = $this->service->createBulk($operations);

        return response()->json([
            'message' => count($items) . ' operaciones agregadas correctamente.',
            'data' => $items
        ], 201);
    }

    /**
     * Actualiza una operación existente
     * PUT /diseno/listados-operacionales/{listItem}
     */
    public function update(UpdateProductOperationListRequest $request, ProductOperationList $listItem): JsonResponse
    {
        $validated = $request->validated();
        $validated['updated_by'] = Auth::id();

        $item = $this->service->update($listItem, $validated);

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
            'operation_ids.*' => ['integer', 'exists:product_operation_lists,id'],
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
            'operation_ids.*' => ['integer', 'exists:product_operation_lists,id'],
        ]);

        $this->service->reorder(
            (int) $validated['product_sheet_id'],
            $validated['operation_ids']
        );

        return response()->json([
            'message' => 'Orden actualizado correctamente.'
        ]);
    }
}
