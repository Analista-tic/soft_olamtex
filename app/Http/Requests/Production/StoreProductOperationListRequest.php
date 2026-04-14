<?php

namespace App\Http\Requests\Production;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductOperationListRequest extends FormRequest
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
        return [
            'product_sheet_id' => ['required', 'integer', 'exists:product_sheets,id'],
            'operation_id' => [
                'required',
                'integer',
                'exists:operations,id',
            ],
            'machine_id' => ['required', 'integer', 'exists:machines,id'],
            'operation_section_id' => ['required', 'integer', 'exists:operation_sections,id'],
            'sequence' => [
                'required',
                'integer',
                'min:1',
            ],
            'optimal_time' => ['required', 'numeric', 'min:0.01', 'max:999.99'],
            'operator_efficiency_factor' => [
                'required',
                'numeric',
                'min:1.0',
                'max:2.0',
            ],
            'machine_supplement_factor' => [
                'required',
                'numeric',
                'min:1.0',
                'max:5.0',
            ],
            'grupo_preparacion' => ['nullable', 'string', 'max:100'],
            'fabric_base_id' => ['nullable', 'integer'],
            'quantity' => ['nullable', 'integer', 'min:1'],
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
            'operator_efficiency_factor.min' => 'El factor de eficiencia del operario debe ser al menos 1.0 (100%).',
            'operator_efficiency_factor.max' => 'El factor de eficiencia del operario no puede superar 2.0 (200%).',
            'operator_efficiency_factor.between' => 'El factor de eficiencia debe estar entre 1.0 y 2.0.',
            'machine_supplement_factor.min' => 'El suplemento de máquina debe ser al menos 1.0 (sin suplemento).',
            'machine_supplement_factor.max' => 'El suplemento de máquina no puede superar 5.0 (500%).',
            'optimal_time.min' => 'El tiempo óptimo debe ser mayor a 0.',
            'optimal_time.max' => 'El tiempo óptimo no puede superar 999.99 minutos.',
            'grupo_preparacion.max' => 'El grupo de preparación no puede superar 100 caracteres.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'product_sheet_id' => 'lote',
            'operation_id' => 'operación',
            'machine_id' => 'máquina',
            'operation_section_id' => 'sección',
            'grupo_preparacion' => 'grupo preparación',
            'sequence' => 'secuencia',
            'optimal_time' => 'tiempo óptimo',
            'operator_efficiency_factor' => 'factor de eficiencia del operario',
            'machine_supplement_factor' => 'suplemento de máquina',
            'fabric_base_id' => 'base tela',
            'quantity' => 'cantidad',
            'actual_time' => 'tiempo real',
        ];
    }
}
