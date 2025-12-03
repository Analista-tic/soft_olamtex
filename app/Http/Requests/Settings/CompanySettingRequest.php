<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class CompanySettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // o tu lógica
    }

    public function rules(): array
    {
        return [
            'name'             => ['required', 'string', 'max:255'],
            'nit'              => ['required', 'string', 'max:50'],
            'phone'            => ['required', 'string', 'max:50'],
            'email'            => ['required', 'email', 'max:255'],
            'accounting_email' => ['nullable', 'email', 'max:255'],
            'website'          => ['nullable', 'string', 'max:255'],
            'address'          => ['required', 'string', 'max:255'],
            'municipality'     => ['required', 'string', 'max:255'],
            'department'       => ['required', 'string', 'max:255'],
            'description'      => ['nullable', 'string'],
            'logo'             => ['nullable', 'image', 'max:2048'],
        ];
    }
}