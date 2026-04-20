<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Concerns\HandlesValidationErrors;
use App\Http\Controllers\Controller;
use App\Models\Inventory\InventoryAdjustment;
use App\Services\Inventory\InventoryAdjustmentService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class InventoryAdjustmentController extends Controller
{
    use HandlesValidationErrors;

    public function __construct(protected InventoryAdjustmentService $service) {}
    /**
     * Devuelve los ajustes paginados para la tabla (API).
     */
    public function getData(Request $request)
    {
        $perPage = $request->input('per_page', 10);
        $sortBy = $request->input('sort_by', 'date');
        $sortDir = in_array($request->input('sort_direction'), ['asc', 'desc'], true)
            ? $request->input('sort_direction')
            : 'desc';
        $allowedSorts = ['date', 'total'];

        if (!in_array($sortBy, $allowedSorts, true)) {
            $sortBy = 'date';
        }

        $query = InventoryAdjustment::query();

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('document_number', 'like', "%{$search}%")
                    ->orWhere('observation', 'like', "%{$search}%");
            });
        }

        if ($request->filled('type') && in_array($request->input('type'), ['entrada', 'salida'], true)) {
            $query->where('type', $request->input('type'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('date', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('date', '<=', $request->input('date_to'));
        }

        if ($sortBy === 'total') {
            $query->orderByRaw("CAST(total AS DECIMAL(15,4)) {$sortDir}");
        } else {
            $query->orderBy($sortBy, $sortDir);
        }

        $adjustments = $query
            ->orderByDesc('id')
            ->paginate($perPage);

        return response()->json([
            'data' => $adjustments->items(),
            'total' => $adjustments->total(),
            'current_page' => $adjustments->currentPage(),
            'last_page' => $adjustments->lastPage(),
            'sort_by' => $sortBy,
            'sort_direction' => $sortDir,
        ]);
    }

    /**
     * (Opcional) Listado simple o redireccion.
     */
    public function index()
    {
        return response()->json([
            'message' => 'Use /ajustes-inventario/data para obtener los ajustes paginados.',
        ]);
    }

    /**
     * Devuelve un ajuste con sus lineas.
     */
    public function show($id)
    {
        $adjustment = $this->service->findOrFail($id);

        return response()->json($adjustment);
    }

    /**
     * Guarda un nuevo ajuste de inventario.
     */
    public function store(Request $request)
    {
        $this->decodeLinesJson($request);

        try {
            $data = $this->validateAdjustment($request, $this->service->movementReasons());
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        }

        try {
            $adjustment = $this->service->createAdjustment($data);

            return response()->json([
                'message' => 'Ajuste de inventario creado correctamente',
                'data' => $adjustment,
            ], 201);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Ocurrio un error al guardar el ajuste de inventario.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Actualiza un ajuste existente.
     */
    public function update(Request $request, $id)
    {
        $this->decodeLinesJson($request);

        try {
            $data = $this->validateAdjustment($request, $this->service->movementReasons(), InventoryAdjustment::find($id));
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        }

        try {
            $updated = $this->service->updateAdjustment($id, $data);

            return response()->json([
                'message' => 'Ajuste de inventario actualizado correctamente',
                'data' => $updated,
            ]);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Ocurrio un error al actualizar el ajuste de inventario.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Elimina un ajuste y sus lineas.
     */
    public function destroy($id)
    {
        $this->service->deleteAdjustment($id);

        return response()->noContent();
    }

    /**
     * Devuelve el siguiente numero consecutivo sugerido (para mostrar en el formulario).
     */
    public function nextNumber()
    {
        $next = $this->service->nextNumber();

        return response()->json(['document_number' => $next]);
    }

    public function movementReasons()
    {
        return response()->json([
            'data' => $this->service->movementReasonOptions(),
        ]);
    }

    /**
     * Mapeo de motivos de ajuste a tipo de movimiento (entrada/salida).
     */
    private function decodeLinesJson(Request $request): void
    {
        if ($request->has('lines') && is_string($request->input('lines'))) {
            $decoded = json_decode($request->input('lines'), true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $request->merge(['lines' => $decoded]);
            }
        }
    }

    private function validateAdjustment(Request $request, array $movementReasons, ?InventoryAdjustment $adjustment = null): array
    {
        $uniqueRule = Rule::unique('inventory_adjustments', 'document_number');
        if ($adjustment) {
            $uniqueRule->ignore($adjustment->id);
        }

        $rules = [
            'document_number' => [
                'nullable',
                'string',
                'max:255',
                $uniqueRule,
            ],
            'date' => 'required|date',
            'type' => 'required|in:entrada,salida',
            'movement_reason' => ['required', Rule::in(array_keys($movementReasons))],
            'observation' => 'nullable|string',
            'production_order' => 'nullable|string|max:255',
            'subtotal' => 'nullable|numeric',
            'iva' => 'nullable|numeric',
            'total' => 'nullable|numeric',
            'lines' => 'required|array|min:1',
            'lines.*.item_id' => 'required|integer|exists:items,id',
            'lines.*.warehouse_id' => 'nullable|integer|exists:warehouses,id',
            'lines.*.price' => 'required|numeric|min:0',
            'lines.*.tax' => 'nullable|numeric|min:0',
            'lines.*.quantity' => 'required|integer|min:1',
            'lines.*.movement_reason' => ['required', Rule::in(array_keys($movementReasons))],
            'lines.*.description' => 'required|string|min:1',
        ];

        return $request->validate($rules);
    }
}
