<?php

namespace App\Console\Commands;

use App\Models\Textil\FabricRoll;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CheckFabricInventoryIntegrity extends Command
{
    protected $signature = 'inventory:check-fabric-integrity';

    protected $description = 'Verifica la integridad del inventario de telas: compara warehouse_items con la suma de rollos y detecta ajustes sin movimiento';

    public function handle(): int
    {
        $this->info('Verificando integridad del inventario de telas...');

        $issues = [];

        // 1. Diferencias entre warehouse_items.quantity y suma de rollos
        $fabricItems = DB::table('items')
            ->join('category_sub_groups', 'category_sub_groups.id', '=', 'items.category_sub_group_id')
            ->whereRaw("LOWER(category_sub_groups.name) LIKE '%tela%'")
            ->whereNull('items.deleted_at')
            ->select('items.id as item_id', 'items.name as item_name')
            ->get();

        $rollSums = DB::table('fabric_rolls as fr')
            ->selectRaw('COALESCE(fr.item_id, pdi.item_id) AS item_id')
            ->selectRaw('SUM(fr.quantity - fr.actual_output) AS roll_quantity')
            ->leftJoin('purchase_document_items as pdi', 'pdi.id', '=', 'fr.purchase_document_item_id')
            ->whereNull('fr.deleted_at')
            ->where('fr.approval_status', FabricRoll::APPROVAL_APPROVED)
            ->whereRaw('COALESCE(fr.item_id, pdi.item_id) IS NOT NULL')
            ->groupByRaw('COALESCE(fr.item_id, pdi.item_id)')
            ->pluck('roll_quantity', 'item_id');

        foreach ($fabricItems as $item) {
            $rollQty  = (float) ($rollSums[$item->item_id] ?? 0);
            $wiQty    = (float) DB::table('warehouse_items')
                ->where('item_id', $item->item_id)
                ->sum('quantity');

            if (abs($rollQty - $wiQty) > 0.01) {
                $issues[] = [
                    'tipo'    => 'DIFERENCIA_CANTIDAD',
                    'item_id' => $item->item_id,
                    'nombre'  => $item->item_name,
                    'detalle' => "Rollos: {$rollQty} | WH: {$wiQty} | Diff: " . round($rollQty - $wiQty, 2),
                ];
            }
        }

        // 2. Ajustes de rollo sin inventory_movement_id
        $adjustmentsWithoutMovement = DB::table('fabric_roll_adjustments')
            ->whereNull('inventory_movement_id')
            ->whereIn('type', ['salida', 'entrada'])
            ->count();

        if ($adjustmentsWithoutMovement > 0) {
            $issues[] = [
                'tipo'    => 'AJUSTES_SIN_MOVIMIENTO',
                'item_id' => '-',
                'nombre'  => '-',
                'detalle' => "{$adjustmentsWithoutMovement} ajuste(s) de rollo sin inventory_movement_id asociado",
            ];
        }

        // 3. Rollos huérfanos (activos, aprobados, sin item resolvible)
        $orphanCount = DB::table('fabric_rolls as fr')
            ->leftJoin('purchase_document_items as pdi', 'pdi.id', '=', 'fr.purchase_document_item_id')
            ->whereNull('fr.deleted_at')
            ->where('fr.approval_status', FabricRoll::APPROVAL_APPROVED)
            ->whereRaw('COALESCE(fr.item_id, pdi.item_id) IS NULL')
            ->count();

        if ($orphanCount > 0) {
            $issues[] = [
                'tipo'    => 'ROLLOS_HUERFANOS',
                'item_id' => '-',
                'nombre'  => '-',
                'detalle' => "{$orphanCount} rollo(s) aprobado(s) sin ítem vinculado",
            ];
        }

        if (empty($issues)) {
            $this->info('✅ Inventario de telas íntegro. Sin inconsistencias detectadas.');
            return Command::SUCCESS;
        }

        $this->warn('⚠️  Se encontraron ' . count($issues) . ' inconsistencia(s):');
        $this->table(
            ['Tipo', 'Item ID', 'Nombre', 'Detalle'],
            array_map(fn ($i) => [$i['tipo'], $i['item_id'], $i['nombre'], $i['detalle']], $issues)
        );
        $this->line('');
        $this->line('Para corregir diferencias de cantidad: php artisan inventory:reconcile-fabric-rolls');

        return Command::FAILURE;
    }
}
