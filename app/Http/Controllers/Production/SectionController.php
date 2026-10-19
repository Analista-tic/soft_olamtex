<?php

namespace App\Http\Controllers\Production;

use App\Http\Controllers\Controller;
use App\Services\Production\SectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SectionController extends Controller
{
    public function __construct(protected SectionService $service) {}

    public function data(): JsonResponse
    {
        return response()->json([
            'data' => $this->service->options(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            // Mismo enum que OperationCatalogController — operation_sections.type ya
            // no acepta confeccion/presentacion (ver migración update_operation_sections_enum_type).
            'type' => ['required', 'string', 'in:PREPARACION,ENSAMBLE,TERMINACION'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $validated['is_active'] = $validated['is_active'] ?? true;

        $section = $this->service->create($validated);

        return response()->json([
            'message' => 'Sección creada correctamente.',
            'data' => $section,
        ], 201);
    }
}
