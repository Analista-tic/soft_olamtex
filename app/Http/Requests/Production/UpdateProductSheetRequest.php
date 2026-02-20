<?php

namespace App\Http\Requests\Production;

use App\Models\Production\ProductSheet;
use Illuminate\Validation\Rule;

class UpdateProductSheetRequest extends BaseProductSheetRequest
{
    public function rules(): array
    {
        $productSheet = $this->resolveProductSheet();

        $sampleReferenceRules = array_filter([
            Rule::requiredIf(fn () => $this->input('type') === self::TYPE_SAMPLE),
            'nullable',
            'string',
            'max:255',
            $productSheet
                ? Rule::unique('product_sheets', 'sample_reference')->ignore($productSheet->id)
                : Rule::unique('product_sheets', 'sample_reference'),
        ]);

        $productReferenceRules = array_filter([
            Rule::requiredIf(fn () => $this->input('type') === self::TYPE_PRODUCT),
            'nullable',
            'string',
            'max:255',
            $productSheet
                ? Rule::unique('product_sheets', 'product_reference')->ignore($productSheet->id)
                : Rule::unique('product_sheets', 'product_reference'),
        ]);

        return $this->baseRules($sampleReferenceRules, $productReferenceRules);
    }

    public function withValidator($validator): void
    {
        $productSheet = $this->resolveProductSheet();
        $validator->after(function ($validator) use ($productSheet) {
            if (!$productSheet) {
                return;
            }

            if ($productSheet->type === self::TYPE_SAMPLE
                && $this->input('type') === self::TYPE_SAMPLE
                && $this->has('sample_reference')
                && $this->input('sample_reference') !== $productSheet->sample_reference
            ) {
                $validator->errors()->add(
                    'sample_reference',
                    'La referencia de muestra no se puede modificar una vez creada.'
                );
            }
        });
    }

    private function resolveProductSheet(): ?ProductSheet
    {
        $param = $this->route('sabana');
        if ($param instanceof ProductSheet) {
            return $param;
        }

        if (is_numeric($param)) {
            return ProductSheet::find((int) $param);
        }

        return null;
    }
}
