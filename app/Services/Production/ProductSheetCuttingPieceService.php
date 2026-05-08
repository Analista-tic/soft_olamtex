<?php

namespace App\Services\Production;

use App\Models\Production\ProductSheet;
use App\Models\Production\ProductSheetCuttingPiece;
use App\Models\Production\ProductSheetCuttingPieceSize;
use Illuminate\Support\Collection;

class ProductSheetCuttingPieceService
{
    /**
     * Obtener ProductSheet por ID
     */
    public function getProductSheet(int $productSheetId): ?ProductSheet
    {
        return ProductSheet::find($productSheetId);
    }

    /**
     * Obtener todas las piezas de corte de un ProductSheet
     */
    public function getCuttingPieces(int $productSheetId): Collection
    {
        return ProductSheetCuttingPiece::where('product_sheet_id', $productSheetId)
            ->orderBy('sequence')
            ->with('sizes')
            ->get();
    }

    /**
     * Sincronizar piezas de corte (upsert + delete de ausentes)
     * 
     * @param int $productSheetId
     * @param array $piecesData Array de piezas con estructura:
     *   [
     *     'piece_uid' => 'uid1',
     *     'sequence' => 1,
     *     'name' => 'Pieza 1',
     *     'piece_type' => 'PRINCIPAL',
     *     'fabric_type' => 'PRINCIPAL',
     *     'fabric_uid' => 'fab123',
     *     'trace_location' => null,
     *     'side' => 'AMBOS',
     *     'grain' => '90',
     *     'quantity' => 2,
     *     'notes' => 'Notas',
     *     'sizes' => [
     *       ['size' => 'S', 'units' => 5, 'curve' => null],
     *       ['size' => 'M', 'units' => 10, 'curve' => 'C1'],
     *     ]
     *   ]
     * @return Collection de piezas sincronizadas
     */
    public function syncCuttingPieces(int $productSheetId, array $piecesData): Collection
    {
        // Validar que el ProductSheet existe
        $productSheet = $this->getProductSheet($productSheetId);
        if (!$productSheet) {
            throw new \Exception("ProductSheet with ID {$productSheetId} not found");
        }

        // Si no vienen piezas, eliminar todas las existentes y retornar vacío
        if (empty($piecesData)) {
            ProductSheetCuttingPiece::where('product_sheet_id', $productSheetId)->delete();
            return collect();
        }

        $upsertedPieceUids = [];

        foreach ($piecesData as $pieceData) {
            // Determinar si es upsert o insert
            $pieceUid = $pieceData['piece_uid'] ?? null;
            
            $piecePayload = [
                'product_sheet_id' => $productSheetId,
                'piece_uid' => $pieceUid,
                'sequence' => $pieceData['sequence'] ?? 0,
                'name' => $pieceData['name'] ?? '',
                'piece_type' => $pieceData['piece_type'] ?? 'PRINCIPAL',
                'trace_location' => $pieceData['trace_location'] ?? null,
                'side' => $pieceData['side'] ?? 'AMBOS',
                'grain' => $pieceData['grain'] ?? '90',
                'quantity' => $pieceData['quantity'] ?? 1,
                'notes' => $pieceData['notes'] ?? null,
            ];

            if ($pieceUid) {
                // Upsert by piece_uid
                $piece = ProductSheetCuttingPiece::updateOrCreate(
                    [
                        'product_sheet_id' => $productSheetId,
                        'piece_uid' => $pieceUid,
                    ],
                    $piecePayload
                );
                $upsertedPieceUids[] = $pieceUid;
            } else {
                // Insert nuevo
                $piece = ProductSheetCuttingPiece::create($piecePayload);
            }

            // Sincronizar sizes si existen
            if (!empty($pieceData['sizes'])) {
                $this->syncPieceSizes($piece->id, $pieceData['sizes']);
            }
        }

        // Eliminar piezas ausentes (que no están en la sincronización)
        // Solo si hay piece_uid definido (para no borrar IDs nuevos sin UID)
        if (!empty($upsertedPieceUids)) {
            ProductSheetCuttingPiece::where('product_sheet_id', $productSheetId)
                ->whereNotNull('piece_uid')
                ->whereNotIn('piece_uid', $upsertedPieceUids)
                ->delete();
        }

        return $this->getCuttingPieces($productSheetId);
    }

    /**
     * Sincronizar sizes de una pieza de corte
     * 
     * @param int $cuttingPieceId
     * @param array $sizesData Array de sizes con estructura:
     *   [
     *     ['size' => 'S', 'units' => 5, 'curve' => null],
     *     ['size' => 'M', 'units' => 10, 'curve' => 'C1'],
     *   ]
     */
    public function syncPieceSizes(int $cuttingPieceId, array $sizesData): void
    {
        // Validar que la pieza existe
        $piece = ProductSheetCuttingPiece::find($cuttingPieceId);
        if (!$piece) {
            throw new \Exception("ProductSheetCuttingPiece with ID {$cuttingPieceId} not found");
        }

        // Eliminar sizes existentes
        ProductSheetCuttingPieceSize::where('cutting_piece_id', $cuttingPieceId)->delete();

        // Crear sizes nuevos
        foreach ($sizesData as $sizeData) {
            ProductSheetCuttingPieceSize::create([
                'cutting_piece_id' => $cuttingPieceId,
                'size' => $sizeData['size'] ?? '',
                'units' => $sizeData['units'] ?? 0,
                'curve' => $sizeData['curve'] ?? null,
            ]);
        }
    }

    /**
     * Obtener payload normalizado de piezas para frontend/PDF
     */
    public function getNormalizedPayload(int $productSheetId): array
    {
        $pieces = $this->getCuttingPieces($productSheetId);

        return $pieces->map(function (ProductSheetCuttingPiece $piece) {
            return [
                'id' => $piece->id,
                'piece_uid' => $piece->piece_uid,
                'sequence' => $piece->sequence,
                'name' => $piece->name,
                'piece_type' => $piece->piece_type,
                'fabric_type' => $piece->fabric_type,
                'fabric_uid' => $piece->fabric_uid,
                'trace_location' => $piece->trace_location,
                'side' => $piece->side,
                'grain' => $piece->grain,
                'quantity' => $piece->quantity,
                'notes' => $piece->notes,
                'sizes' => $piece->sizes->map(function (ProductSheetCuttingPieceSize $size) {
                    return [
                        'id' => $size->id,
                        'size' => $size->size,
                        'units' => $size->units,
                        'curve' => $size->curve,
                    ];
                })->toArray(),
            ];
        })->toArray();
    }
}
