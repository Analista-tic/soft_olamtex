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
            ->selectRaw('SUM(fr.quantity + fr.length_adjustment_meters - fr.actual_output) AS roll_quantity')
            ->leftJoin('purchase_document_items as pdi', 'pdi.id', '=', 'fr.purchase_document_item_id')
            ->whereNull('fr.deleted_at')
            ->where('fr.approval_status', FabricRoll::APPROVAL_APPROVED)
            ->whereRaw('COALESCE(fr.item_id, pdi.item_id) IS NOT NULL')
            ->groupByRaw('COALESCE(fr.item_id, pdi.item_id)')
            ->pluck('roll_quantity', 'item_id');

        foreach ($fabricItems as $item) {
            $rollQty = (float) ($rollSums[$item->item_id] ?? 0);
            $wiQty = (float) DB::table('warehouse_items')
                ->where('item_id', $item->item_id)
                ->sum('quantity');

            if (abs($rollQty - $wiQty) > 0.01) {
                $issues[] = [
                    'tipo' => 'DIFERENCIA_CANTIDAD',
                    'item_id' => $item->item_id,
                    'nombre' => $item->item_name,
                    'detalle' => "Rollos: {$rollQty} | WH: {$wiQty} | Diff: ".round($rollQty - $wiQty, 2),
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
                'tipo' => 'AJUSTES_SIN_MOVIMIENTO',
                'item_id' => '-',
                'nombre' => '-',
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
                'tipo' => 'ROLLOS_HUERFANOS',
                'item_id' => '-',
                'nombre' => '-',
                'detalle' => "{$orphanCount} rollo(s) aprobado(s) sin ítem vinculado",
            ];
        }

        // 4. Rollos con sufijo -DUP: el mismo rollo físico registrado dos veces por
        // colisiones en migraciones antiguas. Solo se reportan — consolidarlos exige
        // decidir qué pasa con su inventario y sus movimientos de kardex, y si dos
        // '-DUP' no eran realmente el mismo rollo se perdería trazabilidad.
        $duplicatedRolls = DB::table('fabric_rolls')
            ->whereNull('deleted_at')
            ->where('roll_number', 'like', '%-DUP%')
            ->orderBy('roll_number')
            ->get(['id', 'roll_number', 'quantity', 'length_adjustment_meters', 'actual_output']);

        if ($duplicatedRolls->isNotEmpty()) {
            $issues[] = [
                'tipo' => 'ROLLOS_DUPLICADOS',
                'item_id' => '-',
                'nombre' => '-',
                'detalle' => $duplicatedRolls->count().' rollo(s) con sufijo -DUP (revisión manual, no se consolidan solos)',
            ];
        }

        if (empty($issues)) {
            $this->info('✅ Inventario de telas íntegro. Sin inconsistencias detectadas.');

            return Command::SUCCESS;
        }

        $this->warn('⚠️  Se encontraron '.count($issues).' inconsistencia(s):');
        $this->table(
            ['Tipo', 'Item ID', 'Nombre', 'Detalle'],
            array_map(fn ($i) => [$i['tipo'], $i['item_id'], $i['nombre'], $i['detalle']], $issues)
        );
        if ($duplicatedRolls->isNotEmpty()) {
            $this->line('');
            $this->warn('Rollos con sufijo -DUP (decidir caso por caso, el comando no los toca):');
            $this->table(
                ['ID', 'N° Rollo', 'Cantidad', 'Consumido'],
                $duplicatedRolls->map(fn ($r) => [$r->id, $r->roll_number, (float) $r->quantity + (float) $r->length_adjustment_meters, $r->actual_output])->toArray()
            );
        }

        $this->line('');
        $this->line('Para corregir diferencias de cantidad: php artisan inventory:reconcile-fabric-rolls');

        return Command::FAILURE;
    }
}
