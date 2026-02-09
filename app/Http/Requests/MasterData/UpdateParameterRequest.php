<?php

namespace App\Http\Requests\MasterData;

use App\Http\Requests\Settings\SettingKindRules;
use App\Models\Setting;
use Illuminate\Validation\Rule;

class UpdateParameterRequest extends MasterDataFormRequest
{
    public function rules(): array
    {
        $baseRules = SettingKindRules::parameter();

        $keyRules = array_merge(
            ['sometimes'],
            $baseRules['key'],
            [
                Rule::unique('settings', 'key')
                    ->ignore($this->route('id'))
                    ->where(fn ($query) => $query->where('kind', Setting::KIND_PARAMETER)),
            ],
        );

        $rules = [
            'key' => $keyRules,
            'label' => ['required', 'string', 'max:191'],
            'description' => ['nullable', 'string'],
            'status' => ['sometimes', 'boolean'],
        ];

        $rules['value'] = array_merge($baseRules['value'], [
            function ($attribute, $value, $fail) {
                if (is_string($value)) {
                    $trimmed = trim($value);
                    if ($trimmed === '') {
                        $fail('El valor no puede estar vacío.');

                        return;
                    }

                    if (in_array($trimmed[0], ['{', '['], true)) {
                        json_decode($trimmed, true);
                        if (json_last_error() !== JSON_ERROR_NONE) {
                            $fail('El valor debe ser un JSON válido.');
                        }
                    }
                }
            },
        ]);

        return array_merge($baseRules, $rules);
    }
}
