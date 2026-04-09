<?php

namespace App\Http\Requests\MasterData;

use App\Http\Requests\Inventory\InventoryFormRequest;
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

    public function messages(): array
    {
        return [
            'name.required' => 'Debe ingresar el nombre del campo.',
        ];
    }
}
