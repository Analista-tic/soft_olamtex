<?php

namespace App\Http\Controllers\Textil;

use App\Http\Controllers\Controller;
use App\Http\Requests\Textil\StoreFabricRollRequest;
use App\Http\Requests\Textil\UpdateFabricRollOutputRequest;
use App\Http\Requests\Textil\UpdateFabricRollRequest;
use App\Http\Resources\Inventory\FabricRollResource;
use App\Models\Expenses\PurchaseDocument;
use App\Models\Expenses\PurchaseDocumentItem;
use App\Models\Production\ProductionOrder;
use App\Models\Production\TechnicalSheet;
use App\Models\Production\TechnicalSheetRoll;
use App\Models\Textil\FabricRoll;
use App\Services\Expenses\PurchaseDocumentInvoiceService;
use App\Services\Textil\FabricRollService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FabricRollController extends Controller
{
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
            'fabricRoll' => $fabricRoll
        ]);
    }

    public function kardex()
    {
        return Inertia::render('Textil/Index');
    }

    public function inventoryRolls()
    {
        return Inertia::render('Textil/FabricRollInventory/Index');
    }

    public function downloadOrders()
    {
        return Inertia::render('Textil/FabricRollOrders/Index');
    }

    public function downloadWindow(Request $request)
    {
        $roll = null;

        if ($request->filled('roll')) {
            try {
                $fabricRoll = $this->service->findOrFail($request->input('roll'));
                $roll = (new FabricRollResource($fabricRoll))->resolve();
            } catch (ModelNotFoundException) {
                $roll = null;
            }
        }

        $programmerId = $request->input('programmer');
        $lotName = $programmerId ? "MF{$programmerId}" : null;

        $sizes = null;
        if ($programmerId) {
            $order = ProductionOrder::with('productSheet')->find($programmerId);
            if ($order) {
                $reference = $order->productSheet?->reference;
                $lotName = $reference ? "MF{$order->id} - {$reference}" : "MF{$order->id}";

                if ($order->productSheet && $order->productSheet->sizes) {
                    $rawSizes = $order->productSheet->sizes;

                    // Procesamiento robusto de tallas - solo tallas activas (true)
                    if (is_array($rawSizes)) {
                        // Si es array asociativo (objeto en JSON), extraer solo las claves con valor true
                        if (array_keys($rawSizes) !== range(0, count($rawSizes) - 1)) {
                            // Es array asociativo - extraer SOLO claves donde el valor sea estrictamente true
                            $sizes = array_values(array_filter(
                                array_keys($rawSizes),
                                fn($key) => $rawSizes[$key] === true // Solo valores booleanos true
                            ));
                        } else {
                            // Es array indexado - filtrar valores vacíos y trimear
                            $sizes = array_values(array_filter(array_map('trim', $rawSizes), fn($s) => !empty($s)));
                        }
                    } elseif (is_string($rawSizes)) {
                        // Si es string, separar por coma y limpiar
                        $sizes = array_values(array_filter(array_map('trim', explode(',', $rawSizes)), fn($s) => !empty($s)));
                    }
                }
            }
        }

        return Inertia::render('Textil/FabricRollOrders/Download', [
            'roll' => $roll,
            'lotName' => $lotName,
            'sizes' => $sizes,
        ]);
    }

    public function inventoryRollsData(Request $request): JsonResponse
    {
        $rawIds = $request->input('fabricItemIds', []);
        $ids    = is_array($rawIds) ? $rawIds : [$rawIds];
        $fabricItemIds = collect($ids)
            ->map(fn ($id) => is_numeric($id) ? (int) $id : null)
            ->filter(fn ($id) => is_int($id) && $id > 0)
            ->unique()->values()->all();

        $filters = [
            'search'          => $request->input('search'),
            'base_fabric'     => $request->input('base_fabric'),
            'provider'        => $request->input('provider'),
            'with_output'     => $request->boolean('with_output'),
            'fabric_item_ids' => $fabricItemIds ?: null,
        ];

        $perPage       = min((int) $request->input('per_page', 100), 500);
        $sortBy        = $request->input('sort_by', 'entry_date');
        $sortDirection = $request->input('sort_direction', 'desc');
        /** @var \Illuminate\Pagination\LengthAwarePaginator $paginator */
        $paginator = $this->service->inventoryWithFilters($filters, $perPage, $sortBy, $sortDirection);

        // Descuentar metros ya programados en fichas técnicas de OTRAS órdenes
        $excludeOpId = $request->input('excludeOpId');
        $committedByRollId = $this->getCommittedMetersByRollId(
            is_numeric($excludeOpId) ? (int) $excludeOpId : null
        );

        $rows = $paginator->getCollection()->map(function (object $row) use ($committedByRollId): array {
            $row     = (array) $row;
            $qty     = (float) ($row['quantity'] ?? 0);
            $partial = (float) ($row['partial_output'] ?? 0);
            $actual  = (float) ($row['actual_output'] ?? 0);
            $reservedOrConsumed = max($partial, $actual);
            $committed = (float) ($committedByRollId[(string) $row['id']] ?? 0.0);
            $color   = $row['supplier_color'] ?? null;

            return [
                'id'                        => $row['id'],
                'fabric_item_id'            => $row['fabric_item_id'],
                'purchase_document_item_id' => $row['purchase_document_item_id'],
                'roll'                      => $row['roll_number'],
                'location'                  => $row['location'],
                'fabric'                    => $row['fabric'],
                'color'                     => $color ?? 'N/A',
                'color_code'                => '#' . substr(md5($color ?? ''), 0, 6),
                'width'                     => $this->fmtMeters((float) ($row['roll_width'] ?? 0)),
                'roll_qty'                  => $this->fmtMeters($qty),
                'partial_output'            => $this->fmtMeters($partial),
                'exit_quantity'             => $this->fmtMeters($actual),
                'partial_availability'      => $this->fmtMeters(max(0.0, $qty - $reservedOrConsumed - $committed)),
                'real_availability'         => $this->fmtMeters(max(0.0, $qty - $actual)),
                'order_number'              => $row['order_number'],
                'provider'                  => $row['provider'],
                'price_per_meter'           => isset($row['price_per_meter']) ? (float) $row['price_per_meter'] : null,
                'created_at'               => isset($row['doc_created_at'])
                    ? \Illuminate\Support\Carbon::parse($row['doc_created_at'])->format('d M Y')
                    : null,
                'creator'                   => $row['creator_name'] ?? null,
                'status'                    => $actual >= $qty ? 'depleted' : 'available',
                'quantity_value'            => $qty,
                'exit_quantity_value'       => $actual,
            ];
        });

        return response()->json([
            'data' => $rows,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
                'from'         => $paginator->firstItem(),
                'to'           => $paginator->lastItem(),
            ],
        ]);
    }

    /**
     * Suma metros programados por rollId en todas las fichas técnicas
     * excepto la orden indicada en $excludeOpId (la orden actualmente activa).
     *
     * @return array<string, float>
     */
    private function getCommittedMetersByRollId(?int $excludeOpId): array
    {
        $query = TechnicalSheetRoll::query()->select('technical_sheet_id', 'lines');

        if ($excludeOpId !== null) {
            $excludeSheetId = TechnicalSheet::where('production_order_id', $excludeOpId)->value('id');
            if ($excludeSheetId) {
                $query->where('technical_sheet_id', '!=', $excludeSheetId);
            }
        }

        $committed = [];
        foreach ($query->get() as $snapshot) {
            $lines = is_array($snapshot->lines) ? $snapshot->lines : [];
            foreach ($lines as $line) {
                $rollId = trim((string) ($line['rollId'] ?? ''));
                if ($rollId === '') {
                    continue;
                }
                $meters = is_numeric($line['programmedMeters'] ?? null)
                    ? (float) $line['programmedMeters']
                    : 0.0;
                if ($meters > 0.0) {
                    $committed[$rollId] = ($committed[$rollId] ?? 0.0) + $meters;
                }
            }
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
            $request
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

        if ($scope === 'fabric' && !$isFabricInvoice) {
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
        $rows = FabricRollResource::collection($rolls)->resolve();
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('rollos');

        $columns = ['Rollo', 'Ubicación', 'Tela', 'Color', 'Cod. color', 'Ancho', 'Cantidad rollo', 'Salida parcial', 'Disponibilidad parcial', 'Cantidad salida', 'Disponibilidad real', 'Proveedor', 'Pedido', 'Creado'];
        $sheet->fromArray($columns, null, 'A1');

        $rowNumber = 2;
        foreach ($rows as $row) {
            $sheet->fromArray([
                $row['roll'] ?? '',
                $row['location'] ?? '',
                $row['fabric'] ?? '',
                $row['color'] ?? '',
                $row['color_code'] ?? '',
                $row['width'] ?? '',
                $row['roll_qty'] ?? '',
                $row['partial_output'] ?? '',
                $row['partial_availability'] ?? '',
                $row['exit_quantity'] ?? '',
                $row['real_availability'] ?? '',
                $row['provider'] ?? '',
                $row['order_number'] ?? '',
                $row['created_at'] ?? '',
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

    public function store(StoreFabricRollRequest $request)
    {
        $data = $request->validated();

        if ($error = $this->validateActualOutput((float) $data['quantity'], (float) ($data['actual_output'] ?? 0))) {
            return $error;
        }

        $fabricRoll = $this->service->create($data);

        return response()->json([
            'message' => 'Rollo creado correctamente',
            'data' => $fabricRoll
        ], 201);
    }

    public function show(FabricRoll $fabricRoll)
    {
        return response()->json($fabricRoll);
    }

    public function update(UpdateFabricRollRequest $request, FabricRoll $fabricRoll)
    {
        $data = $request->validated();

        if ($error = $this->validateActualOutput((float) $data['quantity'], (float) ($data['actual_output'] ?? 0))) {
            return $error;
        }

        try {
            $fabricRoll = $this->service->update($fabricRoll, $data);
            return response()->json($fabricRoll);
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

    public function updateOutput(UpdateFabricRollOutputRequest $request, FabricRoll $fabricRoll)
    {
        $data = $request->validated();
        $actualOutput = array_key_exists('actual_output', $data) ? (float) $data['actual_output'] : $fabricRoll->actual_output;

        if ($error = $this->validateActualOutput((float) $fabricRoll->quantity, $actualOutput)) {
            return $error;
        }

        $productionOrderId = isset($data['production_order_id']) ? (int) $data['production_order_id'] : null;
        $fabricRoll = $this->service->updateOutput($fabricRoll, $data, $productionOrderId);

        return response()->json([
            'message' => 'Salidas actualizadas correctamente',
            'data' => $fabricRoll
        ]);
    }

    public function approveReception($invoiceId)
    {
        try {
            $invoice = $this->service->approveReception($invoiceId);
            return response()->json([
                'message' => 'Recepción aprobada correctamente',
                'data' => $invoice
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
                'message' => 'Error al aprobar la recepción: ' . $e->getMessage()
            ], 500);
        }
    }

    public function rejectReception($invoiceId)
    {
        try {
            $invoice = $this->service->rejectReception($invoiceId);
            return response()->json([
                'message' => 'Recepción rechazada correctamente',
                'data' => $invoice
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
                'message' => 'Error al rechazar la recepción: ' . $e->getMessage()
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
                'data' => $fabricRoll
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al crear el rollo: ' . $e->getMessage(),
                'errors' => ['general' => ['Error interno del servidor']]
            ], 500);
        }
    }

    public function getByInvoiceItem($itemId)
    {
        $rolls = $this->service->getByInvoiceItem($itemId);
        return response()->json($rolls);
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
            'errors' => ['actual_output' => ['La salida real no puede ser mayor que la cantidad del rollo']]
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
            $subgroupName = mb_strtolower(trim((string) ($item->item?->categorySubGroup?->name ?? '')));
            if ($subgroupName !== '') {
                return str_contains($subgroupName, 'tela');
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
}
