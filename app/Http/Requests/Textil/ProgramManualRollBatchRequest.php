<?php

namespace App\Http\Requests\Textil;

class ProgramManualRollBatchRequest extends \App\Http\Requests\Inventory\InventoryFormRequest
{
    public function rules(): array
    {
        return [
            'manual_order_code' => ['required', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.fabric_roll_id' => ['required', 'integer', 'distinct', 'exists:fabric_rolls,id'],
            'lines.*.programmed_meters' => ['required', 'numeric', 'min:0.01'],
        ];
    }

    public function messages(): array
    {
        return [
            'manual_order_code.required' => 'El código de orden es obligatorio.',
            'lines.required' => 'Selecciona al menos un rollo.',
            'lines.min' => 'Selecciona al menos un rollo.',
            'lines.*.fabric_roll_id.required' => 'Selecciona un rollo válido en cada línea.',
            'lines.*.fabric_roll_id.distinct' => 'No puedes repetir el mismo rollo dos veces en el lote.',
            'lines.*.fabric_roll_id.exists' => 'Uno de los rollos seleccionados no existe.',
            'lines.*.programmed_meters.required' => 'Los metros a programar son obligatorios en cada línea.',
            'lines.*.programmed_meters.min' => 'Los metros a programar deben ser mayores a 0.',
        ];
    }
}
