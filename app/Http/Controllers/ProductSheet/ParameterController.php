<?php

namespace App\Http\Controllers\ProductSheet;

use App\Http\Controllers\Controller;
use App\Models\Parameter;
use Illuminate\Http\JsonResponse;

class ParameterController extends Controller
{
    public function data(): JsonResponse
    {
        try {
            $parameters = Parameter::query()->get(['nombre', 'valor']);

            $data = $parameters->mapWithKeys(function (Parameter $parameter) {
                return [$parameter->nombre => $parameter->valor];
            });

            return response()->json([
                'data' => $data,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Error al obtener parametros',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
