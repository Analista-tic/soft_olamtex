<?php

namespace App\Http\Controllers\ProductSheet;

use App\Http\Controllers\Controller;
use App\Models\ProductSheet\ProductSheet;
use App\Services\MasterData\ParameterService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProductSheetController extends Controller
{
    /**
     * Carga datos relacionados y los envía a la vista.
     */
    public function index()
    {
        $parameterService = new ParameterService();
        $sections = $parameterService->list(['search' => '', 'status' => 'active'])->where('key', 'sections')->first()?->value ?? [];
        $groups = $parameterService->list(['search' => '', 'status' => 'active'])->where('key', 'groups')->first()?->value ?? [];
        $brands = $parameterService->list(['search' => '', 'status' => 'active'])->where('key', 'brands')->first()?->value ?? [];
        $worlds = $parameterService->list(['search' => '', 'status' => 'active'])->where('key', 'worlds')->first()?->value ?? [];
        $fabricBases = $parameterService->list(['search' => '', 'status' => 'active'])->where('key', 'fabric_bases')->first()?->value ?? [];
        $yarnBases = $parameterService->list(['search' => '', 'status' => 'active'])->where('key', 'yarn_bases')->first()?->value ?? [];

        return Inertia::render('ProductSheets/Index', [
            'sections' => $sections,
            'groups' => $groups,
            'brands' => $brands,
            'worlds' => $worlds,
            'fabricBases' => $fabricBases,
            'yarnBases' => $yarnBases,
        ]);
    }

    /**
     * Valida y crea un nuevo registro en la base de datos.
     */
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'reference' => 'required|string|max:255',
            'section_id' => 'required|integer', // ID referencia parameters
            'group_id' => 'required|integer', // ID referencia parameters
            'brand_id' => 'required|integer', // ID referencia parameters
            'world_id' => 'required|integer', // ID referencia parameters
            'fabric_base_id' => 'required|integer', // ID referencia parameters
            'billing_price' => 'required|numeric',
            'selling_price' => 'required|numeric',
            'sizes' => 'required|json',
            'woven_product' => 'required|boolean',
            'description' => 'nullable|string',
            'processes' => 'required|json',
            'status' => 'required|string|in:incomplete,complete',
            'yarn_base_id' => 'nullable|integer', // ID referencia parameters
        ]);

        // Normalizar sizes antes de guardar
        $validatedData['sizes'] = $this->normalizeSizes($validatedData['sizes']);

        $productSheet = ProductSheet::create($validatedData); // Asegurarse de que se incluya fabric_base_id

        return response()->json([
            'success' => true,
            'message' => 'Product sheet creado exitosamente.',
            'data' => $productSheet,
        ], 201);
    }

    /**
     * Valida y actualiza un registro existente.
     */
    public function update(Request $request, string $id)
    {
        $validatedData = $request->validate([
            'reference' => 'required|string|max:255',
            'section_id' => 'required|integer', // ID referencia parameters
            'group_id' => 'required|integer', // ID referencia parameters
            'brand_id' => 'required|integer', // ID referencia parameters
            'world_id' => 'required|integer', // ID referencia parameters
            'fabric_base_id' => 'required|integer', // ID referencia parameters
            'billing_price' => 'required|numeric',
            'selling_price' => 'required|numeric',
            'sizes' => 'required|json',
            'woven_product' => 'required|boolean',
            'description' => 'nullable|string',
            'processes' => 'required|json',
            'status' => 'required|string|in:incomplete,complete',
            'yarn_base_id' => 'nullable|integer', // ID referencia parameters
        ]);

        // Normalizar sizes antes de actualizar
        $validatedData['sizes'] = $this->normalizeSizes($validatedData['sizes']);

        $productSheet = ProductSheet::findOrFail($id); // Busca el registro.
        $productSheet->update($validatedData); // Actualiza en la base de datos.

        return response()->json(['success' => 'Product sheet actualizado exitosamente.']);
    }

    /**
     * Obtiene un registro específico con datos enriquecidos.
     */
    public function show(string $id)
    {
        try {
            $productSheet = ProductSheet::findOrFail($id);

            // Cargar los catálogos desde parameters
            $parameterService = new ParameterService();
            $sections = collect($parameterService->list(['search' => '', 'status' => 'active'])->where('key', 'sections')->first()?->value ?? [])->keyBy('id');
            $groups = collect($parameterService->list(['search' => '', 'status' => 'active'])->where('key', 'groups')->first()?->value ?? [])->keyBy('id');
            $brands = collect($parameterService->list(['search' => '', 'status' => 'active'])->where('key', 'brands')->first()?->value ?? [])->keyBy('id');
            $worlds = collect($parameterService->list(['search' => '', 'status' => 'active'])->where('key', 'worlds')->first()?->value ?? [])->keyBy('id');
            $fabricBases = collect($parameterService->list(['search' => '', 'status' => 'active'])->where('key', 'fabric_bases')->first()?->value ?? [])->keyBy('id');
            $yarnBases = collect($parameterService->list(['search' => '', 'status' => 'active'])->where('key', 'yarn_bases')->first()?->value ?? [])->keyBy('id');

            // Enriquecer el producto con los nombres
            $productSheet->section = $sections->get($productSheet->section_id, ['id' => $productSheet->section_id, 'name' => 'N/A']);
            $productSheet->group = $groups->get($productSheet->group_id, ['id' => $productSheet->group_id, 'name' => 'N/A']);
            $productSheet->brand = $brands->get($productSheet->brand_id, ['id' => $productSheet->brand_id, 'name' => 'N/A']);
            $productSheet->world = $worlds->get($productSheet->world_id, ['id' => $productSheet->world_id, 'name' => 'N/A']);
            $productSheet->fabric_base = $fabricBases->get($productSheet->fabric_base_id, ['id' => $productSheet->fabric_base_id, 'name' => 'N/A']);
            $productSheet->yarn_base = $productSheet->yarn_base_id ? $yarnBases->get($productSheet->yarn_base_id, ['id' => $productSheet->yarn_base_id, 'name' => 'N/A']) : null;

            // Convertir arrays a JSON strings para el frontend
            $data = $productSheet->toArray();
            $data['sizes'] = is_array($productSheet->sizes) ? json_encode($productSheet->sizes) : $productSheet->sizes;
            $data['processes'] = is_array($productSheet->processes) ? json_encode($productSheet->processes) : $productSheet->processes;
            $data['section'] = $productSheet->section;
            $data['group'] = $productSheet->group;
            $data['brand'] = $productSheet->brand;
            $data['world'] = $productSheet->world;
            $data['fabric_base'] = $productSheet->fabric_base;
            $data['yarn_base'] = $productSheet->yarn_base;

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener el producto: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Elimina un registro por su ID.
     */
    public function destroy(string $id)
    {
        $productSheet = ProductSheet::findOrFail($id); // Busca el registro.
        $productSheet->delete(); // Elimina el registro.

        return response()->json(['message' => 'ProductSheet deleted successfully'], 200);
    }

    /**
     * Aplica filtros y devuelve datos paginados.
     */
    public function getData(Request $request)
    {
        $query = ProductSheet::query();

        if ($request->has('search') && $request->search != '') {
            $query->where('reference', 'like', '%' . $request->search . '%');
        }

        if ($request->has('status') && $request->status != '') {
            $query->where('status', $request->status);
        }

        $products_sheets = $query->orderBy('id', 'desc')->paginate(10);

        // Cargar los catálogos desde parameters
        $parameterService = new ParameterService();
        $sections = collect($parameterService->list(['search' => '', 'status' => 'active'])->where('key', 'sections')->first()?->value ?? [])->keyBy('id');
        $groups = collect($parameterService->list(['search' => '', 'status' => 'active'])->where('key', 'groups')->first()?->value ?? [])->keyBy('id');
        $brands = collect($parameterService->list(['search' => '', 'status' => 'active'])->where('key', 'brands')->first()?->value ?? [])->keyBy('id');
        $worlds = collect($parameterService->list(['search' => '', 'status' => 'active'])->where('key', 'worlds')->first()?->value ?? [])->keyBy('id');
        $fabricBases = collect($parameterService->list(['search' => '', 'status' => 'active'])->where('key', 'fabric_bases')->first()?->value ?? [])->keyBy('id');
        $yarnBases = collect($parameterService->list(['search' => '', 'status' => 'active'])->where('key', 'yarn_bases')->first()?->value ?? [])->keyBy('id');

        // Enriquecer los productos con los nombres
        $products_sheets->getCollection()->transform(function ($product) use ($sections, $groups, $brands, $worlds, $fabricBases, $yarnBases) {
            $product->section = $sections->get($product->section_id, ['id' => $product->section_id, 'name' => 'N/A']);
            $product->group = $groups->get($product->group_id, ['id' => $product->group_id, 'name' => 'N/A']);
            $product->brand = $brands->get($product->brand_id, ['id' => $product->brand_id, 'name' => 'N/A']);
            $product->world = $worlds->get($product->world_id, ['id' => $product->world_id, 'name' => 'N/A']);
            $product->fabric_base = $fabricBases->get($product->fabric_base_id, ['id' => $product->fabric_base_id, 'name' => 'N/A']);
            $product->yarn_base = $product->yarn_base_id ? $yarnBases->get($product->yarn_base_id, ['id' => $product->yarn_base_id, 'name' => 'N/A']) : null;
            return $product;
        });

        return response()->json([
            'products_sheets' => $products_sheets,
        ]);
    }

    /**
     * Normaliza el campo sizes para guardar solo las tallas seleccionadas (true).
     * Convierte {"28": true, "30": false} -> ["28"]
     * 
     * @param string $sizesJson JSON string con las tallas
     * @return string JSON normalizado
     */
    private function normalizeSizes($sizesJson)
    {
        try {
            $sizes = json_decode($sizesJson, true);
            
            if (!is_array($sizes)) {
                return $sizesJson;
            }
            
            // Si es un array asociativo con valores booleanos, filtrar solo los true
            if (array_keys($sizes) !== range(0, count($sizes) - 1)) {
                // Es array asociativo - extraer solo claves donde el valor sea true
                $filteredSizes = array_values(array_filter(
                    array_keys($sizes),
                    fn($key) => $sizes[$key] === true
                ));
                
                // Retornar como array JSON simple
                return json_encode($filteredSizes);
            }
            
            // Si ya es un array indexado, retornar como está
            return $sizesJson;
        } catch (\Exception $e) {
            // Si hay algún error, retornar el valor original
            return $sizesJson;
        }
    }
}
