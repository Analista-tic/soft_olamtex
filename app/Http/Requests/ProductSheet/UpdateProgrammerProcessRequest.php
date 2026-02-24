<?php

namespace App\Http\Requests\ProductSheet;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProgrammerProcessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $processes = config('product-sheet.batch_control_processes', []);

        return [
            'current_process' => [
                'required',
                'string',
                'max:80',
                Rule::in($processes),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'current_process.required' => 'El proceso es obligatorio.',
            'current_process.in' => 'El proceso seleccionado no es válido.',
        ];
    }
}
