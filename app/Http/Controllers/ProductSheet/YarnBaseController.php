<?php

namespace App\Http\Controllers\ProductSheet;

use App\Http\Controllers\Controller;
use App\Models\ProductSheet\YarnBase;
use Illuminate\Http\JsonResponse;

class YarnBaseController extends Controller
{
    public function data(): JsonResponse
    {
        return response()->json([
            'data' => YarnBase::select('id', 'name')->orderBy('name')->get()
        ]);
    }
}
