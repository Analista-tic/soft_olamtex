<?php
namespace App\Http\Controllers\Production;

use App\Http\Controllers\Controller;
use App\Http\Requests\Production\ConvertProductSheetRequest;
use App\Http\Requests\Production\StoreProductSheetRequest;
use App\Http\Requests\Production\UpdateProductSheetRequest;
use App\Models\Production\ProductSheet;
use App\Services\Production\ProductSheetCreationService;
use App\Services\Production\ProductSheetPersistenceService;
use App\Services\Settings\SettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

class ProductSheetController extends Controller
{
    private const TYPE_PRODUCT = 'producto';
    private const TYPE_SAMPLE = 'muestra';

    public function __construct(
        private SettingService $settingService,
        private ProductSheetCreationService $productSheetCreationService,
        private ProductSheetPersistenceService $productSheetPersistenceService,
    ) {
    }
    /**
     * Carga datos relacionados y los envÃƒÆ’Ã‚Â­a a la vista.
     */
    public function index()
    {
        $catalogs = $this->gatherCatalogs();

        return Inertia::render('Production/ProductSheets/Index', [
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
        $validatedData['sizes'] = $this->productSheetPersistenceService->normalizeSizes($validatedData['sizes'] ?? '[]');
        $validatedData = $this->productSheetPersistenceService->prepareFlatSketchUpload($request, $validatedData);
        $validatedData = $this->productSheetCreationService->sanitizeAttributes($validatedData);
        $storedFlatSketchPath = $validatedData['flat_sketch_path'] ?? null;

        try {
            $productSheet = DB::transaction(function () use ($validatedData) {
                return $this->productSheetCreationService->create($validatedData);
            });
        } catch (\Throwable $exception) {
            $this->productSheetPersistenceService->rollbackFlatSketch($storedFlatSketchPath);

            throw $exception;
        }

        return response()->json([
            'success' => true,
            'message' => 'Product sheet creado exitosamente.',
            'data' => $this->productSheetPersistenceService->serializeProductSheet($productSheet->fresh()),
        ], 201);
    }

    /**
     * Valida y actualiza un registro existente.
     */
    public function update(UpdateProductSheetRequest $request, ProductSheet $sabana)
    {
        $validatedData = $request->validated();
        $validatedData['sizes'] = $this->productSheetPersistenceService->normalizeSizes($validatedData['sizes'] ?? '[]');
        $validatedData = $this->productSheetPersistenceService->prepareFlatSketchUpload($request, $validatedData, $sabana);
        $validatedData = $this->productSheetCreationService->sanitizeAttributes($validatedData);
        $sabana->update($validatedData);

        return response()->json([
            'success' => true,
            'message' => 'Product sheet actualizado exitosamente.',
            'data' => $this->productSheetPersistenceService->serializeProductSheet($sabana->fresh()),
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
            'message' => 'SÃƒÆ’Ã‚Â¡bana convertida a producto correctamente.',
            'data' => $sabana->fresh(),
        ]);
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
            $data = $this->productSheetPersistenceService->serializeProductSheet($productSheet);
            $data['sizes'] = is_array($productSheet->sizes) ? json_encode($productSheet->sizes) : $productSheet->sizes;
            $data['processes'] = is_array($productSheet->processes) ? json_encode($productSheet->processes) : $productSheet->processes;
            $data['section'] = $productSheet->section;
            $data['group'] = $productSheet->group;
            $data['brand'] = $productSheet->brand;
            $data['world'] = $productSheet->world;
            $data['fabric_base'] = $productSheet->fabric_base;
            $data['yarn_base'] = $productSheet->yarn_base;
            $data['flat_sketch_path'] = $this->resolveExistingFlatSketchPath($productSheet->flat_sketch_path);
            $data['has_production_orders'] = $productSheet->hasProductionOrders();

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
        $requestIdHeader = $request->header('X-Request-Id');
        $requestId = is_string($requestIdHeader) && trim($requestIdHeader) !== '' ? trim($requestIdHeader) : null;

        Log::info('product_sheet.list.received', [
            'request_id' => $requestId,
            'endpoint' => 'product-sheets.getData',
            'user_id' => $request->user()?->id,
            'timestamp' => now()->toIso8601String(),
            'filters' => [
                'search' => $request->filled('search') ? mb_substr((string) $request->input('search'), 0, 80) : null,
                'status' => $request->input('status'),
                'section_id' => $request->input('section_id'),
                'world_id' => $request->input('world_id'),
                'page' => $request->input('page', 1),
                'per_page' => 10,
            ],
        ]);

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

        $products_sheets = $query->withCount('productionOrders')->orderBy('id', 'desc')->paginate(10);

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
            $product->flat_sketch_path = $this->resolveExistingFlatSketchPath($product->flat_sketch_path);
            $product->has_production_orders = ($product->production_orders_count ?? 0) > 0;
            return $product;
        });

        Log::info('product_sheet.list.completed', [
            'request_id' => $requestId,
            'endpoint' => 'product-sheets.getData',
            'user_id' => $request->user()?->id,
            'timestamp' => now()->toIso8601String(),
            'returned_count' => count($products_sheets->items()),
            'total' => $products_sheets->total(),
            'page' => $products_sheets->currentPage(),
            'per_page' => $products_sheets->perPage(),
        ]);

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
            return response()->json(['success' => false, 'message' => 'El parámetro key es requerido.'], 422);
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

    /**
     * Retorna la ruta solo si existe físicamente en disco público.
     * Evita exponer rutas huérfanas que disparan 403/404 en frontend.
     */
    private function resolveExistingFlatSketchPath(?string $rawPath): ?string
    {
        if (!is_string($rawPath) || trim($rawPath) === '') {
            return null;
        }

        $path = str_replace('\\', '/', trim($rawPath));
        if (preg_match('/^https?:\/\//i', $path)) {
            return $path;
        }

        $normalized = ltrim($path, '/');
        if (Str::startsWith($normalized, 'storage/')) {
            $normalized = Str::after($normalized, 'storage/');
        }

        $candidates = [$normalized];
        if (Str::startsWith($normalized, 'product-sheets/flat-sketches/')) {
            $candidates[] = Str::replaceFirst('product-sheets/flat-sketches/', 'product_sheets/', $normalized);
        }
        if (Str::startsWith($normalized, 'product_sheets/')) {
            $candidates[] = Str::replaceFirst('product_sheets/', 'product-sheets/flat-sketches/', $normalized);
        }

        foreach ($candidates as $candidate) {
            if ($candidate !== '' && Storage::disk('public')->exists($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

}
