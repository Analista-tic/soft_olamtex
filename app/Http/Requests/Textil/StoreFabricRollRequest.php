<?php

namespace App\Http\Requests\Textil;

class StoreFabricRollRequest extends \App\Http\Requests\Inventory\InventoryFormRequest
{
    public function rules(): array
    {
        return [
            'roll_number' => ['required', 'string', 'max:255', 'unique:fabric_rolls,roll_number'],
            'purchase_document_item_id' => ['required', 'integer', 'exists:purchase_document_items,id'],
            'quantity' => ['required', 'numeric', 'min:0.01', 'decimal:0,2'],
            'price_per_meter' => ['nullable', 'numeric', 'min:0.01'],
            'roll_width' => ['nullable', 'numeric', 'min:0', 'max:10', 'decimal:0,2'],
            'location' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'entry_date' => ['required', 'date'],
            'approval_status' => ['nullable', 'string', 'in:approved,pending_approval,not_approved'],
            // Solo se exige con la recepción ya aprobada (lo valida FabricRollService).
            'post_approval_reason' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return ['roll_width.max' => 'El ancho del rollo se expresa en metros (ej. 1,50) y no puede superar 10 m.'];
    }
}
