<?php

namespace App\Http\Requests\Production;

use Illuminate\Validation\Rule;
use App\Models\Production\Programmer;

class UpdateProgrammerRequest extends BaseProductSheetRequest
{
    public function rules(): array
    {
        return [
            'product_sheet_id' => ['required', 'integer', 'exists:product_sheets,id'],
            'reference' => ['nullable', 'string', 'max:255'],
            'sizes' => ['required', 'json'],
            'status' => ['required', Rule::in(['scheduled', 'in_progress', 'completed'])],
        ];
    }

    protected function programmerId(): ?int
    {
        $routeValue = $this->route('programmer');

        if ($routeValue instanceof Programmer) {
            return $routeValue->id;
        }

        return is_numeric($routeValue) ? (int) $routeValue : null;
    }
}
