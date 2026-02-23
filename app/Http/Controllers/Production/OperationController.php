<?php

namespace App\Http\Controllers\Production;

use App\Http\Controllers\Controller;
use App\Models\ProductSheet\Operation;
use App\Services\Production\OperationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OperationController extends Controller
{
    public function __construct(protected OperationService $service) {}

    /**
     * Lista todas las opciones de operaciones activas
     * GET /diseno/operaciones/data
     */
    public function data(): JsonResponse
    {
        return response()->json([
            'data' => $this->service->options()
        ]);
    }

    /**
     * Obtiene valores ÃƒÆ’Ã‚Âºnicos de acciones y partes de prenda
     * GET /diseno/operaciones/metadata
     */
    public function metadata(): JsonResponse
    {
        return response()->json([
            'data' => [
                'actions' => $this->service->getUniqueActions(),
                'garment_parts' => $this->service->getUniqueGarmentParts(),
            ]
        ]);
    }

    /**
     * Crea una nueva operaciÃƒÆ’Ã‚Â³n (con validaciÃƒÆ’Ã‚Â³n de duplicados)
     * POST /diseno/operaciones
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

        try {
            $operation = $this->service->create($validated);

            return response()->json([
                'message' => 'OperaciÃƒÆ’Ã‚Â³n registrada correctamente.',
                'data' => $operation
            ], 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'type' => 'warning'
            ], 422);
        }
    }

    /**
     * Actualiza una operaciÃƒÆ’Ã‚Â³n existente
     * PUT /diseno/operaciones/{operation}
     */
    public function update(Request $request, Operation $operation): JsonResponse
    {
        $validated = $request->validate([
            'action' => ['sometimes', 'string', 'max:255'],
            'garment_part' => ['sometimes', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $operation = $this->service->update($operation, $validated);

        return response()->json([
            'message' => 'OperaciÃƒÆ’Ã‚Â³n actualizada correctamente.',
            'data' => $operation
        ]);
    }

    /**
     * Actualiza el tiempo estÃƒÆ’Ã‚Â¡ndar de una operaciÃƒÆ’Ã‚Â³n
     * POST /diseno/operaciones/{operation}/update-standard-time
     */
    public function updateStandardTime(Request $request, Operation $operation): JsonResponse
    {
        $validated = $request->validate([
            'optimal_time' => ['required', 'numeric', 'min:0'],
        ]);

        $operation = $this->service->updateStandardTime(
            $operation, 
            $validated['optimal_time']
        );

        return response()->json([
            'message' => 'Tiempo estÃƒÆ’Ã‚Â¡ndar actualizado y recalculado en todas las listas.',
            'data' => $operation
        ]);
    }
}
