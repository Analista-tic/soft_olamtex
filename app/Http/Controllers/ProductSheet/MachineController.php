<?php

namespace App\Http\Controllers\ProductSheet;

use App\Http\Controllers\Controller;
use App\Services\ProductSheet\MachineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MachineController extends Controller
{
    public function __construct(protected MachineService $service) {}

    /**
     * Lista todas las opciones de máquinas activas
     * GET /api/machines/data
     */
    public function data(): JsonResponse
    {
        return response()->json([
            'data' => $this->service->options()
        ]);
    }

    /**
     * Crea una nueva máquina
     * POST /api/machines
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:machines,code'],
            'name' => ['required', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $validated['created_by'] = auth()->id();
        $validated['is_active'] = $validated['is_active'] ?? true;

        $machine = $this->service->create($validated);

        return response()->json([
            'message' => 'Máquina creada correctamente.',
            'data' => $machine
        ], 201);
    }
}
