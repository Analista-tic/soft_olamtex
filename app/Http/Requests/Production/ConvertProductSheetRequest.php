<?php

namespace App\Http\Requests\Production;

class ConvertProductSheetRequest extends BaseProductSheetRequest
{
    public function rules(): array
    {
        return [
            'product_reference' => ['nullable', 'string', 'max:255'],
            'allow_duplicate_reference' => ['sometimes', 'boolean'],
        ];
    }
}
