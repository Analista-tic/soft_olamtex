<?php

namespace App\Http\Controllers\ProductSheet;

use App\Http\Controllers\Controller;
use App\Models\ProductSheet\ProductSheet;
use App\Rules\ValidCatalogOption;
use App\Services\Settings\SettingService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;

class ProductSheetController extends Controller
{
    private const CATALOG_LABELS = [
        'sections' => 'Sección',
        'groups' => 'Grupo',
        'brands' => 'Marca',
        'worlds' => 'Mundo',
        'fabric_bases' => 'Base',
        'yarn_bases' => 'Base de hilaza',
    ];

    public function __construct(private SettingService $settingService)
    {
    }
    /**
     * Carga datos relacionados y los envía a la vista.
     */
    public function index()
    {
        $catalogs = $this->gatherCatalogs();

        return Inertia::render('ProductSheets/Index', [
            'sections' => $catalogs['sections']->values()->all(),
            'groups' => $catalogs['groups']->values()->all(),
            'brands' => $catalogs['brands']->values()->all(),
            'worlds' => $catalogs['worlds']->values()->all(),
            'fabricBases' => $catalogs['fabric_bases']->values()->all(),
            'yarnBases' => $catalogs['yarn_bases']->values()->all(),
            'sizes' => $catalogs['sizes']->values()->all(),
        ]);
    }

    /**
     * Valida y crea un nuevo registro en la base de datos.
     */
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'reference' => 'required|string|max:255',
            'section_id' => ['required', 'integer', $this->catalogRule('sections')],
            'group_id' => ['required', 'integer', $this->catalogRule('groups')],
            'brand_id' => ['required', 'integer', $this->catalogRule('brands')],
            'world_id' => ['required', 'integer', $this->catalogRule('worlds')],
            'fabric_base_id' => ['required', 'integer', $this->catalogRule('fabric_bases')],
            'billing_price' => 'required|numeric',
            'selling_price' => 'required|numeric',
            'sizes' => 'required|json',
            'woven_product' => 'required|boolean',
            'description' => 'nullable|string',
            'processes' => 'required|json',
            'status' => 'required|string|in:incomplete,complete',
            'yarn_base_id' => ['nullable', 'integer', $this->catalogRule('yarn_bases')],
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
            'section_id' => ['required', 'integer', $this->catalogRule('sections')],
            'group_id' => ['required', 'integer', $this->catalogRule('groups')],
            'brand_id' => ['required', 'integer', $this->catalogRule('brands')],
            'world_id' => ['required', 'integer', $this->catalogRule('worlds')],
            'fabric_base_id' => ['required', 'integer', $this->catalogRule('fabric_bases')],
            'billing_price' => 'required|numeric',
            'selling_price' => 'required|numeric',
            'sizes' => 'required|json',
            'woven_product' => 'required|boolean',
            'description' => 'nullable|string',
            'processes' => 'required|json',
            'status' => 'required|string|in:incomplete,complete',
            'yarn_base_id' => ['nullable', 'integer', $this->catalogRule('yarn_bases')],
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

            $catalogs = $this->gatherCatalogs();
            $sections = $catalogs['sections']->keyBy('id');
            $groups = $catalogs['groups']->keyBy('id');
            $brands = $catalogs['brands']->keyBy('id');
            $worlds = $catalogs['worlds']->keyBy('id');
            $fabricBases = $catalogs['fabric_bases']->keyBy('id');
            $yarnBases = $catalogs['yarn_bases']->keyBy('id');

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

        if ($request->filled('section_id')) {
            $query->where('section_id', $request->section_id);
        }

        if ($request->filled('world_id')) {
            $query->where('world_id', $request->world_id);
        }

        $products_sheets = $query->orderBy('id', 'desc')->paginate(10);

        $catalogs = $this->gatherCatalogs();
        $sections = $catalogs['sections']->keyBy('id');
        $groups = $catalogs['groups']->keyBy('id');
        $brands = $catalogs['brands']->keyBy('id');
        $worlds = $catalogs['worlds']->keyBy('id');
        $fabricBases = $catalogs['fabric_bases']->keyBy('id');
        $yarnBases = $catalogs['yarn_bases']->keyBy('id');

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

    private function gatherCatalogs(): array
    {
        $keys = ['sections', 'groups', 'brands', 'worlds', 'fabric_bases', 'yarn_bases', 'sizes'];
        $catalogs = [];
        foreach ($keys as $key) {
            $catalogs[$key] = $this->settingService->getCatalogOptions($key);
        }
        return $catalogs;
    }

    private function catalogRule(string $key): ValidCatalogOption
    {
        $label = self::CATALOG_LABELS[$key] ?? ucfirst(str_replace('_', ' ', $key));
        return new ValidCatalogOption($this->settingService, $key, $label);
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
