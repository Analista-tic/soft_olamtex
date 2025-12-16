<?php

namespace App\Http\Requests\Inventory;

use App\Models\Inventory\AdditionalField;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdditionalFieldRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'field_type' => ['required', Rule::in(AdditionalField::FIELD_TYPES)],
            'options' => ['nullable'],
            'default_value' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
            'is_required' => ['sometimes', 'boolean'],
            'status' => ['sometimes', 'boolean'],
        ];
    }
}
