<?php

namespace App\Http\Requests\Textil;

class ProgramManualRollRequest extends \App\Http\Requests\Inventory\InventoryFormRequest
{
    public function rules(): array
    {
        return [
            'fabric_roll_id' => ['required', 'integer', 'exists:fabric_rolls,id'],
            'manual_order_code' => ['required', 'string', 'max:100'],
            'programmed_meters' => ['required', 'numeric', 'min:0.01', 'decimal:0,2'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'fabric_roll_id.required' => 'Selecciona un rollo.',
            'fabric_roll_id.exists' => 'El rollo seleccionado no existe.',
            'manual_order_code.required' => 'El código de orden es obligatorio.',
            'programmed_meters.required' => 'Los metros a programar son obligatorios.',
            'programmed_meters.min' => 'Los metros a programar deben ser mayores a 0.',
        ];
    }
}
