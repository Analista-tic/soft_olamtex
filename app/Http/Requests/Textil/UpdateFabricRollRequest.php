<?php

namespace App\Http\Requests\Textil;

use App\Models\Textil\FabricRoll;
use Illuminate\Validation\Rule;

class UpdateFabricRollRequest extends \App\Http\Requests\Inventory\InventoryFormRequest
{
    public function rules(): array
    {
        return [
            'roll_number' => [
                'required',
                'string',
                'max:255',
                Rule::unique('fabric_rolls', 'roll_number')->ignore($this->fabricRollId()),
            ],
            'purchase_document_item_id' => ['required', 'integer', 'exists:purchase_document_items,id'],
            'quantity' => ['required', 'numeric', 'min:0.01', 'decimal:0,2'],
            'roll_width' => ['nullable', 'numeric', 'min:0', 'max:10', 'decimal:0,2'],
            'location' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'entry_date' => ['required', 'date'],
        ];
    }

    public function messages(): array
    {
        return ['roll_width.max' => 'El ancho del rollo se expresa en metros (ej. 1,50) y no puede superar 10 m.'];
    }

    protected function fabricRollId(): ?int
    {
        $routeValue = $this->route('fabricRoll');

        if ($routeValue instanceof FabricRoll) {
            return $routeValue->id;
        }

        return is_numeric($routeValue) ? (int) $routeValue : null;
    }
}
