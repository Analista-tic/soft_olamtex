<?php

namespace App\Http\Controllers\ProductSheet;

use App\Http\Controllers\Controller;
use App\Models\ProductSheet\ProductSheet;
use App\Models\ProductSheet\Section;
use App\Models\ProductSheet\Group;
use App\Models\ProductSheet\Brand;
use App\Models\ProductSheet\World;
use App\Models\ProductSheet\FabricBase; // Cambiar Base a FabricBase
use App\Models\ProductSheet\YarnBase;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProductSheetController extends Controller
{
    /**
     * Carga datos relacionados y los envía a la vista.
     */
    public function index()
    {
        $sections = Section::all();
        $groups = Group::all();
        $brands = Brand::all();
        $worlds = World::all();
        $fabricBases = FabricBase::all(); // Cambiar bases a fabricBases
        $yarnBases = YarnBase::all();

        return Inertia::render('ProductSheets/Index', [
            'sections' => $sections,
            'groups' => $groups,
            'brands' => $brands,
            'worlds' => $worlds,
            'fabricBases' => $fabricBases, // Cambiar bases a fabricBases
            'yarnBases' => $yarnBases,
        ]);
    }

    /**
     * Valida y crea un nuevo registro en la base de datos.
     */
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'reference' => 'required|string|max:255', // Campo obligatorio.
            'section_id' => 'required|integer|exists:sections,id', // Relación válida.
            'group_id' => 'required|integer|exists:groups,id', // ID del grupo.
            'brand_id' => 'required|integer|exists:brands,id', // ID de la marca.
            'world_id' => 'required|integer|exists:worlds,id', // ID del mundo.
            'fabric_base_id' => 'required|integer|exists:fabric_bases,id', // Asegurar que sea requerido
            'billing_price' => 'required|numeric', // Precio de facturación.
            'selling_price' => 'required|numeric', // Precio de venta.
            'sizes' => 'required|json', // Tamaños en formato JSON.
            'woven_product' => 'required|boolean', // Indica si es un producto tejido.
            'description' => 'nullable|string', // Descripción opcional.
            'processes' => 'required|json', // Procesos en formato JSON.
            'status' => 'required|string|in:incomplete,complete', // Estado del producto.
            'yarn_base_id' => 'nullable|integer|exists:yarn_bases,id', // Validar yarnBases
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
            'section_id' => 'required|integer|exists:sections,id',
            'group_id' => 'required|integer|exists:groups,id',
            'brand_id' => 'required|integer|exists:brands,id',
            'world_id' => 'required|integer|exists:worlds,id',
            'fabric_base_id' => 'required|integer|exists:fabric_bases,id', // Asegurar que fabric_base_id sea requerido
            'billing_price' => 'required|numeric',
            'selling_price' => 'required|numeric',
            'sizes' => 'required|json',
            'woven_product' => 'required|boolean',
            'description' => 'nullable|string',
            'processes' => 'required|json',
            'status' => 'required|string|in:incomplete,complete',
            'yarn_base_id' => 'nullable|integer|exists:yarn_bases,id', // Validar yarnBases
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
        $query = ProductSheet::with(['section', 'group', 'brand', 'world', 'fabricBase', 'yarnBase']); // Asegurarse de que fabricBase esté definido correctamente

        if ($request->has('search') && $request->search != '') {
            $query->where('reference', 'like', '%' . $request->search . '%'); // Filtro por referencia.
        }

        if ($request->has('status') && $request->status != '') {
            $query->where('status', $request->status); // Filtro por estado.
        }

        $products_sheets = $query->orderBy('id', 'desc')->paginate(10); // Orden y paginación.

        return response()->json([
            'products_sheets' => $products_sheets,
        ]);
    }
}
