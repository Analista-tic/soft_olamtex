<?php

namespace App\Http\Controllers\Expenses;

use App\Http\Controllers\Controller;
use App\Models\Expenses\PurchaseInvoice;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class PurchaseInvoiceTelaController extends Controller
{
    /**
     * Muestra la vista principal de facturas de compra telas.
     */

    public function indexFabric()
    {
        return Inertia::render('Expenses/PurchaseInvoices/indexFabric');
    }


    /**
     * Muestra una factura de compra específica.
     */
    public function show($purchaseInvoice)
    {
        try {
            $invoice = PurchaseInvoice::with([
                'contact',
                'purchaseOrder',
                'items.article.warehouses',
                'items.article.categorySubGroup.categoryGroup',
                'items.article.color',
                'items.article.tax'
            ])->findOrFail($purchaseInvoice);

            return response()->json(['data' => $invoice]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'error' => 'Factura no encontrada',
                'message' => "No se encontró la factura con ID: {$purchaseInvoice}"
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error interno del servidor',
                'message' => 'Ocurrió un error al cargar la factura',
                'debug' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Valida y almacena una nueva factura de compra.
     */
    public function store(Request $request)
    {
        $data = $this->validateInvoice($request);

        // Elimina purchase_order_id si no tiene valor válido
        if (empty($data['purchase_order_id'])) {
            unset($data['purchase_order_id']);
        }

        // Asegura que withholdings sea array
        $data['withholdings'] = $data['withholdings'] ?? [];

        $invoice = PurchaseInvoice::create($data);

        // Crea los items asociados
        $this->syncInvoiceItems($invoice, $data['items'] ?? []);

        // Actualiza el estado de la orden de compra si corresponde
        $this->updatePurchaseOrderStatus($data['purchase_order_id'] ?? $request->purchase_order_id);

        return response()->json(['data' => $invoice->load('items', 'contact', 'purchaseOrder')], 201);
    }

    /**
     * Valida y actualiza una factura de compra existente.
     */
    public function update(Request $request, $id)
    {
        $invoice = PurchaseInvoice::findOrFail($id);

        $data = $this->validateInvoice($request, true);

        if (empty($data['purchase_order_id'])) {
            unset($data['purchase_order_id']);
        }
        $data['withholdings'] = $data['withholdings'] ?? [];

        $invoice->update($data);

        // Sincroniza los items de la factura
        $this->syncInvoiceItems($invoice, $data['items'] ?? [], true);

        // Actualiza el estado de la orden de compra si corresponde
        $this->updatePurchaseOrderStatus($data['purchase_order_id'] ?? $request->purchase_order_id);

        return response()->json(['data' => $invoice->load('items', 'contact', 'purchaseOrder')]);
    }

    /**
     * Elimina una factura de compra y sus items asociados.
     */
    public function destroy($id)
    {
        $invoice = PurchaseInvoice::findOrFail($id);
        $invoice->items()->delete();
        $invoice->delete();
        return response()->json(['message' => 'Factura eliminada']);
    }

    /**
     * Devuelve datos paginados de facturas de compra según filtros.
     */
    public function getData(Request $request)
    {
        $query = PurchaseInvoice::with([
            'contact',
            'purchaseOrder',
            'items.article.warehouses',
            'items.article.categorySubGroup.categoryGroup',
            'items.article.color',
            'items.article.tax'
        ]);

        // Filtros dinámicos
        if ($request->filled('search')) {
            $query->whereHas('contact', function ($q) use ($request) {
                $q->where('full_name', 'like', '%' . $request->search . '%');
            });
        }
        if ($request->filled('date_from')) {
            $query->where('date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->where('date', '<=', $request->date_to);
        }
        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }
        if ($request->filled('provider_id') && $request->provider_id !== 'all') {
            $query->where('contact_id', $request->provider_id);
        }

        // Ordena por fecha e id descendente
        $invoices = $query->orderByDesc('date')->orderByDesc('id')->paginate($request->per_page ?? 20);

        return response()->json([
            'data' => $invoices->items(),
            'total' => $invoices->total(),
            'current_page' => $invoices->currentPage()
        ]);
    }

    /**
     * Valida los datos de la factura de compra.
     * @param Request $request
     * @param bool $isUpdate
     * @return array
     */
    private function validateInvoice(Request $request, $isUpdate = false)
    {
        $itemIdRule = $isUpdate ? ['sometimes', 'exists:purchase_invoice_items,id'] : [];
        return $request->validate([
            'contact_id' => 'required|exists:contacts,id',
            'purchase_order_id' => 'nullable|exists:purchase_orders,id',
            'date' => 'required|date',
            'delivery_date' => 'nullable|date',
            'supplier_invoice_number' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'status' => 'nullable|string|max:50',
            'items' => 'array',
            'items.*.id' => $itemIdRule,
            'items.*.article_id' => 'required|exists:articles,id',
            'items.*.price' => 'required|numeric',
            'items.*.tax' => 'nullable|numeric',
            'items.*.quantity' => 'required|numeric',
            'items.*.description' => 'nullable|string',
            'items.*.supplier_code' => 'nullable|string',
            'items.*.supplier_color' => 'nullable|string',
            'items.*.order_width' => 'nullable|string',
            'items.*.total' => 'nullable|numeric',
            'items.*.total_tax' => 'nullable|numeric',
            'withholdings' => 'nullable|array',
            'withholdings.*.name' => 'required_with:withholdings|string',
            'withholdings.*.percentage' => 'required_with:withholdings|numeric',
            'withholdings.*.base_amount' => 'required_with:withholdings|numeric',
        ]);
    }

    /**
     * Sincroniza los items de la factura (crea, actualiza y elimina según corresponda).
     * @param PurchaseInvoice $invoice
     * @param array $items
     * @param bool $isUpdate
     */
    private function syncInvoiceItems($invoice, $items, $isUpdate = false)
    {
        if (!$isUpdate) {
            // Solo crear (caso store)
            foreach ($items as $item) {
                $invoice->items()->create($item);
            }
            return;
        }

        // Actualizar, crear o eliminar (caso update)
        $existingItems = $invoice->items()->get()->keyBy('id');
        $incomingItems = collect($items);

        // Actualizar o crear items
        foreach ($incomingItems as $itemData) {
            if (!empty($itemData['id']) && $existingItems->has($itemData['id'])) {
                $existingItems[$itemData['id']]->update($itemData);
            } else {
                $invoice->items()->create($itemData);
            }
        }

        // Eliminar items que no tengan rolls asociados
        $incomingIds = $incomingItems->pluck('id')->filter()->all();
        foreach ($existingItems as $item) {
            if (!in_array($item->id, $incomingIds)) {
                // Solo elimina si no tiene rolls asociados
                if ($item->fabricRolls()->count() == 0) {
                    $item->delete();
                }
                // Si tiene rolls asociados, no se elimina
            }
        }
    }

    /**
     * Actualiza el estado de la orden de compra asociada a 'invoiced' si corresponde.
     * @param mixed $purchaseOrderId
     */
    private function updatePurchaseOrderStatus($purchaseOrderId)
    {
        if (!empty($purchaseOrderId)) {
            $order = \App\Models\Expenses\PurchaseOrder::find($purchaseOrderId);
            if ($order) {
                $order->status = 'invoiced'; // Usa el valor permitido por tu ENUM
                $order->save();
            }
        }
    }
}
