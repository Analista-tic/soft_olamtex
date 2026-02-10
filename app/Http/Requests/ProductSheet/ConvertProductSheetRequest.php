<?php

namespace App\Http\Requests\ProductSheet;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConvertProductSheetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_reference' => [
                'required',
                'string',
                'max:255',
                Rule::unique('product_sheets', 'product_reference')
                    ->ignore($this->route('sabana')),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'product_reference.unique' => 'La referencia de producto ya está en uso.',
        ];
    }
}
