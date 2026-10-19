<?php

namespace App\Console\Commands;

use App\Models\Inventory\InventoryMovement;
use App\Models\Inventory\WarehouseItem;
use App\Models\Textil\FabricRoll;
use App\Services\Audit\AuditLogService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ResetRollInventoryCommand extends Command
{
    protected $signature = 'rolls:reset-inventario
                            {--force : Omitir confirmación interactiva}';

    protected $description = 'Reset completo: elimina todos los rollos de tela, sus movimientos kardex y stock en bodega';

    public function handle(): int
    {
        // ── 1. Capturar item_ids ANTES de borrar ──────────────────────────────
        $itemIds = DB::table('fabric_rolls')
            ->whereNotNull('item_id')
            ->distinct()
            ->pluck('item_id')
            ->toArray();

        // ── 2. Conteos para mostrar al operador ───────────────────────────────
        $rollCount  = DB::table('fabric_rolls')->count();
        $adjCount   = DB::table('fabric_roll_adjustments')->count();
        $consCount  = DB::table('production_roll_consumptions')->count();
        $movCount   = count($itemIds) > 0
            ? DB::table('inventory_movements')->whereIn('item_id', $itemIds)->count()
            : 0;
        $stockCount = count($itemIds) > 0
            ? DB::table('warehouse_items')->whereIn('item_id', $itemIds)->count()
            : 0;

        $this->newLine();
        $this->warn('⚠  RESET COMPLETO DE INVENTARIO DE ROLLOS');
        $this->newLine();
        $this->line("  Rollos (fabric_rolls)              : <fg=red>{$rollCount}</>");
        $this->line("  Historial ajustes                  : <fg=red>{$adjCount}</>");
        $this->line("  Consumos en producción             : <fg=red>{$consCount}</>");
        $this->line("  Movimientos kardex (ítems tela)    : <fg=red>{$movCount}</>");
        $this->line("  Registros warehouse_items (stock)  : <fg=red>{$stockCount}</>");
        $this->newLine();
        $this->line('  Ítems afectados: ' . count($itemIds) . ' (el maestro de ítems NO se toca)');
        $this->newLine();

        if (!$this->option('force')) {
            if (!$this->confirm('¿Confirmar reset completo? Esta acción NO se puede deshacer.', false)) {
                $this->info('Operación cancelada.');
                return self::FAILURE;
            }
        }

        // ── 3. Ejecutar en transacción ─────────────────────────────────────────
        DB::transaction(function () use ($itemIds) {
            // Ids capturados antes de borrar para dejar rastro en la auditoría (el borrado
            // por query builder no dispara el AuditObserver).
            $movementIds = [];
            $warehouseItemIds = [];
            $rollIds = DB::table('fabric_rolls')->pluck('id')->all();

            if (count($itemIds) > 0) {
                $movementIds = DB::table('inventory_movements')->whereIn('item_id', $itemIds)->pluck('id')->all();
                $warehouseItemIds = DB::table('warehouse_items')->whereIn('item_id', $itemIds)->pluck('id')->all();

                DB::table('inventory_movements')->whereIn('item_id', $itemIds)->delete();
                DB::table('warehouse_items')->whereIn('item_id', $itemIds)->delete();
            }
            // CASCADE elimina fabric_roll_adjustments y production_roll_consumptions
            DB::statement('DELETE FROM fabric_rolls');

            $audit = app(AuditLogService::class);
            $reason = 'Comando rolls:reset-inventario (reset completo de inventario de rollos)';
            $audit->logBulk(InventoryMovement::class, 'deleted', $movementIds, ['item_ids' => $itemIds], $reason);
            $audit->logBulk(WarehouseItem::class, 'deleted', $warehouseItemIds, ['item_ids' => $itemIds], $reason);
            $audit->logBulk(
                FabricRoll::class,
                'deleted',
                $rollIds,
                ['cascade' => 'fabric_roll_adjustments y production_roll_consumptions eliminados en cascada'],
                $reason,
            );
        });

        $this->newLine();
        $this->info('✔  Reset completado.');
        $this->line("  Rollos eliminados             : {$rollCount}");
        $this->line("  Movimientos kardex eliminados : {$movCount}");
        $this->line("  Registros de stock eliminados : {$stockCount}");
        $this->newLine();

        return self::SUCCESS;
    }
}
