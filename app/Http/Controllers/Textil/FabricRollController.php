<?php

namespace App\Http\Controllers\Textil;

use App\Http\Controllers\Controller;
use App\Http\Requests\Textil\StoreFabricRollRequest;
use App\Http\Requests\Textil\UpdateFabricRollOutputRequest;
use App\Http\Requests\Textil\UpdateFabricRollRequest;
use App\Http\Resources\Inventory\FabricRollResource;
use App\Models\Production\Programmer;
use App\Models\Textil\FabricRoll;
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
            $programmer = Programmer::with('productSheet')->find($programmerId);
            if ($programmer) {
                $reference = $programmer->productSheet?->reference;
                $lotName = $reference ? "MF{$programmer->id} - {$reference}" : "MF{$programmer->id}";

                if ($programmer->productSheet && $programmer->productSheet->sizes) {
                    $rawSizes = $programmer->productSheet->sizes;
                    
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
            'rolls' => FabricRollResource::collection($this->service->available())->resolve(),
            'sizes' => $sizes,
        ]);
    }

    public function inventoryRollsData(Request $request)
    {
        $rawIds = $request->query('fabricItemIds', []);
        $ids = is_array($rawIds) ? $rawIds : [$rawIds];
        $fabricItemIds = collect($ids)
            ->map(fn ($id) => is_numeric($id) ? (int) $id : null)
            ->filter(fn ($id) => is_int($id) && $id > 0)
            ->unique()
            ->values()
            ->all();

        $rolls = $this->service->export($fabricItemIds);
        return FabricRollResource::collection($rolls);
    }

    public function reception()
    {
        return Inertia::render('Expenses/Reception/Reception');
    }

    public function receptionShow($invoiceId)
    {
        return Inertia::render('Expenses/Reception/Reception', [
            'invoiceId' => $invoiceId
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

        $fabricRoll = $this->service->updateOutput($fabricRoll, $data);

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
}
