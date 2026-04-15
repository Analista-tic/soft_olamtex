<?php

namespace App\Http\Requests\Production;

use App\Rules\ValidCatalogOption;
use App\Services\Settings\SettingService;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Str;
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

    protected const OPERATIONAL_LIST_REQUIRED_PROCESS = 'Muestras';
    protected const PROCESS_ALIASES = [
        'Muestra' => 'Muestras',
        'Desarrollo de muestra' => 'Desarrollo de muestras',
        'Desarrollo de Muestra' => 'Desarrollo de muestras',
        'Desarrollo de Muestras' => 'Desarrollo de muestras',
        'Estampando' => 'Estampado',
    ];
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
            'fabric_base_id' => array_filter([
                $this->requiresYarnBase() ? 'nullable' : 'required',
                'integer',
                $this->catalogRule('fabric_bases'),
            ]),
            'yarn_base_id' => array_filter([
                $this->requiresYarnBase() ? 'required' : 'nullable',
                'integer',
                $this->catalogRule('yarn_bases'),
            ]),
            'type' => ['required', 'string', Rule::in([self::TYPE_PRODUCT, self::TYPE_SAMPLE])],
            'sample_reference' => $sampleReferenceRules,
            'product_reference' => $productReferenceRules,
            'product_reference_2' => ['nullable', 'string', 'max:255'],
            'version' => ['nullable', 'integer', 'min:1'],
            'parent_id' => ['nullable', 'integer', 'exists:product_sheets,id'],
            'billing_price' => 'nullable|numeric',
            'costo' => 'nullable|numeric',
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
            'collection_id' => ['nullable', 'integer', 'exists:collections,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'operational_list_id.required' => 'Selecciona el listado operacional porque el proceso Muestras esta activo.',
            'product_reference.required' => 'La referencia de producto es obligatoria para fichas tipo producto.',
            'sample_reference.required' => 'La referencia de muestra es obligatoria cuando el tipo es muestra.',
            'fabric_base_id.required' => 'Base Tela es requerida cuando el producto no es tejido.',
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

        $sampleReference = Str::upper(trim((string) ($this->input('sample_reference') ?? '')));
        $productReference = Str::upper(trim((string) ($this->input('product_reference') ?? '')));
        $productReference2 = Str::upper(trim((string) ($this->input('product_reference_2') ?? '')));
        $this->merge([
            'sample_reference' => $sampleReference !== '' ? $sampleReference : null,
            'product_reference' => $productReference !== '' ? $productReference : null,
            'product_reference_2' => $productReference2 !== '' ? $productReference2 : null,
            'woven_product' => $this->normalizeBoolean($this->input('woven_product')),
            'processes' => json_encode($this->normalizeProcesses($this->input('processes'), $type), JSON_UNESCAPED_UNICODE),
        ]);
    }

    protected function requiresOperationalList(): bool
    {
        $processes = $this->processesArray();
        return isset($processes[self::OPERATIONAL_LIST_REQUIRED_PROCESS]) && (bool) $processes[self::OPERATIONAL_LIST_REQUIRED_PROCESS];
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

    /**
     * Normaliza los procesos al catálogo oficial de control de lotes.
     * Acepta objeto o lista, convierte aliases legacy y filtra por tipo de ficha.
     *
     * @return array<string, bool>
     */
    protected function normalizeProcesses(mixed $value, ?string $type = null): array
    {
        $batchProcesses = config('product-sheet.batch_control_processes', []);

        $decoded = [];
        if (is_string($value)) {
            $parsed = json_decode($value, true);
            $decoded = is_array($parsed) ? $parsed : [];
        } elseif (is_array($value)) {
            $decoded = $value;
        }

        $active = [];
        if (array_is_list($decoded)) {
            foreach ($decoded as $processName) {
                if (!is_string($processName)) {
                    continue;
                }

                $trimmed = trim($processName);
                if ($trimmed === '') {
                    continue;
                }

                $normalized = self::PROCESS_ALIASES[$trimmed] ?? $trimmed;
                $active[$normalized] = true;
            }
        } else {
            foreach ($decoded as $processName => $enabled) {
                if (!is_string($processName) || !$enabled) {
                    continue;
                }

                $trimmed = trim($processName);
                if ($trimmed === '') {
                    continue;
                }

                $normalized = self::PROCESS_ALIASES[$trimmed] ?? $trimmed;
                $active[$normalized] = true;
            }
        }

        $ordered = [];
        foreach ($batchProcesses as $processName) {
            if (isset($active[$processName])) {
                $ordered[$processName] = true;
            }
        }

        // Filtrar por tipo: eliminar procesos no permitidos para este tipo de ficha
        if ($type !== null) {
            $allowedByType = config('product-sheet.processes_allowed_by_type', []);
            if (isset($allowedByType[$type])) {
                $allowedSet = array_flip($allowedByType[$type]);
                $ordered = array_intersect_key($ordered, $allowedSet);
            }
        }

        return $ordered;
    }

    /**
     * Valida reglas adicionales dependientes del tipo de ficha.
     * Llamar desde withValidator() en subclases.
     */
    protected function applyTypeValidation(\Illuminate\Contracts\Validation\Validator $validator): void
    {
        $type = $this->input('type');
        if (!$type) {
            return;
        }

        // PRODUCTO: debe tener listado operacional asignado
        if ($type === self::TYPE_PRODUCT && !$this->filled('operational_list_id')) {
            $validator->errors()->add(
                'operational_list_id',
                'Las fichas tipo producto requieren un listado operacional asignado.'
            );
        }
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'message' => 'Validation failed.',
            'errors' => $validator->errors(),
        ], 422));
    }
}
