<?php

namespace App\Http\Controllers\Expenses;

use App\Http\Controllers\Controller;
use App\Models\Expenses\PurchaseOrder;
use App\Services\Expenses\PurchaseOrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class PurchaseOrderController extends Controller
{
    public function __construct(protected PurchaseOrderService $service) {}

    public function index()
    {
        return Inertia::render('Expenses/PurchaseOrders/Index');
    }

    public function getData(Request $request)
    {
        $filters = $request->only(['search', 'status', 'provider_id', 'date_from', 'date_to']);
        $orders = $this->service->paginate($filters, $request->input('per_page', 10));

        return response()->json($orders);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'contact_id' => 'required|exists:contacts,id',
            'date' => 'required|date',
            'delivery_date' => 'required|date',
            'supplier_order_number' => 'required|string|max:255',
            'description' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|exists:items,id',
            'items.*.price' => 'required|numeric',
            'items.*.tax' => 'required|numeric',
            'items.*.quantity' => 'required|numeric',
            'items.*.description' => 'nullable|string',
            'items.*.supplier_code' => 'nullable|string',
            'items.*.supplier_color' => 'nullable|string',
            'items.*.order_width' => 'nullable|string',
            'items.*.total' => 'required|numeric',
            'status' => 'sometimes|in:pending,invoiced',
        ]);

        $order = $this->service->create($validated);

        return response()->json([
            'message' => 'Purchase order created.',
            'data' => $order,
        ], 201);
    }

    public function show(PurchaseOrder $purchaseOrder)
    {
        return response()->json($this->service->loadWithRelations($purchaseOrder));
    }

    public function update(Request $request, PurchaseOrder $purchaseOrder)
    {
        $validated = $request->validate([
            'contact_id' => 'required|exists:contacts,id',
            'date' => 'required|date',
            'delivery_date' => 'required|date',
            'supplier_order_number' => 'required|string|max:255',
            'description' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|exists:items,id',
            'items.*.price' => 'required|numeric',
            'items.*.tax' => 'required|numeric',
            'items.*.quantity' => 'required|numeric',
            'items.*.description' => 'nullable|string',
            'items.*.supplier_code' => 'nullable|string',
            'items.*.supplier_color' => 'nullable|string',
            'items.*.order_width' => 'nullable|string',
            'items.*.total' => 'required|numeric',
            'status' => 'sometimes|in:pending,invoiced',
        ]);

        $order = $this->service->update($purchaseOrder, $validated);

        return response()->json([
            'message' => 'Purchase order updated.',
            'data' => $order,
        ]);
    }

    public function destroy(PurchaseOrder $purchaseOrder)
    {
        $this->service->delete($purchaseOrder);

        return response()->json(['message' => 'Purchase order deleted.']);
    }

    public function enviarCorreoPdf(PurchaseOrder $purchaseOrder)
    {
        try {
            $this->service->sendPdf($purchaseOrder);
            return response()->json(['message' => 'Correo enviado correctamente.']);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            Log::error('Error enviando correo de orden de compra: ' . $e->getMessage());
            return response()->json(['message' => 'Error enviando el correo: ' . $e->getMessage()], 500);
        }
    }

    public function convertToInvoice(PurchaseOrder $purchaseOrder)
    {
        try {
            $invoice = $this->service->convertToInvoice($purchaseOrder);
            return response()->json([
                'message' => 'Purchase order successfully converted to invoice.',
                'data' => $invoice,
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'error_code' => 'ORDER_ALREADY_INVOICED',
            ], 422);
        }
    }
}
