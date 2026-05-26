<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class UppercaseTextFields
{
    // Claves que NUNCA deben convertirse a mayúsculas
    protected array $except = [
        'email',
        'password',
        'password_confirmation',
        'current_password',
        'token',
        'rejection_evidence_image', // base64 / rutas de archivos
        'evidence_path',
        'difference_evidence_path',
        '_token',
        'redirect',
        'url',
    ];

    public function handle(Request $request, Closure $next)
    {
        $input = $request->all();
        $request->merge($this->uppercaseRecursive($input));

        return $next($request);
    }

    protected function uppercaseRecursive(array $data): array
    {
        foreach ($data as $key => $value) {
            if (in_array($key, $this->except, true)) {
                continue;
            }

            if (is_string($value)) {
                $data[$key] = mb_strtoupper($value, 'UTF-8');
            } elseif (is_array($value)) {
                $data[$key] = $this->uppercaseRecursive($value);
            }
        }

        return $data;
    }
}
