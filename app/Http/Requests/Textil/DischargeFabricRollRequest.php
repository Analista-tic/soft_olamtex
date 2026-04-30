<?php

namespace App\Http\Requests\Textil;

class DischargeFabricRollRequest extends \App\Http\Requests\Inventory\InventoryFormRequest
{
    public function rules(): array
    {
        return [
            'type' => ['required', 'in:salida,entrada'],
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['required', 'string', 'max:500'],
            'production_order_id' => ['nullable', 'integer', 'exists:production_orders,id'],
            'credit_note' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'type.required' => 'El tipo de ajuste es obligatorio.',
            'type.in' => 'El tipo de ajuste debe ser salida o entrada.',
            'quantity.required' => 'La cantidad es obligatoria.',
            'quantity.numeric' => 'La cantidad debe ser numérica.',
            'quantity.min' => 'La cantidad debe ser mayor a 0.01.',
            'reason.required' => 'El motivo es obligatorio.',
            'reason.string' => 'El motivo debe ser un texto válido.',
            'reason.max' => 'El motivo no puede superar los 500 caracteres.',
            'production_order_id.integer' => 'La orden de producción debe ser un número entero.',
            'production_order_id.exists' => 'La orden de producción seleccionada no existe.',
            'credit_note.string' => 'La nota crédito debe ser un texto válido.',
            'credit_note.max' => 'La nota crédito no puede superar los 100 caracteres.',
        ];
    }
}
