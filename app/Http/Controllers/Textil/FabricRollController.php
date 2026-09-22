<?php

namespace App\Http\Controllers\Textil;

use App\Http\Controllers\Controller;
use App\Http\Requests\Textil\DischargeFabricRollRequest;
use App\Http\Requests\Textil\StoreFabricRollRequest;
use App\Http\Requests\Textil\UpdateFabricRollRequest;
use App\Http\Resources\Inventory\FabricRollResource;
use App\Http\Resources\Textil\FabricRollReceptionResource;
use App\Models\Expenses\PurchaseDocument;
use App\Models\Expenses\PurchaseDocumentItem;
use App\Models\MasterData\FabricName;
use App\Models\MasterData\Item;
use App\Http\Requests\Textil\CorrectManualDischargeRequest;
use App\Http\Requests\Textil\DischargeManualRollRequest;
use App\Http\Requests\Textil\ProgramManualRollBatchRequest;
use App\Http\Requests\Textil\ProgramManualRollRequest;
use App\Http\Requests\Textil\UpdateManualRollProgramRequest;
use App\Models\Production\FabricRollProgrammingLine;
use App\Models\Production\ManualRollProgramming;
use App\Models\Production\ProductionOrder;
use App\Models\Production\ProductionRollConsumption;
use App\Models\Production\TechnicalSheet;
use App\Models\Textil\FabricRoll;
use App\Models\Textil\FabricRollAdjustment;
use App\Services\Expenses\PurchaseDocumentInvoiceService;
use App\Services\Textil\FabricRollService;
use App\Support\MasterData\MaterialClassifier;
use App\Support\TextNormalizer;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FabricRollController extends Controller
{
    private const ROLL_IMPORT_EXPORT_HEADERS = [
        'N° ROLLO',
        'UBICACIÓN',
        'BASE TELA',
        'TELA',
        'COLOR',
        'CÓDIGO COLOR',
        'ANCHO (m)',
        'PRECIO/M',
        'CANTIDAD (m)',
        'SALIDA PARCIAL',
        'DISPONIBILIDAD REAL',
        'PROVEEDOR',
        'N° PEDIDO',
        'FECHA INGRESO',
        'REFERENCIA ÍTEM',
    ];

    public function __construct(protected FabricRollService $service) {}

    public function index()
    {
        return Inertia::render('Inventory/FabricRolls/Index');
    }

    public function create()
    {
        return Inertia::render('Inventory/FabricRolls/Create');
    }

    public function edit($id)
    {
        $fabricRoll = $this->service->findOrFail($id);

        return Inertia::render('Inventory/FabricRolls/Edit', [
            'fabricRoll' => $fabricRoll,
        ]);
    }

    public function kardex()
    {
        return Inertia::render('Textil/Index');
    }

    public function inventoryFilterOptions(): JsonResponse
    {
        $colors = DB::table('fabric_rolls')
            ->whereNull('deleted_at')
            ->whereNotNull('internal_color_name')
            ->where('internal_color_name', '!=', '')
            ->distinct()
            ->orderBy('internal_color_name')
            ->pluck('internal_color_name');

        $locations = DB::table('fabric_rolls')
            ->whereNull('deleted_at')
            ->whereNotNull('location')
            ->where('location', '!=', '')
            ->distinct()
            ->orderBy('location')
            ->pluck('location');

        return response()->json([
            'colors' => $colors,
            'locations' => $locations,
        ]);
    }

    public function inventoryRolls()
    {
        return Inertia::render('Textil/FabricRollInventory/Index');
    }

    public function movementsPage()
    {
        return Inertia::render('Textil/FabricRollInventory/Movements');
    }

    public function rollProgrammingReportPage()
    {
        return Inertia::render('Textil/FabricRollInventory/RollProgrammingReport');
    }

    /**
     * Fase 15: control de rollos programados por Diseño (fabric_roll_programming_lines)
     * cruzado con consumo real (production_roll_consumptions), para que Diseño vea de un
     * vistazo programado/disponible/consumido real/diferencia por línea. Se calcula y
     * pagina en PHP (no en SQL) porque "disponible" y "diferencia" son columnas derivadas
     * de sumas agregadas por rollo/orden — mismo criterio ya usado en otras pantallas
     * pequeñas del sistema (Colors, ProductionOrder) que ordenan/paginan en memoria.
     */
    public function rollProgrammingReportData(Request $request): JsonResponse
    {
        $query = FabricRollProgrammingLine::query()
            ->with([
                'fabricRoll:id,roll_number,quantity,actual_output,location',
                'technicalSheet:id,production_order_id',
                'technicalSheet.productionOrder:id,op_code',
            ]);

        if ($request->filled('op_code')) {
            $opCode = $request->input('op_code');
            $query->whereHas(
                'technicalSheet.productionOrder',
                fn ($q) => $q->where('op_code', 'like', "%{$opCode}%")
            );
        }

        if ($request->filled('roll_number')) {
            $rollNumber = $request->input('roll_number');
            $query->whereHas(
                'fabricRoll',
                fn ($q) => $q->where('roll_number', 'like', "%{$rollNumber}%")
            );
        }

        if ($request->boolean('only_with_roll')) {
            $query->whereNotNull('fabric_roll_id');
        }

        $lines = $query->get();

        $manualQuery = ManualRollProgramming::query()->with('fabricRoll:id,roll_number,quantity,actual_output,location');
        if ($request->filled('op_code')) {
            $manualQuery->where('manual_order_code', 'like', '%'.$request->input('op_code').'%');
        }
        if ($request->filled('roll_number')) {
            $rollNumber = $request->input('roll_number');
            $manualQuery->whereHas('fabricRoll', fn ($q) => $q->where('roll_number', 'like', "%{$rollNumber}%"));
        }
        $manualLines = $manualQuery->get();

        // Comprometido total por rollo (todas las líneas de todas las fichas + líneas
        // manuales pendientes) — mismo criterio que getCommittedMetersByRollId(), para
        // que "disponible" coincida con lo que ve Corte en el listado de inventario de
        // rollos, y no quede inflado cuando el rollo también tiene reserva manual.
        $committedByRoll = FabricRollProgrammingLine::query()
            ->whereNotNull('fabric_roll_id')
            ->where('programmed_meters', '>', 0)
            ->selectRaw('fabric_roll_id, SUM(programmed_meters) as total')
            ->groupBy('fabric_roll_id')
            ->pluck('total', 'fabric_roll_id')
            ->all();

        foreach (ManualRollProgramming::query()->get() as $manualLine) {
            $pending = max(0.0, (float) $manualLine->programmed_meters - (float) $manualLine->consumed_meters);
            if ($pending <= 0) {
                continue;
            }
            $committedByRoll[$manualLine->fabric_roll_id] = ($committedByRoll[$manualLine->fabric_roll_id] ?? 0.0) + $pending;
        }

        $rollIds = $lines->pluck('fabric_roll_id')->filter()->unique()->values();
        $consumedByRollAndOrder = ProductionRollConsumption::query()
            ->whereIn('fabric_roll_id', $rollIds)
            ->selectRaw('fabric_roll_id, production_order_id, SUM(meters_consumed) as total')
            ->groupBy('fabric_roll_id', 'production_order_id')
            ->get()
            ->groupBy('fabric_roll_id')
            ->map(fn ($rows) => $rows->pluck('total', 'production_order_id'));

        $rows = $lines->map(function (FabricRollProgrammingLine $line) use ($committedByRoll, $consumedByRollAndOrder) {
            $roll = $line->fabricRoll;
            $opId = $line->technicalSheet?->production_order_id;

            $available = null;
            if ($roll) {
                $totalCommitted = (float) ($committedByRoll[$roll->id] ?? 0.0);
                $available = max(0.0, (float) $roll->quantity - (float) $roll->actual_output - $totalCommitted);
            }

            $consumedReal = 0.0;
            if ($roll && $opId) {
                $consumedReal = (float) ($consumedByRollAndOrder->get($roll->id)?->get($opId) ?? 0.0);
            }

            $programmed = (float) ($line->programmed_meters ?? 0.0);

            return [
                'id' => $line->id,
                'source' => 'order',
                'op_code' => $line->technicalSheet?->productionOrder?->op_code,
                'manual_order_code' => null,
                'roll_number' => $roll?->roll_number,
                'location' => $roll?->location,
                'fabric_name' => $line->fabric_name,
                'color' => $line->color,
                'type' => $line->type,
                'programmed_meters' => $programmed,
                'available_meters' => $available,
                'consumed_real_meters' => $consumedReal,
                'difference_meters' => round($programmed - $consumedReal, 2),
            ];
        });

        $manualRows = $manualLines->map(function (ManualRollProgramming $line) use ($committedByRoll) {
            $roll = $line->fabricRoll;

            $available = null;
            if ($roll) {
                $totalCommitted = (float) ($committedByRoll[$roll->id] ?? 0.0);
                $available = max(0.0, (float) $roll->quantity - (float) $roll->actual_output - $totalCommitted);
            }

            $programmed = (float) $line->programmed_meters;
            $consumedReal = (float) $line->consumed_meters;

            return [
                'id' => $line->id,
                'source' => 'manual',
                'op_code' => null,
                'manual_order_code' => $line->manual_order_code,
                'roll_number' => $roll?->roll_number,
                'location' => $roll?->location,
                'fabric_name' => null,
                'color' => null,
                'type' => null,
                'programmed_meters' => $programmed,
                'available_meters' => $available,
                'consumed_real_meters' => $consumedReal,
                'difference_meters' => round($programmed - $consumedReal, 2),
            ];
        });

        $rows = $rows->concat($manualRows);

        $sortBy = $request->input('sort_by', 'programmed_meters');
        $sortDirection = $request->input('sort_direction', 'desc');
        $allowedSorts = ['programmed_meters', 'available_meters', 'consumed_real_meters', 'difference_meters', 'op_code', 'roll_number'];
        if (! in_array($sortBy, $allowedSorts, true)) {
            $sortBy = 'programmed_meters';
        }

        $sorted = ($sortDirection === 'asc' ? $rows->sortBy($sortBy) : $rows->sortByDesc($sortBy))->values();

        $perPage = min((int) $request->input('per_page', 50), 200);
        $page = max(1, (int) $request->input('page', 1));
        $paged = $sorted->slice(($page - 1) * $perPage, $perPage)->values();

        return response()->json([
            'data' => $paged,
            'total' => $sorted->count(),
            'current_page' => $page,
        ]);
    }

    public function manualOrdersIndex()
    {
        return Inertia::render('Textil/FabricRollOrders/Index');
    }

    /**
     * Listado agrupado por código de orden manual (manual_roll_programmings), con
     * totales programado/consumido y cantidad de rollos por código. Reemplaza al
     * listado de "Programadores" (ProductionOrder) que usaba esta pantalla antes.
     */
    public function manualOrdersData(Request $request): JsonResponse
    {
        $query = ManualRollProgramming::query()->with('fabricRoll:id,roll_number');

        if ($request->filled('search')) {
            $search = (string) $request->input('search');
            $query->where('manual_order_code', 'like', "%{$search}%");
        }

        $groups = $query->get()
            ->groupBy('manual_order_code')
            ->map(function ($lines, $code) {
                return [
                    'manual_order_code' => $code,
                    'rolls_count' => $lines->count(),
                    'programmed_meters' => round((float) $lines->sum('programmed_meters'), 2),
                    'consumed_meters' => round((float) $lines->sum('consumed_meters'), 2),
                    'created_at' => optional($lines->min('created_at'))->toIso8601String(),
                ];
            })
            ->sortByDesc('created_at')
            ->values();

        $perPage = min((int) $request->input('per_page', 50), 200);
        $page = max(1, (int) $request->input('page', 1));
        $paged = $groups->slice(($page - 1) * $perPage, $perPage)->values();

        return response()->json([
            'data' => $paged,
            'total' => $groups->count(),
            'current_page' => $page,
        ]);
    }

    /**
     * Líneas (rollos programados/descargados) de un código de orden manual.
     */
    public function manualOrderLines(string $manualOrderCode): JsonResponse
    {
        $lines = ManualRollProgramming::query()
            ->where('manual_order_code', $manualOrderCode)
            ->with([
                'fabricRoll:id,roll_number,location,quantity,actual_output,item_id,description,internal_color_name,batch',
                'fabricRoll.item:id,fabric_name_id,color_id,category_sub_group_id',
                'fabricRoll.item.fabricName:id,nombre',
                'fabricRoll.item.color:id,name',
                'fabricRoll.item.categorySubGroup:id,name',
            ])
            ->orderByDesc('created_at')
            ->get()
            ->map(function (ManualRollProgramming $line) {
                $roll = $line->fabricRoll;
                $item = $roll?->item;

                return [
                    'id' => $line->id,
                    'fabric_roll_id' => $line->fabric_roll_id,
                    'roll_number' => $roll?->roll_number,
                    'location' => $roll?->location,
                    'manual_order_code' => $line->manual_order_code,
                    'fabric_name' => $item?->fabricName?->nombre ?? $roll?->description,
                    'color' => $item?->color?->name ?? $roll?->internal_color_name,
                    'type' => $item?->categorySubGroup?->name,
                    'batch' => $roll?->batch,
                    'programmed_meters' => (float) $line->programmed_meters,
                    'consumed_meters' => (float) $line->consumed_meters,
                    'remaining_meters' => $line->remaining_meters,
                    'notes' => $line->notes,
                    'has_item' => $item !== null,
                ];
            });

        return response()->json(['data' => $lines]);
    }

    public function manualOrderShow(string $manualOrderCode)
    {
        return Inertia::render('Textil/FabricRollOrders/Download', [
            'manualOrderCode' => $manualOrderCode,
        ]);
    }

    public function programManualRoll(ProgramManualRollRequest $request): JsonResponse
    {
        $data = $request->validated();

        try {
            $line = $this->service->programManual(
                (int) $data['fabric_roll_id'],
                (string) $data['manual_order_code'],
                (float) $data['programmed_meters'],
                $data['notes'] ?? null,
            );
        } catch (ValidationException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        }

        return response()->json([
            'message' => 'Programación registrada correctamente.',
            'data' => $line,
        ], 201);
    }

    public function programManualRollBatch(ProgramManualRollBatchRequest $request): JsonResponse
    {
        $data = $request->validated();

        try {
            $lines = $this->service->programManualBatch(
                (string) $data['manual_order_code'],
                $data['lines'],
                $data['notes'] ?? null,
            );
        } catch (ValidationException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        }

        return response()->json([
            'message' => 'Programación registrada correctamente ('.$lines->count().' rollo(s)).',
            'data' => $lines,
        ], 201);
    }

    public function dischargeManualRoll(DischargeManualRollRequest $request, ManualRollProgramming $manualRollProgramming): JsonResponse
    {
        $data = $request->validated();

        try {
            $line = $this->service->dischargeManual(
                $manualRollProgramming,
                (float) $data['quantity'],
                $data['reason'] ?? null,
            );
        } catch (ValidationException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        }

        return response()->json([
            'message' => 'Descargue registrado correctamente.',
            'data' => $line,
        ]);
    }

    public function updateManualRoll(UpdateManualRollProgramRequest $request, ManualRollProgramming $manualRollProgramming): JsonResponse
    {
        $data = $request->validated();

        try {
            $line = $this->service->updateManualProgram(
                $manualRollProgramming,
                (float) $data['programmed_meters'],
                $data['notes'] ?? null,
            );
        } catch (ValidationException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        }

        return response()->json([
            'message' => 'Programación actualizada correctamente.',
            'data' => $line,
        ]);
    }

    public function destroyManualRoll(ManualRollProgramming $manualRollProgramming): JsonResponse
    {
        try {
            $this->service->unprogramManual($manualRollProgramming);
        } catch (ValidationException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        }

        return response()->json(['message' => 'Programación eliminada correctamente.']);
    }

    public function manualRollHistory(ManualRollProgramming $manualRollProgramming): JsonResponse
    {
        $history = $this->service->manualHistory($manualRollProgramming)
            ->map(fn (FabricRollAdjustment $adjustment) => [
                'id' => $adjustment->id,
                'created_at' => $adjustment->created_at?->toIso8601String(),
                'user_name' => $adjustment->user?->name,
                // Se deriva de actual_output_after - actual_output_before del propio
                // registro, no de inventoryMovement->quantity: un rollo sin ítem
                // vinculado no crea movimiento de inventario y antes quedaba en null.
                'quantity' => round((float) $adjustment->actual_output_after - (float) $adjustment->actual_output_before, 6),
                'reason' => $adjustment->reason,
            ]);

        return response()->json(['data' => $history]);
    }

    public function correctManualDischarge(CorrectManualDischargeRequest $request, ManualRollProgramming $manualRollProgramming, FabricRollAdjustment $fabricRollAdjustment): JsonResponse
    {
        $data = $request->validated();

        try {
            $adjustment = $this->service->correctManualDischarge(
                $fabricRollAdjustment,
                (float) $data['quantity'],
            );
        } catch (ValidationException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        }

        return response()->json([
            'message' => 'Descargue corregido correctamente.',
            'data' => $adjustment,
        ]);
    }

    public function inventoryRollsData(Request $request): JsonResponse
    {
        $rawIds = $request->input('fabricItemIds', []);
        $ids = is_array($rawIds) ? $rawIds : [$rawIds];
        $fabricItemIds = collect($ids)
            ->map(fn ($id) => is_numeric($id) ? (int) $id : null)
            ->filter(fn ($id) => is_int($id) && $id > 0)
            ->unique()->values()->all();

        $filters = [
            'search' => $request->input('search'),
            'color' => $request->input('color'),
            'location' => $request->input('location'),
            'with_output' => $request->boolean('with_output'),
            'fabric_item_ids' => $fabricItemIds ?: null,
            'no_item' => $request->boolean('no_item'),
        ];

        $perPage = min((int) $request->input('per_page', 100), 500);
        $sortBy = $request->input('sort_by', 'roll_number');
        $sortDirection = $request->input('sort_direction', 'asc');
        /** @var LengthAwarePaginator $paginator */
        $paginator = $this->service->inventoryWithFilters($filters, $perPage, $sortBy, $sortDirection);

        // Disponibilidad parcial = lo que NO está consumido NI reservado:
        // quantity - actual_output - partial_output. partial_output es la única fuente
        // de verdad de "cuánto está reservado" (la mantienen sincronizada por igual el
        // motor de Corte vía TechnicalDataSheetRollsController::saveAll() y la
        // programación manual vía FabricRollService::applyProgramManual()/dischargeManual(),
        // que además ya libera partial_output proporcionalmente al descargar). Antes este
        // cálculo ignoraba la columna y volvía a sumar solo fabric_roll_programming_lines
        // (getCommittedMetersByRollId), dejando "Disp. parcial" igual a "Disp. real" en
        // cualquier rollo cuya única reserva fuera manual.
        //
        // excludeOpId es la excepción: al programar rollos para una orden específica en su
        // ficha técnica, esa orden no debe contar su propia reserva ya existente como
        // "no disponible" mientras la vuelve a editar — se le suma de vuelta lo que esa
        // misma orden ya tiene comprometido en este rollo.
        $excludeOpId = $request->input('excludeOpId');
        $ownCommittedByRollId = is_numeric($excludeOpId)
            ? $this->getOwnCommittedMetersByRollId((int) $excludeOpId)
            : [];

        $rows = $paginator->getCollection()->map(function (object $row) use ($ownCommittedByRollId): array {
            $row = (array) $row;
            $qty = (float) ($row['quantity'] ?? 0);
            $partial = (float) ($row['partial_output'] ?? 0);
            $actual = (float) ($row['actual_output'] ?? 0);
            $ownCommitted = (float) ($ownCommittedByRollId[(string) $row['id']] ?? 0.0);
            $partialAvailability = max(0.0, $qty - $actual - $partial + $ownCommitted);
            $color = $row['internal_color_name'] ?? $row['supplier_color'] ?? null;
            $colorName = $row['item_color_name'] ?? $color ?? null;
            $colorHex = isset($row['item_color_hex']) && $row['item_color_hex'] !== ''
                ? $row['item_color_hex']
                : null;

            return [
                'id' => $row['id'],
                'fabric_item_id' => isset($row['fabric_item_id']) ? (int) $row['fabric_item_id'] : null,
                'purchase_document_item_id' => $row['purchase_document_item_id'],
                'roll' => $row['roll_number'],
                'location' => $row['location'],
                'fabric' => $row['fabric'],
                'color' => $colorName ?? 'N/A',
                'color_code' => $colorHex ?? null,
                'width' => $this->fmtMeters((float) ($row['roll_width'] ?? 0)),
                'width_value' => (float) ($row['roll_width'] ?? 0),
                'roll_qty' => $this->fmtMeters($qty),
                'roll_qty_value' => $qty,
                'partial_output' => $this->fmtMeters($partial),
                'partial_output_value' => $partial,
                'partial_availability' => $this->fmtMeters($partialAvailability),
                'partial_availability_value' => $partialAvailability,
                'exit_quantity' => $this->fmtMeters($actual),
                'exit_quantity_value' => $actual,
                'real_availability' => $this->fmtMeters(max(0.0, $qty - $actual)),
                'real_availability_value' => max(0.0, $qty - $actual),
                'order_number' => $row['order_number'],
                'provider' => $row['provider'],
                'price_per_meter' => isset($row['price_per_meter']) ? (float) $row['price_per_meter'] : null,
                'created_at' => isset($row['doc_created_at'])
                    ? Carbon::parse($row['doc_created_at'])->format('d M Y')
                    : null,
                'creator' => $row['creator_name'] ?? null,
                'status' => $actual >= $qty ? 'depleted' : 'available',
            ];
        });

        return response()->json([
            'data' => $rows,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ]);
    }

    /**
     * Suma metros programados por rollId, SOLO en la ficha técnica de la orden dada.
     * Se le "devuelve" este valor a quantity - partial_output para que una orden no
     * vea su propia reserva ya existente como indisponible mientras la reprograma.
     *
     * @return array<string, float>
     */
    private function getOwnCommittedMetersByRollId(int $opId): array
    {
        $sheetId = TechnicalSheet::where('production_order_id', $opId)->value('id');
        if (! $sheetId) {
            return [];
        }

        $committed = [];
        foreach (
            FabricRollProgrammingLine::query()
                ->where('technical_sheet_id', $sheetId)
                ->whereNotNull('fabric_roll_id')
                ->where('programmed_meters', '>', 0)
                ->get(['fabric_roll_id', 'programmed_meters']) as $line
        ) {
            $rollId = (string) $line->fabric_roll_id;
            $committed[$rollId] = ($committed[$rollId] ?? 0.0) + (float) $line->programmed_meters;
        }

        return $committed;
    }

    private function fmtMeters(?float $value): string
    {
        if ($value === null) {
            return '0 m';
        }
        $normalized = rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');

        return "{$normalized} m";
    }

    public function reception(Request $request)
    {
        $scope = $this->resolveReceptionScope($request->query('scope', 'all'));

        return Inertia::render('Expenses/Reception/Reception', [
            'receptionScope' => $scope,
        ]);
    }

    public function receptionShow(Request $request, $invoiceId)
    {
        $invoice = PurchaseDocument::query()
            ->where('document_type', PurchaseDocument::TYPE_INVOICE)
            ->findOrFail($invoiceId);

        $this->abortIfDifferenceRejected($invoice);

        $scope = $this->resolveReceptionScope($request->query('scope', 'all'));

        return Inertia::render('Expenses/Reception/Reception', [
            'invoiceId' => $invoiceId,
            'receptionScope' => $scope,
        ]);
    }

    public function receptionData(Request $request, PurchaseDocumentInvoiceService $invoiceService): JsonResponse
    {
        $scope = $this->resolveReceptionScope($request->query('scope'));
        $filters = $request->only([
            'search',
            'status',
            'date_from',
            'date_to',
            'provider_id',
            'contact_id',
            'subgroup_id',
        ]);
        $filters['exclude_difference_rejected'] = true;

        if (($filters['status'] ?? null) === 'pending') {
            $filters['status'] = 'pending_receipt';
        }

        if ($scope === 'general') {
            $filters['exclude_fabric'] = true;
        } elseif ($scope === 'fabric') {
            $filters['fabric_only'] = true;
        }

        $documents = $invoiceService->paginateDocuments(
            PurchaseDocument::TYPE_INVOICE,
            $filters,
            (int) $request->input('per_page', 10),
            $request,
            $request->user()
        );

        return response()->json([
            'data' => $documents->items(),
            'total' => $documents->total(),
            'current_page' => $documents->currentPage(),
        ]);
    }

    public function receptionInvoice(Request $request, PurchaseDocument $purchaseDocument, PurchaseDocumentInvoiceService $invoiceService): JsonResponse
    {
        if ($purchaseDocument->document_type !== PurchaseDocument::TYPE_INVOICE) {
            abort(404);
        }

        $this->abortIfDifferenceRejected($purchaseDocument);

        $scope = $this->resolveReceptionScope($request->query('scope'));
        $purchaseDocument->loadMissing('items.item.categorySubGroup');
        $isFabricInvoice = $this->isFabricInvoice($purchaseDocument);

        if ($scope === 'general' && $isFabricInvoice) {
            abort(404);
        }

        if ($scope === 'fabric' && ! $isFabricInvoice) {
            abort(404);
        }

        return response()->json([
            'data' => $invoiceService->loadWithRelations($purchaseDocument),
        ]);
    }

    public function getData(Request $request)
    {
        $filters = $request->only(['search', 'status', 'date_from', 'date_to']);
        $perPage = $request->input('per_page', 10);
        $fabricRolls = $this->service->paginate($filters, $perPage);

        return FabricRollResource::collection($fabricRolls);
    }

    public function exportRolls(Request $request): StreamedResponse
    {
        $rolls = $this->service->export();
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('rollos');
        $sheet->fromArray(self::ROLL_IMPORT_EXPORT_HEADERS, null, 'A1');

        $rowNumber = 2;
        foreach ($rolls as $roll) {
            $item = $roll->item ?? $roll->purchaseDocumentItem?->item;
            $document = $roll->purchaseDocumentItem?->document;
            $baseTela = trim((string) ($item?->fabricName?->nombre ?? ''));
            $color = trim((string) (
                $roll->internal_color_name
                ?? $item?->color?->name
                ?? $roll->purchaseDocumentItem?->item?->color?->name
                ?? ''
            ));

            $sheet->fromArray([
                $roll->roll_number ?? '',
                $roll->location ?? '',
                $baseTela,
                $item?->name
                    ?? $roll->purchaseDocumentItem?->item_name
                    ?? $roll->description
                    ?? 'Tela',
                $color,
                $roll->internal_color_code ?? '',
                $roll->roll_width !== null ? (float) $roll->roll_width : '',
                $roll->price_per_meter !== null ? (float) $roll->price_per_meter : '',
                $roll->quantity !== null ? (float) $roll->quantity : '',
                $roll->partial_output !== null ? (float) $roll->partial_output : '',
                max(0.0, (float) $roll->quantity - (float) $roll->actual_output),
                $document?->supplier?->full_name
                    ?? $document?->provider_name
                    ?? '',
                $document?->supplier_order_number
                    ?? $document?->document_number
                    ?? '',
                $roll->entry_date?->format('Y-m-d') ?? '',
                $item?->reference ?? '',
            ], null, "A{$rowNumber}");
            $rowNumber++;
        }

        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, 'rollos_tela.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="rollos_tela.xlsx"',
        ]);
    }

    public function downloadTemplate(): BinaryFileResponse
    {
        $path = storage_path('app/public/plantilla_importacion_rollos.xlsx');

        if (file_exists($path)) {
            $spreadsheet = IOFactory::load($path);
            $sheet = $spreadsheet->getSheetByName('rollos') ?? $spreadsheet->getActiveSheet();
        } else {
            $spreadsheet = new Spreadsheet;
            $sheet = $spreadsheet->getActiveSheet();
        }

        $sheet->setTitle('rollos');

        $currentHeaders = array_map(
            fn ($header): string => $this->normalizeImportHeader((string) $header),
            $sheet->rangeToArray('A1:O1', '', true, true, false)[0]
        );

        $legacyHeaders = self::ROLL_IMPORT_EXPORT_HEADERS;
        unset($legacyHeaders[7]);
        $legacyHeaders = array_values($legacyHeaders);

        if (array_values(array_filter($currentHeaders, fn ($header): bool => $header !== '')) === $legacyHeaders) {
            $sheet->insertNewColumnBefore('H', 1);
        }

        $sheet->fromArray(self::ROLL_IMPORT_EXPORT_HEADERS, null, 'A1');

        $writer = new Xlsx($spreadsheet);
        $writer->save($path);

        $tempPath = tempnam(sys_get_temp_dir(), 'fabric-roll-template-');
        if ($tempPath === false) {
            abort(500, 'No fue posible generar la plantilla de rollos.');
        }

        $xlsxPath = $tempPath.'.xlsx';
        rename($tempPath, $xlsxPath);
        $writer->save($xlsxPath);

        return response()->download($xlsxPath, 'plantilla_importacion_rollos.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ])->deleteFileAfterSend(true);
    }

    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls'],
        ]);

        $file = $request->file('file');
        if (! $file instanceof UploadedFile || ! $file->isValid()) {
            return $this->importResponse(0, 0, [
                $this->importError(null, 'Debes enviar un archivo Excel válido.'),
            ], 422);
        }

        $extension = strtolower($file->getClientOriginalExtension());
        if (! in_array($extension, ['xlsx', 'xls'], true)) {
            return $this->importResponse(0, 0, [
                $this->importError(null, 'El archivo debe tener extension .xlsx o .xls.'),
            ], 422);
        }

        try {
            $spreadsheet = IOFactory::load($file->getPathname());
        } catch (\Throwable) {
            return $this->importResponse(0, 0, [
                $this->importError(null, 'No se pudo abrir el archivo Excel.'),
            ], 422);
        }

        $sheet = $spreadsheet->getSheetByName('rollos') ?? $spreadsheet->getActiveSheet();
        $rawRows = $sheet->toArray('', true, true, false);
        if (empty($rawRows)) {
            return $this->importResponse(0, 0, [
                $this->importError(null, 'El archivo Excel esta vacio o no tiene encabezados.'),
            ], 422);
        }

        $expectedHeaders = $this->expectedImportHeaders();
        $headerRow = is_array($rawRows[0] ?? null) ? array_values($rawRows[0]) : [];
        $columnCount = count($headerRow);
        $receivedHeaders = array_map(
            fn ($header): string => $this->normalizeImportHeader((string) $header),
            array_slice(array_pad($headerRow, count($expectedHeaders), ''), 0, count($expectedHeaders))
        );
        $missingHeaders = array_values(array_diff($expectedHeaders, $receivedHeaders));
        $extraHeaders = array_values(array_diff($receivedHeaders, $expectedHeaders));

        if ($columnCount !== count($expectedHeaders) || $receivedHeaders !== $expectedHeaders) {
            return $this->importResponse(0, 0, [
                $this->importError(null, 'Los encabezados de la plantilla no coinciden con el formato esperado.'),
                $this->importError(null, 'Columnas esperadas: '.implode(' | ', $expectedHeaders)),
                $this->importError(null, 'Columnas recibidas: '.implode(' | ', $receivedHeaders)),
                $this->importError(null, 'Columnas faltantes: '.(empty($missingHeaders) ? 'ninguna' : implode(' | ', $missingHeaders))),
                $this->importError(null, 'Columnas sobrantes: '.(empty($extraHeaders) ? 'ninguna' : implode(' | ', $extraHeaders))),
            ], 422);
        }

        $rows = [];
        $errors = [];
        $lineNumber = 1;

        foreach (array_slice($rawRows, 1) as $line) {
            $lineNumber++;

            $line = is_array($line) ? array_values($line) : [];
            $line = array_pad($line, 15, '');
            $line = array_slice($line, 0, 15);

            $hasValues = collect($line)->contains(
                fn ($value): bool => trim((string) $value) !== ''
            );

            if (! $hasValues) {
                continue;
            }

            $quantity = $this->normalizeImportFloat($line[8] ?? null);
            $partialOutput = $this->normalizeImportFloat($line[9] ?? null);
            $realAvailabilityRaw = trim((string) ($line[10] ?? ''));
            $realAvailability = $realAvailabilityRaw === ''
                ? $quantity
                : $this->normalizeImportFloat($line[10] ?? null);

            if ($realAvailability < 0) {
                $errors[] = "Fila {$lineNumber}: DISPONIBILIDAD REAL no puede ser negativa.";

                continue;
            }

            if ($realAvailability > $quantity) {
                $errors[] = "Fila {$lineNumber}: DISPONIBILIDAD REAL no puede ser mayor que CANTIDAD (m).";

                continue;
            }

            if ($partialOutput > $quantity) {
                $errors[] = "Fila {$lineNumber}: SALIDA PARCIAL no puede ser mayor que CANTIDAD (m).";

                continue;
            }

            $mapped = [
                'roll_number' => trim((string) ($line[0] ?? '')),
                'location' => trim((string) ($line[1] ?? '')),
                'base_tela' => trim((string) ($line[2] ?? '')),
                'description' => trim((string) ($line[3] ?? '')),
                'internal_color_name' => trim((string) ($line[4] ?? '')),
                'internal_color_code' => trim((string) ($line[5] ?? '')),
                'roll_width' => $this->normalizeImportFloat($line[6] ?? null),
                'price_per_meter' => trim((string) ($line[7] ?? '')) === ''
                    ? null
                    : $this->normalizeImportFloat($line[7] ?? null),
                'quantity' => $quantity,
                'partial_output' => $partialOutput,
                'actual_output' => round($quantity - $realAvailability, 2),
                'proveedor' => trim((string) ($line[11] ?? '')),
                'purchase_order_ref' => trim((string) ($line[12] ?? '')),
                'entry_date' => $line[13] ?? null,
                'item_referencia' => trim((string) ($line[14] ?? '')),
            ];

            $rows[] = [
                'line_number' => $lineNumber,
                'data' => $mapped,
            ];
        }

        if (empty($rows)) {
            $normalizedErrors = $this->normalizeImportErrors($errors);
            array_unshift($normalizedErrors, $this->importError(null, 'El archivo Excel no contiene filas validas para importar.'));

            return $this->importResponse(0, 0, $normalizedErrors, 422);
        }

        $refs = collect($rows)
            ->pluck('data.item_referencia')
            ->map(fn ($ref) => trim((string) $ref))
            ->filter()
            ->unique()
            ->values();

        $itemsByReference = Item::query()
            ->whereIn('reference', $refs->all())
            ->with('fabricName:id,nombre')
            ->get(['id', 'reference', 'extra_fields', 'fabric_name_id'])
            ->keyBy('reference');

        $fabricNamesByNormalizedName = FabricName::query()
            ->get(['id', 'nombre'])
            ->keyBy(fn ($fabricName) => TextNormalizer::semanticKey($fabricName->nombre));

        $knownRollNumbers = DB::table('fabric_rolls')
            ->pluck('roll_number')
            ->flip()
            ->toArray();

        $toInsert = [];
        $skipped = 0;
        $now = now();

        foreach ($rows as $row) {
            $line = $row['line_number'];
            $data = $row['data'];
            $rollNumber = trim((string) ($data['roll_number'] ?? ''));

            if ($rollNumber === '') {
                $errors[] = 'Fila '.$line.': N° ROLLO es obligatorio.';
                $skipped++;

                continue;
            }

            // Era la única condición de rechazo muda de este bucle: el usuario veía el
            // rollo omitido en el contador pero nunca sabía que la causa era un duplicado.
            if (isset($knownRollNumbers[$rollNumber])) {
                $errors[] = 'Fila '.$line.': el N° ROLLO '.$rollNumber.' ya existe (duplicado en el archivo o en el sistema).';
                $skipped++;

                continue;
            }

            $entryDate = $this->normalizeImportDate($data['entry_date'] ?? null);
            if ($entryDate === null) {
                $errors[] = 'Fila '.$line.': FECHA INGRESO inválida.';
                $skipped++;

                continue;
            }

            $ref = trim((string) ($data['item_referencia'] ?? ''));
            if ($ref === '') {
                $errors[] = 'Fila '.$line.': REFERENCIA ÍTEM es obligatoria.';
                $skipped++;

                continue;
            }

            $item = $itemsByReference->get($ref);
            if (! $item) {
                $errors[] = 'Fila '.$line.': La referencia de ítem '.$ref.' no existe en el catálogo.';
                $skipped++;

                continue;
            }

            $baseTela = trim((string) ($data['base_tela'] ?? ''));

            if ($baseTela !== '') {
                $normalizedBaseTela = TextNormalizer::semanticKey($baseTela);
                $matchedFabricName = $fabricNamesByNormalizedName->get($normalizedBaseTela);

                if (! $matchedFabricName) {
                    $errors[] = 'Fila '.$line.': BASE TELA "'.$baseTela.'" no coincide con ningún nombre de tela registrado en el catálogo (Datos Maestros → Nombres de tela / hilaza).';
                    $skipped++;

                    continue;
                }

                if ($item->fabric_name_id !== null && $item->fabric_name_id !== $matchedFabricName->id) {
                    $errors[] = 'Fila '.$line.': BASE TELA '.$baseTela.' no coincide con la del ítem '.$ref.' ('.($item->fabricName?->nombre ?? 'sin nombre de tela asignado').').';
                    $skipped++;

                    continue;
                }
            }

            if (($data['quantity'] ?? 0) <= 0) {
                $errors[] = 'Fila '.$line.': CANTIDAD (m) debe ser mayor que cero.';
                $skipped++;

                continue;
            }

            if (($data['roll_width'] ?? 0) < 0) {
                $errors[] = 'Fila '.$line.': ANCHO (m) no puede ser negativo.';
                $skipped++;

                continue;
            }

            if (($data['partial_output'] ?? 0) < 0) {
                $errors[] = 'Fila '.$line.': SALIDA PARCIAL no puede ser negativa.';
                $skipped++;

                continue;
            }

            if (($data['actual_output'] ?? 0) > ($data['quantity'] ?? 0)) {
                $errors[] = 'Fila '.$line.': la DISPONIBILIDAD REAL genera una salida real mayor que la cantidad del rollo.';
                $skipped++;

                continue;
            }

            if (($data['price_per_meter'] ?? null) !== null && (float) $data['price_per_meter'] < 0) {
                $errors[] = 'Fila '.$line.': PRECIO/M no puede ser negativo.';
                $skipped++;

                continue;
            }

            $toInsert[] = [
                'roll_number' => $rollNumber,
                'purchase_document_item_id' => null,
                'item_id' => $item->id,
                'location' => trim((string) ($data['location'] ?? '')),
                'description' => trim((string) ($data['description'] ?? '')),
                'internal_color_name' => trim((string) ($data['internal_color_name'] ?? '')),
                'internal_color_code' => trim((string) ($data['internal_color_code'] ?? '')),
                'roll_width' => $this->normalizeImportFloat($data['roll_width'] ?? null),
                'price_per_meter' => $data['price_per_meter'] !== null
                    ? $this->normalizeImportFloat($data['price_per_meter'])
                    : null,
                'quantity' => $this->normalizeImportFloat($data['quantity'] ?? null),
                'partial_output' => $this->normalizeImportFloat($data['partial_output'] ?? null),
                'actual_output' => $this->normalizeImportFloat($data['actual_output'] ?? null),
                'movement_type' => 'entrada',
                'approval_status' => FabricRoll::APPROVAL_APPROVED,
                'entry_date' => $entryDate,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            $knownRollNumbers[$rollNumber] = true;
        }

        $errors = $this->normalizeImportErrors($errors);

        if (empty($toInsert)) {
            return $this->importResponse(0, $skipped, $errors, 422);
        }

        // insert (no insertOrIgnore): una colisión de N° de rollo debe verse. Con
        // insertOrIgnore la fila se descartaba en silencio y el usuario solo notaba
        // un contador de insertados menor, sin ningún error que lo explicara.
        try {
            $inserted = DB::transaction(function () use ($toInsert) {
                $count = 0;

                foreach (array_chunk($toInsert, 100) as $chunk) {
                    DB::table('fabric_rolls')->insert($chunk);
                    $count += count($chunk);
                }

                return $count;
            });
        } catch (QueryException) {
            // Los duplicados del archivo ya se filtraron arriba, así que llegar aquí
            // significa que otro usuario registró uno de esos rollos mientras tanto.
            $errors[] = $this->importError(null, 'No se importó ninguna fila: otro usuario registró un N° ROLLO del archivo mientras se procesaba. Vuelve a intentarlo.');

            return $this->importResponse(0, $skipped + count($toInsert), $errors, 409);
        }

        return $this->importResponse($inserted, $skipped, $errors);
    }

    public function nextRollNumber()
    {
        return response()->json([
            'roll_number' => $this->service->nextRollNumber(),
        ]);
    }

    public function store(StoreFabricRollRequest $request)
    {
        $data = $request->validated();

        if ($error = $this->validateActualOutput((float) $data['quantity'], (float) ($data['actual_output'] ?? 0))) {
            return $error;
        }

        $fabricRoll = $this->service->create($data);

        return response()->json([
            'message' => 'Rollo creado correctamente',
            'data' => (new FabricRollReceptionResource($fabricRoll))->resolve(),
        ], 201);
    }

    public function show(FabricRoll $fabricRoll)
    {
        return response()->json((new FabricRollReceptionResource($fabricRoll))->resolve());
    }

    public function update(UpdateFabricRollRequest $request, FabricRoll $fabricRoll)
    {
        $data = $request->validated();

        if ($error = $this->validateActualOutput((float) $data['quantity'], (float) ($data['actual_output'] ?? 0))) {
            return $error;
        }

        try {
            $fabricRoll = $this->service->update($fabricRoll, $data);

            return response()->json((new FabricRollReceptionResource($fabricRoll))->resolve());
        } catch (ValidationException $e) {
            throw $e;
        }
    }

    public function destroy(FabricRoll $fabricRoll)
    {
        try {
            $this->service->delete($fabricRoll);

            return response()->noContent();
        } catch (ValidationException $e) {
            throw $e;
        }
    }

    public function discharge(DischargeFabricRollRequest $request, FabricRoll $fabricRoll): JsonResponse
    {
        $data = $request->validated();
        $type = (string) $data['type'];
        $quantity = (float) $data['quantity'];
        $reason = (string) ($data['reason'] ?? '');
        $productionOrderId = isset($data['production_order_id']) ? (int) $data['production_order_id'] : null;
        $creditNote = isset($data['credit_note']) ? (string) $data['credit_note'] : null;

        try {
            $fabricRoll = $this->service->discharge($fabricRoll, $type, $quantity, $reason, $productionOrderId, $creditNote);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $rollQtyValue = (float) $fabricRoll->quantity;
        $partialOutputValue = (float) $fabricRoll->partial_output;
        $exitQuantityValue = (float) $fabricRoll->actual_output;
        $partialAvailabilityValue = max(0.0, $rollQtyValue - $exitQuantityValue - $partialOutputValue);
        $realAvailabilityValue = $rollQtyValue - $exitQuantityValue;

        return response()->json([
            'message' => $type === 'salida'
                ? 'Descargue de rollo registrado correctamente.'
                : 'Entrada de rollo registrada correctamente.',
            'real_availability_value' => $realAvailabilityValue,
            'partial_output_value' => $partialOutputValue,
            'partial_availability_value' => $partialAvailabilityValue,
            'roll_qty_value' => $rollQtyValue,
            'exit_quantity_value' => $exitQuantityValue,
        ]);
    }

    public function correctQuantity(Request $request, FabricRoll $fabricRoll): JsonResponse
    {
        $data = $request->validate([
            'new_quantity' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        try {
            $fabricRoll = $this->service->correctQuantity(
                $fabricRoll,
                (float) $data['new_quantity'],
                (string) $data['reason']
            );
        } catch (ValidationException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => $e->errors()], 422);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $rollQtyValue = (float) $fabricRoll->quantity;
        $exitQuantityValue = (float) $fabricRoll->actual_output;
        $partialOutputValue = (float) $fabricRoll->partial_output;
        $partialAvailability = max(0.0, $rollQtyValue - $exitQuantityValue - $partialOutputValue);
        $realAvailability = $rollQtyValue - $exitQuantityValue;

        return response()->json([
            'message' => 'Cantidad del rollo corregida correctamente.',
            'roll_qty_value' => $rollQtyValue,
            'exit_quantity_value' => $exitQuantityValue,
            'partial_output_value' => $partialOutputValue,
            'partial_availability_value' => $partialAvailability,
            'real_availability_value' => $realAvailability,
        ]);
    }

    public function updatePrice(Request $request, FabricRoll $fabricRoll): JsonResponse
    {
        $data = $request->validate([
            'price_per_meter' => ['required', 'numeric', 'min:0'],
        ]);

        $fabricRoll->update(['price_per_meter' => (float) $data['price_per_meter']]);

        // Recalcular el PP ponderado en warehouse_items para el ítem vinculado al rollo.
        // Se usa el promedio ponderado de todos los rollos aprobados del ítem para
        // que el cambio de precio se refleje inmediatamente en el costo de producción.
        $item = $fabricRoll->item ?? $fabricRoll->purchaseDocumentItem?->item;
        if ($item) {
            $this->service->refreshWarehouseItemPP($item->id);
        }

        return response()->json([
            'message' => 'Precio por metro actualizado correctamente.',
            'price_per_meter' => (float) $fabricRoll->price_per_meter,
        ]);
    }

    public function registerGeneralReception(Request $request, $invoiceId)
    {
        $validated = $request->validate([
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.purchase_document_item_id' => ['required', 'integer'],
            'lines.*.warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'lines.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'lines.*.over_received_reason' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $invoice = $this->service->registerGeneralReception($invoiceId, $validated['lines']);

            return response()->json([
                'message' => 'Recepción registrada correctamente',
                'data' => $invoice,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'No se pudo registrar la recepción.',
                'errors' => $e->errors(),
            ], 422);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Factura no encontrada.',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al registrar la recepción: '.$e->getMessage(),
            ], 500);
        }
    }

    public function approveReception($invoiceId)
    {
        try {
            $invoice = $this->service->approveReception($invoiceId);

            return response()->json([
                'message' => 'Recepción aprobada correctamente',
                'data' => $invoice,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'No se pudo aprobar la recepción.',
                'errors' => $e->errors(),
            ], 422);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Factura no encontrada.',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al aprobar la recepción: '.$e->getMessage(),
            ], 500);
        }
    }

    public function rejectReception($invoiceId)
    {
        try {
            $invoice = $this->service->rejectReception($invoiceId);

            return response()->json([
                'message' => 'Recepción rechazada correctamente',
                'data' => $invoice,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'No se pudo rechazar la recepción.',
                'errors' => $e->errors(),
            ], 422);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Factura no encontrada.',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al rechazar la recepción: '.$e->getMessage(),
            ], 500);
        }
    }

    public function storeFromReception(StoreFabricRollRequest $request)
    {
        $data = $request->validated();

        if ($error = $this->validateActualOutput((float) $data['quantity'], (float) ($data['actual_output'] ?? 0))) {
            return $error;
        }

        try {
            $fabricRoll = $this->service->storeFromReception($data);

            return response()->json([
                'message' => 'Rollo creado correctamente desde recepción',
                'data' => (new FabricRollReceptionResource($fabricRoll))->resolve(),
            ], 201);
        } catch (ValidationException $e) {
            $errors = $e->errors();
            $firstError = collect($errors)->flatten()->first();

            return response()->json([
                'message' => is_string($firstError) && $firstError !== ''
                    ? $firstError
                    : 'No fue posible registrar el rollo.',
                'errors' => $errors,
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al crear el rollo: '.$e->getMessage(),
                'errors' => ['general' => ['Error interno del servidor']],
            ], 500);
        }
    }

    public function relinkMainFabric(Request $request, FabricRoll $fabricRoll): JsonResponse
    {
        $validated = $request->validate([
            'item_id' => ['required', 'integer', 'exists:items,id'],
        ]);

        $updatedRoll = $this->service->relinkMainFabric($fabricRoll, (int) $validated['item_id']);
        $updatedItem = $updatedRoll->item;

        return response()->json([
            'message' => 'Tela principal del rollo actualizada correctamente.',
            'data' => [
                'roll_id' => $updatedRoll->id,
                'fabric_item_id' => $updatedItem?->id,
                'fabric_name' => $updatedItem?->name,
            ],
        ]);
    }

    /**
     * @throws AuthorizationException
     */
    private function ensureSuperadminForInventoryChanges(Request $request): void
    {
        $user = $request->user();

        if (! $user || ! $user->hasRole('superadmin')) {
            throw new AuthorizationException('Solo superadmin puede realizar cambios en el inventario de rollos.');
        }
    }

    public function getByInvoiceItem($itemId)
    {
        $rolls = $this->service->getByInvoiceItem($itemId);

        return response()->json(FabricRollReceptionResource::collection($rolls)->resolve());
    }

    protected function validateActualOutput(float $quantity, float $actualOutput): ?JsonResponse
    {
        if ($actualOutput > $quantity) {
            return $this->actualOutputErrorResponse();
        }

        return null;
    }

    protected function actualOutputErrorResponse(): JsonResponse
    {
        return response()->json([
            'message' => 'La salida real no puede ser mayor que la cantidad del rollo',
            'errors' => ['actual_output' => ['La salida real no puede ser mayor que la cantidad del rollo']],
        ], 422);
    }

    protected function resolveReceptionScope(mixed $scope): string
    {
        $normalized = is_string($scope) ? strtolower(trim($scope)) : '';

        return in_array($normalized, ['all', 'general', 'fabric'], true) ? $normalized : 'all';
    }

    protected function isFabricInvoice(PurchaseDocument $invoice): bool
    {
        return $invoice->items->contains(function (PurchaseDocumentItem $item): bool {
            if ($item->item?->categorySubGroup) {
                return MaterialClassifier::isFabricPurchaseDocumentItem($item);
            }

            $fallbackName = mb_strtolower(trim((string) ($item->item_name ?? $item->item?->name ?? '')));

            return $fallbackName !== '' && str_contains($fallbackName, 'tela');
        });
    }

    protected function abortIfDifferenceRejected(PurchaseDocument $invoice): void
    {
        if ($this->isDifferenceRejectedInvoice($invoice)) {
            abort(403, 'La factura tiene diferencia rechazada y no puede gestionarse en recepción.');
        }
    }

    protected function isDifferenceRejectedInvoice(PurchaseDocument $invoice): bool
    {
        $approvalStatus = mb_strtolower(trim((string) ($invoice->approval_status ?? '')));
        $status = mb_strtolower(trim((string) ($invoice->status ?? '')));

        return in_array($approvalStatus, [
            PurchaseDocument::APPROVAL_DIFFERENCE_REJECTED,
            'diferencia rechazada',
        ], true) || in_array($status, [
            PurchaseDocument::APPROVAL_DIFFERENCE_REJECTED,
            'diferencia rechazada',
        ], true);
    }

    /**
     * @return array<int, string>
     */
    protected function expectedImportHeaders(): array
    {
        return self::ROLL_IMPORT_EXPORT_HEADERS;
    }

    protected function normalizeImportHeader(string $header): string
    {
        return trim((string) preg_replace('/^\xEF\xBB\xBF/', '', $header));
    }

    /**
     * @return array{row: int|null, message: string}
     */
    protected function importError(?int $row, string $message): array
    {
        return [
            'row' => $row,
            'message' => $message,
        ];
    }

    /**
     * @param  array<int, string|array{row?: int|null, message?: string}>  $errors
     * @return array<int, array{row: int|null, message: string}>
     */
    protected function normalizeImportErrors(array $errors): array
    {
        $normalized = [];

        foreach ($errors as $error) {
            if (is_array($error) && array_key_exists('message', $error)) {
                $normalized[] = $this->importError(
                    isset($error['row']) && is_numeric($error['row']) ? (int) $error['row'] : null,
                    trim((string) $error['message'])
                );

                continue;
            }

            if (is_string($error)) {
                $normalized[] = $this->parseImportErrorString($error);
            }
        }

        return array_values(array_filter($normalized, fn (array $error): bool => $error['message'] !== ''));
    }

    /**
     * @return array{row: int|null, message: string}
     */
    protected function parseImportErrorString(string $error): array
    {
        $trimmed = trim($error);

        if (preg_match('/^(?:Fila|Row)\s+(\d+)\s*:\s*(.+)$/iu', $trimmed, $matches) === 1) {
            return $this->importError((int) $matches[1], trim($matches[2]));
        }

        return $this->importError(null, $trimmed);
    }

    /**
     * @param  array<int, array{row: int|null, message: string}>  $errors
     */
    protected function importResponse(int $inserted, int $skipped, array $errors, int $status = 200): JsonResponse
    {
        // Construir mensaje legible para el frontend
        if ($status !== 200) {
            if ($inserted === 0 && $skipped > 0) {
                $message = "No se insertaron rollos; {$skipped} filas fueron omitidas. Revisa errores.";
            } elseif ($inserted > 0 && $skipped > 0) {
                $message = "Importación procesada con errores. Insertados: {$inserted}, omitidos: {$skipped}.";
            } else {
                $message = 'Error en la importación.';
            }
        } else {
            $message = 'Importación completada correctamente.';
        }

        return response()->json([
            'inserted' => $inserted,
            'skipped' => $skipped,
            'errors' => array_values($errors),
            'message' => $message,
        ], $status);
    }

    protected function normalizeImportFloat(mixed $value): float
    {
        if ($value === null || trim((string) $value) === '') {
            return 0.0;
        }

        return round((float) str_replace(',', '.', trim((string) $value)), 2);
    }

    protected function normalizeImportDate(mixed $value): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        $value = trim((string) $value);

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value;
        }

        $date = date_create($value);

        return $date ? date_format($date, 'Y-m-d') : null;
    }

    // ─── Importador de actualización de cantidades ───────────────────────────

    private const QUANTITY_IMPORT_HEADERS = [
        'N° ROLLO',
        'CANTIDAD (m)',
        'SALIDA PARCIAL (m)',
        'DISPONIBILIDAD REAL (m)',
        'REFERENCIA ÍTEM',
        'LOTE',
    ];

    public function downloadQuantitiesTemplate(): BinaryFileResponse
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('cantidades');

        foreach (self::QUANTITY_IMPORT_HEADERS as $col => $header) {
            $sheet->setCellValueByColumnAndRow($col + 1, 1, $header);
        }

        $tmpPath = sys_get_temp_dir().'/plantilla_cantidades_rollos.xlsx';
        (new Xlsx($spreadsheet))->save($tmpPath);

        return response()->download($tmpPath, 'plantilla_cantidades_rollos.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    /**
     * Detecta números de rollo repetidos dentro del mismo archivo.
     * La comparación es insensible a mayúsculas porque la columna de BD también lo es:
     * "rl100" y "RL100" resolverían al mismo rollo.
     *
     * @param  array<int, array{row:int, roll_number:string}>  $rows
     * @return array<int, array{row:int, message:string}>
     */
    private function findDuplicateRollNumbers(array $rows): array
    {
        $linesByRollNumber = [];
        foreach ($rows as $row) {
            $linesByRollNumber[mb_strtoupper($row['roll_number'])][] = $row['row'];
        }

        $errors = [];
        foreach ($linesByRollNumber as $rollNumber => $lines) {
            if (count($lines) > 1) {
                $errors[] = [
                    'row' => $lines[0],
                    'message' => "N° ROLLO {$rollNumber} aparece en las filas ".implode(', ', $lines).'. Deje una sola fila por rollo.',
                ];
            }
        }

        return $errors;
    }

    public function importQuantities(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls'],
        ]);

        $file = $request->file('file');
        if (! $file instanceof UploadedFile || ! $file->isValid()) {
            return response()->json(['message' => 'Archivo inválido.'], 422);
        }

        try {
            $spreadsheet = IOFactory::load($file->getPathname());
        } catch (\Throwable) {
            return response()->json(['message' => 'No se pudo abrir el archivo Excel.'], 422);
        }

        $sheet = $spreadsheet->getSheetByName('cantidades') ?? $spreadsheet->getActiveSheet();
        $rawRows = $sheet->toArray('', true, true, false);

        if (empty($rawRows)) {
            return response()->json(['message' => 'El archivo está vacío.'], 422);
        }

        // Validar encabezados
        $headerRow = array_map(fn ($h) => $this->normalizeImportHeader((string) ($h ?? '')), $rawRows[0]);
        $expectedHeaders = array_map(fn ($h) => $this->normalizeImportHeader($h), self::QUANTITY_IMPORT_HEADERS);

        foreach ($expectedHeaders as $i => $expected) {
            if (($headerRow[$i] ?? '') !== $expected) {
                return response()->json([
                    'message' => 'Encabezados incorrectos. Usa la plantilla descargada.',
                ], 422);
            }
        }

        $dataRows = array_slice($rawRows, 1);
        $batchRef = 'IMP-'.now()->format('YmdHis');

        // 'row' guarda el número de fila real del Excel (la fila 1 son los encabezados).
        // Es necesario porque más abajo se filtran filas vacías y el array se reindexa:
        // sin este dato, los errores se reportarían sobre la fila equivocada.
        $rows = [];
        foreach ($dataRows as $index => $r) {
            $rows[] = [
                'row' => $index + 2,
                'roll_number' => trim((string) ($r[0] ?? '')),
                'quantity' => $this->normalizeImportFloat($r[1] ?? 0),
                'partial_output' => $this->normalizeImportFloat($r[2] ?? 0),
                'real_availability' => $this->normalizeImportFloat($r[3] ?? 0),
                'item_reference' => trim((string) ($r[4] ?? '')),
                'batch' => trim((string) ($r[5] ?? '')),
            ];
        }

        // Filtrar filas vacías
        $rows = array_values(array_filter($rows, fn ($r) => $r['roll_number'] !== ''));

        if (empty($rows)) {
            return response()->json(['message' => 'No hay datos para procesar.'], 422);
        }

        // Un mismo rollo repetido en el archivo se aplicaría dos veces sobre el mismo
        // registro: el delta de la segunda fila se calcularía sobre el resultado de la
        // primera, dejando dos ajustes y dos movimientos de kardex contradictorios.
        // Se rechaza el archivo completo en vez de quedarse con la última fila.
        if ($duplicateErrors = $this->findDuplicateRollNumbers($rows)) {
            return response()->json([
                'updated' => 0,
                'skipped' => count($rows),
                'errors' => $duplicateErrors,
                'message' => 'El archivo tiene números de rollo repetidos. No se procesó ninguna fila.',
                'batch' => $batchRef,
            ], 422);
        }

        $result = $this->service->importQuantities($rows, $batchRef);

        $status = empty($result['errors']) ? 200 : ($result['updated'] > 0 ? 200 : 422);
        $message = $result['updated'] > 0
            ? "Se actualizaron {$result['updated']} rollo(s). Omitidos: {$result['skipped']}."
            : "No se actualizó ningún rollo. Omitidos: {$result['skipped']}.";

        return response()->json([
            'updated' => $result['updated'],
            'skipped' => $result['skipped'],
            'errors' => array_values($result['errors']),
            'message' => $message,
            'batch' => $batchRef,
        ], $status);
    }

    // ─── Importador CSV histórico ─────────────────────────────────────────────

    public function importFromCsv(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx,xls', 'max:10240'],
        ]);

        /** @var UploadedFile $file */
        $file = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension());

        if (in_array($extension, ['xlsx', 'xls'], true)) {
            $rows = $this->readLegacyRollRowsFromExcel($file);
            if ($rows === null) {
                return response()->json(['message' => 'No se pudo abrir el archivo Excel.'], 422);
            }
        } else {
            $rows = $this->readLegacyRollRowsFromCsv($file);
            if ($rows === null) {
                return response()->json(['message' => 'No se pudo leer el archivo.'], 422);
            }
        }

        if (empty($rows)) {
            return response()->json(['message' => 'El archivo no tiene filas de datos.'], 422);
        }

        $result = $this->service->importFromCsv($rows);

        $status = $result['inserted'] > 0 ? 200 : 422;
        $message = $result['inserted'] > 0
            ? "Importación completada. Insertados: {$result['inserted']}, omitidos: {$result['skipped']}."
            : "No se insertaron rollos. Omitidos: {$result['skipped']}.";

        return response()->json([
            'inserted' => $result['inserted'],
            'skipped' => $result['skipped'],
            'errors' => array_values($result['errors']),
            'message' => $message,
        ], $status);
    }

    private const LEGACY_ROLL_COLUMN_COUNT = 15;

    /**
     * Lee el CSV/TXT histórico de rollos (15 columnas posicionales). El delimitador
     * se detecta a partir del encabezado en vez de asumir uno fijo: hay archivos
     * exportados con "," y otros con ";" (un mismo archivo nunca mezcla ambos).
     *
     * @return array<int, array<int, string>>|null null si el archivo no se pudo leer.
     */
    protected function readLegacyRollRowsFromCsv(UploadedFile $file): ?array
    {
        $contents = file_get_contents($file->getPathname());
        if ($contents === false) {
            return null;
        }

        // Quitar BOM UTF-8
        $contents = preg_replace('/^\xEF\xBB\xBF/', '', $contents) ?? $contents;

        $lines = preg_split('/\r\n|\r|\n/', trim($contents)) ?: [];
        if (count($lines) < 2) {
            return [];
        }

        $delimiter = $this->detectCsvDelimiter($lines[0]);

        $rows = [];
        foreach (array_slice($lines, 1) as $line) {
            if (trim($line) === '') {
                continue;
            }
            $rows[] = str_getcsv($line, $delimiter);
        }

        return $rows;
    }

    /**
     * Decide el delimitador probando "," y ";" sobre el encabezado y quedándose con
     * el que produce más columnas (el correcto separa las 15 columnas esperadas; el
     * incorrecto las deja todas pegadas en una sola).
     */
    protected function detectCsvDelimiter(string $headerLine): string
    {
        $commaColumns = count(str_getcsv($headerLine, ','));
        $semicolonColumns = count(str_getcsv($headerLine, ';'));

        return $semicolonColumns > $commaColumns ? ';' : ',';
    }

    /**
     * Lee el histórico de rollos desde Excel (.xlsx/.xls) con el mismo formato
     * posicional de 15 columnas que el CSV histórico. La fecha se normaliza a Y-m-d
     * con normalizeImportDate() porque PhpSpreadsheet formatea las celdas de fecha
     * como m/d/Y (mes primero), distinto del d/m/Y que espera
     * FabricRollService::parseCsvDate() para el CSV histórico — sin esta conversión
     * previa, día y mes quedarían invertidos en silencio.
     *
     * @return array<int, array<int, string>>|null null si el archivo no se pudo abrir.
     */
    protected function readLegacyRollRowsFromExcel(UploadedFile $file): ?array
    {
        try {
            $spreadsheet = IOFactory::load($file->getPathname());
        } catch (\Throwable) {
            return null;
        }

        $sheet = $spreadsheet->getActiveSheet();
        $rawRows = $sheet->toArray('', true, true, false);

        if (count($rawRows) < 2) {
            return [];
        }

        $rows = [];
        foreach (array_slice($rawRows, 1) as $line) {
            $line = is_array($line) ? array_values($line) : [];
            $line = array_pad($line, self::LEGACY_ROLL_COLUMN_COUNT, '');
            $line = array_slice($line, 0, self::LEGACY_ROLL_COLUMN_COUNT);

            $hasValues = collect($line)->contains(fn ($value): bool => trim((string) $value) !== '');
            if (! $hasValues) {
                continue;
            }

            $line[14] = $this->normalizeImportDate($line[14] ?? null) ?? '';

            $rows[] = $line;
        }

        return $rows;
    }

    // ─── Reportes ─────────────────────────────────────────────────────────────

    public function reportSinRelacion(Request $request): JsonResponse
    {
        $perPage = min((int) ($request->query('per_page', 50)), 100);

        $paginator = FabricRoll::whereNull('item_id')
            ->where('approval_status', FabricRoll::APPROVAL_APPROVED)
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return response()->json($paginator);
    }

    public function reportHistorialAjustes(Request $request): JsonResponse
    {
        $perPage = min((int) ($request->query('per_page', 50)), 100);

        $query = FabricRollAdjustment::with([
            'roll:id,roll_number',
            'user:id,name',
            'inventoryMovement:id,type,quantity,unit_price,notes,created_at',
        ])
            ->when($request->query('roll_number'), fn ($q, $rn) => $q->whereHas('roll', fn ($r) => $r->where('roll_number', $rn)))
            ->when($request->query('type'), fn ($q, $t) => $q->where('type', $t))
            ->when($request->query('from'), fn ($q, $f) => $q->whereDate('created_at', '>=', $f))
            ->when($request->query('to'), fn ($q, $t) => $q->whereDate('created_at', '<=', $t))
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return response()->json($query);
    }
}
