<?php

namespace App\Http\Controllers\Production;

use App\Http\Controllers\Controller;
use App\Models\Production\ProductSheet;
use App\Services\Production\ProductSheetCuttingPieceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductSheetCuttingPieceController extends Controller
{
    public function __construct(
        private ProductSheetCuttingPieceService $service
    ) {}

    /**
     * GET /produccion/sabana-productos/{productSheet}/despiece-corte
     * Obtener piezas de corte de un ProductSheet
     */
    public function show(ProductSheet $productSheet): JsonResponse
    {
        $pieces = $this->service->getCuttingPieces($productSheet->id);

        return response()->json([
            'data' => $pieces->map(function ($piece) {
                return [
                    'id' => $piece->id,
                    'piece_uid' => $piece->piece_uid,
                    'sequence' => $piece->sequence,
                    'name' => $piece->name,
                    'piece_type' => $piece->piece_type,
                    'side' => $piece->side,
                    'grain' => $piece->grain,
                    'quantity' => $piece->quantity,
                    'notes' => $piece->notes,
                ];
            })->toArray(),
        ]);
    }

    /**
     * PUT /produccion/sabana-productos/{productSheet}/despiece-corte
     * Sincronizar piezas de corte
     */
    public function update(Request $request, ProductSheet $productSheet): JsonResponse
    {
        $validated = $request->validate([
            'pieces' => 'nullable|array',
            'pieces.*.piece_uid' => 'nullable|string',
            'pieces.*.sequence' => 'required|integer',
            'pieces.*.name' => 'nullable|string|max:150',
            'pieces.*.piece_type' => 'required|string|max:30',
            'pieces.*.trace_location' => 'nullable|string|max:30',
            'pieces.*.side' => 'required|string|max:20',
            'pieces.*.grain' => 'required|string|max:10',
            'pieces.*.quantity' => 'required|integer',
            'pieces.*.notes' => 'nullable|string',
        ]);

        try {
            $pieces = $this->service->syncCuttingPieces($productSheet->id, $validated['pieces'] ?? []);

            return response()->json([
                'message' => 'Despiece sincronizado correctamente',
                'data' => $pieces->map(function ($piece) {
                    return [
                        'id' => $piece->id,
                        'piece_uid' => $piece->piece_uid,
                        'sequence' => $piece->sequence,
                        'name' => $piece->name,
                        'piece_type' => $piece->piece_type,
                        'side' => $piece->side,
                        'grain' => $piece->grain,
                        'quantity' => $piece->quantity,
                        'notes' => $piece->notes,
                    ];
                })->toArray(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al sincronizar despiece',
                'error' => $e->getMessage(),
            ], 422);
        }
    }
}
