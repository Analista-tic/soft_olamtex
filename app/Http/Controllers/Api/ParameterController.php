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

    /**
     * Devuelve los catálogos para ProductSheet desde settings (kind=parameter)
     * GET /api/parameters/product-sheet
     */
    public function productSheetCatalogs(): JsonResponse
    {
        $keys = [
            'sections', 'groups', 'brands', 'worlds', 'fabric_bases', 'yarn_bases'
        ];
        $parameters = Setting::parameters()
            ->whereIn('key', $keys)
            ->where('status', true)
            ->get()
            ->keyBy('key');

        // Formato para GroupSelect: [{ id, name }]
        $format = fn($items) => collect($items)->map(function ($item) {
            if (is_array($item)) {
                return [
                    'id' => $item['id'] ?? $item['value'] ?? $item['key'] ?? null,
                    'name' => $item['name'] ?? $item['label'] ?? $item['value'] ?? null,
                ];
            }
            return [ 'id' => $item, 'name' => $item ];
        })->filter(fn($i) => $i['id'] && $i['name'])->values();

        return response()->json([
            'sections' => $format($parameters['sections']->value ?? []),
            'groups' => $format($parameters['groups']->value ?? []),
            'brands' => $format($parameters['brands']->value ?? []),
            'worlds' => $format($parameters['worlds']->value ?? []),
            'fabricBases' => $format($parameters['fabric_bases']->value ?? []),
            'yarnBases' => $format($parameters['yarn_bases']->value ?? []),
        ]);
    }
}
