<?php
namespace App\Http\Controllers\ProductSheet;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProductSheet\ConvertProductSheetRequest;
use App\Http\Requests\ProductSheet\StoreProductSheetRequest;
use App\Http\Requests\ProductSheet\UpdateProductSheetRequest;
use App\Models\ProductSheet\ProductSheet;
use App\Services\ProductSheet\ProductSheetCreationService;
use App\Services\Settings\SettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class ProductSheetController extends Controller
{
    private const TYPE_PRODUCT = 'producto';
    private const TYPE_SAMPLE = 'muestra';
    public function __construct(
        private SettingService $settingService,
        private ProductSheetCreationService $productSheetCreationService,
    )
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
            'operationalLists' => $catalogs['operational_lists']->values()->all(),
        ]);
    }

    /**
     * Valida y crea un nuevo registro en la base de datos.
     */
    public function store(StoreProductSheetRequest $request)
    {
        $validatedData = $request->validated();
        $validatedData['sizes'] = $this->normalizeSizes($validatedData['sizes'] ?? '[]');
        $validatedData = $this->prepareFlatSketchUpload($request, $validatedData);
        $validatedData = $this->productSheetCreationService->sanitizeAttributes($validatedData);
        $storedFlatSketchPath = $validatedData['flat_sketch_path'] ?? null;
        $this->logProductSheetSchemaDebug('store', $validatedData);

        try {
            $productSheet = DB::transaction(function () use ($validatedData) {
                return $this->productSheetCreationService->create($validatedData);
            });
        } catch (\Throwable $exception) {
            if ($storedFlatSketchPath) {
                Storage::disk('public')->delete($storedFlatSketchPath);
            }

            throw $exception;
        }

        return response()->json([
            'success' => true,
            'message' => 'Product sheet creado exitosamente.',
            'data' => $this->serializeProductSheet($productSheet->fresh()),
        ], 201);
    }

    /**
     * Valida y actualiza un registro existente.
     */
    public function update(UpdateProductSheetRequest $request, ProductSheet $sabana)
    {
        $validatedData = $request->validated();
        $validatedData['sizes'] = $this->normalizeSizes($validatedData['sizes'] ?? '[]');
        $validatedData = $this->prepareFlatSketchUpload($request, $validatedData, $sabana);
        $validatedData = $this->productSheetCreationService->sanitizeAttributes($validatedData);
        $this->logProductSheetSchemaDebug('update', $validatedData);
        $sabana->update($validatedData);

        return response()->json([
            'success' => true,
            'message' => 'Product sheet actualizado exitosamente.',
            'data' => $this->serializeProductSheet($sabana->fresh()),
        ]);
    }

    public function convertToProduct(ConvertProductSheetRequest $request, ProductSheet $sabana)
    {
        if ($sabana->type !== self::TYPE_SAMPLE) {
            return response()->json([
                'success' => false,
                'message' => 'Solo se pueden convertir fichas de tipo muestra.',
            ], 422);
        }

        $productReference = $request->input('product_reference') ?? $this->generateProductReference($sabana);

        $sabana->update([
            'product_reference' => $productReference,
            'type' => self::TYPE_PRODUCT,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Sábana convertida a producto correctamente.',
            'data' => $sabana->fresh(),
        ]);
    }

    /**
     * Obtiene un registro especÃ­fico con datos enriquecidos.
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
            $data = $this->serializeProductSheet($productSheet);
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
            $query->where(function ($sub) use ($request) {
                $sub->where('sample_reference', 'like', '%' . $request->search . '%')
                    ->orWhere('product_reference', 'like', '%' . $request->search . '%');
            });
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
        $keys = ['sections', 'groups', 'brands', 'worlds', 'fabric_bases', 'yarn_bases', 'sizes', 'operational_lists'];
        $catalogs = [];
        foreach ($keys as $key) {
            $catalogs[$key] = $this->settingService->getCatalogOptions($key);
        }
        return $catalogs;
    }

    public function parameters(Request $request): JsonResponse
    {
        $key = $request->query('key');
        if (!$key) {
            return response()->json(['success' => false, 'message' => 'El parÃ¡metro key es requerido.'], 422);
        }

        $options = $this->settingService->getCatalogOptions($key);

        if ($options->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'No se encontraron opciones para esa clave.'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $options->values()->all(),
        ]);
    }

    private function prepareFlatSketchUpload(Request $request, array $data, ?ProductSheet $productSheet = null): array
    {
        // Never persist the uploaded file object as a DB attribute.
        unset($data['flat_sketch']);

        if (!$request->hasFile('flat_sketch')) {
            unset($data['flat_sketch_path']);
            return $data;
        }

        $existingPath = $productSheet?->flat_sketch_path;

        if ($existingPath) {
            Storage::disk('public')->delete($existingPath);
        }

        $path = Storage::disk('public')->putFile('product_sheets', $request->file('flat_sketch'));
        $data['flat_sketch_path'] = $path;

        return $data;
    }

    private function logProductSheetSchemaDebug(string $action, array $data): void
    {
        $connection = DB::connection();
        Log::debug('product_sheet_schema_debug', [
            'action' => $action,
            'connection' => $connection->getName(),
            'database' => $connection->getDatabaseName(),
            'table' => (new ProductSheet())->getTable(),
            'keys' => array_keys($data),
            'contains_flat_sketch' => array_key_exists('flat_sketch', $data),
            'contains_flat_sketch_path' => array_key_exists('flat_sketch_path', $data),
            'has_column_flat_sketch' => Schema::connection($connection->getName())->hasColumn('product_sheets', 'flat_sketch'),
            'has_column_flat_sketch_path' => Schema::connection($connection->getName())->hasColumn('product_sheets', 'flat_sketch_path'),
        ]);
    }

    private function serializeProductSheet(ProductSheet $productSheet): array
    {
        $data = $productSheet->toArray();
        $data['flat_sketch_path'] = $productSheet->flat_sketch_path ?? null;

        return $data;
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
            
            // Si ya es un array indexado, retornar como estÃ¡
            return $sizesJson;
        } catch (\Exception $e) {
            // Si hay algÃºn error, retornar el valor original
            return $sizesJson;
        }
    }
    private function generateProductReference(ProductSheet $productSheet): string
    {
        $base = trim((string) ($productSheet->product_reference ?? $productSheet->sample_reference ?? 'PRODUCTO'));
        $candidate = $base;
        $attempt = 0;

        while (ProductSheet::where('product_reference', $candidate)
            ->where('id', '!=', $productSheet->id)
            ->exists()
        ) {
            $attempt++;
            $candidate = "{$base}-P{$attempt}";
        }

        return $candidate;
    }

}
