<?php

namespace App\Http\Controllers\Expenses;

use App\Http\Controllers\Controller;
use App\Models\Expenses\PurchaseOrder;
use App\Models\Expenses\PurchaseOrderItem;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PurchaseOrderController extends Controller
{
    public function index()
    {
        return Inertia::render('Expenses/PurchaseOrders/Index');
    }

    public function getData(Request $request)
    {
        $query = PurchaseOrder::with(['contact', 'items.article']);

        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where('supplier_order_number', 'like', "%$search%");
        }

        // FILTRO POR ESTADO
        if ($request->has('status') && $request->status && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // FILTRO POR PROVEEDOR
        if ($request->has('provider_id') && $request->provider_id && $request->provider_id !== 'all') {
            $query->where('contact_id', $request->provider_id);
        }

        // FILTRO POR RANGO DE FECHAS
        if ($request->has('date_from') && $request->date_from) {
            $query->whereDate('date', '>=', $request->date_from);
        }
        if ($request->has('date_to') && $request->date_to) {
            $query->whereDate('date', '<=', $request->date_to);
        }

        $orders = $query->orderBy('id', 'desc')->paginate($request->input('per_page', 10));

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
            'items.*.article_id' => 'required|exists:articles,id',
            'items.*.price' => 'required|numeric',
            'items.*.tax' => 'required|numeric',
            'items.*.quantity' => 'required|numeric',
            'items.*.description' => 'nullable|string',
            'items.*.total' => 'required|numeric',
            'status' => 'sometimes|in:pending,converted',
        ]);

        $orderData = [
            'contact_id' => $validated['contact_id'],
            'date' => $validated['date'],
            'delivery_date' => $validated['delivery_date'],
            'supplier_order_number' => $validated['supplier_order_number'],
            'description' => $validated['description'] ?? null,
        ];

        $order = PurchaseOrder::create($orderData);

        foreach ($validated['items'] as $item) {
            $order->items()->create([
                'article_id' => $item['article_id'],
                'price' => $item['price'],
                'tax' => $item['tax'],
                'quantity' => $item['quantity'],
                'description' => $item['description'] ?? null,
                'total' => $item['total'],
            ]);
        }

        return response()->json([
            'message' => 'Purchase order created.',
            'data' => $order->load('items.article')
        ], 201);
    }

    public function show(PurchaseOrder $purchaseOrder)
    {
        $purchaseOrder->load(['contact', 'items.article']);

        return response()->json($purchaseOrder);
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
            'items.*.id' => 'sometimes|exists:purchase_order_items,id',
            'items.*.article_id' => 'required|exists:articles,id',
            'items.*.price' => 'required|numeric',
            'items.*.tax' => 'required|numeric',
            'items.*.quantity' => 'required|numeric',
            'items.*.description' => 'nullable|string',
            'items.*.total' => 'required|numeric',
            'status' => 'sometimes|in:pending,converted',
        ]);

        $orderData = [
            'contact_id' => $validated['contact_id'],
            'date' => $validated['date'],
            'delivery_date' => $validated['delivery_date'],
            'supplier_order_number' => $validated['supplier_order_number'],
            'description' => $validated['description'] ?? null,
        ];

        $purchaseOrder->update($orderData);

        $purchaseOrder->items()->delete();
        foreach ($validated['items'] as $item) {
            $purchaseOrder->items()->create([
                'article_id' => $item['article_id'],
                'price' => $item['price'],
                'tax' => $item['tax'],
                'quantity' => $item['quantity'],
                'description' => $item['description'] ?? null,
                'total' => $item['total'],
            ]);
        }

        return response()->json([
            'message' => 'Purchase order updated.',
            'data' => $purchaseOrder->load('items.article')
        ]);
    }

    public function destroy(PurchaseOrder $purchaseOrder)
    {
        $purchaseOrder->items()->delete();
        $purchaseOrder->delete();

        return response()->json(['message' => 'Purchase order deleted.']);
    }
}
