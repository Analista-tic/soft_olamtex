<?php

namespace App\Http\Controllers\ProductSheet;

use App\Http\Controllers\Controller;
use App\Models\ProductSheet\ProductSheet;
use App\Services\ParameterService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProductSheetController extends Controller
{
    /**
     * Carga datos relacionados y los envía a la vista.
     */
    public function index()
    {
        $sections = ParameterService::getList('sections');
        $groups = ParameterService::getList('groups');
        $brands = ParameterService::getList('brands');
        $worlds = ParameterService::getList('worlds');
        $fabricBases = ParameterService::getList('fabric_bases');
        $yarnBases = ParameterService::getList('yarn_bases');

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

        $productSheet = ProductSheet::findOrFail($id); // Busca el registro.
        $productSheet->update($validatedData); // Actualiza en la base de datos.

        return response()->json(['success' => 'Product sheet actualizado exitosamente.']);
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
        $sections = ParameterService::getList('sections')->keyBy('id');
        $groups = ParameterService::getList('groups')->keyBy('id');
        $brands = ParameterService::getList('brands')->keyBy('id');
        $worlds = ParameterService::getList('worlds')->keyBy('id');
        $fabricBases = ParameterService::getList('fabric_bases')->keyBy('id');
        $yarnBases = ParameterService::getList('yarn_bases')->keyBy('id');

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
}
