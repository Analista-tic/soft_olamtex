<?php

namespace App\Http\Controllers\Production;

use App\Helpers\MonthLabel;
use App\Helpers\SeasonParser;
use App\Helpers\ScopeHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Production\StoreProgrammerRequest;
use App\Http\Requests\Production\UpdateProgrammerRequest;
use App\Models\Production\ProductSheet;
use App\Models\Production\ProductionOrder;
use App\Services\Production\ProductionOrderService;
use App\Services\Production\ProductionOrderSchedulingService;
use App\Services\Production\ProductSheetStatusService;
use App\Services\Settings\SettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;
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
        private ProductionOrderService $productionOrderService,
        private ProductSheetStatusService $productSheetStatusService,
        private ProductionOrderSchedulingService $schedulingService,
    ) {
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $this->productSheetStatusService->promoteIncompleteWithOperations();

        $catalogs = $this->loadCatalogs(self::CATALOG_KEYS);
        $sections = $catalogs['sections']->keyBy('id');
        $groups = $catalogs['groups']->keyBy('id');
        $brands = $catalogs['brands']->keyBy('id');
        $worlds = $catalogs['worlds']->keyBy('id');
        $fabricBases = $catalogs['fabric_bases']->keyBy('id');
        $yarnBases = $catalogs['yarn_bases']->keyBy('id');
        $destinations = $catalogs['destinations'];

        $displayReferenceSql = 'product_reference';

        $productSheets = ProductSheet::query()
            ->whereNull('deleted_at')
            ->where('type', 'producto')
            ->whereRaw("NULLIF(TRIM({$displayReferenceSql}), '') IS NOT NULL")
            ->select([
                'id',
                'type',
                'sample_reference',
                'product_reference',
                'product_reference_2',
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
                'version',
                'is_enabled',
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
                $hasReference = trim((string) ($product->product_reference ?? '')) !== '';
                $isComplete = ($product->status ?? '') === 'complete';
                $isEnabled = (bool) ($product->is_enabled ?? true);
                return $hasReference && $isComplete && $isEnabled;
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
            'months' => $catalogs['months']->isNotEmpty()
                ? $catalogs['months']->values()->all()
                : MonthLabel::options(),
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
        $userId = $request->user()?->id;

        Log::info('production_order.store.received', [
            'request_id' => $requestId,
            'endpoint' => 'programador.store',
            'user_id' => $request->user()?->id,
            'timestamp' => now()->toIso8601String(),
            'payload_summary' => [
                'lots_count' => is_array($validatedData['lots'] ?? null) ? count($validatedData['lots']) : 0,
                'sample_lot' => $this->summarizeLot($validatedData['lots'][0] ?? null),
            ],
        ]);

        $orders = DB::transaction(function () use ($validatedData, $request, $userId) {
            return collect($validatedData['lots'])->map(function (array $lot) use ($request, $userId) {
                    $productSheet = ProductSheet::findOrFail($lot['product_sheet_id']);
                    $ecommerceUnits = $this->normalizeEcommerceUnits($lot['ecommerce_units'] ?? null);
                    $units = isset($lot['units']) ? (int) $lot['units'] : null;
                    $season = (string) ($lot['season'] ?? '');
                    $dispatchMonthRaw = isset($lot['dispatch_month']) ? trim((string) $lot['dispatch_month']) : null;
                    $dispatchMonth = $dispatchMonthRaw !== null && $dispatchMonthRaw !== ''
                        ? $dispatchMonthRaw
                        : $season;
                    $seasonToken = $this->normalizeTemporalComparisonToken($season);
                    $dispatchMonthToken = $this->normalizeTemporalComparisonToken($dispatchMonth);
                    $isCrossSeasonAdvance = $seasonToken !== '' && $dispatchMonthToken !== '' && $seasonToken !== $dispatchMonthToken;
                    // Si hay cruce de temporadas, SIEMPRE es adelanto (sin opción a deshabilitar)
                    $isAdvance = $isCrossSeasonAdvance ? true : (bool) ($lot['is_advance'] ?? false);
                    $inputPriority = isset($lot['priority']) ? (string) $lot['priority'] : null;
                    $notes = isset($lot['notes']) ? trim((string) $lot['notes']) : null;

                    if ($season === '') {
                        throw ValidationException::withMessages([
                            'lots' => 'La temporada es requerida para programar órdenes.',
                        ]);
                    }

                    $priority = $this->schedulingService->resolvePriorityForNewOrder(
                        $productSheet,
                        $season,
                        max(1, (int) ($units ?? 1)),
                        $dispatchMonth,
                        $inputPriority,
                    );

                    if ($isAdvance) {
                        $estimatedDeliveryDate = isset($lot['estimated_delivery_date']) ? trim((string) $lot['estimated_delivery_date']) : '';

                        if ($estimatedDeliveryDate === '') {
                            if ($isCrossSeasonAdvance) {
                                $estimatedDeliveryDate = $this->schedulingService->resolveStandardDate($dispatchMonth, $priority);
                            } else {
                                throw ValidationException::withMessages([
                                    'lots' => 'La fecha estimada es obligatoria cuando el lote es adelanto.',
                                ]);
                            }
                        }

                        if (!$isCrossSeasonAdvance) {
                            try {
                                $this->schedulingService->validateAdvanceDate($estimatedDeliveryDate);
                            } catch (\InvalidArgumentException $exception) {
                                throw ValidationException::withMessages([
                                    'lots' => $exception->getMessage(),
                                ]);
                            }
                        }
                    } else {
                        $estimatedDeliveryDate = $this->schedulingService->resolveStandardDate($season, $priority);
                    }

                    $status = 'incomplete';

                    // Fase C — escritura dual canónica
                    $seasonParsed   = SeasonParser::parse($season);
                    $dispatchParsed = SeasonParser::parse($dispatchMonth);

                    $order = ProductionOrder::create([
                        'product_sheet_id'    => $lot['product_sheet_id'],
                        'season'              => $season,
                        'season_month'        => $seasonParsed['month'],
                        'season_year'         => $seasonParsed['year'],
                        'dispatch_month'      => $dispatchMonth,
                        'dispatch_month_num'  => $dispatchParsed['month'],
                        'dispatch_year'       => $dispatchParsed['year'],
                        'destination_id'      => $lot['destination_id'],
                        'priority'            => $priority,
                        'units'               => $units,
                        'estimated_delivery_date' => $estimatedDeliveryDate,
                        'is_advance'          => $isAdvance,
                        'notes'               => $notes,
                        'ecommerce_units'     => $ecommerceUnits,
                        'status'              => $status,
                        'current_process'     => config('product-sheet.batch_control_processes.0', 'I&D'),
                        'source'              => 'manual',
                        'created_by_user_id'  => $userId,
                    ]);

                    $order->op_code = $this->productionOrderService->nextOpCode(
                        $productSheet->type ?? 'muestra',
                        (bool) $productSheet->woven_product
                    );
                    $order->save();

                    $this->productionOrderService->createSnapshotForOrder($order);

                    return $order;
                });
        });

        Log::info('production_order.store.completed', [
            'request_id' => $requestId,
            'endpoint' => 'programador.store',
            'user_id' => $request->user()?->id,
            'timestamp' => now()->toIso8601String(),
            'created_count' => $orders->count(),
            'created_ids' => $orders->pluck('id')->values()->all(),
        ]);

        return response()->json([
            'message' => 'Programmers created successfully.',
            'data' => $orders,
        ], 201);
    }

    public function show(ProductionOrder $programador)
    {
        return response()->json(['data' => $programador], 200);
    }

    public function edit(ProductionOrder $programador)
    {
        return response()->json(['message' => 'Edit method not implemented'], 501);
    }

    /**
     * Update the specified resource in storage.
     * Both auto-generated and manual rows can have estimated_delivery_date.
     * Auto-generated rows do not have priority; manual rows do.
     */
    public function update(UpdateProgrammerRequest $request, ProductionOrder $programador)
    {
        if ($programador->getKey() === null) {
            $routeProgrammer = $request->route('programador');

            if ($routeProgrammer instanceof ProductionOrder) {
                $programador = $routeProgrammer;
            } elseif (is_numeric($routeProgrammer) || is_string($routeProgrammer)) {
                $programador = ProductionOrder::query()->findOrFail($routeProgrammer);
            }
        }

        $requestId = $this->resolveRequestId($request);

        Log::info('production_order.update.received', [
            'request_id' => $requestId,
            'endpoint' => 'programador.update',
            'order_id' => $programador->id,
            'user_id' => $request->user()?->id,
            'timestamp' => now()->toIso8601String(),
            'payload_summary' => $this->summarizeLot($request->all()),
        ]);

        $isAutoGenerated = $programador->isAutoGenerated();
        $validatedData = $request->validatedWithCleanup();

        // Si por alguna razÃƒÆ’Ã‚Â³n la validaciÃƒÆ’Ã‚Â³n omitiÃƒÆ’Ã‚Â³ campos que vienen en el request
        // (p. ej. reglas condicionales), tomar valores directos del request como respaldo.
        $fallbackFields = [
            'product_sheet_id',
            'season',
            'dispatch_month',
            'destination_id',
            'priority',
            'units',
            'estimated_delivery_date',
            'is_advance',
            'notes',
            'ecommerce_units',
        ];
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

        $season = (string) ($validatedData['season'] ?? $programador->season ?? '');
        $dispatchMonthRaw = array_key_exists('dispatch_month', $validatedData)
            ? trim((string) ($validatedData['dispatch_month'] ?? ''))
            : trim((string) ($programador->dispatch_month ?? ''));
        $dispatchMonth = $dispatchMonthRaw !== '' ? $dispatchMonthRaw : $season;
        $seasonToken = $this->normalizeTemporalComparisonToken($season);
        $dispatchMonthToken = $this->normalizeTemporalComparisonToken($dispatchMonth);
        $isCrossSeasonAdvance = $seasonToken !== '' && $dispatchMonthToken !== '' && $seasonToken !== $dispatchMonthToken;
        // Si hay cruce de temporadas, SIEMPRE es adelanto (sin opción a deshabilitar)
        $isAdvance = $isCrossSeasonAdvance ? true : (array_key_exists('is_advance', $validatedData)
            ? (bool) $validatedData['is_advance']
            : (bool) $programador->is_advance);

        $priority = (string) ($validatedData['priority'] ?? $programador->priority ?? '');

        if ($priority === '' && $season !== '' && $programador->product_sheet_id) {
            $sheet = ProductSheet::find($programador->product_sheet_id);
            if ($sheet) {
                $priority = $this->schedulingService->resolvePriorityForNewOrder(
                    $sheet,
                    $season,
                    max(1, (int) ($units ?? 1)),
                    $dispatchMonth,
                    null,
                );
            }
        }

        if ($isAdvance) {
            $advanceDate = (string) ($validatedData['estimated_delivery_date']
                ?? ($programador->estimated_delivery_date?->toDateString() ?? ''));

            if ($advanceDate === '' && $isCrossSeasonAdvance && $season !== '' && $priority !== '') {
                $advanceDate = $this->schedulingService->resolveStandardDate($dispatchMonth, $priority);
            }

            if ($advanceDate !== '') {
                if (!$isCrossSeasonAdvance) {
                    try {
                        $this->schedulingService->validateAdvanceDate($advanceDate);
                    } catch (\InvalidArgumentException $exception) {
                        throw ValidationException::withMessages([
                            'estimated_delivery_date' => $exception->getMessage(),
                        ]);
                    }
                }
            }

            $validatedData['estimated_delivery_date'] = $advanceDate !== '' ? $advanceDate : null;
        } elseif ($season !== '' && $priority !== '') {
            $validatedData['estimated_delivery_date'] = $this->schedulingService->resolveStandardDate($season, $priority);
        }

        if ($priority !== '') {
            $validatedData['priority'] = $priority;
        }

        $validatedData['dispatch_month'] = $dispatchMonth;
        $validatedData['is_advance'] = $isAdvance;

        // Fase C — escritura dual canónica (update)
        $seasonParsedU   = SeasonParser::parse($season);
        $dispatchParsedU = SeasonParser::parse($dispatchMonth);
        $validatedData['season_month']       = $seasonParsedU['month'];
        $validatedData['season_year']        = $seasonParsedU['year'];
        $validatedData['dispatch_month_num'] = $dispatchParsedU['month'];
        $validatedData['dispatch_year']      = $dispatchParsedU['year'];

        $validatedData['status'] = $programador->status === 'complete' ? 'complete' : 'incomplete';

        // Aplicar explÃƒÆ’Ã‚Â­citamente los campos validados para evitar dependencias
            // en el comportamiento de mass-assignment en entornos de test.
            $updatable = [
                'product_sheet_id',
                'season',
                'season_month',
                'season_year',
                'dispatch_month',
                'dispatch_month_num',
                'dispatch_year',
                'destination_id',
                'priority',
                'units',
                'estimated_delivery_date',
                'is_advance',
                'notes',
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

            // Garantizar persistencia directa en BD por si el modelo no aplicÃƒÆ’Ã‚Â³ cambios
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

                DB::table('production_orders')->where('id', $programador->id)->update($dbValues);
                $programador->refresh();
            } catch (\Throwable $e) {
                Log::warning('production_order.update.force_db_update_failed', ['error' => $e->getMessage()]);
            }

        Log::info('production_order.update.completed', [
            'request_id' => $requestId,
            'endpoint' => 'programador.update',
            'order_id' => $programador->id,
            'user_id' => $request->user()?->id,
            'timestamp' => now()->toIso8601String(),
            'updated_count' => 1,
        ]);

        return response()->json([
            'message' => 'Production order updated successfully.',
            'data' => $programador,
        ]);
    }

    public function destroy(ProductionOrder $programador)
    {
        $programador->delete();

        return response()->json(['message' => 'Production order deleted successfully'], 200);
    }

    /**
     * Get data with related product sheets.
     */
    public function getData(Request $request)
    {
        $filters = $request->only(['season', 'priority', 'status', 'type', 'destination_id', 'search']);
        $estimatedDeliveryDateFilter = trim((string) $request->input('estimated_delivery_date', ''));
        $perPage = $request->input('per_page', 10);
        $page = $request->input('page', 1);
        $sortBy = in_array($request->input('sort_by'), ['id', 'estimated_delivery_date'], true)
            ? (string) $request->input('sort_by')
            : 'id';
        $sortDir = strtolower((string) $request->input('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        $requestId = $this->resolveRequestId($request);

        Log::info('production_order.list.received', [
            'request_id' => $requestId,
            'endpoint' => 'production_orders.getData',
            'user_id' => $request->user()?->id,
            'timestamp' => now()->toIso8601String(),
            'filters' => [
                'season' => $filters['season'] ?? null,
                'priority' => $filters['priority'] ?? null,
                'status' => $filters['status'] ?? null,
                'destination_id' => $filters['destination_id'] ?? null,
                'estimated_delivery_date' => $estimatedDeliveryDateFilter !== '' ? $estimatedDeliveryDateFilter : null,
                'search' => isset($filters['search']) ? mb_substr((string) $filters['search'], 0, 80) : null,
                'sort_by' => $sortBy,
                'sort_dir' => $sortDir,
                'page' => $page,
                'per_page' => $perPage,
            ],
        ]);

        $query = ProductionOrder::query()
            ->select([
                'id',
                'op_code',
                'product_sheet_id',
                'season',
                'season_month',
                'season_year',
                'dispatch_month',
                'dispatch_month_num',
                'dispatch_year',
                'destination_id',
                'priority',
                'ecommerce_units',
                'units',
                'estimated_delivery_date',
                'is_advance',
                'notes',
                'status',
                'source',
                'created_at',
                'updated_at',
            ])
            ->with([
                'productSheet:id,type,sample_reference,product_reference,section_id,group_id,billing_price,selling_price,woven_product',
            ]);

        $query->whereHas('productSheet', function ($q) {
            $q->where('type', 'producto');
        });

        $user = $request->user();
        $scopePermissions = [
            'ver propios programacion produccion',
            'ver area programacion produccion',
            'ver todos programacion produccion',
        ];

        $hasScopedVisibilityPermission = false;
        foreach ($scopePermissions as $permission) {
            try {
                if ($user?->hasPermissionTo($permission)) {
                    $hasScopedVisibilityPermission = true;
                    break;
                }
            } catch (PermissionDoesNotExist) {
            }
        }

        if ($hasScopedVisibilityPermission) {
            ScopeHelper::apply($query, $user, 'ver propios programacion produccion', 'ver area programacion produccion', 'ver todos programacion produccion');
        }

        $legacyTemporalFallbackEnabled = (bool) config('product-sheet.temporal_legacy_fallback_enabled', true);

        if (!empty($filters['season']) && $filters['season'] !== 'all') {
            $seasonFilter = trim((string) $filters['season']);

            if ($seasonFilter === '__EMPTY_SEASON__') {
                $query->where(function ($q) use ($legacyTemporalFallbackEnabled) {
                    $q->whereNull('season_month')
                        ->orWhereNull('season_year');

                    if ($legacyTemporalFallbackEnabled) {
                        $q->orWhereNull('season')
                            ->orWhereRaw("TRIM(COALESCE(season, '')) = ''");
                    }
                });
            } elseif ($seasonFilter === '__EMPTY_DISPATCH__') {
                $query->where(function ($q) use ($legacyTemporalFallbackEnabled) {
                    $q->whereNull('dispatch_month_num')
                        ->orWhereNull('dispatch_year');

                    if ($legacyTemporalFallbackEnabled) {
                        $q->orWhereNull('dispatch_month')
                            ->orWhereRaw("TRIM(COALESCE(dispatch_month, '')) = ''");
                    }
                });
            } else {
                $parsedSeason = SeasonParser::parse($seasonFilter);
                if ($parsedSeason['month'] !== null) {
                    $month = (int) $parsedSeason['month'];
                    $year = $parsedSeason['year'] !== null ? (int) $parsedSeason['year'] : null;
                    $legacyMonthName = SeasonParser::monthName($month);

                    $query->where(function ($q) use ($month, $year, $legacyMonthName, $legacyTemporalFallbackEnabled) {
                        if ($year !== null) {
                            $q->where(function ($canonical) use ($month, $year) {
                                $canonical->where('season_month', $month)
                                    ->where('season_year', $year);
                            });
                        } else {
                            $q->where('season_month', $month);
                        }

                        if ($legacyTemporalFallbackEnabled && $legacyMonthName !== null) {
                            $q->orWhere(function ($legacy) use ($legacyMonthName, $year) {
                                if ($year !== null) {
                                    $legacy->whereRaw('UPPER(TRIM(season)) = ?', ["{$legacyMonthName} {$year}"]);
                                } else {
                                    $legacy->whereRaw('UPPER(TRIM(season)) = ?', [$legacyMonthName])
                                        ->orWhereRaw('UPPER(TRIM(season)) LIKE ?', [$legacyMonthName . ' %']);
                                }
                            });
                        }
                    });
                } elseif ($legacyTemporalFallbackEnabled) {
                    $normalizedSeason = $this->resolveMonthFilter($seasonFilter);
                    if ($normalizedSeason !== '') {
                        $query->where(function ($q) use ($normalizedSeason) {
                            $q->whereRaw('UPPER(TRIM(season)) = ?', [$normalizedSeason])
                                ->orWhereRaw('UPPER(TRIM(season)) LIKE ?', [$normalizedSeason . ' %']);
                        });
                    }
                }
            }
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

        if ($estimatedDeliveryDateFilter !== '') {
            $query->whereDate('estimated_delivery_date', $estimatedDeliveryDateFilter);
        }

        if (!empty($filters['woven_product']) && $filters['woven_product'] !== 'all') {
            $wovenValue = filter_var($filters['woven_product'], FILTER_VALIDATE_BOOLEAN);
            $query->whereHas('productSheet', function ($q) use ($wovenValue) {
                $q->where('woven_product', $wovenValue);
            });
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

        if ($sortBy === 'estimated_delivery_date') {
            $query
                ->orderByRaw('CASE WHEN estimated_delivery_date IS NULL THEN 1 ELSE 0 END ASC')
                ->orderBy('estimated_delivery_date', $sortDir)
                ->orderBy('id', $sortDir);
        } else {
            $query->orderBy('id', $sortDir);
        }
        $orders = $query->paginate($perPage, ['*'], 'page', $page);

        $catalogs = $this->loadCatalogs(['destinations', 'sections', 'groups']);
        $destinations = $catalogs['destinations']->keyBy('id');
        $sections = $catalogs['sections']->keyBy('id');
        $groups = $catalogs['groups']->keyBy('id');

        $items = $orders->getCollection()->map(function (ProductionOrder $order) use ($destinations, $sections, $groups, $legacyTemporalFallbackEnabled) {
            $isAuto = $order->isAutoGenerated();

            $destination = $destinations->get($order->destination_id, ['id' => $order->destination_id, 'name' => 'N/A']);

            if ($isAuto) {
                $isPendingCompletion = !$order->units || !$order->estimated_delivery_date;
                $pendingFields = collect([
                    !$order->units ? 'unidades' : null,
                    !$order->estimated_delivery_date ? 'fecha de entrega' : null,
                ])->filter()->values()->all();
            } else {
                $isPendingCompletion = !$order->units;
                $pendingFields = collect([
                    !$order->units ? 'unidades' : null,
                ])->filter()->values()->all();
            }

            $season = $order->season;
            $seasonDisplay = $season;

            if ($order->season_month !== null) {
                $monthName = SeasonParser::monthName((int) $order->season_month)
                    ?? $this->productionOrderService->getMonthName((int) $order->season_month);
                $seasonDisplay = $order->season_year !== null
                    ? sprintf('%s %d', $monthName, (int) $order->season_year)
                    : $monthName;
            } elseif ($legacyTemporalFallbackEnabled && is_numeric($season)) {
                $seasonDisplay = $this->productionOrderService->getMonthName((int) $season);
            }

            $temporadaLabel = $order->season_month !== null
                ? SeasonParser::monthName((int) $order->season_month)
                : ($legacyTemporalFallbackEnabled ? MonthLabel::fromNumber($order->season) : null);

            $productSheet = null;

            if ($order->productSheet) {
                $sectionId = $order->productSheet->section_id ?? null;
                $groupId = $order->productSheet->group_id ?? null;
                $type = $order->productSheet->type === 'producto' ? 'producto' : 'muestra';
                $displayReference = $type === 'producto'
                    ? trim((string) ($order->productSheet->product_reference ?? ''))
                    : trim((string) ($order->productSheet->sample_reference ?? ''));

                $productSheet = [
                    'id' => $order->productSheet->id,
                    'type' => $order->productSheet->type,
                    'sample_reference' => $order->productSheet->sample_reference,
                    'product_reference' => $order->productSheet->product_reference,
                    'billing_price' => $order->productSheet->billing_price,
                    'selling_price' => $order->productSheet->selling_price,
                    'display_reference' => $displayReference,
                    'reference' => $displayReference,
                    'section_id' => $sectionId,
                    'group_id' => $groupId,
                    'section' => $sectionId
                        ? $sections->get($sectionId, ['id' => $sectionId, 'name' => 'N/A'])
                        : null,
                    'group' => $groupId
                        ? $groups->get($groupId, ['id' => $groupId, 'name' => 'N/A'])
                        : null,
                ];
            }

            return [
                'id' => $order->id,
                'op_code' => $order->op_code,
                'product_sheet_id' => $order->product_sheet_id,
                'product_sheet' => $productSheet,
                'season' => $order->season,
                'season_month' => $order->season_month,
                'season_year' => $order->season_year,
                'dispatch_month' => $order->dispatch_month,
                'dispatch_month_num' => $order->dispatch_month_num,
                'dispatch_year' => $order->dispatch_year,
                'season_display' => $seasonDisplay,
                'temporada_label' => $temporadaLabel,
                'destination_id' => $order->destination_id,
                'destination' => $destination,
                'priority' => $order->priority,
                'ecommerce_units' => $order->ecommerce_units,
                'units' => $order->units,
                'estimated_delivery_date' => $order->estimated_delivery_date?->toDateString(),
                'is_advance' => (bool) $order->is_advance,
                'notes' => $order->notes,
                'status' => $order->status,
                'source' => $order->source,
                'is_auto_from_product_sheet' => $order->is_auto_from_product_sheet,
                'is_pending_completion' => $isPendingCompletion,
                'pending_fields' => $pendingFields,
                'created_at' => $order->created_at?->toISOString(),
                'updated_at' => $order->updated_at?->toISOString(),
            ];
        })->values();

        Log::info('production_order.list.completed', [
            'request_id' => $requestId,
            'endpoint' => 'production_orders.getData',
            'user_id' => $request->user()?->id,
            'timestamp' => now()->toIso8601String(),
            'returned_count' => count($orders->items()),
            'total' => $orders->total(),
            'page' => $orders->currentPage(),
            'per_page' => $orders->perPage(),
        ]);

        return response()->json([
            'data' => $items,
            'total' => $orders->total(),
            'current_page' => $orders->currentPage(),
            'last_page' => $orders->lastPage(),
            'per_page' => $orders->perPage(),
        ]);
    }

    public function nextOpCode(Request $request): JsonResponse
    {
        $prefix = strtoupper(trim((string) $request->input('prefix', 'MF')));

        if (! in_array($prefix, ['MU', 'MF'], true)) {
            return response()->json(['error' => 'Prefijo inválido.'], 422);
        }

        $nextCode = $this->productionOrderService->peekNextOpCode($prefix);

        return response()->json(['data' => ['next_code' => $nextCode]]);
    }

    public function distributionDashboard(Request $request)
    {
        $filters = $request->only(['season', 'priority', 'status', 'type', 'destination_id', 'search']);
        $estimatedDeliveryDateFilter = trim((string) $request->input('estimated_delivery_date', ''));

        $query = ProductionOrder::query()
            ->select([
                'id',
                'priority',
                'units',
                'estimated_delivery_date',
                'season',
                'season_month',
                'season_year',
                'dispatch_month',
                'dispatch_month_num',
                'dispatch_year',
                'destination_id',
                'status',
            ])
            ->with(['productSheet:id,type,sample_reference,product_reference']);

        $query->whereHas('productSheet', function ($q) {
            $q->where('type', 'producto');
        });

        $legacyTemporalFallbackEnabled = (bool) config('product-sheet.temporal_legacy_fallback_enabled', true);

        if (!empty($filters['season']) && $filters['season'] !== 'all') {
            $seasonFilterRaw = trim((string) $filters['season']);

            if ($seasonFilterRaw === '__EMPTY_SEASON__') {
                $query->where(function ($q) use ($legacyTemporalFallbackEnabled) {
                    $q->whereNull('season_month')
                        ->orWhereNull('season_year');

                    if ($legacyTemporalFallbackEnabled) {
                        $q->orWhereNull('season')
                            ->orWhereRaw("TRIM(COALESCE(season, '')) = ''");
                    }
                });
            } elseif ($seasonFilterRaw === '__EMPTY_DISPATCH__') {
                $query->where(function ($q) use ($legacyTemporalFallbackEnabled) {
                    $q->whereNull('dispatch_month_num')
                        ->orWhereNull('dispatch_year');

                    if ($legacyTemporalFallbackEnabled) {
                        $q->orWhereNull('dispatch_month')
                            ->orWhereRaw("TRIM(COALESCE(dispatch_month, '')) = ''");
                    }
                });
            } else {
                $parsedSeason = SeasonParser::parse($seasonFilterRaw);
                if ($parsedSeason['month'] !== null) {
                    $month = (int) $parsedSeason['month'];
                    $year = $parsedSeason['year'] !== null ? (int) $parsedSeason['year'] : null;
                    $legacyMonthName = SeasonParser::monthName($month);

                    $query->where(function ($q) use ($month, $year, $legacyMonthName, $legacyTemporalFallbackEnabled) {
                        if ($year !== null) {
                            $q->where(function ($canonical) use ($month, $year) {
                                $canonical->where('season_month', $month)
                                    ->where('season_year', $year);
                            });
                        } else {
                            $q->where('season_month', $month);
                        }

                        if ($legacyTemporalFallbackEnabled && $legacyMonthName !== null) {
                            $q->orWhere(function ($legacy) use ($legacyMonthName, $year) {
                                if ($year !== null) {
                                    $legacy->whereRaw('UPPER(TRIM(season)) = ?', ["{$legacyMonthName} {$year}"]);
                                } else {
                                    $legacy->whereRaw('UPPER(TRIM(season)) = ?', [$legacyMonthName])
                                        ->orWhereRaw('UPPER(TRIM(season)) LIKE ?', [$legacyMonthName . ' %']);
                                }
                            });
                        }
                    });
                } elseif ($legacyTemporalFallbackEnabled) {
                    $seasonFilter = $this->resolveMonthFilter($seasonFilterRaw);
                    if ($seasonFilter !== '') {
                        $query->where(function ($q) use ($seasonFilter) {
                            $q->whereRaw('UPPER(TRIM(season)) = ?', [$seasonFilter])
                                ->orWhereRaw('UPPER(TRIM(season)) LIKE ?', [$seasonFilter . ' %']);
                        });
                    }
                }
            }
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

        if ($estimatedDeliveryDateFilter !== '') {
            $query->whereDate('estimated_delivery_date', $estimatedDeliveryDateFilter);
        }

        if (!empty($filters['woven_product']) && $filters['woven_product'] !== 'all') {
            $wovenValue = filter_var($filters['woven_product'], FILTER_VALIDATE_BOOLEAN);
            $query->whereHas('productSheet', function ($q) use ($wovenValue) {
                $q->where('woven_product', $wovenValue);
            });
        }

        if (!empty($filters['search']) && trim((string) $filters['search']) !== '') {
            $search = trim((string) $filters['search']);
            $searchLike = '%' . $search . '%';
            $query->whereHas('productSheet', function ($sheetQuery) use ($searchLike) {
                $sheetQuery->where('sample_reference', 'like', $searchLike)
                    ->orWhere('product_reference', 'like', $searchLike);
            });
        }

        return response()->json([
            'data' => $this->schedulingService->calculateDashboardFromQuery($query),
        ]);
    }

    public function bulkUpdateEstimatedDate(Request $request)
    {
        $validated = $request->validate([
            'order_ids' => ['required', 'array', 'min:1'],
            'order_ids.*' => ['integer', 'exists:production_orders,id'],
            'estimated_delivery_date' => ['required', 'date'],
        ]);

        $date = (string) $validated['estimated_delivery_date'];

        $orders = ProductionOrder::query()
            ->whereIn('id', $validated['order_ids'])
            ->get();

        $updated = 0;

        DB::transaction(function () use ($orders, $date, &$updated) {
            foreach ($orders as $order) {
                /** @var ProductionOrder $order */
                $order->estimated_delivery_date = $date;
                $order->is_advance = true;
                $order->status = $order->status === 'complete' ? 'complete' : 'incomplete';
                $order->save();
                $updated++;
            }
        });

        return response()->json([
            'message' => "Fecha estimada actualizada en {$updated} registros.",
            'updated_count' => $updated,
        ]);
    }

    public function bulkUpdatePriority(Request $request)
    {
        $validated = $request->validate([
            'order_ids' => ['required', 'array', 'min:1'],
            'order_ids.*' => ['integer', 'exists:production_orders,id'],
            'priority' => ['required', 'string', 'in:A,B,C,D'],
        ]);

        $priority = strtoupper((string) $validated['priority']);

        $orders = ProductionOrder::query()
            ->whereIn('id', $validated['order_ids'])
            ->get();

        $updated = 0;

        DB::transaction(function () use ($orders, $priority, &$updated) {
            foreach ($orders as $order) {
                /** @var ProductionOrder $order */
                $season = (string) ($order->season ?? '');
                if ($season === '') {
                    continue;
                }

                $order->priority = $priority;

                if (!$order->is_advance) {
                    $order->estimated_delivery_date = $this->schedulingService->resolveStandardDate($season, $priority);
                }

                $order->status = $order->status === 'complete' ? 'complete' : 'incomplete';

                $order->save();
                $updated++;
            }
        });

        return response()->json([
            'message' => "Prioridad actualizada en {$updated} registros.",
            'updated_count' => $updated,
        ]);
    }

    public function bulkUpdateSeasonDispatch(Request $request)
    {
        $validated = $request->validate([
            'order_ids' => ['required', 'array', 'min:1'],
            'order_ids.*' => ['integer', 'exists:production_orders,id'],
            'season' => ['required', 'string', 'max:60'],
            'dispatch_month' => ['nullable', 'string', 'max:60'],
        ]);

        $season = strtoupper(trim((string) $validated['season']));
        $dispatchMonthRaw = trim((string) ($validated['dispatch_month'] ?? ''));
        $dispatchMonth = $dispatchMonthRaw !== '' ? strtoupper($dispatchMonthRaw) : $season;

        $seasonParsed = SeasonParser::parse($season);
        if ($seasonParsed['month'] === null || $seasonParsed['year'] === null) {
            throw ValidationException::withMessages([
                'season' => 'Selecciona una temporada válida con mes y año.',
            ]);
        }

        $dispatchParsed = SeasonParser::parse($dispatchMonth);
        if ($dispatchParsed['month'] === null || $dispatchParsed['year'] === null) {
            throw ValidationException::withMessages([
                'dispatch_month' => 'Selecciona un mes de despacho válido con mes y año.',
            ]);
        }

        $orders = ProductionOrder::query()
            ->whereIn('id', $validated['order_ids'])
            ->get();

        $updated = 0;

        DB::transaction(function () use ($orders, $season, $dispatchMonth, &$updated) {
            foreach ($orders as $order) {
                /** @var ProductionOrder $order */
                $order->season = $season;
                $order->dispatch_month = $dispatchMonth;

                // Mantener sincronía canónica (Fase C/D)
                $seasonParsed = SeasonParser::parse($season);
                $dispatchParsed = SeasonParser::parse($dispatchMonth);
                $order->season_month = $seasonParsed['month'];
                $order->season_year = $seasonParsed['year'];
                $order->dispatch_month_num = $dispatchParsed['month'];
                $order->dispatch_year = $dispatchParsed['year'];

                $seasonToken = $this->normalizeTemporalComparisonToken($season);
                $dispatchToken = $this->normalizeTemporalComparisonToken($dispatchMonth);
                $isCrossSeasonAdvance = $seasonToken !== '' && $dispatchToken !== '' && $seasonToken !== $dispatchToken;

                $order->is_advance = $isCrossSeasonAdvance ? true : (bool) $order->is_advance;

                $priority = strtoupper(trim((string) ($order->priority ?? '')));
                if ($priority !== '') {
                    if ($order->is_advance) {
                        if ($isCrossSeasonAdvance) {
                            $order->estimated_delivery_date = $this->schedulingService->resolveStandardDate($dispatchMonth, $priority);
                        }
                    } else {
                        $order->estimated_delivery_date = $this->schedulingService->resolveStandardDate($season, $priority);
                    }
                }

                $order->status = $order->status === 'complete' ? 'complete' : 'incomplete';
                $order->save();
                $updated++;
            }
        });

        return response()->json([
            'message' => "Temporada y mes de despacho actualizados en {$updated} registros.",
            'updated_count' => $updated,
        ]);
    }

    public function revertAdvance(Request $request, ProductionOrder $programador): JsonResponse
    {
        Log::info('[ProgrammerController] revertAdvance', [
            'order_id' => $programador->id,
            'op_code'  => $programador->op_code,
            'user_id'  => $request->user()?->id,
        ]);

        $season = trim((string) ($programador->season ?? ''));
        $dispatchMonthRaw = trim((string) ($programador->dispatch_month ?? ''));
        $dispatchMonth = $dispatchMonthRaw !== '' ? $dispatchMonthRaw : $season;
        $units = max(1, (int) ($programador->units ?? 1));
        $priority = trim((string) ($programador->priority ?? ''));

        if ($priority === '' && $programador->product_sheet_id) {
            $sheet = ProductSheet::find($programador->product_sheet_id);
            if ($sheet && $season !== '') {
                $priority = $this->schedulingService->resolvePriorityForNewOrder(
                    $sheet,
                    $season,
                    $units,
                    $dispatchMonth,
                    null,
                );
            }
        }

        if ($priority === '' || $season === '') {
            return response()->json([
                'message' => 'No se pudo recalcular la lógica de prioridad para revertir el adelanto.',
            ], 422);
        }

        $programador->priority = $priority;
        $programador->is_advance = false;
        $programador->estimated_delivery_date = $this->schedulingService->resolveStandardDate($season, $priority);
        $programador->status = $programador->status === 'complete' ? 'complete' : 'incomplete';
        $programador->save();
        $programador->refresh();

        return response()->json([
            'message' => 'La orden "' . $programador->op_code . '" ya no es un adelanto y volvió a la lógica de prioridad.',
            'data' => [
                'id' => $programador->id,
                'priority' => $programador->priority,
                'estimated_delivery_date' => $programador->estimated_delivery_date?->toDateString(),
                'is_advance' => (bool) $programador->is_advance,
                'status' => $programador->status,
            ],
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

    private function resolveMonthFilter(string $value): string
    {
        return $this->normalizeMonthToken($value);
    }

    private function normalizeMonthToken(string|int|null $value): string
    {
        $raw = strtoupper(trim((string) $value));
        if ($raw === '') {
            return '';
        }

        $option = $this->settingService->getCatalogOptionById('months', $raw);
        if (is_array($option)) {
            $name = strtoupper(trim((string) ($option['name'] ?? '')));
            if ($name !== '') {
                return $name;
            }
        }

        if (ctype_digit($raw)) {
            $optionByInt = $this->settingService->getCatalogOptionById('months', (int) $raw);
            if (is_array($optionByInt)) {
                $name = strtoupper(trim((string) ($optionByInt['name'] ?? '')));
                if ($name !== '') {
                    return $name;
                }
            }
        }

        return $raw;
    }

    /**
     * Token para comparar temporada/despacho considerando año cuando exista.
     *
     * - Si detecta mes + año: YYYY-MM
     * - Si detecta solo mes: MM-XX
     * - Si no detecta mes: valor normalizado original
     */
    private function normalizeTemporalComparisonToken(string|int|null $value): string
    {
        $normalized = $this->normalizeMonthToken($value);
        if ($normalized === '') {
            return '';
        }

        $upper = strtoupper(trim($normalized));

        $monthMap = [
            'ENERO' => 1,
            'FEBRERO' => 2,
            'MARZO' => 3,
            'ABRIL' => 4,
            'MAYO' => 5,
            'JUNIO' => 6,
            'JULIO' => 7,
            'AGOSTO' => 8,
            'SEPTIEMBRE' => 9,
            'SETIEMBRE' => 9,
            'OCTUBRE' => 10,
            'NOVIEMBRE' => 11,
            'DICIEMBRE' => 12,
        ];

        $month = null;
        foreach ($monthMap as $label => $number) {
            if (preg_match('/\b' . preg_quote($label, '/') . '\b/u', $upper)) {
                $month = $number;
                break;
            }
        }

        if ($month === null && preg_match('/\b(1[0-2]|0?[1-9])\b/', $upper, $monthMatch) === 1) {
            $month = (int) $monthMatch[1];
        }

        if ($month === null) {
            return $upper;
        }

        $year = null;
        if (preg_match('/\b(19|20)\d{2}\b/', $upper, $yearMatch) === 1) {
            $year = (int) $yearMatch[0];
        }

        if ($year !== null) {
            return sprintf('%04d-%02d', $year, $month);
        }

        return sprintf('MM-%02d', $month);
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

    private function summarizeLot(?array $lot): array
    {
        if (!is_array($lot)) {
            return [];
        }

        return [
            'product_sheet_id' => $lot['product_sheet_id'] ?? null,
            'season' => $lot['season'] ?? null,
            'dispatch_month' => $lot['dispatch_month'] ?? null,
            'destination_id' => $lot['destination_id'] ?? null,
            'priority' => $lot['priority'] ?? null,
            'units' => $lot['units'] ?? null,
            'is_advance' => $lot['is_advance'] ?? null,
            'status' => $lot['status'] ?? null,
            'has_ecommerce_units' => array_key_exists('ecommerce_units', $lot) && $lot['ecommerce_units'] !== null,
        ];
    }
}
