<?php

namespace App\Http\Controllers\Expenses\Concerns;

use Illuminate\Http\Request;

trait ValidatesPurchaseInvoiceRequest
{
    protected function validateInvoiceRequest(Request $request, bool $isUpdate = false): array
    {
        $itemIdRule = $isUpdate ? ['sometimes', 'exists:purchase_invoice_items,id'] : [];

        return $request->validate([
            'contact_id' => 'required|exists:contacts,id',
            'purchase_order_id' => 'nullable|exists:purchase_orders,id',
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
            'withholdings.*.base_amount' => 'required_with:withholdings|numeric',
        ]);
    }
}
