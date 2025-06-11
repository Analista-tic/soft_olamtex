<?php

namespace App\Http\Controllers\ProductSheet;

use App\Http\Controllers\Controller;
use App\Models\ProductSheet\Section;
use Illuminate\Http\JsonResponse;

class SectionController extends Controller
{
    public function data(): JsonResponse
    {
        return response()->json([
            'data' => Section::select('id', 'name')->orderBy('name')->get()
        ]);
    }
}