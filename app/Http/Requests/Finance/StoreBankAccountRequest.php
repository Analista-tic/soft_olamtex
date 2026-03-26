<?php

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;

class StoreBankAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'type' => ['required', 'in:bank,cash,credit_card'],
            'bank_name' => ['nullable', 'string', 'max:100'],
            'account_number' => ['nullable', 'string', 'max:30'],
            'currency' => ['nullable', 'string', 'size:3'],
            'opening_balance' => ['nullable', 'numeric', 'min:0'],
            'opening_balance_date' => ['nullable', 'date'],
            'description' => ['nullable', 'string'],
        ];
    }
}
