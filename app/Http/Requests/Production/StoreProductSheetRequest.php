<?php

namespace App\Http\Requests\Production;

use Illuminate\Validation\Rule;

class StoreProductSheetRequest extends BaseProductSheetRequest
{
    public function rules(): array
    {
        $sampleReferenceRules = array_filter([
            Rule::requiredIf(fn () => $this->input('type') === self::TYPE_SAMPLE),
            'nullable',
            'string',
            'max:255',
            Rule::unique('product_sheets', 'sample_reference'),
        ]);

        $productReferenceRules = array_filter([
            Rule::requiredIf(fn () => $this->input('type') === self::TYPE_PRODUCT),
            'nullable',
            'string',
            'max:255',
            Rule::unique('product_sheets', 'product_reference'),
        ]);

        return $this->baseRules($sampleReferenceRules, $productReferenceRules);
    }
}
