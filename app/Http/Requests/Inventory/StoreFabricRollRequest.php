<?php

namespace App\Http\Requests\Inventory;

class StoreFabricRollRequest extends InventoryFormRequest
{
    public function rules(): array
    {
        return [
            'roll_number' => ['required', 'string', 'max:255', 'unique:fabric_rolls,roll_number'],
            'purchase_document_item_id' => ['required', 'integer', 'exists:purchase_document_items,id'],
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'roll_width' => ['nullable', 'numeric', 'min:0'],
            'location' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'entry_date' => ['required', 'date'],
            'partial_output' => ['nullable', 'numeric', 'min:0'],
            'actual_output' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
