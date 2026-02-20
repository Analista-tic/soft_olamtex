<?php

namespace App\Http\Requests\Production;

use Illuminate\Validation\Rule;

class ConvertProductSheetRequest extends BaseProductSheetRequest
{
    public function rules(): array
    {
        return [
            'product_reference' => ['nullable', 'string', 'max:255'],
        ];
    }
}
