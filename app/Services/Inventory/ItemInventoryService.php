<?php

namespace App\Services\Inventory;

use App\Models\Textil\FabricRoll;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ItemInventoryService
{
    private const ALLOWED_SORTS = ['quantity', 'average_cost', 'last_unit_cost', 'total_cost'];

    public function paginate(array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        return $this->orderedQuery($filters)->paginate($perPage);
    }

    /** Mismas filas y orden que la pantalla, sin paginar (exportación a Excel). */
    public function all(array $filters = []): Collection
    {
        return $this->orderedQuery($filters)->get();
    }

    protected function orderedQuery(array $filters)
    {
        $sortBy = in_array($filters['sort_by'] ?? null, self::ALLOWED_SORTS, true)
            ? (string) $filters['sort_by']
            : 'total_cost';
        $sortDir = ($filters['sort_dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        return $this->buildQuery($filters)
            ->orderBy($sortBy, $sortDir)
            ->orderBy('items.name');
    }

    /**
     * Una fila por ítem: suma sus bodegas. Con filtro de bodega solo suma esa bodega.
     */
    protected function buildQuery(array $filters)
    {
        $isFabricSql = "COALESCE(category_sub_groups.material_kind,'') = 'fabric'";

        // roll_quantity / reserved_quantity cuentan solo rollos aprobados (stock disponible).
        // restricted_quantity suma los rollos rechazados en recepción y aún sin resolver
        // (nota crédito pendiente): están fuera del disponible pero se muestran aparte.
        $restrictedCondition = 'fr.tolerance_status = ? AND fr.resolved_at IS NULL';
        $rollSubquery = DB::table('fabric_rolls as fr')
            ->selectRaw('COALESCE(fr.item_id, pdi.item_id) AS item_id')
            ->selectRaw(
                'SUM(CASE WHEN fr.approval_status = ? THEN fr.quantity + fr.length_adjustment_meters - fr.actual_output ELSE 0 END) AS roll_quantity',
                [FabricRoll::APPROVAL_APPROVED],
            )
            // Valor de los metros disponibles: la tela se valora por sus rollos (precio por metro, o el de la línea
            // de compra si el rollo no lo trae), no por el costo de la bodega, que en telas suele estar vacío.
            // Solo cuentan los metros positivos de cada rollo: uno con más consumo que metros (dato inconsistente)
            // no debe restar valor ni dejar un costo promedio negativo.
            ->selectRaw(
                'SUM(CASE WHEN fr.approval_status = ? THEN GREATEST(fr.quantity + fr.length_adjustment_meters - fr.actual_output, 0) * COALESCE(fr.price_per_meter, pdi.price, 0) ELSE 0 END) AS roll_value',
                [FabricRoll::APPROVAL_APPROVED],
            )
            ->selectRaw(
                'SUM(CASE WHEN fr.approval_status = ? THEN GREATEST(fr.quantity + fr.length_adjustment_meters - fr.actual_output, 0) ELSE 0 END) AS roll_priced_quantity',
                [FabricRoll::APPROVAL_APPROVED],
            )
            ->selectRaw(
                'SUM(CASE WHEN fr.approval_status = ? THEN fr.partial_output ELSE 0 END) AS reserved_quantity',
                [FabricRoll::APPROVAL_APPROVED],
            )
            ->selectRaw(
                "SUM(CASE WHEN {$restrictedCondition} THEN fr.quantity ELSE 0 END) AS restricted_quantity",
                [FabricRoll::TOLERANCE_REJECTED],
            )
            ->leftJoin('purchase_document_items as pdi', 'pdi.id', '=', 'fr.purchase_document_item_id')
            ->whereNull('fr.deleted_at')
            ->where(function ($q) use ($restrictedCondition) {
                $q->where('fr.approval_status', FabricRoll::APPROVAL_APPROVED)
                    ->orWhereRaw($restrictedCondition, [FabricRoll::TOLERANCE_REJECTED]);
            })
            ->groupByRaw('COALESCE(fr.item_id, pdi.item_id)');

        // Costo promedio del ítem = valor total de sus bodegas / unidades totales (promedio ponderado).
        $stockValue = 'SUM(warehouse_items.quantity * COALESCE(warehouse_items.weighted_average_price, 0))';
        $stockUnits = 'SUM(warehouse_items.quantity)';
        $warehouseAverageCost = "COALESCE({$stockValue} / NULLIF({$stockUnits}, 0), MAX(COALESCE(warehouse_items.weighted_average_price, 0)), 0)";
        // Telas: promedio ponderado de los rollos disponibles; sin rollos con metros, el de la bodega.
        $averageCost = "CASE WHEN {$isFabricSql} AND COALESCE(MAX(roll_inv.roll_priced_quantity), 0) > 0 THEN MAX(roll_inv.roll_value) / MAX(roll_inv.roll_priced_quantity) ELSE {$warehouseAverageCost} END";

        $query = DB::table('warehouse_items')
            ->join('items', 'items.id', '=', 'warehouse_items.item_id')
            ->join('warehouses', 'warehouses.id', '=', 'warehouse_items.warehouse_id')
            ->leftJoin('category_sub_groups', 'category_sub_groups.id', '=', 'items.category_sub_group_id')
            ->leftJoinSub($rollSubquery, 'roll_inv', 'roll_inv.item_id', '=', 'items.id')
            ->select([
                'items.id',
                'items.id as item_id',
                'items.name as item_name',
                'items.reference',
                'items.status',
                'category_sub_groups.id as category_sub_group_id',
                'category_sub_groups.category_group_id',
                DB::raw("GROUP_CONCAT(DISTINCT warehouses.name ORDER BY warehouses.name SEPARATOR ', ') AS warehouse_name"),
                DB::raw("CASE WHEN {$isFabricSql} THEN COALESCE(MAX(roll_inv.roll_quantity), 0) ELSE {$stockUnits} END AS quantity"),
                DB::raw("CASE WHEN {$isFabricSql} THEN COALESCE(MAX(roll_inv.reserved_quantity), 0) ELSE 0 END AS reserved_quantity"),
                DB::raw("CASE WHEN {$isFabricSql} THEN COALESCE(MAX(roll_inv.restricted_quantity), 0) ELSE 0 END AS restricted_quantity"),
                DB::raw("({$isFabricSql}) AS is_fabric"),
                DB::raw("{$averageCost} AS average_cost"),
                DB::raw('MAX(COALESCE(warehouse_items.last_unit_cost, warehouse_items.weighted_average_price, 0)) AS last_unit_cost'),
                DB::raw("
                    CASE WHEN {$isFabricSql}
                         THEN COALESCE(MAX(roll_inv.roll_value), 0)
                         ELSE COALESCE(SUM(COALESCE(warehouse_items.total_cost, warehouse_items.quantity * COALESCE(warehouse_items.weighted_average_price, 0))), 0)
                    END AS total_cost
                "),
                DB::raw('SUM(warehouse_items.stock_min) AS min_quantity'),
                DB::raw('SUM(warehouse_items.stock_max) AS max_quantity'),
            ])
            ->groupBy(
                'items.id',
                'items.name',
                'items.reference',
                'items.status',
                'category_sub_groups.id',
                'category_sub_groups.name',
                'category_sub_groups.category_group_id',
            );

        if (!empty($filters['warehouse_id'])) {
            $query->where('warehouse_items.warehouse_id', (int) $filters['warehouse_id']);
        }

        if (!empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->where(function ($q) use ($search) {
                $q->where('items.name', 'like', "%{$search}%")
                  ->orWhere('items.reference', 'like', "%{$search}%");
            });
        }

        if (\array_key_exists('status', $filters) && $filters['status'] !== '') {
            $status = filter_var(
                $filters['status'],
                FILTER_VALIDATE_BOOLEAN,
                FILTER_NULL_ON_FAILURE
            );
            if ($status !== null) {
                $query->where('items.status', $status);
            }
        }

        $query->where('items.deleted_at', null);

        // Para ítems normales: excluir bodegas con quantity = 0.
        // Para telas: excluir si no hay metros disponibles NI restringidos en rollos
        // (un ítem con solo stock restringido sigue visible para que la restricción no
        // quede oculta; su `quantity` disponible será 0).
        $query->where(function ($q) {
            $q->where(function ($inner) {
                $inner->whereRaw("COALESCE(category_sub_groups.material_kind,'') <> 'fabric'")
                      ->where('warehouse_items.quantity', '<>', 0);
            })->orWhere(function ($inner) {
                $inner->whereRaw("COALESCE(category_sub_groups.material_kind,'') = 'fabric'")
                      ->whereRaw('(COALESCE(roll_inv.roll_quantity, 0) <> 0 OR COALESCE(roll_inv.restricted_quantity, 0) <> 0)');
            });
        });

        return $query;
    }
}
