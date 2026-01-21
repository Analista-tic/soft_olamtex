<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Parameter;
use Illuminate\Http\JsonResponse;

class ParameterController extends Controller
{
    public function areas(): JsonResponse
    {
        $areas = Parameter::where('nombre', 'areas')->first();
        return response()->json($areas ? $areas->valor : []);
    }
}
