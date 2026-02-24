<?php

namespace App\Services\Production;

use App\Enums\EstatusOperacionOrden;
use App\Models\Production\Programmer;
use App\Models\Production\ProductionOrderOperation;
use App\Services\Production\ProductOperationListService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Servicio para crear y gestionar snapshots congelados de operaciones (ProductionOrderOperation)
 * a partir de un Programmer y su listado operacional (ProductSheetOperation).
 *
 * ProductionOrder en documentación = Programmer en código (ver .github/copilot-instructions.md).
 */
class ProductionOrderService
{
    public function __construct(
        protected ProductOperationListService $listService,
    ) {
    }

    /**
     * Crea o reemplaza el snapshot de operaciones para un programador (orden de producción).
     * Copia cada ProductSheetOperation del product_sheet del programador a ProductionOrderOperation
     * con los valores congelados del momento.
     */
    public function createSnapshotForProgrammer(Programmer $programmer): Collection
    {
        $productSheetId = $programmer->product_sheet_id;
        if (!$productSheetId) {
            return collect();
        }

        $operations = $this->listService->listBySheet($productSheetId);
        $quantityLote = max(1, (int) ($programmer->units ?? 1));

        return DB::transaction(function () use ($programmer, $operations, $quantityLote) {
            ProductionOrderOperation::where('programmer_id', $programmer->id)->forceDelete();

            return $operations->map(function ($psOp) use ($programmer, $quantityLote) {
                $samTotalOperacion = (float) ($psOp->sam_total_operacion ?? $psOp->sam_total_unitario * ($psOp->quantity ?? 1));
                $samTotalLote = round($samTotalOperacion * $quantityLote, 4);

                return ProductionOrderOperation::create([
                    'programmer_id' => $programmer->id,
                    'product_sheet_operation_id' => $psOp->id,
                    'sequence' => $psOp->sequence,
                    'operation_id' => $psOp->operation_id,
                    'machine_id' => $psOp->machine_id,
                    'quantity' => $psOp->quantity ?? 1,
                    'sam_base' => $psOp->sam_base ?? $psOp->sam ?? $psOp->optimal_time ?? 0,
                    'machine_supplement_factor' => $psOp->machine_supplement_factor ?? 1.0,
                    'fabric_base_factor' => $psOp->fabric_base_factor ?? 1.0,
                    'operator_efficiency_factor' => $psOp->operator_efficiency_factor ?? 1.0,
                    'sam_total_unitario' => $psOp->sam_total_unitario ?? 0,
                    'sam_total_operacion' => $samTotalOperacion,
                    'sam_total_lote' => $samTotalLote,
                    'quantity_progressed' => 0,
                    'status' => EstatusOperacionOrden::PENDIENTE,
                ]);
            });
        });
    }

    /**
     * Obtiene las operaciones (snapshot read-only) de un programador con relaciones cargadas.
     */
    public function getOperationsByProgrammer(int $programmerId): Collection
    {
        return ProductionOrderOperation::byOrder($programmerId)
            ->with(['operation', 'machine', 'productSheetOperation'])
            ->get();
    }

    /**
     * Actualiza SOLO el progreso de una operación del snapshot.
     * Es la única modificación permitida en datos congelados.
     */
    public function updateProgressOnly(
        ProductionOrderOperation $operation,
        int $quantityProgressed,
        string $motivo,
    ): ProductionOrderOperation {
        $operation->updateProgressOnly($quantityProgressed);
        return $operation->fresh();
    }

    /**
     * SAM total del lote para un programador (suma de sam_total_lote de sus operaciones).
     */
    public function getTotalSamLote(int $programmerId): float
    {
        return (float) ProductionOrderOperation::where('programmer_id', $programmerId)
            ->sum('sam_total_lote');
    }
}
