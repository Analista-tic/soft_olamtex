<?php

namespace App\Http\Controllers\Expenses\Concerns;

use App\Models\Expenses\PurchaseDocument;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

trait ValidatesPurchaseInvoiceRequest
{
    protected function validateInvoiceRequest(Request $request, bool $isUpdate = false): array
    {
        $itemIdRule = $isUpdate ? ['sometimes', 'exists:purchase_document_items,id'] : [];
        $parentOrderRule = Rule::exists('purchase_documents', 'id')
            ->where(fn ($query) => $query->where('document_type', PurchaseDocument::TYPE_ORDER));

        $validated = $request->validate([
            'contact_id' => 'nullable|exists:contacts,id',
            'provider_name' => $isUpdate ? 'sometimes|required_without:contact_id|string|max:255' : 'required_without:contact_id|string|max:255',
            'provider_phone' => 'nullable|string|max:50',
            'parent_document_id' => ['nullable', $parentOrderRule],
            'purchase_order_id' => ['nullable', $parentOrderRule],
            'date' => 'required|date',
            'delivery_date' => 'nullable|date',
            'supplier_invoice_number' => ($isUpdate ? 'sometimes|' : '') . 'required|string|max:255',
            'description' => 'nullable|string',
            'reason' => 'nullable|string',
            'difference_reason' => 'nullable|string',
            'evidence_path' => 'nullable|string',
            'difference_evidence_path' => 'nullable|string',
            'status' => 'nullable|string|max:50',
            'items' => 'array',
            'items.*.id' => $itemIdRule,
            'items.*.item_id' => 'required|exists:items,id',
            'items.*.price' => 'required|numeric|decimal:0,2',
            'items.*.tax' => 'nullable|numeric',
            'items.*.quantity' => 'required|numeric|decimal:0,2',
            'items.*.description' => 'nullable|string',
            'items.*.supplier_code' => 'nullable|string',
            'items.*.supplier_color' => 'nullable|string',
            'items.*.order_width' => 'nullable|string',
            'items.*.total' => 'nullable|numeric',
            'items.*.total_tax' => 'nullable|numeric',
            'withholdings' => 'nullable|array',
            'withholdings.*.name' => 'required_with:withholdings|string',
            'withholdings.*.percentage' => 'required_with:withholdings|numeric',
            // base_amount es opcional: puede ser explícita o asumida del subtotal de la factura
            'withholdings.*.base_amount' => 'nullable|numeric',
        ]);

        // Normalizar: el frontend puede enviar purchase_order_id; el backend usa parent_document_id.
        $validated['parent_document_id'] = $validated['parent_document_id']
            ?? $validated['purchase_order_id']
            ?? null;
        unset($validated['purchase_order_id']);

        $this->ensureFabricItemsHaveSupplierData($validated['items'] ?? []);
        $this->ensureSupplierInvoiceNumberIsUnique($request, $validated);

        return $validated;
    }

    /**
     * El número de factura del proveedor no puede repetirse para el mismo proveedor (facturas normales y de tela,
     * sin contar las eliminadas). Al editar se ignora la propia factura y se completan los datos que no vengan.
     */
    protected function ensureSupplierInvoiceNumberIsUnique(Request $request, array $validated): void
    {
        if (! array_key_exists('supplier_invoice_number', $validated) && ! array_key_exists('contact_id', $validated)) {
            return;
        }

        $current = $request->route('purchaseInvoice') ?? $request->route('purchaseDocument');
        $current = $current instanceof PurchaseDocument ? $current : null;

        $number = trim((string) ($validated['supplier_invoice_number'] ?? $current?->supplier_invoice_number ?? ''));
        $contactId = $validated['contact_id'] ?? $current?->contact_id;

        if ($number === '' || $contactId === null) {
            return;
        }

        $duplicate = PurchaseDocument::query()
            ->where('document_type', PurchaseDocument::TYPE_INVOICE)
            ->where('contact_id', $contactId)
            ->whereRaw('LOWER(TRIM(supplier_invoice_number)) = ?', [mb_strtolower($number)])
            ->when($current, fn ($query) => $query->where('id', '!=', $current->id))
            ->first(['id', 'document_number']);

        if ($duplicate) {
            throw ValidationException::withMessages([
                'supplier_invoice_number' => ["Este proveedor ya tiene registrada una factura con el número {$number} ({$duplicate->document_number})."],
            ]);
        }
    }

    /**
     * Valida que la orden de compra vinculada sea compatible con el tipo de factura.
     *
     * @param  int|null  $parentDocumentId  ID de la orden (parent_document_id)
     * @param  bool      $fabricOnly        true = factura de tela, false = factura normal
     */
    protected function validateOrderFabricCompatibility(?int $parentDocumentId, bool $fabricOnly): void
    {
        if ($parentDocumentId === null) {
            return;
        }

        $order = PurchaseDocument::with('items.item.categorySubGroup')
            ->where('id', $parentDocumentId)
            ->where('document_type', PurchaseDocument::TYPE_ORDER)
            ->first();

        if (!$order) {
            return;
        }

        $hasFabricItems = $order->items->contains(
            fn ($item) => strtolower($item->item?->categorySubGroup?->name ?? '') === 'tela'
        );

        if ($fabricOnly && !$hasFabricItems) {
            throw ValidationException::withMessages([
                'purchase_order_id' => ['La orden seleccionada no contiene ítems de tela y no puede usarse en una Factura de Compra Tela.'],
            ]);
        }

        if (!$fabricOnly && $hasFabricItems) {
            throw ValidationException::withMessages([
                'purchase_order_id' => ['La orden seleccionada contiene ítems de tela y no puede usarse en una Factura de Compra normal.'],
            ]);
        }
    }
}
