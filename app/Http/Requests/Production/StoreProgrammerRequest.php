<?php

namespace App\Http\Requests\Production;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProgrammerRequest extends FormRequest
{
    private const PRIORITIES = ['A', 'B', 'C', 'D'];
    private const STATUSES = ['complete', 'incomplete'];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'lots' => ['required', 'array', 'min:1'],
            'lots.*.product_sheet_id' => ['required', 'integer', 'exists:product_sheets,id'],
            'lots.*.season' => ['required', 'string', 'max:255'],
            'lots.*.destination_id' => ['required', 'integer', 'min:1'],
            'lots.*.priority' => ['nullable', 'string', Rule::in(self::PRIORITIES)],
            'lots.*.units' => ['nullable', 'integer', 'min:1'],
            'lots.*.ecommerce_units' => ['nullable'],
            'lots.*.status' => ['nullable', 'string', Rule::in(self::STATUSES)],
        ];
    }
}
