<?php

namespace App\Http\Controllers\ProductSheet;

use App\Http\Controllers\Controller;
use App\Models\ProductSheet\ProductSheet;
use App\Models\ProductSheet\Programmer;
use App\Services\Settings\SettingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class ProgrammerController extends Controller
{
    private const PRIORITIES = ['A', 'B', 'C', 'D'];
    private const STATUSES = ['complete', 'incomplete'];
    private const CATALOG_KEYS = [
        'sections',
        'groups',
        'brands',
        'worlds',
        'fabric_bases',
        'yarn_bases',
        'destinations',
    ];

    public function __construct(private SettingService $settingService)
    {
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $catalogs = $this->loadCatalogs(self::CATALOG_KEYS);
        $sections = $catalogs['sections']->keyBy('id');
        $groups = $catalogs['groups']->keyBy('id');
        $brands = $catalogs['brands']->keyBy('id');
        $worlds = $catalogs['worlds']->keyBy('id');
        $fabricBases = $catalogs['fabric_bases']->keyBy('id');
        $yarnBases = $catalogs['yarn_bases']->keyBy('id');
        $destinations = $catalogs['destinations'];

        $productSheets = ProductSheet::all(); // Traer todos los product sheets

        // Enriquecer productSheets con los nombres desde settings
        $productSheets->transform(function ($product) use ($sections, $groups, $brands, $worlds, $fabricBases, $yarnBases) {
            $product->section = $sections->get($product->section_id, ['id' => $product->section_id, 'name' => 'N/A']);
            $product->group = $groups->get($product->group_id, ['id' => $product->group_id, 'name' => 'N/A']);
            $product->brand = $brands->get($product->brand_id, ['id' => $product->brand_id, 'name' => 'N/A']);
            $product->world = $worlds->get($product->world_id, ['id' => $product->world_id, 'name' => 'N/A']);
            $product->fabric_base = $fabricBases->get($product->fabric_base_id, ['id' => $product->fabric_base_id, 'name' => 'N/A']);
            $product->yarn_base = $product->yarn_base_id ? $yarnBases->get($product->yarn_base_id, ['id' => $product->yarn_base_id, 'name' => 'N/A']) : null;
            return $product;
        });

        // Asegurarse de que los datos sean arreglos antes de pasarlos a la vista
        return Inertia::render('Programmers/Index', [
            'productSheets' => $productSheets ?? [],
            'destinations' => $destinations->values()->all(),
            'sections' => $sections->values()->all(),
            'groups' => $groups->values()->all(),
            'brands' => $brands->values()->all(),
            'worlds' => $worlds->values()->all(),
            'fabricBases' => $fabricBases->values()->all(),
            'yarnBases' => $yarnBases->values()->all(),
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
        $validatedData = $request->validate([
            'lots' => 'required|array|min:1',
            'lots.*.product_sheet_id' => 'required|exists:product_sheets,id',
            'lots.*.season' => 'required|string|max:255',
            'lots.*.destination_id' => 'required|integer|min:1',
            'lots.*.priority' => ['required', 'string', Rule::in(self::PRIORITIES)],
            'lots.*.units' => 'required|integer|min:1',
            'lots.*.ecommerce_units' => 'nullable',
            'lots.*.status' => ['required', 'string', Rule::in(self::STATUSES)],
        ]);

        $programmers = DB::transaction(function () use ($validatedData) {
            return collect($validatedData['lots'])->map(function (array $lot) {
                $ecommerceUnits = $this->normalizeEcommerceUnits($lot['ecommerce_units'] ?? null);

                return Programmer::create([
                    'product_sheet_id' => $lot['product_sheet_id'],
                    'season' => $lot['season'],
                    'destination_id' => $lot['destination_id'],
                    'priority' => $lot['priority'],
                    'units' => $lot['units'],
                    'ecommerce_units' => $ecommerceUnits,
                    'status' => $lot['status'],
                ]);
            });
        });

        return response()->json([
            'message' => 'Programmers created successfully.',
            'data' => $programmers,
        ], 201);
    }

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
        $validatedData = $request->validate([
            'product_sheet_id' => 'sometimes|exists:product_sheets,id',
            'season' => 'sometimes|string|max:255',
            'destination_id' => 'sometimes|integer|min:1',
            'priority' => ['sometimes', 'string', Rule::in(self::PRIORITIES)],
            'units' => 'sometimes|integer|min:1',
            'ecommerce_units' => 'sometimes|nullable',
            'status' => ['sometimes', 'string', Rule::in(self::STATUSES)],
        ]);

        if (array_key_exists('ecommerce_units', $validatedData)) {
            $validatedData['ecommerce_units'] = $this->normalizeEcommerceUnits($validatedData['ecommerce_units']);
        }

        $programador->update($validatedData);
        $programador->refresh();

        return response()->json([
            'message' => 'Programmer updated successfully.',
            'data' => $programador,
        ]);
    }

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

        $catalogs = $this->loadCatalogs(['destinations', 'sections', 'groups']);
        $destinations = $catalogs['destinations']->keyBy('id');
        $sections = $catalogs['sections']->keyBy('id');
        $groups = $catalogs['groups']->keyBy('id');
        
        $programmers->getCollection()->transform(function ($programmer) use ($destinations, $sections, $groups) {
            $programmer->destination = $destinations->get($programmer->destination_id, ['id' => $programmer->destination_id, 'name' => 'N/A']);
            if ($programmer->productSheet) {
                $sectionId = $programmer->productSheet->section_id ?? null;
                $groupId = $programmer->productSheet->group_id ?? null;
                $programmer->productSheet->section = $sectionId
                    ? $sections->get($sectionId, ['id' => $sectionId, 'name' => 'N/A'])
                    : null;
                $programmer->productSheet->group = $groupId
                    ? $groups->get($groupId, ['id' => $groupId, 'name' => 'N/A'])
                    : null;
            }
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

    private function loadCatalogs(array $keys): array
    {
        $catalogs = [];

        foreach ($keys as $key) {
            $catalogs[$key] = $this->settingService->getCatalogOptions($key);
        }

        return $catalogs;
    }

    protected function normalizeEcommerceUnits($value): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_array($value)) {
            return $value;
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw ValidationException::withMessages([
                    'ecommerce_units' => 'El formato de ecommerce_units no es valido.',
                ]);
            }

            if ($decoded !== null && !is_array($decoded)) {
                throw ValidationException::withMessages([
                    'ecommerce_units' => 'El formato de ecommerce_units no es valido.',
                ]);
            }

            return $decoded;
        }

        throw ValidationException::withMessages([
            'ecommerce_units' => 'El formato de ecommerce_units no es valido.',
        ]);
    }
}
