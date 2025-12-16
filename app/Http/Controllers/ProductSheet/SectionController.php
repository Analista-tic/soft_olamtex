<?php

namespace App\Http\Controllers\ProductSheet;

use App\Http\Controllers\Controller;
use App\Services\ProductSheet\SectionService;
use Illuminate\Http\JsonResponse;

class SectionController extends Controller
{
    public function __construct(protected SectionService $service) {}

    public function data(): JsonResponse
    {
        return response()->json([
            'data' => $this->service->options()
        ]);
    }
}