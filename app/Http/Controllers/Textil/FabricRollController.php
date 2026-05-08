<?php

namespace App\Http\Controllers\Textil;

use App\Http\Controllers\Controller;
use App\Http\Requests\Textil\DischargeFabricRollRequest;
use App\Http\Requests\Textil\StoreFabricRollRequest;
use App\Http\Requests\Textil\UpdateFabricRollOutputRequest;
use App\Http\Requests\Textil\UpdateFabricRollRequest;
use App\Http\Resources\Inventory\FabricRollResource;
use App\Http\Resources\Textil\FabricRollReceptionResource;
use App\Models\MasterData\Item;
use App\Models\Expenses\PurchaseDocument;
use App\Models\Expenses\PurchaseDocumentItem;
use App\Models\Production\ProductionOrder;
use App\Models\Production\TechnicalSheet;
use App\Models\Production\TechnicalSheetRoll;
use App\Models\Textil\FabricRoll;
use App\Services\Expenses\PurchaseDocumentInvoiceService;
use App\Services\Textil\FabricRollService;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

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
            'fabricRoll' => $fabricRoll
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
            'color'           => $request->input('color'),
            'location'        => $request->input('location'),
            'with_output'     => $request->boolean('with_output'),
            'fabric_item_ids' => $fabricItemIds ?: null,
        ];

        $perPage       = min((int) $request->input('per_page', 100), 500);
        $sortBy        = $request->input('sort_by', 'roll_number');
        $sortDirection = $request->input('sort_direction', 'asc');
        /** @var \Illuminate\Pagination\LengthAwarePaginator $paginator */
        $paginator = $this->service->inventoryWithFilters($filters, $perPage, $sortBy, $sortDirection);

        // Descuentar metros ya programados en fichas técnicas de OTRAS órdenes
        $excludeOpId = $request->input('excludeOpId');
        $committedByRollId = $this->getCommittedMetersByRollId(
            is_numeric($excludeOpId) ? (int) $excludeOpId : null
        );

        $useCommittedAvailability = is_numeric($excludeOpId);

        $rows = $paginator->getCollection()->map(function (object $row) use ($committedByRollId, $useCommittedAvailability): array {
            $row     = (array) $row;
            $qty     = (float) ($row['quantity'] ?? 0);
            $partial = (float) ($row['partial_output'] ?? 0);
            $actual  = (float) ($row['actual_output'] ?? 0);
            $committed = (float) ($committedByRollId[(string) $row['id']] ?? 0.0);
            $partialAvailability = $useCommittedAvailability
                ? max(0.0, $qty - $actual - $committed)
                : max(0.0, $qty - $actual - $partial);
            $color     = $row['internal_color_name'] ?? $row['supplier_color'] ?? null;
            $colorName = $row['item_color_name'] ?? $color ?? null;
            $colorHex  = isset($row['item_color_hex']) && $row['item_color_hex'] !== ''
                ? $row['item_color_hex']
                : null;

            return [
                'id'                        => $row['id'],
                'fabric_item_id'            => isset($row['fabric_item_id']) ? (int) $row['fabric_item_id'] : null,
                'purchase_document_item_id' => $row['purchase_document_item_id'],
                'roll'                      => $row['roll_number'],
                'location'                  => $row['location'],
                'fabric'                    => $row['fabric'],
                'color'                     => $colorName ?? 'N/A',
                'color_code'                => $colorHex ?? null,
                'width'                     => $this->fmtMeters((float) ($row['roll_width'] ?? 0)),
                'width_value'               => (float) ($row['roll_width'] ?? 0),
                'roll_qty'                  => $this->fmtMeters($qty),
                'roll_qty_value'            => $qty,
                'partial_output'            => $this->fmtMeters($partial),
                'partial_output_value'      => $partial,
                'partial_availability'      => $this->fmtMeters($partialAvailability),
                'partial_availability_value' => $partialAvailability,
                'exit_quantity'             => $this->fmtMeters($actual),
                'exit_quantity_value'       => $actual,
                'real_availability'         => $this->fmtMeters(max(0.0, $qty - $actual)),
                'real_availability_value'   => max(0.0, $qty - $actual),
                'order_number'              => $row['order_number'],
                'provider'                  => $row['provider'],
                'price_per_meter'           => isset($row['price_per_meter']) ? (float) $row['price_per_meter'] : null,
                'created_at'               => isset($row['doc_created_at'])
                    ? \Illuminate\Support\Carbon::parse($row['doc_created_at'])->format('d M Y')
                    : null,
                'creator'                   => $row['creator_name'] ?? null,
                'status'                    => $actual >= $qty ? 'depleted' : 'available',
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
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('rollos');
        $sheet->fromArray(self::ROLL_IMPORT_EXPORT_HEADERS, null, 'A1');

        $rowNumber = 2;
        foreach ($rolls as $roll) {
            $item = $roll->item ?? $roll->purchaseDocumentItem?->item;
            $document = $roll->purchaseDocumentItem?->document;
            $extraFields = is_array($item?->extra_fields) ? $item->extra_fields : [];
            $baseTela = trim((string) ($extraFields['BASE TELA'] ?? $extraFields['base_tela'] ?? ''));
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

    public function downloadTemplate(): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $path = storage_path('app/public/plantilla_importacion_rollos.xlsx');

        if (file_exists($path)) {
            $spreadsheet = IOFactory::load($path);
            $sheet = $spreadsheet->getSheetByName('rollos') ?? $spreadsheet->getActiveSheet();
        } else {
            $spreadsheet = new Spreadsheet();
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

        $xlsxPath = $tempPath . '.xlsx';
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
        if (!$file instanceof UploadedFile || !$file->isValid()) {
            return $this->importResponse(0, 0, [
                $this->importError(null, 'Debes enviar un archivo Excel valido.'),
            ], 422);
        }

        $extension = strtolower($file->getClientOriginalExtension());
        if (!in_array($extension, ['xlsx', 'xls'], true)) {
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
                $this->importError(null, 'Columnas esperadas: ' . implode(' | ', $expectedHeaders)),
                $this->importError(null, 'Columnas recibidas: ' . implode(' | ', $receivedHeaders)),
                $this->importError(null, 'Columnas faltantes: ' . (empty($missingHeaders) ? 'ninguna' : implode(' | ', $missingHeaders))),
                $this->importError(null, 'Columnas sobrantes: ' . (empty($extraHeaders) ? 'ninguna' : implode(' | ', $extraHeaders))),
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

            if (!$hasValues) {
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
            ->get(['id', 'reference', 'extra_fields'])
            ->keyBy('reference');

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
                $errors[] = 'Fila ' . $line . ': N° ROLLO es obligatorio.';
                $skipped++;
                continue;
            }

            if (isset($knownRollNumbers[$rollNumber])) {
                $skipped++;
                continue;
            }

            $entryDate = $this->normalizeImportDate($data['entry_date'] ?? null);
            if ($entryDate === null) {
                $errors[] = 'Fila ' . $line . ': FECHA INGRESO inválida.';
                $skipped++;
                continue;
            }

            $ref = trim((string) ($data['item_referencia'] ?? ''));
            if ($ref === '') {
                $errors[] = 'Fila ' . $line . ': REFERENCIA ÍTEM es obligatoria.';
                $skipped++;
                continue;
            }

            $item = $itemsByReference->get($ref);
            if (!$item) {
                $errors[] = 'Fila ' . $line . ': La referencia de ítem ' . $ref . ' no existe en el catálogo.';
                $skipped++;
                continue;
            }

            $itemExtraFields = is_array($item->extra_fields) ? $item->extra_fields : [];
            $itemBaseTela = trim((string) ($itemExtraFields['BASE TELA'] ?? $itemExtraFields['base_tela'] ?? ''));
            $baseTela = trim((string) ($data['base_tela'] ?? ''));

            if ($baseTela !== '' && $itemBaseTela !== '' && mb_strtolower($baseTela) !== mb_strtolower($itemBaseTela)) {
                $errors[] = 'Fila ' . $line . ': BASE TELA ' . $baseTela . ' no coincide con la del ítem ' . $ref . ' (' . $itemBaseTela . ').';
                $skipped++;
                continue;
            }

            if (($data['quantity'] ?? 0) <= 0) {
                $errors[] = 'Fila ' . $line . ': CANTIDAD (m) debe ser mayor que cero.';
                $skipped++;
                continue;
            }

            if (($data['roll_width'] ?? 0) < 0) {
                $errors[] = 'Fila ' . $line . ': ANCHO (m) no puede ser negativo.';
                $skipped++;
                continue;
            }

            if (($data['partial_output'] ?? 0) < 0) {
                $errors[] = 'Fila ' . $line . ': SALIDA PARCIAL no puede ser negativa.';
                $skipped++;
                continue;
            }

            if (($data['actual_output'] ?? 0) > ($data['quantity'] ?? 0)) {
                $errors[] = 'Fila ' . $line . ': la DISPONIBILIDAD REAL genera una salida real mayor que la cantidad del rollo.';
                $skipped++;
                continue;
            }

            if (($data['price_per_meter'] ?? null) !== null && (float) $data['price_per_meter'] < 0) {
                $errors[] = 'Fila ' . $line . ': PRECIO/M no puede ser negativo.';
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

        $inserted = DB::transaction(function () use ($toInsert) {
            $count = 0;

            foreach (array_chunk($toInsert, 100) as $chunk) {
                $count += DB::table('fabric_rolls')->insertOrIgnore($chunk);
            }

            return $count;
        });

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
            'data' => (new FabricRollReceptionResource($fabricRoll))->resolve()
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
            'data' => (new FabricRollReceptionResource($fabricRoll))->resolve()
        ]);
    }

    public function discharge(DischargeFabricRollRequest $request, FabricRoll $fabricRoll): JsonResponse
    {
        $data = $request->validated();
        $type = (string) $data['type'];
        $quantity = (float) $data['quantity'];
        $productionOrderId = isset($data['production_order_id']) ? (int) $data['production_order_id'] : null;

        if ($type === 'salida') {
            $newActualOutput = (float) $fabricRoll->actual_output + $quantity;

            if ($newActualOutput > (float) $fabricRoll->quantity) {
                return response()->json([
                    'message' => 'La cantidad de salida no puede superar la disponibilidad real del rollo.',
                    'errors' => [
                        'quantity' => ['La cantidad de salida no puede superar la disponibilidad real del rollo.'],
                    ],
                ], 422);
            }

            $fabricRoll = $this->service->updateOutput(
                $fabricRoll,
                ['actual_output' => $newActualOutput],
                $productionOrderId
            );
        } else {
            $fabricRoll->increment('quantity', $quantity);
            $fabricRoll->refresh();
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
                'data' => (new FabricRollReceptionResource($fabricRoll))->resolve()
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
                'message' => 'Error al crear el rollo: ' . $e->getMessage(),
                'errors' => ['general' => ['Error interno del servidor']]
            ], 500);
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
     * @param array<int, string|array{row?: int|null, message?: string}> $errors
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
     * @param array<int, array{row: int|null, message: string}> $errors
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
}
