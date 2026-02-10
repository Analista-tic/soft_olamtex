<?php

namespace App\Http\Controllers\ProductSheet;

use App\Helpers\MonthLabel;
use App\Http\Controllers\Controller;
use App\Http\Requests\ProductSheet\StoreProgrammerRequest;
use App\Http\Requests\ProductSheet\UpdateProgrammerRequest;
use App\Models\ProductSheet\ProductSheet;
use App\Models\ProductSheet\Programmer;
use App\Services\ProductSheet\ProgrammerService;
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
        'months',
        'priorities',
    ];

    public function __construct(
        private SettingService $settingService,
        private ProgrammerService $programmerService,
    )
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

        $displayReferenceSql = "CASE 
            WHEN type = 'muestra' THEN sample_reference 
            WHEN type = 'producto' THEN product_reference 
            ELSE NULL 
        END";

        $productSheets = ProductSheet::query()
            ->whereNull('deleted_at')
            ->whereRaw("NULLIF(TRIM({$displayReferenceSql}), '') IS NOT NULL")
            ->select([
                'id',
                'type',
                'sample_reference',
                'product_reference',
                'status',
                'section_id',
                'group_id',
                'brand_id',
                'world_id',
                'fabric_base_id',
                'yarn_base_id',
                'billing_price',
                'selling_price',
                'sizes',
                'description',
            ])
            ->selectRaw("{$displayReferenceSql} AS display_reference")
            ->orderByDesc('id')
            ->get();

        // Enriquecer productSheets con los nombres desde settings
        $productSheets->transform(function ($product) use ($sections, $groups, $brands, $worlds, $fabricBases, $yarnBases) {
            $displayReference = trim((string) ($product->display_reference ?? ''));

            $product->section = $sections->get($product->section_id, ['id' => $product->section_id, 'name' => 'N/A']);
            $product->group = $groups->get($product->group_id, ['id' => $product->group_id, 'name' => 'N/A']);
            $product->brand = $brands->get($product->brand_id, ['id' => $product->brand_id, 'name' => 'N/A']);
            $product->world = $worlds->get($product->world_id, ['id' => $product->world_id, 'name' => 'N/A']);
            $product->fabric_base = $fabricBases->get($product->fabric_base_id, ['id' => $product->fabric_base_id, 'name' => 'N/A']);
            $product->yarn_base = $product->yarn_base_id ? $yarnBases->get($product->yarn_base_id, ['id' => $product->yarn_base_id, 'name' => 'N/A']) : null;
            $product->display_reference = $displayReference;
            $product->reference = $displayReference;
            $product->value = $product->id;
            $product->label = $displayReference;
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
            'months' => $catalogs['months']->values()->all(),
            'priorities' => $catalogs['priorities']->values()->all(),
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
     * Los registros manuales NO usan estimated_delivery_date, sí usan priority.
     */
    public function store(StoreProgrammerRequest $request)
    {
        $validatedData = $request->validated();

        $programmers = DB::transaction(function () use ($validatedData) {
            return collect($validatedData['lots'])->map(function (array $lot) {
                $productSheet = ProductSheet::findOrFail($lot['product_sheet_id']);
                $ecommerceUnits = $this->normalizeEcommerceUnits($lot['ecommerce_units'] ?? null);
                $units = isset($lot['units']) ? (int) $lot['units'] : null;

                // Registros manuales: resuelven status solo con units (no usan estimated_delivery_date)
                $status = $units !== null && $units > 0 ? 'complete' : 'incomplete';

                $programmer = Programmer::create([
                    'product_sheet_id' => $lot['product_sheet_id'],
                    'season' => $lot['season'],
                    'destination_id' => $lot['destination_id'],
                    'priority' => $lot['priority'] ?? null,
                    'units' => $units,
                    'estimated_delivery_date' => null, // Manuales no usan este campo
                    'ecommerce_units' => $ecommerceUnits,
                    'status' => $status,
                    'source' => 'manual',
                ]);

                // Generar op_code para registro manual
                $programmer->op_code = $this->programmerService->nextOpCode(
                    $productSheet->type ?? 'muestra',
                    (bool) $productSheet->woven_product
                );
                $programmer->save();

                return $programmer;
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
     * - Auto-generados: ignoran priority, usan estimated_delivery_date
     * - Manuales: ignoran estimated_delivery_date, usan priority
     */
    public function update(UpdateProgrammerRequest $request, Programmer $programador)
    {
        $isAutoGenerated = $programador->isAutoGenerated();

        // Obtener datos validados con limpieza de campos según tipo
        $validatedData = $request->validatedWithCleanup();

        if (array_key_exists('ecommerce_units', $validatedData)) {
            $validatedData['ecommerce_units'] = $this->normalizeEcommerceUnits($validatedData['ecommerce_units']);
        }

        $units = array_key_exists('units', $validatedData)
            ? (isset($validatedData['units']) ? (int) $validatedData['units'] : null)
            : $programador->units;

        // Calcular status según tipo
        if ($isAutoGenerated) {
            $estimatedDeliveryDate = array_key_exists('estimated_delivery_date', $validatedData)
                ? $validatedData['estimated_delivery_date']
                : ($programador->estimated_delivery_date ? $programador->estimated_delivery_date->toDateString() : null);
            $validatedData['status'] = $this->programmerService->resolveStatus($units, $estimatedDeliveryDate);
        } else {
            // Manuales: status solo depende de units
            $validatedData['status'] = $units !== null && $units > 0 ? 'complete' : 'incomplete';
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

        $query = Programmer::with([
            'productSheet:id,type,sample_reference,product_reference,section_id,group_id,world_id,brand_id,fabric_base_id,yarn_base_id,billing_price,selling_price,sizes,description,status,deleted_at',
        ]);

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
                $search = trim((string) $filters['search']);
                $q->whereNull('deleted_at')
                    ->where(function ($sub) use ($search) {
                        $sub->where('sample_reference', 'like', '%' . $search . '%')
                            ->orWhere('product_reference', 'like', '%' . $search . '%');
                    });
            });
        }

        $programmers = $query->paginate($perPage, ['*'], 'page', $page);

        $catalogs = $this->loadCatalogs(['destinations', 'sections', 'groups']);
        $destinations = $catalogs['destinations']->keyBy('id');
        $sections = $catalogs['sections']->keyBy('id');
        $groups = $catalogs['groups']->keyBy('id');
        
        $programmers->getCollection()->transform(function ($programmer) use ($destinations, $sections, $groups) {
            $isAuto = $programmer->isAutoGenerated();
            
            $programmer->destination = $destinations->get($programmer->destination_id, ['id' => $programmer->destination_id, 'name' => 'N/A']);
            
            // Determinar campos pendientes según tipo de registro
            if ($isAuto) {
                // Auto-generados: pendiente si falta units o estimated_delivery_date
                $programmer->is_pending_completion = !$programmer->units || !$programmer->estimated_delivery_date;
                $programmer->pending_fields = collect([
                    !$programmer->units ? 'unidades' : null,
                    !$programmer->estimated_delivery_date ? 'fecha de entrega' : null,
                ])->filter()->values()->all();
            } else {
                // Manuales: pendiente solo si falta units (no usan estimated_delivery_date)
                $programmer->is_pending_completion = !$programmer->units;
                $programmer->pending_fields = collect([
                    !$programmer->units ? 'unidades' : null,
                ])->filter()->values()->all();
            }

            // Traducir temporada numérica a nombre del mes
            $season = $programmer->season;
            if (is_numeric($season)) {
                $programmer->season_display = $this->programmerService->getMonthName((int) $season);
            } else {
                $programmer->season_display = $season;
            }

            // Campo adicional temporada_label usando el helper centralizado
            $programmer->temporada_label = MonthLabel::fromNumber($programmer->season);

            if ($programmer->productSheet) {
                $sectionId = $programmer->productSheet->section_id ?? null;
                $groupId = $programmer->productSheet->group_id ?? null;
                $type = $programmer->productSheet->type === 'producto' ? 'producto' : 'muestra';
                $displayReference = $type === 'producto'
                    ? trim((string) ($programmer->productSheet->product_reference ?? ''))
                    : trim((string) ($programmer->productSheet->sample_reference ?? ''));

                $programmer->productSheet->section = $sectionId
                    ? $sections->get($sectionId, ['id' => $sectionId, 'name' => 'N/A'])
                    : null;
                $programmer->productSheet->group = $groupId
                    ? $groups->get($groupId, ['id' => $groupId, 'name' => 'N/A'])
                    : null;
                $programmer->productSheet->display_reference = $displayReference;
                $programmer->productSheet->reference = $displayReference;
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
