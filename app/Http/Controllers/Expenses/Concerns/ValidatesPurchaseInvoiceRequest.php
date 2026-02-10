<?php

namespace App\Http\Controllers\Expenses\Concerns;

use App\Models\Expenses\PurchaseDocument;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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
            'supplier_invoice_number' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'reason' => 'nullable|string',
            'difference_reason' => 'nullable|string',
            'evidence_path' => 'nullable|string',
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
            // base_amount es opcional: puede ser explícita o asumida del subtotal de la factura
            'withholdings.*.base_amount' => 'nullable|numeric',
        ]);

        // Normalizar: el frontend puede enviar purchase_order_id; el backend usa parent_document_id.
        $validated['parent_document_id'] = $validated['parent_document_id']
            ?? $validated['purchase_order_id']
            ?? null;
        unset($validated['purchase_order_id']);

        return $validated;
    }
}
