<?php

namespace App\Http\Controllers\ProductSheet;

use App\Http\Controllers\Controller;
use App\Models\ProductSheet\ProductSheet;
use App\Models\ProductSheet\Programmer;
use App\Services\ParameterService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProgrammerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $sections = ParameterService::getList('sections')->keyBy('id');
        $groups = ParameterService::getList('groups')->keyBy('id');
        $brands = ParameterService::getList('brands')->keyBy('id');
        $worlds = ParameterService::getList('worlds')->keyBy('id');
        $fabricBases = ParameterService::getList('fabric_bases')->keyBy('id');
        $yarnBases = ParameterService::getList('yarn_bases')->keyBy('id');

        $productSheets = ProductSheet::all(); // Traer todos los product sheets

        // Enriquecer productSheets con los nombres desde parameters
        $productSheets->transform(function ($product) use ($sections, $groups, $brands, $worlds, $fabricBases, $yarnBases) {
            $product->section = $sections->get($product->section_id, ['id' => $product->section_id, 'name' => 'N/A']);
            $product->group = $groups->get($product->group_id, ['id' => $product->group_id, 'name' => 'N/A']);
            $product->brand = $brands->get($product->brand_id, ['id' => $product->brand_id, 'name' => 'N/A']);
            $product->world = $worlds->get($product->world_id, ['id' => $product->world_id, 'name' => 'N/A']);
            $product->fabric_base = $fabricBases->get($product->fabric_base_id, ['id' => $product->fabric_base_id, 'name' => 'N/A']);
            $product->yarn_base = $product->yarn_base_id ? $yarnBases->get($product->yarn_base_id, ['id' => $product->yarn_base_id, 'name' => 'N/A']) : null;
            return $product;
        });

        $destination = ParameterService::getList('destinations');

        // Asegurarse de que los datos sean arreglos antes de pasarlos a la vista
        return Inertia::render('Programmers/Index', [
            'productSheets' => $productSheets ?? [],
            'destinations' => $destination ?? [],
            'sections' => $sections->values() ?? [],
            'groups' => $groups->values() ?? [],
            'brands' => $brands->values() ?? [],
            'worlds' => $worlds->values() ?? [],
            'fabricBases' => $fabricBases->values() ?? [],
            'yarnBases' => $yarnBases->values() ?? [],
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return response()->json(['message' => 'Create method not implemented'], 501);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        \Log::info('Datos recibidos:', $request->all()); // Registrar los datos recibidos
        \Log::info('Datos recibidos para ecommerce_units:', $request->input('lots.*.ecommerce_units'));
        \Log::info('Datos recibidos para destination_id:', $request->input('lots.*.destination_id'));

        $validatedData = $request->validate([
            'lots' => 'required|array', // Validar que se envíe un array de lotes
            'lots.*.product_sheet_id' => 'required|exists:product_sheets,id',
            'lots.*.season' => 'required|string|max:255',
            'lots.*.destination_id' => 'required|integer', // ID referencia parameters
            'lots.*.priority' => 'required|string|max:1',
            'lots.*.units' => 'required|integer|min:0',
            'lots.*.ecommerce_units' => 'nullable|array', // Validar que sea un array o null
            'lots.*.status' => 'required|string|max:255',
        ]);

        // Insertar múltiples lotes en la tabla programmers
        $programmers = collect($validatedData['lots'])->map(function ($lot) {
            return Programmer::create([
                'product_sheet_id' => $lot['product_sheet_id'],
                'season' => $lot['season'],
                'destination_id' => $lot['destination_id'], // Guardar el ID de la destinación
                'priority' => $lot['priority'],
                'units' => $lot['units'],
                'ecommerce_units' => isset($lot['ecommerce_units']) ? json_encode($lot['ecommerce_units']) : null, // Convertir a JSON
                'status' => $lot['status'],
            ]);
        });

        return response()->json([
            'message' => 'Programmers created successfully.',
            'data' => $programmers,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Programmer $programmer)
    {
        return response()->json(['data' => $programmer], 200);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Programmer $programmer)
    {
        return response()->json(['message' => 'Edit method not implemented'], 501);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Programmer $programador)
    {
        \Log::info('Datos recibidos para actualizar:', $request->all());

        // Validar los datos recibidos
        $validatedData = $request->validate([
            'product_sheet_id' => 'sometimes|exists:product_sheets,id',
            'season' => 'sometimes|string|max:255',
            'destination_id' => 'sometimes|integer', // ID referencia parameters
            'priority' => 'sometimes|string|max:1',
            'units' => 'sometimes|integer|min:0',
            'ecommerce_units' => 'sometimes|json', // Validar que sea un JSON válido
            'status' => 'sometimes|string|max:255',
        ]);

        \Log::info('Datos validados:', $validatedData);

        // Actualizar los datos del programador
        $programador->update($validatedData);

        // Refrescar el modelo para obtener los datos actualizados
        $programador->refresh();

        \Log::info('Programador actualizado:', $programador->toArray());

        return response()->json([
            'message' => 'Programmer updated successfully.',
            'data' => $programador, // Devolver los datos actualizados
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Programmer $programador)
    {
        $programador->delete();

        return response()->json(['message' => 'Programmer deleted successfully'], 200);
    }

    /**
     * Get data with related product sheets.
     */
    public function getData(Request $request)
    {
        $filters = $request->only(['season', 'priority', 'status', 'destination_id', 'search']);
        $perPage = $request->input('per_page', 10);
        $page = $request->input('page', 1);

        $query = Programmer::with('productSheet');

        // Aplicar filtros dinámicamente
        if (!empty($filters['season']) && $filters['season'] !== 'all') {
            $query->where('season', $filters['season']);
        }

        if (!empty($filters['priority']) && $filters['priority'] !== 'all') {
            $query->where('priority', $filters['priority']);
        }

        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['destination_id']) && $filters['destination_id'] !== 'all') {
            $query->where('destination_id', $filters['destination_id']);
        }

        if (!empty($filters['search'])) {
            $query->whereHas('productSheet', function ($q) use ($filters) {
                $q->where('reference', 'like', '%' . $filters['search'] . '%');
            });
        }

        $programmers = $query->paginate($perPage, ['*'], 'page', $page);

        // Enriquecer con destinations desde parameters
        $destinations = ParameterService::getList('destinations')->keyBy('id');
        
        $programmers->getCollection()->transform(function ($programmer) use ($destinations) {
            $programmer->destination = $destinations->get($programmer->destination_id, ['id' => $programmer->destination_id, 'name' => 'N/A']);
            return $programmer;
        });

        return response()->json([
            'data' => $programmers->items(),
            'total' => $programmers->total(),
            'current_page' => $programmers->currentPage(),
            'last_page' => $programmers->lastPage(),
            'per_page' => $programmers->perPage(),
        ]);
    }
}
