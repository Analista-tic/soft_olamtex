<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;

class ParameterController extends Controller
{
    public function areas(): JsonResponse
    {
        $setting = Setting::parameters()->where('key', 'areas')->first();
        return response()->json($setting ? $setting->value : []);
    }
}
