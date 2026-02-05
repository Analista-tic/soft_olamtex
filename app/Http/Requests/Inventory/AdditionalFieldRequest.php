<?php

namespace App\Http\Requests\Inventory;

use App\Http\Requests\Settings\SettingKindRules;

class AdditionalFieldRequest extends InventoryFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return array_merge(
            SettingKindRules::field(),
            [
                'name' => ['required', 'string', 'max:255'],
                'default_value' => ['nullable', 'string'],
                'description' => ['nullable', 'string'],
                'is_required' => ['sometimes', 'boolean'],
                'status' => ['sometimes', 'boolean'],
            ],
        );
    }
}
