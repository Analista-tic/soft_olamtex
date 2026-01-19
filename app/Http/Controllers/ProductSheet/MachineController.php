<?php

namespace App\Http\Controllers\ProductSheet;

use App\Http\Controllers\Controller;
use App\Models\ProductSheet\Machine;
use App\Services\ProductSheet\MachineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MachineController extends Controller
{
    public function __construct(protected MachineService $service) {}

    /**
     * Lista todas las opciones de máquinas activas
     * GET /diseno/maquinas/data
     */
    public function data(): JsonResponse
    {
        return response()->json([
            'data' => $this->service->options()
        ]);
    }

    /**
     * Lista máquinas con sus factores de suplemento
     * GET /diseno/maquinas/with-supplements
     */
    public function withSupplements(): JsonResponse
    {
        return response()->json([
            'data' => $this->service->listWithSupplements()
        ]);
    }

    /**
     * Obtiene una máquina con su suplemento
     * GET /diseno/maquinas/{machine}/supplement
     */
    public function getSupplement(Machine $machine): JsonResponse
    {
        return response()->json([
            'data' => $this->service->getWithSupplement($machine->id)
        ]);
    }

    /**
     * Crea una nueva máquina
     * POST /diseno/maquinas
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
            'message' => 'Máquina registrada correctamente.',
            'data' => $machine
        ], 201);
    }

    /**
     * Actualiza una máquina existente
     * PUT /diseno/maquinas/{machine}
     */
    public function update(Request $request, Machine $machine): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['sometimes', 'string', 'max:50', 'unique:machines,code,' . $machine->id],
            'name' => ['sometimes', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $machine = $this->service->update($machine, $validated);

        return response()->json([
            'message' => 'Máquina actualizada correctamente.',
            'data' => $machine
        ]);
    }
}
