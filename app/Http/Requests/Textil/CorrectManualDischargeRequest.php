<?php

namespace App\Http\Requests\Textil;

use App\Http\Requests\Inventory\InventoryFormRequest;

class CorrectManualDischargeRequest extends InventoryFormRequest
{
    public function rules(): array
    {
        return [
            'quantity' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'quantity.required' => 'La cantidad corregida es obligatoria.',
            'quantity.min' => 'La cantidad corregida no puede ser negativa.',
        ];
    }
}
