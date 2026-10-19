<?php

namespace App\Http\Requests\MasterData;

class UpdateCategorySubGroupRequest extends MasterDataFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'category_group_id' => ['required', 'integer', 'exists:category_groups,id'],
            'material_kind' => ['nullable', 'in:fabric,yarn'],
        ];
    }
}
