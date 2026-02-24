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
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'roll_width' => ['nullable', 'numeric', 'min:0'],
            'location' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'partial_output' => ['nullable', 'numeric', 'min:0'],
            'actual_output' => ['nullable', 'numeric', 'min:0'],
            'entry_date' => ['required', 'date'],
        ];
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
