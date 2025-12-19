<?php

namespace App\Http\Controllers\ProductSheet;

use App\Http\Controllers\Controller;
use App\Models\ProductSheet\Group;
use Illuminate\Http\JsonResponse;

class GroupController extends Controller
{
    public function data(): JsonResponse
    {
        return response()->json([
            'data' => Group::select('id', 'name')->orderBy('name')->get()
        ]);
    }
}
