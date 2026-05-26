<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Inventory\WarehouseLocation;
use Illuminate\Http\Request;

class WarehouseLocationController extends Controller
{
    public function index(Request $request)
    {
        $query = WarehouseLocation::query();

        if ($request->boolean('status_active', false)) {
            $query->where('status', true);
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            });
        }

        $perPage = (int) $request->input('per_page', 100);

        return response()->json($query->orderBy('name')->paginate($perPage));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:100',
            'code'        => 'nullable|string|max:50|unique:warehouse_locations,code',
            'description' => 'nullable|string|max:255',
            'status'      => 'boolean',
        ]);

        return response()->json(WarehouseLocation::create($data), 201);
    }

    public function update(Request $request, WarehouseLocation $warehouseLocation)
    {
        $data = $request->validate([
            'name'        => 'sometimes|required|string|max:100',
            'code'        => 'nullable|string|max:50|unique:warehouse_locations,code,' . $warehouseLocation->id,
            'description' => 'nullable|string|max:255',
            'status'      => 'boolean',
        ]);

        $warehouseLocation->update($data);

        return response()->json($warehouseLocation);
    }

    public function destroy(WarehouseLocation $warehouseLocation)
    {
        $inUse = $warehouseLocation->warehouseItems()->exists();

        if ($inUse) {
            return response()->json([
                'message' => 'No se puede eliminar la ubicación porque está en uso por uno o más ítems.',
            ], 409);
        }

        $warehouseLocation->delete();

        return response()->noContent();
    }
}
