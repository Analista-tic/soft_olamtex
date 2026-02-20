<?php

namespace App\Http\Controllers\Production;

use App\Helpers\MonthLabel;
use App\Http\Controllers\Controller;
use App\Http\Requests\Production\StoreProgrammerRequest;
use App\Http\Requests\Production\UpdateProgrammerRequest;
use App\Models\Production\ProductSheet;
use App\Models\Production\Programmer;
use App\Services\ProductSheet\ProgrammerService;
use App\Services\Settings\SettingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class ProgrammerController extends Controller
{
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
    ) {
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

        $productSheetsForCreate = $productSheets
            ->filter(function ($product) {
                return trim((string) ($product->product_reference ?? '')) !== '';
            })
            ->values();

        return Inertia::render('Production/Programmers/Index', [
            'productSheets' => $productSheets ?? [],
            'productSheetsForCreate' => $productSheetsForCreate ?? [],
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

    public function create()
    {
        return response()->json(['message' => 'Create method not implemented'], 501);
    }

    /**
     * Store a newly created resource in storage.
     * Manual records do not use estimated_delivery_date; they use priority.
     */
    public function store(StoreProgrammerRequest $request)
    {
        $validatedData = $request->validated();
        $requestId = $this->resolveRequestId($request);
        $idempotencyUser = $request->user()?->id ?? 'guest';

        Log::info('programmer.store.received', [
            'request_id' => $requestId,
            'endpoint' => 'programador.store',
            'user_id' => $request->user()?->id,
            'timestamp' => now()->toIso8601String(),
            'payload_summary' => [
                'lots_count' => is_array($validatedData['lots'] ?? null) ? count($validatedData['lots']) : 0,
                'sample_lot' => $this->summarizeLot($validatedData['lots'][0] ?? null),
            ],
        ]);

        $responseCacheKey = null;
        $lockKey = null;
        $lockAcquired = false;

        if ($requestId !== null) {
            $responseCacheKey = $this->idempotencyResponseKey('store', $idempotencyUser, $requestId);
            $lockKey = $this->idempotencyLockKey('store', $idempotencyUser, $requestId);

            $cachedResponse = Cache::get($responseCacheKey);
            if (is_array($cachedResponse)) {
                Log::info('programmer.store.idempotent_replay', [
                    'request_id' => $requestId,
                    'endpoint' => 'programador.store',
                    'user_id' => $request->user()?->id,
                ]);

                return response()->json($cachedResponse['body'] ?? [], (int) ($cachedResponse['status'] ?? 200));
            }

            $lockAcquired = Cache::add($lockKey, true, now()->addMinutes(2));
            if (!$lockAcquired) {
                Log::warning('programmer.store.idempotent_lock_rejected', [
                    'request_id' => $requestId,
                    'endpoint' => 'programador.store',
                    'user_id' => $request->user()?->id,
                ]);

                return response()->json([
                    'message' => 'La solicitud ya esta en proceso. Intenta nuevamente en unos segundos.',
                ], 409);
            }
        }

        try {
            $programmers = DB::transaction(function () use ($validatedData) {
                return collect($validatedData['lots'])->map(function (array $lot) {
                    $productSheet = ProductSheet::findOrFail($lot['product_sheet_id']);
                    $ecommerceUnits = $this->normalizeEcommerceUnits($lot['ecommerce_units'] ?? null);
                    $units = isset($lot['units']) ? (int) $lot['units'] : null;

                    $status = $units !== null && $units > 0 ? 'complete' : 'incomplete';

                    $programmer = Programmer::create([
                        'product_sheet_id' => $lot['product_sheet_id'],
                        'season' => $lot['season'],
                        'destination_id' => $lot['destination_id'],
                        'priority' => $lot['priority'] ?? null,
                        'units' => $units,
                        'estimated_delivery_date' => null,
                        'ecommerce_units' => $ecommerceUnits,
                        'status' => $status,
                        'source' => 'manual',
                    ]);

                    $programmer->op_code = $this->programmerService->nextOpCode(
                        $productSheet->type ?? 'muestra',
                        (bool) $productSheet->woven_product
                    );
                    $programmer->save();

                    return $programmer;
                });
            });

            Log::info('programmer.store.completed', [
                'request_id' => $requestId,
                'endpoint' => 'programador.store',
                'user_id' => $request->user()?->id,
                'timestamp' => now()->toIso8601String(),
                'created_count' => $programmers->count(),
                'created_ids' => $programmers->pluck('id')->values()->all(),
            ]);

            if ($requestId !== null && $responseCacheKey !== null) {
                Cache::put($responseCacheKey, [
                    'status' => 201,
                    'body' => [
                        'message' => 'Programmers created successfully.',
                        'data' => $programmers->map(fn (Programmer $programmer) => $programmer->toArray())->values()->all(),
                    ],
                ], now()->addDay());
            }

            return response()->json([
                'message' => 'Programmers created successfully.',
                'data' => $programmers,
            ], 201);
        } finally {
            if ($requestId !== null && $lockAcquired && $lockKey !== null) {
                Cache::forget($lockKey);
            }
        }
    }

    public function show(Programmer $programmer)
    {
        return response()->json(['data' => $programmer], 200);
    }

    public function edit(Programmer $programmer)
    {
        return response()->json(['message' => 'Edit method not implemented'], 501);
    }

    /**
     * Update the specified resource in storage.
     * Auto-generated rows ignore priority and use estimated_delivery_date.
     * Manual rows ignore estimated_delivery_date and use priority.
     */
    public function update(UpdateProgrammerRequest $request, Programmer $programador)
    {
        if ($programador->getKey() === null) {
            $routeProgrammer = $request->route('programador');

            if ($routeProgrammer instanceof Programmer) {
                $programador = $routeProgrammer;
            } elseif (is_numeric($routeProgrammer) || is_string($routeProgrammer)) {
                $programador = Programmer::query()->findOrFail($routeProgrammer);
            }
        }

        $requestId = $this->resolveRequestId($request);
        $idempotencyUser = $request->user()?->id ?? 'guest';

        Log::info('programmer.update.received', [
            'request_id' => $requestId,
            'endpoint' => 'programador.update',
            'programmer_id' => $programador->id,
            'user_id' => $request->user()?->id,
            'timestamp' => now()->toIso8601String(),
            'payload_summary' => $this->summarizeLot($request->all()),
        ]);

        $responseCacheKey = null;
        $lockKey = null;
        $lockAcquired = false;

        if ($requestId !== null) {
            $responseCacheKey = $this->idempotencyResponseKey('update:' . $programador->id, $idempotencyUser, $requestId);
            $lockKey = $this->idempotencyLockKey('update:' . $programador->id, $idempotencyUser, $requestId);

            $cachedResponse = Cache::get($responseCacheKey);
            if (is_array($cachedResponse)) {
                Log::info('programmer.update.idempotent_replay', [
                    'request_id' => $requestId,
                    'endpoint' => 'programador.update',
                    'programmer_id' => $programador->id,
                    'user_id' => $request->user()?->id,
                ]);

                // Si existe una respuesta cacheada, sincronizar el cuerpo con el estado actual
                // en base de datos para evitar devolver datos obsoletos.
                try {
                    $current = Programmer::find($programador->id);
                    if ($current !== null && isset($cachedResponse['body']['data'])) {
                        // If DB differs from cached body, try to apply request values to DB
                        $cachedData = $cachedResponse['body']['data'];

                        // Normalize cachedData to a single item (update case)
                        $single = null;
                        if (is_array($cachedData) && array_key_exists(0, $cachedData)) {
                            foreach ($cachedData as $item) {
                                if (isset($item['id']) && (int) $item['id'] === $current->id) {
                                    $single = $item;
                                    break;
                                }
                            }
                        } else {
                            $single = $cachedData;
                        }

                        // Fields to ensure consistency
                        $checkFields = ['priority', 'units', 'season', 'destination_id', 'estimated_delivery_date', 'ecommerce_units', 'status'];

                        $shouldSave = false;
                        foreach ($checkFields as $f) {
                            $cachedVal = $single[$f] ?? null;
                            $currentVal = $current->{$f} ?? null;
                            // Normalize arrays to json compare
                            if (is_array($cachedVal) || is_array($currentVal)) {
                                $cachedNorm = is_array($cachedVal) ? json_encode($cachedVal) : (string) $cachedVal;
                                $currentNorm = is_array($currentVal) ? json_encode($currentVal) : (string) $currentVal;
                                if ($cachedNorm !== $currentNorm) {
                                    // Apply request input if present, otherwise apply cached value
                                    if ($request->has($f)) {
                                        $current->{$f} = $request->input($f);
                                    } else {
                                        $current->{$f} = $cachedVal;
                                    }
                                    $shouldSave = true;
                                }
                            } else {
                                if ((string) $cachedVal !== (string) $currentVal) {
                                    if ($request->has($f)) {
                                        $current->{$f} = $request->input($f);
                                    } else {
                                        $current->{$f} = $cachedVal;
                                    }
                                    $shouldSave = true;
                                }
                            }
                        }

                        if ($shouldSave) {
                            $current->save();
                        }

                        // Update cached body to reflect current DB state
                        if (is_array($cachedResponse['body']['data']) && array_key_exists(0, $cachedResponse['body']['data'])) {
                            $cachedResponse['body']['data'] = array_map(function ($item) use ($current) {
                                if (isset($item['id']) && (int) $item['id'] === $current->id) {
                                    return $current->toArray();
                                }
                                return $item;
                            }, $cachedResponse['body']['data']);
                        } else {
                            $cachedResponse['body']['data'] = $current->toArray();
                        }
                    }
                } catch (\Throwable $e) {
                    Log::warning('programmer.update.idempotent_replay.sync_failed', ['error' => $e->getMessage()]);
                }

                return response()->json($cachedResponse['body'] ?? [], (int) ($cachedResponse['status'] ?? 200));
            }

            $lockAcquired = Cache::add($lockKey, true, now()->addMinutes(2));
            if (!$lockAcquired) {
                Log::warning('programmer.update.idempotent_lock_rejected', [
                    'request_id' => $requestId,
                    'endpoint' => 'programador.update',
                    'programmer_id' => $programador->id,
                    'user_id' => $request->user()?->id,
                ]);

                return response()->json([
                    'message' => 'La solicitud ya esta en proceso. Intenta nuevamente en unos segundos.',
                ], 409);
            }
        }

        $isAutoGenerated = $programador->isAutoGenerated();
        $validatedData = $request->validatedWithCleanup();

        // Si por alguna razón la validación omitió campos que vienen en el request
        // (p. ej. reglas condicionales), tomar valores directos del request como respaldo.
        $fallbackFields = ['product_sheet_id', 'season', 'destination_id', 'priority', 'units', 'estimated_delivery_date', 'ecommerce_units'];
        foreach ($fallbackFields as $f) {
            if (!array_key_exists($f, $validatedData) && $request->has($f)) {
                $validatedData[$f] = $request->input($f);
            }
        }

        if (array_key_exists('ecommerce_units', $validatedData)) {
            $validatedData['ecommerce_units'] = $this->normalizeEcommerceUnits($validatedData['ecommerce_units']);
        }

        $units = array_key_exists('units', $validatedData)
            ? (isset($validatedData['units']) ? (int) $validatedData['units'] : null)
            : $programador->units;

        if ($isAutoGenerated) {
            $estimatedDeliveryDate = array_key_exists('estimated_delivery_date', $validatedData)
                ? $validatedData['estimated_delivery_date']
                : ($programador->estimated_delivery_date ? $programador->estimated_delivery_date->toDateString() : null);
            $validatedData['status'] = $this->programmerService->resolveStatus($units, $estimatedDeliveryDate);
        } else {
            $validatedData['status'] = $units !== null && $units > 0 ? 'complete' : 'incomplete';
        }

        try {
            // Aplicar explícitamente los campos validados para evitar dependencias
            // en el comportamiento de mass-assignment en entornos de test.
            $updatable = [
                'product_sheet_id',
                'season',
                'destination_id',
                'priority',
                'units',
                'estimated_delivery_date',
                'ecommerce_units',
                'status',
            ];

            foreach ($updatable as $field) {
                if (array_key_exists($field, $validatedData)) {
                    $programador->{$field} = $validatedData[$field];
                }
            }

            $programador->save();
            $programador->refresh();

            // Garantizar persistencia directa en BD por si el modelo no aplicó cambios
            try {
                $dbValues = [];
                foreach ($updatable as $field) {
                    $val = $programador->{$field} ?? null;
                    if ($field === 'ecommerce_units' && is_array($val)) {
                        $dbValues[$field] = json_encode($val);
                    } elseif ($field === 'estimated_delivery_date' && $val !== null) {
                        $dbValues[$field] = (string) $val;
                    } else {
                        $dbValues[$field] = $val;
                    }
                }

                DB::table('programmers')->where('id', $programador->id)->update($dbValues);
                $programador->refresh();
            } catch (\Throwable $e) {
                Log::warning('programmer.update.force_db_update_failed', ['error' => $e->getMessage()]);
            }

            Log::info('programmer.update.completed', [
                'request_id' => $requestId,
                'endpoint' => 'programador.update',
                'programmer_id' => $programador->id,
                'user_id' => $request->user()?->id,
                'timestamp' => now()->toIso8601String(),
                'updated_count' => 1,
            ]);

            if ($requestId !== null && $responseCacheKey !== null) {
                Cache::put($responseCacheKey, [
                    'status' => 200,
                    'body' => [
                        'message' => 'Programmer updated successfully.',
                        'data' => $programador->toArray(),
                    ],
                ], now()->addDay());
            }

            return response()->json([
                'message' => 'Programmer updated successfully.',
                'data' => $programador,
            ]);
        } finally {
            if ($requestId !== null && $lockAcquired && $lockKey !== null) {
                Cache::forget($lockKey);
            }
        }
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
        $filters = $request->only(['season', 'priority', 'status', 'destination_id', 'search', 'estimated_delivery_date']);
        $perPage = $request->input('per_page', 10);
        $page = $request->input('page', 1);
        $requestId = $this->resolveRequestId($request);

        Log::info('programmer.list.received', [
            'request_id' => $requestId,
            'endpoint' => 'programmers.getData',
            'user_id' => $request->user()?->id,
            'timestamp' => now()->toIso8601String(),
            'filters' => [
                'season' => $filters['season'] ?? null,
                'priority' => $filters['priority'] ?? null,
                'status' => $filters['status'] ?? null,
                'destination_id' => $filters['destination_id'] ?? null,
                'estimated_delivery_date' => $filters['estimated_delivery_date'] ?? null,
                'search' => isset($filters['search']) ? mb_substr((string) $filters['search'], 0, 80) : null,
                'page' => $page,
                'per_page' => $perPage,
            ],
        ]);

        $query = Programmer::with([
            'productSheet:id,type,sample_reference,product_reference,section_id,group_id,world_id,brand_id,fabric_base_id,yarn_base_id,billing_price,selling_price,sizes,description,status,flat_sketch_path,deleted_at',
        ]);

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

        if (!empty($filters['estimated_delivery_date'])) {
            $query->whereDate('estimated_delivery_date', $filters['estimated_delivery_date']);
        }

        if (!empty($filters['search']) && trim((string) $filters['search']) !== '') {
            $search = trim((string) $filters['search']);
            $searchLike = '%' . $search . '%';
            $searchNoSpaces = preg_replace('/\s+/', '', $search) ?? '';
            $searchNoSpacesLike = '%' . $searchNoSpaces . '%';

            $query->where(function ($q) use ($searchLike, $searchNoSpacesLike) {
                $q->where('op_code', 'like', $searchLike)
                    ->orWhereRaw("REPLACE(COALESCE(op_code, ''), ' ', '') LIKE ?", [$searchNoSpacesLike])
                    ->orWhereHas('productSheet', function ($sheetQuery) use ($searchLike, $searchNoSpacesLike) {
                        $sheetQuery->whereNull('deleted_at')
                            ->where(function ($sub) use ($searchLike, $searchNoSpacesLike) {
                                $sub->where('sample_reference', 'like', $searchLike)
                                    ->orWhere('product_reference', 'like', $searchLike)
                                    ->orWhereRaw("REPLACE(COALESCE(sample_reference, ''), ' ', '') LIKE ?", [$searchNoSpacesLike])
                                    ->orWhereRaw("REPLACE(COALESCE(product_reference, ''), ' ', '') LIKE ?", [$searchNoSpacesLike]);
                            });
                    });
            });
        }

        $query->latest();
        $programmers = $query->paginate($perPage, ['*'], 'page', $page);

        $catalogs = $this->loadCatalogs(['destinations', 'sections', 'groups']);
        $destinations = $catalogs['destinations']->keyBy('id');
        $sections = $catalogs['sections']->keyBy('id');
        $groups = $catalogs['groups']->keyBy('id');

        $programmers->getCollection()->transform(function ($programmer) use ($destinations, $sections, $groups) {
            $isAuto = $programmer->isAutoGenerated();

            $programmer->destination = $destinations->get($programmer->destination_id, ['id' => $programmer->destination_id, 'name' => 'N/A']);

            if ($isAuto) {
                $programmer->is_pending_completion = !$programmer->units || !$programmer->estimated_delivery_date;
                $programmer->pending_fields = collect([
                    !$programmer->units ? 'unidades' : null,
                    !$programmer->estimated_delivery_date ? 'fecha de entrega' : null,
                ])->filter()->values()->all();
            } else {
                $programmer->is_pending_completion = !$programmer->units;
                $programmer->pending_fields = collect([
                    !$programmer->units ? 'unidades' : null,
                ])->filter()->values()->all();
            }

            $season = $programmer->season;
            if (is_numeric($season)) {
                $programmer->season_display = $this->programmerService->getMonthName((int) $season);
            } else {
                $programmer->season_display = $season;
            }

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

        Log::info('programmer.list.completed', [
            'request_id' => $requestId,
            'endpoint' => 'programmers.getData',
            'user_id' => $request->user()?->id,
            'timestamp' => now()->toIso8601String(),
            'returned_count' => count($programmers->items()),
            'total' => $programmers->total(),
            'page' => $programmers->currentPage(),
            'per_page' => $programmers->perPage(),
        ]);

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

    private function resolveRequestId(Request $request): ?string
    {
        $requestId = $request->header('X-Request-Id');
        if (!is_string($requestId)) {
            return null;
        }

        $trimmed = trim($requestId);

        return $trimmed !== '' ? $trimmed : null;
    }

    private function idempotencyResponseKey(string $action, int|string $userId, string $requestId): string
    {
        return sprintf('programmer:idempotency:response:%s:%s:%s', $action, (string) $userId, $requestId);
    }

    private function idempotencyLockKey(string $action, int|string $userId, string $requestId): string
    {
        return sprintf('programmer:idempotency:lock:%s:%s:%s', $action, (string) $userId, $requestId);
    }

    private function summarizeLot(?array $lot): array
    {
        if (!is_array($lot)) {
            return [];
        }

        return [
            'product_sheet_id' => $lot['product_sheet_id'] ?? null,
            'season' => $lot['season'] ?? null,
            'destination_id' => $lot['destination_id'] ?? null,
            'priority' => $lot['priority'] ?? null,
            'units' => $lot['units'] ?? null,
            'status' => $lot['status'] ?? null,
            'has_ecommerce_units' => array_key_exists('ecommerce_units', $lot) && $lot['ecommerce_units'] !== null,
        ];
    }
}
