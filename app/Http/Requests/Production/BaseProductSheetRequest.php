<?php

namespace App\Http\Requests\Production;

use App\Rules\ValidCatalogOption;
use App\Services\Settings\SettingService;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

abstract class BaseProductSheetRequest extends FormRequest
{
    protected const CATALOG_LABELS = [
        'sections' => 'Seccion',
        'groups' => 'Grupo',
        'brands' => 'Marca',
        'worlds' => 'Mundo',
        'fabric_bases' => 'Base',
        'yarn_bases' => 'Base de hilaza',
        'operational_lists' => 'Listado operacional',
    ];

    protected const DEVELOPMENT_PROCESS = 'Desarrollo de muestras';
    protected const TYPE_PRODUCT = 'producto';
    protected const TYPE_SAMPLE = 'muestra';

    public function __construct(protected SettingService $settingService)
    {
    }

    public function authorize(): bool
    {
        return true;
    }

    protected function baseRules(array $sampleReferenceRules, array $productReferenceRules): array
    {
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
            'sample_reference' => $sampleReferenceRules,
            'product_reference' => $productReferenceRules,
            'billing_price' => 'required|numeric',
            'selling_price' => 'required|numeric',
            'sizes' => 'required|json',
            'woven_product' => 'required|boolean',
            'description' => 'nullable|string',
            'processes' => 'required|json',
            'flat_sketch' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'status' => ['required', Rule::in(['incomplete', 'complete'])],
            'operational_list_id' => [
                'nullable',
                'integer',
                $this->catalogRule('operational_lists'),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'operational_list_id.required' => 'Selecciona el listado operacional porque el proceso Desarrollo de muestras esta activo.',
            'product_reference.required' => 'La referencia de producto es obligatoria para fichas tipo producto.',
            'sample_reference.required' => 'La referencia de muestra es obligatoria cuando el tipo es muestra.',
        ];
    }

    protected function catalogRule(string $key): ValidCatalogOption
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

    protected function requiresOperationalList(): bool
    {
        $processes = $this->processesArray();
        return isset($processes[self::DEVELOPMENT_PROCESS]) && $processes[self::DEVELOPMENT_PROCESS];
    }

    protected function processesArray(): array
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

    protected function requiresYarnBase(): bool
    {
        return filter_var($this->input('woven_product'), FILTER_VALIDATE_BOOLEAN);
    }

    protected function normalizeBoolean(mixed $value): bool
    {
        if (is_array($value)) {
            $value = $value[0] ?? false;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'message' => 'Validation failed.',
            'errors' => $validator->errors(),
        ], 422));
    }
}
