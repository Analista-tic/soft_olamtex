<?php

namespace App\Http\Requests\Textil;

class UpdateFabricRollOutputRequest extends \App\Http\Requests\Inventory\InventoryFormRequest
{
    public function rules(): array
    {
        return [
            'partial_output' => ['nullable', 'numeric', 'min:0'],
            'actual_output' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
