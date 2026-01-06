<?php

namespace App\Http\Controllers\ProductSheet;

use App\Http\Controllers\Controller;
use App\Models\ProductSheet\ProductOperationList;
use App\Services\ProductSheet\ProductOperationListService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class ProductOperationListController extends Controller
{
    public function __construct(protected ProductOperationListService $service)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $sheetId = (int) $request->input('product_sheet_id', 0);

        if ($sheetId <= 0) {
            return response()->json(['message' => 'El identificador de ficha es requerido.'], 422);
        }

        $lists = $this->service->listBySheet($sheetId);

        return response()->json(['data' => $lists]);
    }

    public function store(Request $request): JsonResponse
    {
        $payload = $this->validateData($request);
        $item = $this->service->create($payload);

        return response()->json(['data' => $item], 201);
    }

    public function update(Request $request, ProductOperationList $listItem): JsonResponse
    {
        $payload = $this->validateData($request, true);
        $item = $this->service->update($listItem, $payload);

        return response()->json(['data' => $item]);
    }

    public function destroy(ProductOperationList $listItem): JsonResponse
    {
        $this->service->delete($listItem);

        return response()->json(['message' => 'Listado eliminado.']);
    }

    public function copyFromProduct(Request $request): JsonResponse
    {
        $request->validate([
            'source_sheet_id' => ['required', 'integer'],
            'target_sheet_id' => ['required', 'integer'],
        ]);

        $copied = $this->service->copyFromProduct(
            (int) $request->input('source_sheet_id'),
            (int) $request->input('target_sheet_id'),
        );

        return response()->json(['data' => $copied]);
    }

    protected function validateData(Request $request, bool $sometimes = false): array
    {
        $rules = [
            'product_sheet_id' => ['required', 'integer'],
            'operation_id' => ['required', 'integer'],
            'machine_id' => ['required', 'integer'],
            'sequence_order' => ['required', 'integer', 'min:1'],
            'section' => ['required', 'in:delantero,trasero,pretina,ensamble,terminacion,presentacion'],
            'optimal_time' => ['required', 'numeric', 'min:0'],
            'operator_efficiency_factor' => ['required', 'numeric', 'between:1,2'],
            'machine_supplement_factor' => ['required', 'numeric', 'min:1'],
            'is_active' => ['sometimes', 'boolean'],
            'operator_name' => ['nullable', 'string', 'max:191'],
            'actual_time' => ['nullable', 'numeric', 'min:0'],
            'variance' => ['nullable', 'numeric'],
            'notes' => ['nullable', 'string'],
            'created_by' => ['sometimes', 'integer'],
            'updated_by' => ['sometimes', 'integer'],
        ];

        $method = $sometimes ? 'sometimes' : 'required';
        $validated = $request->validate($rules);

        if ($sometimes) {
            $only = array_filter($validated, static fn ($value) => $value !== null);
            return $only;
        }

        return $validated;
    }
}
