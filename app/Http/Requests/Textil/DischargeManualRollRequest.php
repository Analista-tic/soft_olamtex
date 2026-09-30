<?php

namespace App\Http\Requests\Textil;

class DischargeManualRollRequest extends \App\Http\Requests\Inventory\InventoryFormRequest
{
    public function rules(): array
    {
        return [
            // 0 solo es válido al dar el rollo por terminado sin consumo adicional (lo valida el servicio).
            'quantity' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
            'reason' => ['nullable', 'string', 'max:500'],
            'finish_roll' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'quantity.required' => 'La cantidad es obligatoria.',
            'quantity.min' => 'La cantidad no puede ser negativa.',
            'quantity.decimal' => 'La cantidad admite máximo 2 decimales.',
        ];
    }
}
