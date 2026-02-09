<?php

namespace App\Http\Requests\Settings;

use App\Models\Setting;
use Illuminate\Validation\Rule;

final class SettingKindRules
{
    public static function parameter(): array
    {
        return [
            'key' => [
                'nullable',
                'string',
                'max:191',
                'regex:' . Setting::KEY_PATTERN,
            ],
            'value' => [
                'present',
                'required',
            ],
        ];
    }

    public static function field(): array
    {
        return [
            'key' => ['nullable', 'string', 'max:255'],
            'field_type' => ['required', Rule::in(Setting::FIELD_TYPES)],
            'options' => ['required_if:field_type,select'],
        ];
    }
}
