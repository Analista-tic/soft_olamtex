<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StoreFabricRollRequest;
use App\Http\Requests\Inventory\UpdateFabricRollOutputRequest;
use App\Http\Requests\Inventory\UpdateFabricRollRequest;
use App\Http\Resources\Inventory\FabricRollResource;
use App\Models\Inventory\FabricRoll;
use App\Services\Inventory\FabricRollService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
        return Inertia::render('KardexFabric/Index');
    }

    public function inventoryRolls()
    {
        return Inertia::render('KardexFabric/FabricRollInventory/Index');
    }

    public function inventoryRollsData()
    {
        $rolls = $this->service->export();
        return FabricRollResource::collection($rolls);
    }

    public function reception()
    {
        return Inertia::render('KardexFabric/Reception');
    }

    public function receptionShow($invoiceId)
    {
        return Inertia::render('KardexFabric/Reception', [
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
                $row['colorCode'] ?? '',
                $row['width'] ?? '',
                $row['rollQty'] ?? '',
                $row['partialOutput'] ?? '',
                $row['partialAvailability'] ?? '',
                $row['exitQuantity'] ?? '',
                $row['realAvailability'] ?? '',
                $row['provider'] ?? '',
                $row['orderNumber'] ?? '',
                $row['createdAt'] ?? '',
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

        $fabricRoll = $this->service->update($fabricRoll, $data);

        return response()->json($fabricRoll);
    }

    public function destroy(FabricRoll $fabricRoll)
    {
        $this->service->delete($fabricRoll);
        return response()->noContent();
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
