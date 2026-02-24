<?php

namespace App\Http\Requests\ProductSheet;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOperacionProgresoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'quantity_progressed' => ['required', 'integer', 'min:0'],
            'motivo' => ['required', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'quantity_progressed.required' => 'La cantidad progresada es obligatoria.',
            'quantity_progressed.integer' => 'La cantidad debe ser un número entero.',
            'quantity_progressed.min' => 'La cantidad no puede ser negativa.',
            'motivo.required' => 'El motivo del cambio es obligatorio para auditoría.',
            'motivo.max' => 'El motivo no puede exceder 500 caracteres.',
        ];
    }
}
