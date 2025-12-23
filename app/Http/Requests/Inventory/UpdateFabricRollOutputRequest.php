<?php

namespace App\Http\Requests\Inventory;

class UpdateFabricRollOutputRequest extends InventoryFormRequest
{
    public function rules(): array
    {
        return [
            'partial_output' => ['nullable', 'numeric', 'min:0'],
            'actual_output' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
