<?php

namespace App\Http\Requests\ProductSheet;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductOperationListRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $listItem = $this->route('listItem');
        
        return [
            'operation_id' => [
                'sometimes',
                'integer',
                'exists:operations,id',
                // Validar que la operación no esté duplicada (excepto el registro actual)
                Rule::unique('product_operation_lists')
                    ->where('product_sheet_id', $listItem->product_sheet_id)
                    ->ignore($listItem->id)
                    ->whereNull('deleted_at'),
            ],
            'machine_id' => ['sometimes', 'integer', 'exists:machines,id'],
            'operation_section_id' => ['sometimes', 'integer', 'exists:operation_sections,id'],
            'sequence_order' => [
                'sometimes',
                'integer',
                'min:1',
                // Validar que el orden sea único (excepto el registro actual)
                Rule::unique('product_operation_lists')
                    ->where('product_sheet_id', $listItem->product_sheet_id)
                    ->ignore($listItem->id)
                    ->whereNull('deleted_at'),
            ],
            'optimal_time' => ['sometimes', 'numeric', 'min:0.01', 'max:999.99'],
            'operator_efficiency_factor' => [
                'sometimes',
                'numeric',
                'min:1.0',
                'max:2.0',
            ],
            'machine_supplement_factor' => [
                'sometimes',
                'numeric',
                'min:1.0',
                'max:5.0',
            ],
            'actual_time' => ['nullable', 'numeric', 'min:0', 'max:999.99'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'operation_id.unique' => 'Esta operación ya está agregada en este lote. No se pueden duplicar operaciones.',
            'sequence_order.unique' => 'Ya existe una operación con este orden de secuencia en el lote.',
            'operator_efficiency_factor.min' => 'El factor de eficiencia del operario debe ser al menos 1.0 (100%).',
            'operator_efficiency_factor.max' => 'El factor de eficiencia del operario no puede superar 2.0 (200%).',
            'machine_supplement_factor.min' => 'El suplemento de máquina debe ser al menos 1.0 (sin suplemento).',
            'machine_supplement_factor.max' => 'El suplemento de máquina no puede superar 5.0 (500%).',
            'optimal_time.min' => 'El tiempo óptimo debe ser mayor a 0.',
            'optimal_time.max' => 'El tiempo óptimo no puede superar 999.99 minutos.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'operation_id' => 'operación',
            'machine_id' => 'máquina',
            'operation_section_id' => 'sección',
            'sequence_order' => 'orden de secuencia',
            'optimal_time' => 'tiempo óptimo',
            'operator_efficiency_factor' => 'factor de eficiencia del operario',
            'machine_supplement_factor' => 'suplemento de máquina',
            'actual_time' => 'tiempo real',
        ];
    }
}
