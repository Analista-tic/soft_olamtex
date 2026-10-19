<?php

namespace App\Http\Requests\Settings;

use Illuminate\Validation\Rule;

class UpdateAreaRequest extends StoreAreaRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150', Rule::unique('areas', 'name')->ignore($this->route('area'))],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
