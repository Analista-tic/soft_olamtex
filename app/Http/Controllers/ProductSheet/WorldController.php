<?php

namespace App\Http\Controllers\ProductSheet;

use App\Http\Controllers\Controller;
use App\Models\ProductSheet\World;
use Illuminate\Http\JsonResponse;

class WorldController extends Controller
{
    public function data(): JsonResponse
    {
        return response()->json([
            'data' => World::select('id', 'name')->orderBy('name')->get()
        ]);
    }
}
