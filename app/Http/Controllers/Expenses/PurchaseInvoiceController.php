<?php

namespace App\Http\Controllers\Expenses;

use App\Http\Controllers\Controller;
use App\Models\Expenses\PurchaseInvoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class PurchaseInvoiceController extends Controller
{
    /**
     * Muestra la vista principal de facturas de compra.
     */
    public function index()
    {
        return Inertia::render('Expenses/PurchaseInvoices/Index');
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
                'items.item.warehouses',
                'items.item.categorySubGroup.categoryGroup',
                'items.item.tax'
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

        $data['is_fabric'] = false;

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
        $data['is_fabric'] = false;

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
        $this->handleDeletedPurchaseOrder($invoice->purchase_order_id);
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
            'items.item.warehouses',
            'items.item.categorySubGroup.categoryGroup',
            'items.item.tax'
        ])
        ->where('is_fabric', false);

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
        $validated = $request->validate([
            'contact_id' => 'required|exists:contacts,id',
            'purchase_order_id' => 'nullable|exists:purchase_orders,id',
            'date' => 'required|date',
            'delivery_date' => 'nullable|date',
            'supplier_invoice_number' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'reason' => 'nullable|string',
            'evidence_path' => 'nullable|string',
            'difference_reason' => 'nullable|string',
            'difference_evidence_path' => 'nullable|string',
            'status' => 'nullable|string|max:50',
            'items' => 'array',
            'items.*.id' => $itemIdRule,
            'items.*.item_id' => 'required|exists:items,id',
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

        if (array_key_exists('difference_reason', $validated)) {
            if (!empty($validated['difference_reason'])) {
                $validated['reason'] = $validated['difference_reason'];
            }
            unset($validated['difference_reason']);
        }

        if (array_key_exists('difference_evidence_path', $validated)) {
            $validated['evidence_path'] = $this->prepareEvidencePath($validated['difference_evidence_path']);
            unset($validated['difference_evidence_path']);
        }

        if (array_key_exists('evidence_path', $validated)) {
            $validated['evidence_path'] = $this->prepareEvidencePath($validated['evidence_path']);
        }

        return $validated;
    }

    private function prepareEvidencePath(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim($value);
        if ($normalized === '') {
            return null;
        }

        if (Str::startsWith($normalized, 'data:image/')) {
            return $this->storeEvidenceFromBase64($normalized);
        }

        return $normalized;
    }

    private function storeEvidenceFromBase64(string $value): string
    {
        if (!preg_match('/^data:(image\/[a-zA-Z0-9.+-]+);base64,/', $value, $matches)) {
            throw ValidationException::withMessages([
                'difference_evidence_path' => 'La evidencia proporcionada no tiene un formato válido.',
            ]);
        }

        $payload = substr($value, strpos($value, ',') + 1);
        $decoded = base64_decode($payload);
        if ($decoded === false) {
            throw ValidationException::withMessages([
                'difference_evidence_path' => 'No se pudo decodificar la evidencia.',
            ]);
        }

        $extensionMap = [
            'image/jpeg' => 'jpg',
            'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ];

        $extension = $extensionMap[$matches[1]] ?? 'png';
        $path = sprintf('purchase-invoices/evidence/%s/%s.%s', now()->format('Y/m/d'), Str::uuid(), $extension);

        Storage::disk('public')->put($path, $decoded);

        return $path;
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

    private function handleDeletedPurchaseOrder(?int $purchaseOrderId)
    {
        if (empty($purchaseOrderId)) {
            return;
        }

        $order = \App\Models\Expenses\PurchaseOrder::find($purchaseOrderId);
        if (!$order) {
            return;
        }

        $hasActiveInvoices = PurchaseInvoice::where('purchase_order_id', $purchaseOrderId)
            ->whereNull('deleted_at')
            ->exists();

        if (!$hasActiveInvoices) {
            $order->status = 'pending';
            $order->save();
        }
    }
}
