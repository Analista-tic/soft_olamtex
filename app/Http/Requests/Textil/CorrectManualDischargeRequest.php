<?php

namespace App\Http\Requests\Textil;

use App\Http\Requests\Inventory\InventoryFormRequest;

class CorrectManualDischargeRequest extends InventoryFormRequest
{
    public function rules(): array
    {
        return [
            'quantity' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
            // Obligatorio (lo valida el servicio) si la corrección deja la línea por encima de lo programado.
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'quantity.required' => 'La cantidad corregida es obligatoria.',
            'quantity.min' => 'La cantidad corregida no puede ser negativa.',
            'quantity.decimal' => 'La cantidad corregida admite máximo 2 decimales.',
        ];
    }
}
