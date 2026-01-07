<?php

namespace App\Http\Controllers\ProductSheet;

use App\Http\Controllers\Controller;
use App\Services\ProductSheet\SectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SectionController extends Controller
{
    public function __construct(protected SectionService $service) {}

    /**
     * Lista todas las opciones de secciones activas
     * GET /api/sections/data
     */
    public function data(): JsonResponse
    {
        return response()->json([
            'data' => $this->service->options()
        ]);
    }

    /**
     * Crea una nueva sección
     * POST /api/sections
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $validated['created_by'] = auth()->id();
        $validated['is_active'] = $validated['is_active'] ?? true;
        
        // Auto-generar código si no se proporciona
        if (empty($validated['code'])) {
            $validated['code'] = strtoupper(substr($validated['name'], 0, 3));
        }

        $section = $this->service->create($validated);

        return response()->json([
            'message' => 'Sección creada correctamente.',
            'data' => $section
        ], 201);
    }
}