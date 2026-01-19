<?php

namespace App\Http\Controllers\ProductSheet;

use App\Http\Controllers\Controller;
use App\Services\ParameterService;
use Illuminate\Http\JsonResponse;

class FabricBaseController extends Controller
{
    public function data(): JsonResponse
    {
        return response()->json([
            'data' => ParameterService::getList('fabric_bases')
        ]);
    }
}
