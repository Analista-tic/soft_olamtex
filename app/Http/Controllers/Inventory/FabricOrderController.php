<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Inventory\FabricOrder;
use App\Models\Inventory\FabricOrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Arr; // Utilidad para manejo de arreglos
use Inertia\Inertia;

class FabricOrderController extends Controller
{
    // Renderiza la vista principal de órdenes de tela
    public function index()
    {
        return Inertia::render('Inventory/Fabrics/Index');
    }

    // Crea una nueva orden de tela con sus ítems asociados
    public function store(Request $request)
    {
        $validated = $request->validate([
            'fabric_provider_id' => 'required|string|max:255',
            'status' => 'required|string',
            'items' => 'required|array|min:1',
            'items.*.fabric' => 'required|string|max:255',
            'items.*.base_fabric_id' => 'required|integer',
            'items.*.provider_color' => 'required|string|max:255',
            'items.*.provider_color_code' => 'required|string|max:255',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.estimated_width' => 'required|numeric|min:0.01',
            'items.*.price' => 'required|numeric|min:0',
        ]);

        try {
            // Crear la orden principal
            $order = FabricOrder::create([
                'fabric_provider_id' => $validated['fabric_provider_id'],
                'status' => $validated['status'],
            ]);

            // Crear los ítems asociados a la orden
            foreach ($validated['items'] as $item) {
                $order->items()->create($item);
            }

            return response()->json($order->load('items'), 201);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al guardar la orden: ' . $e->getMessage()], 500);
        }
    }

    // Muestra los detalles de una orden específica
    public function show(FabricOrder $pedido)
    {
        return response()->json($pedido);
    }

    // Actualiza una orden de tela y sus ítems asociados
    public function update(Request $request, FabricOrder $pedido)
    {
        $validated = $request->validate([
            'fabric_provider_id' => 'sometimes|required|string|max:255',
            'status' => 'sometimes|required|string',
            'items' => 'sometimes|required|array|min:1',
            'items.*.fabric' => 'required|string|max:255',
            'items.*.base_fabric_id' => 'required|integer',
            'items.*.provider_color' => 'required|string|max:255',
            'items.*.provider_color_code' => 'required|string|max:255',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.estimated_width' => 'required|numeric|min:0.01',
            'items.*.price' => 'required|numeric|min:0',
        ]);

        try {
            // Actualizar la orden principal
            $pedido->update([
                'fabric_provider_id' => $validated['fabric_provider_id'] ?? $pedido->fabric_provider_id,
                'status' => $validated['status'] ?? $pedido->status,
            ]);

            // Actualizar los ítems asociados
            if (isset($validated['items'])) {
                $pedido->items()->delete(); // Eliminar ítems existentes
                foreach ($validated['items'] as $item) {
                    $pedido->items()->create($item);
                }
            }

            return response()->json($pedido->load('items'));
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al actualizar la orden: ' . $e->getMessage()], 500);
        }
    }

    // Elimina una orden de tela si su estado lo permite
    public function destroy(FabricOrder $pedido)
    {
        // Verificar si el estado permite la eliminación
        if (in_array($pedido->status, ['in_reception', 'received'])) {
            return response()->json(['error' => 'No se puede eliminar una orden que está en recepción o ya ha sido recepcionada.'], 403);
        }

        $pedido->delete();
        return response()->json(['message' => 'Pedido eliminado correctamente.'], 200);
    }

    // Obtiene una lista paginada de órdenes con filtros y búsqueda
    public function getData(Request $request)
    {
        $query = FabricOrder::with('items', 'fabricProvider');

        // Aplicar búsqueda por proveedor
        if ($search = $request->input('search')) {
            $query->whereHas('fabricProvider', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%");
            });
        }

        // Aplicar filtro por estado
        if ($status = $request->input('status')) {
            if ($status !== 'all') {
                $query->where('status', $status);
            }
        }

        // Paginación
        $perPage = $request->input('per_page', 10);
        $orders = $query->paginate($perPage);

        return response()->json($orders);
    }
}
