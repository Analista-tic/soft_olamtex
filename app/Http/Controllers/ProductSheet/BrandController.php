<?php

namespace App\Http\Controllers\ProductSheet;

use App\Http\Controllers\Controller;
use App\Models\ProductSheet\Brand;
use Illuminate\Http\JsonResponse;

class BrandController extends Controller
{
    public function data(): JsonResponse
    {
        return response()->json([
            'data' => Brand::select('id', 'name')->orderBy('name')->get()
        ]);
    }
}
