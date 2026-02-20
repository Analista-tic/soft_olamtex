<?php

namespace App\Http\Requests\Production;

use Illuminate\Validation\Rule;

class UpdateProductOperationListRequest extends BaseProductSheetRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'operations' => ['required', 'json'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }
}
