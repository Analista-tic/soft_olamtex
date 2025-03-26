<?php

namespace App\Http\Controllers\ProductSheet;

use App\Http\Controllers\Controller;
use App\Models\ProductSheet\ProductSheet;
use App\Models\ProductSheet\Section;
use App\Models\ProductSheet\Group;
use App\Models\ProductSheet\Brand;
use App\Models\ProductSheet\World;
use App\Models\ProductSheet\Base;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProductSheetController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $sections = Section::all();
        $groups = Group::all();
        $brands = Brand::all();
        $worlds = World::all();
        $bases = Base::all();

        return Inertia::render('ProductSheets/Index', [
            'sections' => $sections,
            'groups' => $groups,
            'brands' => $brands,
            'worlds' => $worlds,
            'bases' => $bases,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'reference' => 'required|string|max:255',
            'section_id' => 'required|integer|exists:sections,id',
            'group_id' => 'required|integer|exists:groups,id',
            'brand_id' => 'required|integer|exists:brands,id',
            'world_id' => 'required|integer|exists:worlds,id',
            'base_id' => 'required|integer|exists:bases,id',
            'billing_price' => 'required|numeric',
            'selling_price' => 'required|numeric',
            'sizes' => 'required|json',
            'woven_product' => 'required|boolean',
            'description' => 'nullable|string',
            'processes' => 'required|json',
            'status' => 'required|string|in:incomplete,complete',
        ]);

        $productSheet = ProductSheet::create($validatedData);

        return redirect()->route('comercial-diseno.index')->with('success', 'Product sheet creado exitosamente.');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $validatedData = $request->validate([
            'reference' => 'required|string|max:255',
            'section_id' => 'required|integer|exists:sections,id',
            'group_id' => 'required|integer|exists:groups,id',
            'brand_id' => 'required|integer|exists:brands,id',
            'world_id' => 'required|integer|exists:worlds,id',
            'base_id' => 'required|integer|exists:bases,id',
            'billing_price' => 'required|numeric',
            'selling_price' => 'required|numeric',
            'sizes' => 'required|json',
            'woven_product' => 'required|boolean',
            'description' => 'nullable|string',
            'processes' => 'required|json',
            'status' => 'required|string|in:incomplete,complete',
        ]);

        $productSheet = ProductSheet::findOrFail($id);
        $productSheet->update($validatedData);

        return response()->json(['success' => 'Product sheet actualizado exitosamente.']);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $productSheet = ProductSheet::findOrFail($id);
        $productSheet->delete();

        return redirect()->route('comercial-diseno.index')->with('success', 'Product sheet eliminado exitosamente.');
    }

    /**
     * Get data for the resource.
     */
    public function getData(Request $request)
    {
        $query = ProductSheet::with(['section', 'group', 'brand', 'world', 'base']);

        // Búsqueda por referencia
        if ($request->has('search') && $request->search != '') {
            $query->where('reference', 'like', '%' . $request->search . '%');
        }

        // Filtro por estado
        if ($request->has('status') && $request->status != '') {
            $query->where('status', $request->status == 1 ? 'complete' : 'incomplete');
        }

        // Paginación
        $products_sheets = $query->orderBy('id', 'desc')->paginate(10);

        return response()->json([
            'products_sheets' => $products_sheets,
        ]);
    }
}
