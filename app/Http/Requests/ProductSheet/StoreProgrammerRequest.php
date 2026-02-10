<?php

namespace App\Http\Requests\ProductSheet;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

/**
 * FormRequest para crear nuevos Programadores (lotes manuales).
 * 
 * IMPORTANTE: Los registros creados desde este formulario son SIEMPRE manuales.
 * El op_code se genera después de la creación, por lo que:
 * - priority es requerido (lotes manuales usan prioridad)
 * - estimated_delivery_date se ignora (no aplica a manuales)
 */
class StoreProgrammerRequest extends FormRequest
{
    private const PRIORITIES = ['A', 'B', 'C', 'D'];
    private const STATUSES = ['complete', 'incomplete'];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * Limpia el estimated_delivery_date ya que los lotes manuales no lo usan.
     */
    protected function prepareForValidation(): void
    {
        // Los lotes creados manualmente NO usan estimated_delivery_date
        // Forzamos a null cualquier valor que venga
        $lots = $this->input('lots', []);

        if (is_array($lots)) {
            $cleanedLots = array_map(function (array $lot) {
                // Forzar estimated_delivery_date a null para lotes manuales
                $lot['estimated_delivery_date'] = null;
                return $lot;
            }, $lots);

            $this->merge(['lots' => $cleanedLots]);
        }
    }

    public function rules(): array
    {
        return [
            'lots' => 'required|array|min:1',
            'lots.*.product_sheet_id' => 'required|exists:product_sheets,id',
            'lots.*.season' => 'required|string|max:255',
            'lots.*.destination_id' => 'required|integer|min:1',
            
            // Priority: requerido para lotes manuales
            'lots.*.priority' => [
                'required',
                'string',
                Rule::in(self::PRIORITIES),
            ],

            'lots.*.units' => 'nullable|integer|min:1',
            'lots.*.ecommerce_units' => 'nullable',
            'lots.*.status' => ['sometimes', 'string', Rule::in(self::STATUSES)],
        ];
    }

    public function messages(): array
    {
        return [
            'lots.required' => 'Debe enviar al menos un lote.',
            'lots.min' => 'Debe enviar al menos un lote.',
            'lots.*.product_sheet_id.required' => 'La referencia de producto es obligatoria para cada lote.',
            'lots.*.product_sheet_id.exists' => 'La referencia de producto seleccionada no existe.',
            'lots.*.season.required' => 'La temporada es obligatoria para cada lote.',
            'lots.*.destination_id.required' => 'El destino es obligatorio para cada lote.',
            'lots.*.destination_id.integer' => 'El destino debe ser un número válido.',
            'lots.*.priority.required' => 'La prioridad es obligatoria para lotes manuales.',
            'lots.*.priority.in' => 'La prioridad debe ser A, B, C o D.',
            'lots.*.units.integer' => 'Las unidades deben ser un número entero.',
            'lots.*.units.min' => 'Las unidades deben ser al menos 1.',
            'lots.*.status.in' => 'El estado debe ser "complete" o "incomplete".',
        ];
    }

    /**
     * Maneja errores de validación retornando JSON.
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'message' => 'Error de validación.',
            'errors' => $validator->errors(),
        ], 422));
    }
}
