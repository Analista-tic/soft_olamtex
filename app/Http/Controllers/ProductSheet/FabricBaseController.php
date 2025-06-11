<?php

namespace App\Http\Controllers\ProductSheet;

use App\Http\Controllers\Controller;
use App\Models\ProductSheet\FabricBase;
use Illuminate\Http\JsonResponse;

class FabricBaseController extends Controller
{
    public function data(): JsonResponse
    {
        return response()->json([
            'data' => FabricBase::select('id', 'name')->orderBy('name')->get()
        ]);
    }
}
