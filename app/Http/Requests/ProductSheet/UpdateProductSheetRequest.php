<?php

namespace App\Http\Requests\ProductSheet;

use App\Models\ProductSheet\ProductSheet;
use App\Rules\ValidCatalogOption;
use App\Services\Settings\SettingService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class UpdateProductSheetRequest extends FormRequest
{
    private const CATALOG_LABELS = [
        'sections' => 'Sección',
        'groups' => 'Grupo',
        'brands' => 'Marca',
        'worlds' => 'Mundo',
        'fabric_bases' => 'Base',
        'yarn_bases' => 'Base de hilaza',
        'operational_lists' => 'Listado operacional',
    ];

    private const DEVELOPMENT_PROCESS = 'Desarrollo de muestras';
    private const TYPE_PRODUCT = 'producto';
    private const TYPE_SAMPLE = 'muestra';

    public function __construct(private SettingService $settingService)
    {
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $productSheet = $this->resolveProductSheet();
        $requiresOperationalList = $this->requiresOperationalList();

        return [
            'section_id' => ['required', 'integer', $this->catalogRule('sections')],
            'group_id' => ['required', 'integer', $this->catalogRule('groups')],
            'brand_id' => ['required', 'integer', $this->catalogRule('brands')],
            'world_id' => ['required', 'integer', $this->catalogRule('worlds')],
            'fabric_base_id' => ['required', 'integer', $this->catalogRule('fabric_bases')],
            'yarn_base_id' => array_filter([
                $this->requiresYarnBase() ? 'required' : 'nullable',
                'integer',
                $this->catalogRule('yarn_bases'),
            ]),
            'type' => ['required', 'string', Rule::in([self::TYPE_PRODUCT, self::TYPE_SAMPLE])],
            'sample_reference' => array_filter([
                Rule::requiredIf(fn () => $this->input('type') === self::TYPE_SAMPLE),
                'nullable',
                'string',
                'max:255',
                $productSheet
                    ? Rule::unique('product_sheets', 'sample_reference')->ignore($productSheet->id)
                    : Rule::unique('product_sheets', 'sample_reference'),
            ]),
            'product_reference' => array_filter([
                Rule::requiredIf(fn () => $this->input('type') === self::TYPE_PRODUCT),
                'nullable',
                'string',
                'max:255',
                $productSheet
                    ? Rule::unique('product_sheets', 'product_reference')->ignore($productSheet->id)
                    : Rule::unique('product_sheets', 'product_reference'),
            ]),
            'billing_price' => 'required|numeric',
            'selling_price' => 'required|numeric',
            'sizes' => 'required|json',
            'woven_product' => 'required|boolean',
            'description' => 'nullable|string',
            'processes' => 'required|json',
            'flat_sketch' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'status' => ['required', Rule::in(['incomplete', 'complete'])],
            'operational_list_id' => array_filter([
                $requiresOperationalList ? Rule::requiredIf(fn () => $requiresOperationalList) : null,
                'nullable',
                'integer',
                $this->catalogRule('operational_lists'),
            ]),
        ];
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

    public function messages(): array
    {
        return [
            'operational_list_id.required' => 'Selecciona el listado operacional porque el proceso Desarrollo de muestras está activo.',
            'product_reference.required' => 'La referencia de producto es obligatoria para fichas tipo producto.',
            'sample_reference.required' => 'La referencia de muestra es obligatoria cuando el tipo es muestra.',
        ];
    }

    private function catalogRule(string $key): ValidCatalogOption
    {
        $label = self::CATALOG_LABELS[$key] ?? ucfirst(str_replace('_', ' ', $key));
        return new ValidCatalogOption($this->settingService, $key, $label);
    }

    protected function prepareForValidation(): void
    {
        $type = $this->input('type');
        if (!$type) {
            return;
        }

        $sampleReference = trim((string) ($this->input('sample_reference') ?? ''));
        $productReference = trim((string) ($this->input('product_reference') ?? ''));
        $this->merge([
            'sample_reference' => $sampleReference !== '' ? $sampleReference : null,
            'product_reference' => $productReference !== '' ? $productReference : null,
            'woven_product' => $this->normalizeBoolean($this->input('woven_product')),
        ]);
    }

    private function requiresOperationalList(): bool
    {
        $processes = $this->processesArray();
        return isset($processes[self::DEVELOPMENT_PROCESS]) && $processes[self::DEVELOPMENT_PROCESS];
    }

    private function processesArray(): array
    {
        $value = $this->input('processes');
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return is_array($value) ? $value : [];
    }

    private function requiresYarnBase(): bool
    {
        return filter_var($this->input('woven_product'), FILTER_VALIDATE_BOOLEAN);
    }

    private function normalizeBoolean(mixed $value): bool
    {
        if (is_array($value)) {
            $value = $value[0] ?? false;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
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

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'message' => 'Validation failed.',
            'errors' => $validator->errors(),
        ], 422));
    }
}
