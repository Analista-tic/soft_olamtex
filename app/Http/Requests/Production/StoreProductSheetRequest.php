<?php

namespace App\Http\Requests\Production;

use Illuminate\Validation\Rule;

class StoreProductSheetRequest extends BaseProductSheetRequest
{
    public function rules(): array
    {
        $isFromTemplate = $this->filled('parent_id');

        // Si se crea desde template, permitir duplicar referencias (se guardará como nueva versión)
        // Si NO se crea desde template, sample_reference debe ser única
        $sampleReferenceRules = array_filter([
            Rule::requiredIf(fn () => $this->input('type') === self::TYPE_SAMPLE),
            'nullable',
            'string',
            'max:255',
            $isFromTemplate ? null : Rule::unique('product_sheets', 'sample_reference'),
        ]);

        $productReferenceRules = array_filter([
            Rule::requiredIf(fn () => $this->input('type') === self::TYPE_PRODUCT),
            'nullable',
            'string',
            'max:255',
        ]);

        return $this->baseRules($sampleReferenceRules, $productReferenceRules);
    }

    public function withValidator(\Illuminate\Contracts\Validation\Validator $validator): void
    {
        $validator->after(fn ($v) => $this->applyTypeValidation($v));
    }
}
