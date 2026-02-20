<?php

namespace App\Http\Requests\Production;

use Illuminate\Validation\Rule;

class StoreProgrammerRequest extends BaseProductSheetRequest
{
    public function rules(): array
    {
        return [
            'product_sheet_id' => ['required', 'integer', 'exists:product_sheets,id'],
            'reference' => ['nullable', 'string', 'max:255'],
            'sizes' => ['required', 'json'],
            'status' => ['required', Rule::in(['scheduled', 'in_progress', 'completed'])],
        ];
    }
}
