<?php

namespace App\Http\Controllers\Production;

use App\Http\Controllers\Controller;
use App\Models\Production\ProductionOrder;
use App\Models\Production\TechnicalSheet;
use App\Models\Production\TechnicalSheetCuttingPiece;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Despiece de trazo de corte (tabla relacional).
 *
 * Dos endpoints sencillos:
 *  GET  /produccion/ficha-tecnica/{opId}/despiece-corte  → listar piezas
 *  PUT  /produccion/ficha-tecnica/{opId}/despiece-corte  → sync completo (upsert + delete)
 */
class TechnicalSheetCuttingPieceController extends Controller
{
    /**
     * Retorna las piezas de despiece para la OP dada.
     */
    public function index(int $opId): JsonResponse
    {
        ProductionOrder::query()->findOrFail($opId);

        $sheet = TechnicalSheet::where('production_order_id', $opId)->first();

        if (! $sheet) {
            return response()->json(['pieces' => []]);
        }

        return response()->json([
            'pieces' => $sheet->cuttingPieces()->get()->map(fn (TechnicalSheetCuttingPiece $p) => $this->mapPiece($p)),
        ]);
    }

    /**
     * Reemplaza el listado completo de piezas de despiece (bulk-sync).
     *
     * Recibe un array `pieces`; las que tienen `piece_uid` se upsertean,
     * las que no existen en el payload se eliminan.
     */
    public function bulkSync(int $opId, Request $request): JsonResponse
    {
        ProductionOrder::query()->findOrFail($opId);

        $validated = $request->validate([
            'pieces'                       => ['required', 'array'],
            'pieces.*.piece_uid'           => ['required', 'string', 'max:100'],
            'pieces.*.sequence'            => ['nullable', 'integer', 'min:0'],
            'pieces.*.name'                => ['required', 'string', 'max:150'],
            'pieces.*.piece_type'          => ['nullable', 'in:PRINCIPAL,SECUNDARIO'],
            'pieces.*.fabric_type'         => ['nullable', 'in:PRINCIPAL,SECUNDARIA,ENTRETELA,RETAL'],
            'pieces.*.fabric_uid'          => ['nullable', 'string', 'max:100'],
            'pieces.*.trace_location'      => ['nullable', 'in:AL_HILO,AL_DOBLEZ,APROVECHAMIENTO,SESGO'],
            'pieces.*.side'                => ['required', 'in:IZQUIERDA,DERECHA,AMBOS,NO_APLICA'],
            'pieces.*.grain'               => ['required', 'in:90,180,SESGO'],
            'pieces.*.quantity'            => ['nullable', 'integer', 'min:0'],
            'pieces.*.notes'               => ['nullable', 'string', 'max:1000'],
        ]);

        $sheet = TechnicalSheet::firstOrCreate(
            ['production_order_id' => $opId],
            ['status'              => TechnicalSheet::STATUS_DRAFT],
        );

        DB::transaction(function () use ($sheet, $validated) {
            $incomingUids = collect($validated['pieces'])->pluck('piece_uid')->filter()->unique()->values();

            // Eliminar piezas que ya no vienen en el payload
            $sheet->cuttingPieces()
                ->whereNotIn('piece_uid', $incomingUids)
                ->delete();

            // Upsert piezas (actualizar o crear por piece_uid)
            foreach ($validated['pieces'] as $index => $item) {
                $sheet->cuttingPieces()->updateOrCreate(
                    ['piece_uid' => $item['piece_uid']],
                    [
                        'sequence'       => $item['sequence'] ?? $index,
                        'name'           => $item['name'],
                        'piece_type'     => $item['piece_type'] ?? 'PRINCIPAL',
                        'fabric_type'    => $item['fabric_type'] ?? 'PRINCIPAL',
                        'fabric_uid'     => $item['fabric_uid'] ?? null,
                        'trace_location' => $item['trace_location'] ?? null,
                        'side'           => $item['side'],
                        'grain'          => $item['grain'],
                        'quantity'       => $item['quantity'] ?? $this->quantityFromSide($item['side']),
                        'notes'          => $item['notes'] ?? null,
                    ],
                );
            }
        });

        return response()->json([
            'pieces' => $sheet->cuttingPieces()->get()->map(fn (TechnicalSheetCuttingPiece $p) => $this->mapPiece($p)),
        ]);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function mapPiece(TechnicalSheetCuttingPiece $p): array
    {
        return [
            'id'             => (string) ($p->piece_uid ?? $p->id),
            'dbId'           => $p->id,
            'pieceUid'       => $p->piece_uid,
            'sequence'       => $p->sequence,
            'name'           => $p->name,
            'pieceType'      => $p->piece_type,
            'fabricType'     => $p->fabric_type,
            'fabricUid'      => $p->fabric_uid,
            'traceLocation'  => $p->trace_location,
            'side'           => $p->side,
            'grain'          => $p->grain,
            'quantity'       => $p->quantity,
            'notes'          => $p->notes,
        ];
    }

    private function quantityFromSide(string $side): int
    {
        return match ($side) {
            'AMBOS'    => 2,
            'IZQUIERDA', 'DERECHA' => 1,
            default    => 0,
        };
    }
}
