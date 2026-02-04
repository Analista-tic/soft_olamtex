<?php

namespace App\Http\Requests\Inventory;

use App\Models\Setting;
use Illuminate\Validation\Rule;

class AdditionalFieldRequest extends InventoryFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'key' => ['nullable', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'field_type' => ['required', Rule::in(Setting::FIELD_TYPES)],
            'options' => ['required_if:field_type,select'],
            'default_value' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
            'is_required' => ['sometimes', 'boolean'],
            'status' => ['sometimes', 'boolean'],
        ];
    }
}
