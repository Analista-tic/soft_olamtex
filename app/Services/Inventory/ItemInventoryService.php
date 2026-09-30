<?php

namespace App\Services\Inventory;

use App\Models\Textil\FabricRoll;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ItemInventoryService
{
    public function paginate(array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        $allowedSorts = ['quantity', 'average_cost', 'last_unit_cost', 'total_cost'];
        $sortBy = in_array($filters['sort_by'] ?? null, $allowedSorts, true)
            ? (string) $filters['sort_by']
            : 'total_cost';
        $sortDir = ($filters['sort_dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        return $this->buildQuery($filters)
            ->orderBy($sortBy, $sortDir)
            ->orderBy('warehouses.name')
            ->orderBy('items.name')
            ->paginate($perPage);
    }

    protected function buildQuery(array $filters)
    {
        // roll_quantity / reserved_quantity cuentan solo rollos aprobados (stock disponible).
        // restricted_quantity suma los rollos rechazados en recepción y aún sin resolver
        // (nota crédito pendiente): están fuera del disponible pero se muestran aparte.
        $restrictedCondition = 'fr.tolerance_status = ? AND fr.resolved_at IS NULL';
        $rollSubquery = DB::table('fabric_rolls as fr')
            ->selectRaw('COALESCE(fr.item_id, pdi.item_id) AS item_id')
            ->selectRaw(
                'SUM(CASE WHEN fr.approval_status = ? THEN fr.quantity - fr.actual_output ELSE 0 END) AS roll_quantity',
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

        $query = DB::table('warehouse_items')
            ->join('items', 'items.id', '=', 'warehouse_items.item_id')
            ->join('warehouses', 'warehouses.id', '=', 'warehouse_items.warehouse_id')
            ->leftJoin('category_sub_groups',
                       'category_sub_groups.id', '=',
                       'items.category_sub_group_id')
            ->leftJoinSub($rollSubquery, 'roll_inv', 'roll_inv.item_id', '=', 'items.id')
            ->select([
                'warehouse_items.id',
                'items.id as item_id',
                'items.name as item_name',
                'items.reference',
                'items.status',
                'warehouses.id as warehouse_id',
                'warehouses.name as warehouse_name',
                'category_sub_groups.id as category_sub_group_id',
                'category_sub_groups.category_group_id',
                DB::raw("
                    CASE WHEN LOWER(COALESCE(category_sub_groups.name,'')) LIKE '%tela%'
                         THEN COALESCE(roll_inv.roll_quantity, 0)
                         ELSE warehouse_items.quantity
                    END AS quantity
                "),
                DB::raw("
                    CASE WHEN LOWER(COALESCE(category_sub_groups.name,'')) LIKE '%tela%'
                         THEN COALESCE(roll_inv.reserved_quantity, 0)
                         ELSE 0
                    END AS reserved_quantity
                "),
                DB::raw("
                    CASE WHEN LOWER(COALESCE(category_sub_groups.name,'')) LIKE '%tela%'
                         THEN COALESCE(roll_inv.restricted_quantity, 0)
                         ELSE 0
                    END AS restricted_quantity
                "),
                DB::raw("(LOWER(COALESCE(category_sub_groups.name,'')) LIKE '%tela%') AS is_fabric"),
                DB::raw('COALESCE(warehouse_items.weighted_average_price, 0) as average_cost'),
                DB::raw('COALESCE(warehouse_items.last_unit_cost, warehouse_items.weighted_average_price, 0) as last_unit_cost'),
                DB::raw("
                    COALESCE(
                        CASE WHEN LOWER(COALESCE(category_sub_groups.name,'')) LIKE '%tela%'
                             THEN COALESCE(roll_inv.roll_quantity, 0) * COALESCE(warehouse_items.weighted_average_price, 0)
                             ELSE warehouse_items.total_cost
                        END,
                        warehouse_items.quantity * COALESCE(warehouse_items.weighted_average_price, 0),
                        0
                    ) AS total_cost
                "),
                'warehouse_items.stock_min as min_quantity',
                'warehouse_items.stock_max as max_quantity',
                'warehouse_items.source_type',
                'warehouse_items.source_reference',
            ]);

        if (!empty($filters['warehouse_id'])) {
            $query->where('warehouse_items.warehouse_id',
                          (int) $filters['warehouse_id']);
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

        // Para ítems normales: excluir si quantity = 0.
        // Para telas: excluir si no hay metros disponibles NI restringidos en rollos
        // (un ítem con solo stock restringido sigue visible para que la restricción no
        // quede oculta; su `quantity` disponible será 0).
        $query->where(function ($q) {
            $q->where(function ($inner) {
                $inner->whereRaw("LOWER(COALESCE(category_sub_groups.name,'')) NOT LIKE '%tela%'")
                      ->where('warehouse_items.quantity', '<>', 0);
            })->orWhere(function ($inner) {
                $inner->whereRaw("LOWER(COALESCE(category_sub_groups.name,'')) LIKE '%tela%'")
                      ->whereRaw("(COALESCE(roll_inv.roll_quantity, 0) <> 0 OR COALESCE(roll_inv.restricted_quantity, 0) <> 0)");
            });
        });

        return $query;
    }
}
