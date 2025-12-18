<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Expenses\PurchaseInvoice;
use App\Services\Inventory\FabricRollService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Validation\ValidationException;

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

        return response()->json($fabricRolls);
    }

    public function store(Request $request)
    {
        try {
            $data = $request->validate([
                'roll_number' => 'required|string|max:255|unique:fabric_rolls,roll_number',
                'purchase_document_item_id' => 'required|integer|exists:purchase_document_items,id',
                'quantity' => 'required|numeric|min:0.01',
                'roll_width' => 'nullable|numeric|min:0',
                'location' => 'nullable|string|max:255',
                'description' => 'nullable|string',
                'entry_date' => 'required|date',
                'partial_output' => 'nullable|numeric|min:0',
                'actual_output' => 'nullable|numeric|min:0',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Errores de validación',
                'errors' => $e->errors(),
            ], 422);
        }

        if (($data['actual_output'] ?? 0) > $data['quantity']) {
            return response()->json([
                'message' => 'La salida real no puede ser mayor que la cantidad del rollo',
                'errors' => ['actual_output' => ['La salida real no puede ser mayor que la cantidad del rollo']]
            ], 422);
        }

        $fabricRoll = $this->service->create($data);

        return response()->json([
            'message' => 'Rollo creado correctamente',
            'data' => $fabricRoll
        ], 201);
    }

    public function show($id)
    {
        $fabricRoll = $this->service->findOrFail($id);
        return response()->json($fabricRoll);
    }

    public function update(Request $request, $id)
    {
        $fabricRoll = $this->service->findOrFail($id);

        try {
            $data = $request->validate([
                'roll_number' => 'required|string|max:255|unique:fabric_rolls,roll_number,' . $fabricRoll->id,
                'purchase_document_item_id' => 'required|integer|exists:purchase_document_items,id',
                'quantity' => 'required|numeric|min:0.01',
                'partial_output' => 'nullable|numeric|min:0',
                'actual_output' => 'nullable|numeric|min:0',
                'entry_date' => 'required|date',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Errores de validación',
                'errors' => $e->errors(),
            ], 422);
        }

        if (($data['actual_output'] ?? 0) > $data['quantity']) {
            return response()->json([
                'message' => 'La salida real no puede ser mayor que la cantidad del rollo',
                'errors' => ['actual_output' => ['La salida real no puede ser mayor que la cantidad del rollo']]
            ], 422);
        }

        $fabricRoll = $this->service->update($fabricRoll, $data);

        return response()->json($fabricRoll);
    }

    public function destroy($id)
    {
        $fabricRoll = $this->service->findOrFail($id);
        $this->service->delete($fabricRoll);
        return response()->noContent();
    }

    public function updateOutput(Request $request, $id)
    {
        $fabricRoll = $this->service->findOrFail($id);

        $data = $request->validate([
            'partial_output' => 'nullable|numeric|min:0',
            'actual_output' => 'nullable|numeric|min:0',
        ]);

        $actualOutput = $data['actual_output'] ?? $fabricRoll->actual_output;
        if ($actualOutput > $fabricRoll->quantity) {
            return response()->json([
                'message' => 'La salida real no puede ser mayor que la cantidad del rollo',
                'errors' => ['actual_output' => ['La salida real no puede ser mayor que la cantidad del rollo']]
            ], 422);
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

    public function storeFromReception(Request $request)
    {
        try {
            $data = $request->validate([
                'roll_number' => 'required|string|max:255|unique:fabric_rolls,roll_number',
                'purchase_document_item_id' => 'required|integer|exists:purchase_document_items,id',
                'quantity' => 'required|numeric|min:0.01',
                'roll_width' => 'nullable|numeric|min:0',
                'location' => 'nullable|string|max:255',
                'description' => 'nullable|string',
                'entry_date' => 'required|date',
                'partial_output' => 'nullable|numeric|min:0',
                'actual_output' => 'nullable|numeric|min:0',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Errores de validación',
                'errors' => $e->errors(),
            ], 422);
        }

        if (($data['actual_output'] ?? 0) > $data['quantity']) {
            return response()->json([
                'message' => 'La salida real no puede ser mayor que la cantidad del rollo',
                'errors' => ['actual_output' => ['La salida real no puede ser mayor que la cantidad del rollo']]
            ], 422);
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

    // Nuevo método para obtener rollos por item de factura
    public function getByInvoiceItem($itemId)
    {
        $rolls = $this->service->getByInvoiceItem($itemId);
        return response()->json($rolls);
    }
}
