<?php

namespace App\Http\Controllers\Expenses;

use App\Http\Controllers\Controller;
use App\Models\Expenses\PurchaseInvoice;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PurchaseInvoiceController extends Controller
{
    public function index()
    {
        return Inertia::render('Expenses/PurchaseInvoices/Index');
    }

    public function show($id)
    {
        $invoice = PurchaseInvoice::with('contact', 'items')->findOrFail($id);
        return response()->json(['data' => $invoice]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'contact_id' => 'required|exists:contacts,id',
            'purchase_order_id' => 'nullable|exists:purchase_orders,id',
            'date' => 'required|date',
            'delivery_date' => 'nullable|date',
            'supplier_invoice_number' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'status' => 'nullable|string|max:50',
            'items' => 'array',
            'items.*.article_id' => 'required|exists:articles,id',
            'items.*.price' => 'required|numeric',
            'items.*.tax' => 'nullable|numeric',
            'items.*.quantity' => 'required|numeric',
            'items.*.description' => 'nullable|string',
            'items.*.total' => 'nullable|numeric',
            'items.*.total_tax' => 'nullable|numeric',
        ]);

        // Solo incluye purchase_order_id si tiene valor válido
        if (empty($data['purchase_order_id'])) {
            unset($data['purchase_order_id']);
        }

        $invoice = PurchaseInvoice::create($data);

        if (!empty($data['items'])) {
            foreach ($data['items'] as $item) {
                $invoice->items()->create($item);
            }
        }

        // Actualiza el estado de la orden de compra si corresponde
        if (!empty($data['purchase_order_id'] ?? $request->purchase_order_id)) {
            $orderId = $data['purchase_order_id'] ?? $request->purchase_order_id;
            $order = \App\Models\Expenses\PurchaseOrder::find($orderId);
            if ($order) {
                $order->status = 'invoiced'; // Usa el valor permitido por tu ENUM
                $order->save();
            }
        }

        return response()->json(['data' => $invoice->load('items', 'contact')], 201);
    }

    public function update(Request $request, $id)
    {
        $invoice = PurchaseInvoice::findOrFail($id);

        $data = $request->validate([
            'contact_id' => 'required|exists:contacts,id',
            'purchase_order_id' => 'nullable|exists:purchase_orders,id',
            'date' => 'required|date',
            'delivery_date' => 'nullable|date',
            'supplier_invoice_number' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'status' => 'nullable|string|max:50',
            'items' => 'array',
            'items.*.id' => 'sometimes|exists:purchase_invoice_items,id',
            'items.*.article_id' => 'required|exists:articles,id',
            'items.*.price' => 'required|numeric',
            'items.*.tax' => 'nullable|numeric',
            'items.*.quantity' => 'required|numeric',
            'items.*.description' => 'nullable|string',
            'items.*.total' => 'nullable|numeric',
            'items.*.total_tax' => 'nullable|numeric',
        ]);

        // Solo incluye purchase_order_id si tiene valor válido
        if (empty($data['purchase_order_id'])) {
            unset($data['purchase_order_id']);
        }

        $invoice->update($data);

        // Actualizar items (simple: borrar y volver a crear)
        $invoice->items()->delete();
        if (!empty($data['items'])) {
            foreach ($data['items'] as $item) {
                $invoice->items()->create($item);
            }
        }

        // Actualiza el estado de la orden de compra si corresponde
        if (!empty($data['purchase_order_id'] ?? $request->purchase_order_id)) {
            $orderId = $data['purchase_order_id'] ?? $request->purchase_order_id;
            $order = \App\Models\Expenses\PurchaseOrder::find($orderId);
            if ($order) {
                $order->status = 'invoiced'; // Usa el valor permitido por tu ENUM
                $order->save();
            }
        }

        return response()->json(['data' => $invoice->load('items', 'contact')]);
    }

    public function destroy($id)
    {
        $invoice = PurchaseInvoice::findOrFail($id);
        $invoice->items()->delete();
        $invoice->delete();
        return response()->json(['message' => 'Factura eliminada']);
    }

    public function getData(Request $request)
    {
        $query = PurchaseInvoice::with('contact', 'items');
        if ($request->has('search') && $request->search) {
            $query->whereHas('contact', function ($q) use ($request) {
                $q->where('full_name', 'like', '%' . $request->search . '%');
            });
        }
        if ($request->has('date_from') && $request->date_from) {
            $query->where('date', '>=', $request->date_from);
        }
        if ($request->has('date_to') && $request->date_to) {
            $query->where('date', '<=', $request->date_to);
        }
        if ($request->has('status') && $request->status && $request->status !== 'all') {
            $query->where('status', $request->status);
        }
        if ($request->has('provider_id') && $request->provider_id && $request->provider_id !== 'all') {
            $query->where('contact_id', $request->provider_id);
        }
        $invoices = $query->orderByDesc('date')->paginate($request->per_page ?? 20);
        return response()->json([
            'data' => $invoices->items(),
            'total' => $invoices->total(),
            'current_page' => $invoices->currentPage()
        ]);
    }
}
