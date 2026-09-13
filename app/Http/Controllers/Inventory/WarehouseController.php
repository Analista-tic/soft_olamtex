<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Inventory\Warehouse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WarehouseController extends Controller
{
    public function index()
    {
        // El frontend (Inventory/Warehouse/Index.vue) no declara ningún prop y trae
        // las bodegas vía getData() en su propio onMounted() — Warehouse::all() aquí
        // era una consulta completa de la tabla en cada carga, sin usarse.
        return Inertia::render('Inventory/Warehouse/Index');
    }

    public function store(Request $request)
    {
        return Warehouse::create($this->validateWarehouse($request));
    }

    public function show($id)
    {
        return Warehouse::findOrFail($id);
    }

    public function update(Request $request, $id)
    {
        $warehouse = Warehouse::findOrFail($id);
        $warehouse->update($this->validateWarehouse($request));

        return $warehouse;
    }

    public function destroy($id)
    {
        $warehouse = Warehouse::findOrFail($id);
        $warehouse->delete();

        return response()->noContent();
    }

    /**
     * Obtener bodegas con paginación y búsqueda.
     */
    public function getData(Request $request)
    {
        $query = Warehouse::select('id', 'name', 'observation');

        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where('name', 'like', "%{$search}%")
                ->orWhere('observation', 'like', "%{$search}%");
        }

        $perPage = $request->input('per_page', 10);

        $sortBy = in_array($request->input('sort_by'), ['name', 'observation'], true) ? $request->input('sort_by') : 'id';
        $direction = strtolower((string) $request->input('sort_direction', 'desc')) === 'asc' ? 'asc' : 'desc';

        $warehouses = $query->orderBy($sortBy, $direction)->paginate($perPage);

        return response()->json($warehouses);
    }

    public function list()
    {
        return response()->json(
            Warehouse::select('id', 'name')->orderBy('name')->get()
        );
    }

    /**
     * @return array{name: string, observation: string}
     */
    private function validateWarehouse(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'observation' => ['required', 'string', 'max:255'],
        ]);

        return [
            'name' => trim((string) $validated['name']),
            'observation' => trim((string) $validated['observation']),
        ];
    }
}
