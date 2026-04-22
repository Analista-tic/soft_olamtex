<?php

namespace App\Http\Requests\MasterData;

class StoreCategorySubGroupRequest extends MasterDataFormRequest
{
    public function rules(): array
    {
        return [
            'category_group_id' => ['required', 'integer', 'exists:category_groups,id'],
            'subgroups' => ['required', 'array', 'min:1'],
            'subgroups.*.name' => ['required', 'string', 'max:255'],
        ];
    }
}
