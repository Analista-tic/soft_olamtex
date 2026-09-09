<?php

namespace App\Services\Production;

use App\Models\Production\FabricBase;
use App\Models\Production\ProductSheet;
use App\Models\Production\TechnicalSheetOperation;
use App\Models\Production\TechnicalSheetOperationHistory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Propaga el factor de una base de tela hacia lo que ya existe cuando Diseño lo
 * cambia desde el catálogo de materiales — antes esto no pasaba: una ficha o una
 * operación conservaban el factor con el que se crearon hasta que algo más las
 * volviera a tocar.
 *
 * Alcance (decidido con el usuario): fichas técnicas (product_operation_lists) se
 * recalculan siempre, sin excepción — son plantilla, no tienen concepto de avance.
 * Operaciones de órdenes ya generadas (technical_sheet_operations) se recalculan
 * SOLO si no tienen avance reportado (quantity_progressed 0/null Y progressed_at
 * null) — una operación con avance conserva su factor porque su SAM es la vara con
 * la que se midió ese trabajo; cambiarlo falsearía la eficiencia histórica.
 */
class FabricFactorPropagationService
{
    public function __construct(
        protected ProductOperationListService $operationListService,
    ) {}

    /**
     * Solo lectura: cuántas fichas y operaciones de orden se verían afectadas si se
     * repropagara el factor de esta base ahora mismo. Para el mensaje de
     * confirmación en la UI antes de guardar.
     *
     * @return array{sheets: int, order_operations: int}
     */
    public function preview(FabricBase $fabricBase): array
    {
        return [
            'sheets' => ProductSheet::query()->where('fabric_base_id', $fabricBase->id)->count(),
            'order_operations' => $this->pendingOrderOperationsQuery($fabricBase->id)->count(),
        ];
    }

    /**
     * Aplica el factor actual de $fabricBase a las fichas y operaciones elegibles.
     *
     * @return array{sheets: int, order_operations: int}
     */
    public function propagate(FabricBase $fabricBase): array
    {
        $newFactor = (float) $fabricBase->factor > 0 ? (float) $fabricBase->factor : 1.0;

        return DB::transaction(function () use ($fabricBase, $newFactor) {
            $sheetIds = ProductSheet::query()->where('fabric_base_id', $fabricBase->id)->pluck('id');

            foreach ($sheetIds as $sheetId) {
                $this->operationListService->recalculateFabricFactorForSheet((int) $sheetId, $fabricBase->id);
            }

            $touched = 0;
            $operations = $this->pendingOrderOperationsQuery($fabricBase->id)
                ->with('technicalSheet.productionOrder:id,units')
                ->get();

            foreach ($operations as $operation) {
                if (abs((float) $operation->fabric_base_factor - $newFactor) < 0.00001) {
                    continue;
                }

                $before = $operation->getAttributes();
                $quantityLote = max(1, (int) ($operation->technicalSheet?->productionOrder?->units ?? 1));

                $operation->fabric_base_factor = $newFactor;
                $operation->sam_total_unitario = round(
                    (float) $operation->sam_base * (float) $operation->machine_supplement_factor * $newFactor * (float) $operation->operator_efficiency_factor,
                    4,
                );
                $operation->sam_total_operacion = round($operation->sam_total_unitario * (int) $operation->quantity, 4);
                $operation->sam_total_lote = round($operation->sam_total_operacion * $quantityLote, 4);
                $operation->save();

                TechnicalSheetOperationHistory::create([
                    'technical_sheet_id' => $operation->technical_sheet_id,
                    'technical_sheet_operation_id' => $operation->id,
                    'event_type' => 'FABRIC_FACTOR_PROPAGATED',
                    'changed_by_user_id' => auth()->id(),
                    'reason' => "Cambio de factor en la base de tela \"{$fabricBase->nombre}\" (Datos Maestros → Catálogo de materiales).",
                    'before_fabric_base_factor' => $before['fabric_base_factor'],
                    'before_sam_total_unitario' => $before['sam_total_unitario'],
                    'before_sam_total_operacion' => $before['sam_total_operacion'],
                    'before_sam_total_lote' => $before['sam_total_lote'],
                    'after_fabric_base_factor' => $operation->fabric_base_factor,
                    'after_sam_total_unitario' => $operation->sam_total_unitario,
                    'after_sam_total_operacion' => $operation->sam_total_operacion,
                    'after_sam_total_lote' => $operation->sam_total_lote,
                ]);

                $touched++;
            }

            return [
                'sheets' => $sheetIds->count(),
                'order_operations' => $touched,
            ];
        });
    }

    private function pendingOrderOperationsQuery(int $fabricBaseId): Builder
    {
        return TechnicalSheetOperation::query()
            ->whereHas(
                'technicalSheet.productionOrder.productSheet',
                fn ($q) => $q->where('fabric_base_id', $fabricBaseId),
            )
            ->where(fn ($q) => $q->where('quantity_progressed', '<=', 0)->orWhereNull('quantity_progressed'))
            ->whereNull('progressed_at');
    }
}
