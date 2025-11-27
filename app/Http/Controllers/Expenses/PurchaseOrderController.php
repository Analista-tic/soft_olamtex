<?php

namespace App\Http\Controllers\Expenses;

use App\Http\Controllers\Controller;
use App\Models\Expenses\PurchaseOrder;
use App\Models\Expenses\PurchaseOrderItem;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Mail;
use Barryvdh\DomPDF\Facade\Pdf as DomPDF;
use App\Mail\PurchaseOrderMail;

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
        if ($request->has('status') && $request->status && strtolower($request->status) !== 'all') {
            $query->where('status', strtolower($request->status));
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

        // Ordenar por fecha descendente y luego por id descendente
        $orders = $query->orderByDesc('date')->orderByDesc('id')->paginate($request->input('per_page', 10));

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
            'items.*.supplier_code' => 'nullable|string',
            'items.*.supplier_color' => 'nullable|string',
            'items.*.order_width' => 'nullable|string',
            'items.*.total' => 'required|numeric',
            'status' => 'sometimes|in:pending,invoiced',
        ]);

        $orderData = [
            'contact_id' => $validated['contact_id'],
            'date' => $validated['date'],
            'delivery_date' => $validated['delivery_date'],
            'supplier_order_number' => $validated['supplier_order_number'],
            'description' => $validated['description'] ?? null,
            'status' => $validated['status'] ?? 'pending', // <-- Valor por defecto
        ];

        $order = PurchaseOrder::create($orderData);

        foreach ($validated['items'] as $item) {
            $order->items()->create([
                'article_id' => $item['article_id'],
                'price' => $item['price'],
                'tax' => $item['tax'],
                'quantity' => $item['quantity'],
                'description' => $item['description'] ?? null,
                'supplier_code' => $item['supplier_code'] ?? null,
                'supplier_color' => $item['supplier_color'] ?? null,
                'order_width' => $item['order_width'] ?? null,
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
            'items.*.article_id' => 'required|exists:articles,id',
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

        $orderData = [
            'contact_id' => $validated['contact_id'],
            'date' => $validated['date'],
            'delivery_date' => $validated['delivery_date'],
            'supplier_order_number' => $validated['supplier_order_number'],
            'description' => $validated['description'] ?? null,
            'status' => $validated['status'] ?? 'pending', // <-- Valor por defecto
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
                'supplier_code' => $item['supplier_code'] ?? null,
                'supplier_color' => $item['supplier_color'] ?? null,
                'order_width' => $item['order_width'] ?? null,
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

    public function enviarCorreoPdf(PurchaseOrder $purchaseOrder)
    {
        $purchaseOrder->load(['contact', 'items.article']);

        // Validar que el proveedor tenga email
        if (empty($purchaseOrder->contact->email)) {
            return response()->json(['message' => 'El proveedor no tiene correo electrónico registrado.'], 422);
        }

        // Generar PDF
        $pdf = DomPDF::loadView('pdf.purchase_order', ['order' => $purchaseOrder]);

        try {
            Mail::to($purchaseOrder->contact->email)
                ->send(new PurchaseOrderMail($purchaseOrder, $pdf->output()));

            // Marcar como enviado
            $purchaseOrder->email_sent = true;
            $purchaseOrder->save();

            return response()->json(['message' => 'Correo enviado correctamente.']);
        } catch (\Exception $e) {
            \Log::error('Error enviando correo de orden de compra: ' . $e->getMessage());
            return response()->json(['message' => 'Error enviando el correo: ' . $e->getMessage()], 500);
        }
    }

    public function convertToInvoice(PurchaseOrder $purchaseOrder)
    {
        $purchaseOrder->load(['contact', 'items', 'invoice']); // <-- Asegura que la relación 'invoice' esté cargada

        // Check if already has an associated invoice
        if ($purchaseOrder->invoice) {
            return response()->json([
                'message' => 'Esta orden de compra ya tiene una factura asociada.',
                'error_code' => 'ORDER_ALREADY_INVOICED'
            ], 422);
        }

        // Create the purchase invoice
        $invoice = \App\Models\Expenses\PurchaseInvoice::create([
            'contact_id' => $purchaseOrder->contact_id,
            'purchase_order_id' => $purchaseOrder->id,
            'date' => $purchaseOrder->date,
            'delivery_date' => $purchaseOrder->delivery_date,
            'supplier_invoice_number' => $purchaseOrder->supplier_order_number,
            'description' => $purchaseOrder->description,
            'status' => 'pending',
        ]);

        // Copy items
        foreach ($purchaseOrder->items as $item) {
            $invoice->items()->create([
                'article_id' => $item->article_id,
                'price' => $item->price,
                'tax' => $item->tax,
                'quantity' => $item->quantity,
                'description' => $item->description,
                'supplier_code' => $item->supplier_code,
                'supplier_color' => $item->supplier_color,
                'order_width' => $item->order_width,
                'total' => $item->total,
                'total_tax' => isset($item->total_tax) ? $item->total_tax : 0,
            ]);
        }

        // Cambia el status a 'invoiced' en vez de 'converted'
        $purchaseOrder->status = 'invoiced';
        $purchaseOrder->save();

        return response()->json([
            'message' => 'Purchase order successfully converted to invoice.',
            'data' => $invoice->load('items', 'contact', 'purchaseOrder')
        ]);
    }
}
