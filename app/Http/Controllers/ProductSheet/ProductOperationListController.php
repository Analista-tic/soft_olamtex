<?php

namespace App\Http\Controllers\ProductSheet;

use App\Http\Controllers\Controller;
use App\Models\ProductSheet\ProductOperationList;
use App\Models\ProductSheet\Operation;
use App\Models\ProductSheet\Machine;
use App\Models\ProductSheet\OperationSection;
use App\Models\ProductSheet\ProductSheet;
use App\Services\ProductSheet\ProductOperationListService;
use App\Services\ProductSheet\OperationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class ProductOperationListController extends Controller
{
    public function __construct(
        protected ProductOperationListService $service,
        protected OperationService $operationService
    ) {
    }

    /**
     * Renderiza la vista principal con los datos necesarios
     * GET /diseno/listados-operacionales
     */
    public function indexView(): Response
    {
        $operations = Operation::active()->get(['id', 'action', 'garment_part'])->map(function ($op) {
            return [
                'id' => $op->id,
                'name' => $op->name,
                'action' => $op->action,
                'garment_part' => $op->garment_part,
            ];
        });

        return Inertia::render('ProductOperationList/Index', [
            'sections' => OperationSection::active()->ordered()->get(['id', 'code', 'name']),
            'operations' => $operations,
            'machines' => Machine::active()->get(['id', 'code', 'name']),
            'productSheets' => ProductSheet::orderBy('created_at', 'desc')
                ->take(50)
                ->get(['id', 'reference'])
                ->map(function ($sheet) {
                    return [
                        'id' => $sheet->id,
                        'reference' => $sheet->reference,
                        'type' => 'muestra',
                    ];
                }),
            'actions' => $this->operationService->getUniqueActions(),
            'garmentParts' => $this->operationService->getUniqueGarmentParts(),
        ]);
    }

    /**
     * Lista todas las operaciones de una ficha
     * GET /api/product-operation-lists?product_sheet_id=1
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'product_sheet_id' => ['required', 'integer', 'exists:product_sheets,id'],
        ]);

        $lists = $this->service->listBySheet((int) $request->input('product_sheet_id'));

        return response()->json(['data' => $lists]);
    }

    /**
     * Crea una nueva operación en el listado
     * POST /api/product-operation-lists
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_sheet_id' => ['required', 'integer', 'exists:product_sheets,id'],
            'operation_id' => ['required', 'integer', 'exists:operations,id'],
            'machine_id' => ['required', 'integer', 'exists:machines,id'],
            'operation_section_id' => ['required', 'integer', 'exists:operation_sections,id'],
            'sequence_order' => ['required', 'integer', 'min:1'],
            'optimal_time' => ['required', 'numeric', 'min:0'],
            'operator_efficiency_factor' => ['required', 'numeric', 'between:1,2'],
            'machine_supplement_factor' => ['required', 'numeric', 'min:1'],
            'actual_time' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $validated['created_by'] = auth()->id();

        $item = $this->service->create($validated);

        return response()->json(['data' => $item], 201);
    }

    /**
     * Actualiza una operación existente
     * PUT /api/product-operation-lists/{id}
     */
    public function update(Request $request, ProductOperationList $listItem): JsonResponse
    {
        $validated = $request->validate([
            'operation_id' => ['sometimes', 'integer', 'exists:operations,id'],
            'machine_id' => ['sometimes', 'integer', 'exists:machines,id'],
            'operation_section_id' => ['sometimes', 'integer', 'exists:operation_sections,id'],
            'sequence_order' => ['sometimes', 'integer', 'min:1'],
            'optimal_time' => ['sometimes', 'numeric', 'min:0'],
            'operator_efficiency_factor' => ['sometimes', 'numeric', 'between:1,2'],
            'machine_supplement_factor' => ['sometimes', 'numeric', 'min:1'],
            'actual_time' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $validated['updated_by'] = auth()->id();

        $item = $this->service->update($listItem, $validated);

        return response()->json(['data' => $item]);
    }

    /**
     * Elimina una operación del listado
     * DELETE /api/product-operation-lists/{id}
     */
    public function destroy(ProductOperationList $listItem): JsonResponse
    {
        $this->service->delete($listItem);

        return response()->json(['message' => 'Operación eliminada correctamente.']);
    }

    /**
     * Copia operaciones de una ficha a otra
     * POST /api/product-operation-lists/copy
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
            'message' => 'Operaciones copiadas correctamente.',
            'data' => $copied,
        ]);
    }

    /**
     * Reordena las operaciones
     * POST /api/product-operation-lists/reorder
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

        return response()->json(['message' => 'Orden actualizado correctamente.']);
    }
}
