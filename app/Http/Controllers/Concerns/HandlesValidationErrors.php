<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

trait HandlesValidationErrors
{
    protected function validationErrorResponse(ValidationException $exception): JsonResponse
    {
        return response()->json([
            'message' => 'Errores de validación',
            'errors' => $exception->errors(),
        ], 422);
    }
}
