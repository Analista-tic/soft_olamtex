<?php

namespace App\Http\Requests\Textil;

use App\Http\Requests\Inventory\InventoryFormRequest;

class UpdateManualRollProgramRequest extends InventoryFormRequest
{
    public function rules(): array
    {
        return [
            'programmed_meters' => ['required', 'numeric', 'min:0.01'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'programmed_meters.required' => 'Los metros a programar son obligatorios.',
            'programmed_meters.min' => 'Los metros a programar deben ser mayores a 0.',
        ];
    }
}
