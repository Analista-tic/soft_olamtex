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
            'code' => ['nullable', 'string', 'max:2', 'unique:category_sub_groups,code,'.$this->route('subgroup')?->id],
        ];
    }
}
