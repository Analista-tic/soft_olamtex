<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Inventory\FabricRoll;
use App\Models\Expenses\PurchaseInvoice;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Validation\ValidationException;

class FabricRollController extends Controller
{
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
        $fabricRoll = FabricRoll::with(['purchaseInvoiceItem.article'])->findOrFail($id);
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
        $query = FabricRoll::with(['purchaseInvoiceItem.article']);

        // Filtro por búsqueda general
        if ($request->has('search') && trim($request->search) !== '') {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('roll_number', 'like', "%$search%")
                    ->orWhereHas('purchaseInvoiceItem.article', function ($article) use ($search) {
                        $article->where('description', 'like', "%$search%")
                                ->orWhere('reference', 'like', "%$search%");
                    });
            });
        }

        // Filtro por estado (disponible o agotado)
        if ($request->has('status') && $request->status !== '') {
            if ($request->status === 'available') {
                $query->whereRaw('quantity > actual_output');
            } elseif ($request->status === 'depleted') {
                $query->whereRaw('quantity <= actual_output');
            }
        }

        // Filtro por fecha de entrada
        if ($request->has('date_from') && $request->date_from) {
            $query->whereDate('entry_date', '>=', $request->date_from);
        }

        if ($request->has('date_to') && $request->date_to) {
            $query->whereDate('entry_date', '<=', $request->date_to);
        }

        $perPage = $request->input('per_page', 10);
        $fabricRolls = $query->orderBy('entry_date', 'desc')->paginate($perPage);

        return response()->json($fabricRolls);
    }

    public function store(Request $request)
    {
        try {
            $data = $request->validate([
                'roll_number' => 'required|string|max:255|unique:fabric_rolls,roll_number',
                'purchase_invoice_item_id' => 'required|integer|exists:purchase_invoice_items,id',
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

        // Valores por defecto
        $data['partial_output'] = $data['partial_output'] ?? 0;
        $data['actual_output'] = $data['actual_output'] ?? 0;
        $data['roll_width'] = $data['roll_width'] ?? 0;

        // Validación de salidas
        if ($data['actual_output'] > $data['quantity']) {
            return response()->json([
                'message' => 'La salida real no puede ser mayor que la cantidad del rollo',
                'errors' => ['actual_output' => ['La salida real no puede ser mayor que la cantidad del rollo']]
            ], 422);
        }

        $fabricRoll = FabricRoll::create($data);
        $fabricRoll->load('purchaseInvoiceItem.article');

        return response()->json([
            'message' => 'Rollo creado correctamente',
            'data' => $fabricRoll
        ], 201);
    }

    public function show($id)
    {
        $fabricRoll = FabricRoll::with(['purchaseInvoiceItem.article'])->findOrFail($id);
        return response()->json($fabricRoll);
    }

    public function update(Request $request, $id)
    {
        $fabricRoll = FabricRoll::findOrFail($id);

        try {
            $data = $request->validate([
                'roll_number' => 'required|string|max:255|unique:fabric_rolls,roll_number,' . $fabricRoll->id,
                'purchase_invoice_item_id' => 'required|integer|exists:purchase_invoice_items,id',
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

        $data['partial_output'] = $data['partial_output'] ?? 0;
        $data['actual_output'] = $data['actual_output'] ?? 0;

        if ($data['actual_output'] > $data['quantity']) {
            return response()->json([
                'message' => 'La salida real no puede ser mayor que la cantidad del rollo',
                'errors' => ['actual_output' => ['La salida real no puede ser mayor que la cantidad del rollo']]
            ], 422);
        }

        $fabricRoll->update($data);
        $fabricRoll->load('purchaseInvoiceItem.article');

        return response()->json($fabricRoll);
    }

    public function destroy($id)
    {
        $fabricRoll = FabricRoll::findOrFail($id);
        $fabricRoll->delete();
        return response()->noContent();
    }

    public function updateOutput(Request $request, $id)
    {
        $fabricRoll = FabricRoll::findOrFail($id);

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

        $fabricRoll->update($data);
        $fabricRoll->load('purchaseInvoiceItem.article');

        return response()->json([
            'message' => 'Salidas actualizadas correctamente',
            'data' => $fabricRoll
        ]);
    }

    public function approveReception($invoiceId)
    {
        try {
            $invoice = PurchaseInvoice::findOrFail($invoiceId);
            $invoice->update(['status' => 'approved']);
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
            $invoice = PurchaseInvoice::findOrFail($invoiceId);
            $invoice->update(['status' => 'rejected']);
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
                'purchase_invoice_item_id' => 'required|integer|exists:purchase_invoice_items,id',
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

        $data['partial_output'] = $data['partial_output'] ?? 0;
        $data['actual_output'] = $data['actual_output'] ?? 0;
        $data['roll_width'] = $data['roll_width'] ?? 0;

        if ($data['actual_output'] > $data['quantity']) {
            return response()->json([
                'message' => 'La salida real no puede ser mayor que la cantidad del rollo',
                'errors' => ['actual_output' => ['La salida real no puede ser mayor que la cantidad del rollo']]
            ], 422);
        }

        try {
            $fabricRoll = FabricRoll::create($data);
            $fabricRoll->load('purchaseInvoiceItem.article');

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
        $rolls = FabricRoll::where('purchase_invoice_item_id', $itemId)->orderBy('entry_date')->get();
        return response()->json($rolls);
    }
}
