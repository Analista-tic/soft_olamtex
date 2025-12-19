<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Common\Warehouse;
use App\Models\Inventory\InventoryAdjustment;
use App\Models\Inventory\Item;
use App\Services\Inventory\InventoryAdjustmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class InventoryAdjustmentController extends Controller
{
    public function __construct(protected InventoryAdjustmentService $service) {}
    /**
     * Devuelve los ajustes paginados para la tabla (API).
     */
    public function getData(Request $request)
    {
        $filters = $request->only(['search','type','date_from','date_to']);
        $perPage = $request->input('per_page', 10);
        $adjustments = $this->service->paginate($filters, $perPage);
        return response()->json($adjustments);
    }

    /**
     * (Opcional) Listado simple o redireccion.
     */
    public function index()
    {
        return response()->json([
            'message' => 'Use /ajustes-inventario/data para obtener los ajustes paginados.'
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
                'data'    => $adjustment,
            ], 201);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Ocurrio un error al guardar el ajuste de inventario.',
                'error'   => $e->getMessage(),
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
                'data'    => $updated,
            ]);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Ocurrio un error al actualizar el ajuste de inventario.',
                'error'   => $e->getMessage(),
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
     * Devuelve una bodega valida para el item, o lanza error si falta.
     */
    private function resolveWarehouseId(Item $item, ?int $warehouseId): int
    {
        if ($warehouseId) {
            return $warehouseId;
        }

        $warehouse = $item->warehouses()->first();
        if ($warehouse) {
            return $warehouse->id;
        }

        $fallback = Warehouse::first();
        if ($fallback) {
            $item->warehouses()->attach($fallback->id, [
                'quantity' => 0,
                'stock_min' => 0,
                'stock_max' => 0,
            ]);
            return $fallback->id;
        }

        throw ValidationException::withMessages([
            'lines' => ["No hay bodegas configuradas. Crea una bodega o envía warehouse_id."],
        ]);
    }

    /**
     * Aplica el movimiento de inventario en la tabla pivot `warehouse_items`.
     */
    private function applyStockMovement(Item $item, int $warehouseId, int $quantity, string $type): void
    {
        $quantity = abs($quantity);
        $sign = $type === 'entrada' ? 1 : -1;

        $row = DB::table('warehouse_items')
            ->where('item_id', $item->id)
            ->where('warehouse_id', $warehouseId)
            ->lockForUpdate()
            ->first();

        $currentQty = $row ? (int) $row->quantity : 0;
        $newQty = $currentQty + ($sign * $quantity);

        if ($newQty < 0 && !$item->negative_movement) {
            $item->negative_movement = 1;
            $item->save();
        }

        $quantityToStore = $item->negative_movement ? $newQty : max($newQty, 0);

        $payload = [
            'quantity'   => $quantityToStore,
            'stock_min'  => $row ? $row->stock_min : 0,
            'stock_max'  => $row ? $row->stock_max : 0,
            'updated_at' => now(),
        ];

        if ($row) {
            DB::table('warehouse_items')
                ->where('id', $row->id)
                ->update($payload);
        } else {
            $payload['item_id'] = $item->id;
            $payload['warehouse_id'] = $warehouseId;
            $payload['created_at'] = now();
            DB::table('warehouse_items')->insert($payload);
        }
    }

    /**
     * Genera un numero consecutivo unico para el ajuste de inventario.
     */
    private function generateDocumentNumber(): string
    {
        $prefix = 'OL';
        $sequenceLength = 3;

        $lastNumber = InventoryAdjustment::whereNotNull('document_number')
            ->where('document_number', 'like', "{$prefix}-%")
            ->orderByDesc('id')
            ->lockForUpdate()
            ->value('document_number');

        $next = 1;
        if ($lastNumber && preg_match("/^{$prefix}--(\\d{{$sequenceLength}})$/", $lastNumber, $matches)) {
            $next = (int) $matches[1] + 1;
        }

        $sequence = str_pad((string) $next, $sequenceLength, '0', STR_PAD_LEFT);

        return "{$prefix}-{$sequence}";
    }

    /**
     * Devuelve el siguiente numero consecutivo sugerido (para mostrar en el formulario).
     */
    public function nextNumber()
    {
        $next = $this->service->nextNumber();

        return response()->json(['document_number' => $next]);
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
            'document_number'      => [
                'nullable',
                'string',
                'max:255',
                $uniqueRule,
            ],
            'date'                 => 'required|date',
            'type'                 => 'required|in:entrada,salida',
            'movement_reason'      => ['required', Rule::in(array_keys($movementReasons))],
            'observation'          => 'nullable|string',
            'production_order'     => 'nullable|string|max:255',
            'subtotal'             => 'nullable|numeric',
            'iva'                  => 'nullable|numeric',
            'total'                => 'nullable|numeric',
            'lines'                => 'required|array|min:1',
            'lines.*.item_id'   => 'required|integer|exists:items,id',
            'lines.*.warehouse_id' => 'nullable|integer|exists:warehouses,id',
            'lines.*.price'        => 'required|numeric|min:0',
            'lines.*.tax'          => 'nullable|numeric|min:0',
            'lines.*.quantity'     => 'required|integer|min:1',
            'lines.*.movement_reason' => ['required', Rule::in(array_keys($movementReasons))],
            'lines.*.description'  => 'required|string|min:1',
        ];

        return $request->validate($rules);
    }

    private function validationErrorResponse(ValidationException $exception)
    {
        return response()->json([
            'message' => 'Errores de validacion',
            'errors'  => $exception->errors(),
        ], 422);
    }
}
