<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Inventory\FabricProvider;
use Illuminate\Http\JsonResponse;

class FabricProviderController extends Controller
{
    public function index(): JsonResponse
    {
        $providers = FabricProvider::all(['id', 'name']);
        return response()->json($providers);
    }
}
