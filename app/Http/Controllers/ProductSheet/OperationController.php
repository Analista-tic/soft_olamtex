<?php

namespace App\Http\Controllers\ProductSheet;

use App\Http\Controllers\Controller;
use App\Services\ProductSheet\OperationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OperationController extends Controller
{
    public function __construct(protected OperationService $service) {}

    /**
     * Lista todas las opciones de operaciones activas
     * GET /api/operations/data
     */
    public function data(): JsonResponse
    {
        return response()->json([
            'data' => $this->service->options()
        ]);
    }

    /**
     * Crea una nueva operación
     * POST /api/operations
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'action' => ['required', 'string', 'max:255'],
            'garment_part' => ['required', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $validated['created_by'] = auth()->id();
        $validated['is_active'] = $validated['is_active'] ?? true;

        $operation = $this->service->create($validated);

        return response()->json([
            'message' => 'Operación creada correctamente.',
            'data' => $operation
        ], 201);
    }
}
