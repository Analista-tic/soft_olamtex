<?php

namespace App\Http\Requests\MasterData;

use App\Http\Requests\Inventory\InventoryFormRequest;
use App\Http\Requests\Settings\SettingKindRules;
use App\Models\Settings\Setting;
use Illuminate\Validation\Rule;

class AdditionalFieldRequest extends InventoryFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isCatalogSourced = $this->input('field_type') === 'select'
            && $this->input('source_type', Setting::SOURCE_TYPE_LIST) === Setting::SOURCE_TYPE_CATALOG;

        return array_merge(
            SettingKindRules::field(),
            [
                'name' => ['required', 'string', 'max:255'],
                'default_value' => ['nullable', 'string'],
                'description' => ['nullable', 'string'],
                'is_required' => ['sometimes', 'boolean'],
                'status' => ['sometimes', 'boolean'],
                'category_sub_group_ids' => ['sometimes', 'array'],
                'category_sub_group_ids.*' => ['integer', 'exists:category_sub_groups,id'],
                'source_type' => ['sometimes', Rule::in([Setting::SOURCE_TYPE_LIST, Setting::SOURCE_TYPE_CATALOG])],
                'catalog_key' => [
                    Rule::requiredIf($isCatalogSourced),
                    'nullable',
                    'string',
                    Rule::in(array_keys(config('additional_field_catalogs', []))),
                ],
                // Una lista escrita a mano exige sus opciones; un campo de catálogo las
                // resuelve en vivo desde su fuente y no las guarda en `options`.
                'options' => [Rule::requiredIf($this->input('field_type') === 'select' && ! $isCatalogSourced)],
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
