<?php

namespace App\Http\Controllers\ProductSheet;

use App\Http\Controllers\Controller;
use App\Services\ParameterService;
use Illuminate\Http\JsonResponse;

class GroupController extends Controller
{
    public function data(): JsonResponse
    {
        return response()->json([
            'data' => ParameterService::getList('groups')
        ]);
    }
}
