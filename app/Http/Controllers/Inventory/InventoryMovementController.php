<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Services\Inventory\InventoryMovementService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class InventoryMovementController extends Controller
{
    public function __construct(
        protected InventoryMovementService $service
    ) {}

    /**
     * Mostrar listado de movimientos de inventario.
     */
    public function index(): Response
    {
        return Inertia::render('Inventory/Movements/Index');
    }

    /**
     * Obtener datos paginados de movimientos (JSON).
     */
    public function getData(Request $request): JsonResponse
    {
        $filters = [
            'item_id' => $request->input('item_id'),
            'warehouse_id' => $request->input('warehouse_id'),
            'type' => $request->input('type'),
            'search' => $request->input('search'),
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
        ];

        $perPage = (int) $request->input('per_page', 20);
        $paginator = $this->service->paginate($filters, $perPage);

        return response()->json([
            'data' => $paginator->items(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ]);
    }

    /**
     * Obtener movimientos de un item específico.
     */
    public function getItemMovements(Request $request, int $itemId): JsonResponse
    {
        $filters = [
            'type' => $request->input('type'),
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
        ];

        $warehouseId = $request->input('warehouse_id');
        $perPage = (int) $request->input('per_page', 20);

        $paginator = $this->service->getItemMovements($itemId, $warehouseId, $filters, $perPage);

        return response()->json([
            'data' => $paginator->items(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ]);
    }

    /**
     * Crear un nuevo movimiento de inventario.
     * Requerido: notes (motivo), unit_price en entradas.
     */
    public function store(Request $request): JsonResponse
    {
        $rules = [
            'item_id' => 'required|exists:items,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'type' => 'required|in:entrada,salida,ajuste',
            'date' => 'required|date',
            'quantity' => 'required|numeric|min:0.0001',
            'unit_price' => 'nullable|numeric|min:0',
            'source_type' => 'nullable|string',
            'source_id' => 'nullable|integer',
            'notes' => 'required|string|max:1000',
        ];

        $validated = $request->validate($rules);

        if (($validated['type'] ?? '') === 'entrada') {
            $request->validate(['unit_price' => 'required|numeric|min:0.0001']);
            $validated['unit_price'] = (float) $request->input('unit_price');
        }

        try {
            $movement = $this->service->createMovement($validated);

            return response()->json([
                'message' => 'Movimiento registrado correctamente.',
                'data' => $movement,
            ], 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        } catch (\RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al registrar el movimiento: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Mostrar un movimiento específico.
     */
    public function show(int $id): JsonResponse
    {
        $movement = $this->service->find($id);

        if (!$movement) {
            return response()->json([
                'message' => 'Movimiento no encontrado.',
            ], 404);
        }

        return response()->json([
            'data' => $movement->load(['item', 'warehouse', 'createdBy', 'source']),
        ]);
    }

    /**
     * Revertir un movimiento. Los movimientos son inmutables; la reversión crea
     * un movimiento contrapartida que deshace el efecto del original.
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $movement = $this->service->find($id);

            if (!$movement) {
                return response()->json([
                    'message' => 'Movimiento no encontrado.',
                ], 404);
            }

            $reversed = $this->service->reverseMovement(
                $movement,
                (string) request('notes', '')
            );

            return response()->json([
                'message' => 'Movimiento revertido correctamente.',
                'data' => $reversed->load(['item', 'warehouse', 'createdBy']),
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al revertir el movimiento: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Traslado entre bodegas: salida en origen y entrada en destino.
     */
    public function storeTransfer(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'item_id' => 'required|exists:items,id',
            'warehouse_origin_id' => 'required|exists:warehouses,id',
            'warehouse_dest_id' => 'required|exists:warehouses,id',
            'quantity' => 'required|numeric|min:0.0001',
            'notes' => 'required|string|max:1000',
            'date' => 'nullable|date',
        ]);

        try {
            $result = $this->service->createTransfer($validated);

            return response()->json([
                'message' => 'Traslado registrado correctamente.',
                'data' => [
                    'origin' => $result['origin'],
                    'destination' => $result['destination'],
                ],
            ], 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al registrar el traslado: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener el costo promedio ponderado actual de un item.
     */
    public function getCurrentWeightedPrice(Request $request): JsonResponse
    {
        $request->validate([
            'item_id' => 'required|exists:items,id',
            'warehouse_id' => 'nullable|exists:warehouses,id',
        ]);

        $itemId = $request->input('item_id');
        $warehouseId = $request->input('warehouse_id');

        $price = $this->service->getCurrentWeightedPrice($itemId, $warehouseId);
        $stock = $this->service->getCurrentStockPublic($itemId, $warehouseId);

        return response()->json([
            'weighted_price' => $price,
            'current_stock' => $stock,
        ]);
    }
}
