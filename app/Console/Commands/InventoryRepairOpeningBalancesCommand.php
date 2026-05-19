<?php

namespace App\Console\Commands;

use App\Models\Inventory\InventoryMovement;
use App\Models\Inventory\WarehouseItem;
use App\Services\Inventory\InventoryMovementService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class InventoryRepairOpeningBalancesCommand extends Command
{
    protected $signature = 'inventory:repair-opening-balances
        {--apply : Aplica cambios. Sin esta opción solo simula}
        {--item_id= : Filtrar por item_id}
        {--warehouse_id= : Filtrar por warehouse_id}
        {--date= : Fecha de saldo inicial (YYYY-MM-DD). Por defecto hoy}
        {--source_reference= : Referencia del proceso (ej. MIG-20260519)}';

    protected $description = 'Repara saldos iniciales para item+bodega con stock positivo y PP nulo/cero, creando movimientos de entrada auditables';

    public function __construct(private InventoryMovementService $movementService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $itemId = $this->option('item_id');
        $warehouseId = $this->option('warehouse_id');
        $date = (string) ($this->option('date') ?: now()->toDateString());
        $sourceReference = (string) ($this->option('source_reference') ?: ('opening-balance-repair-' . now()->format('Ymd-His')));
        $hasItemCostPrice = Schema::hasColumn('items', 'cost_price');

        $query = WarehouseItem::query()
            ->with('item:id,name')
            ->where('quantity', '>', 0)
            ->where(function ($q) {
                $q->whereNull('weighted_average_price')
                    ->orWhere('weighted_average_price', '<=', 0);
            });

        if ($itemId !== null && $itemId !== '') {
            $query->where('item_id', (int) $itemId);
        }
        if ($warehouseId !== null && $warehouseId !== '') {
            $query->where('warehouse_id', (int) $warehouseId);
        }

        $rows = $query->orderBy('id')->get();
        if ($rows->isEmpty()) {
            $this->info('No se encontraron registros para saneamiento.');
            return self::SUCCESS;
        }

        $this->line('Registros candidatos: ' . $rows->count());
        $this->line($apply ? 'Modo: APPLY (se aplicarán cambios)' : 'Modo: DRY-RUN (solo simulación)');

        $eligible = 0;
        $skippedWithMovements = 0;
        $skippedWithoutCost = 0;
        $repaired = 0;
        $failed = 0;

        foreach ($rows as $row) {
            $currentQty = (float) $row->quantity;
            if ($currentQty <= 0) {
                continue;
            }

            $hasMovements = InventoryMovement::query()
                ->where('item_id', (int) $row->item_id)
                ->where('warehouse_id', (int) $row->warehouse_id)
                ->exists();

            if ($hasMovements) {
                $skippedWithMovements++;
                if ($this->getOutput()->isVerbose()) {
                    $this->warn("SKIP [warehouse_item_id={$row->id}] item={$row->item_id} wh={$row->warehouse_id}: ya tiene movimientos.");
                }
                continue;
            }

            $candidatePrice = $this->resolveCandidatePrice($row, $hasItemCostPrice);
            if ($candidatePrice <= 0) {
                $skippedWithoutCost++;
                if ($this->getOutput()->isVerbose()) {
                    $this->error("SKIP [warehouse_item_id={$row->id}] item={$row->item_id} wh={$row->warehouse_id}: sin costo base (last_unit_cost/cost_price).");
                }
                continue;
            }

            $eligible++;
            if ($this->getOutput()->isVerbose()) {
                $this->line("PLAN [warehouse_item_id={$row->id}] item={$row->item_id} wh={$row->warehouse_id} qty={$currentQty} unit_price={$candidatePrice}");
            }

            if (!$apply) {
                continue;
            }

            try {
                DB::transaction(function () use ($row, $currentQty, $candidatePrice, $date, $sourceReference) {
                    WarehouseItem::query()
                        ->where('id', (int) $row->id)
                        ->update([
                            'quantity' => 0,
                            'weighted_average_price' => null,
                            'total_cost' => 0,
                            'source_type' => 'opening_balance_repair',
                            'source_reference' => $sourceReference,
                            'updated_at' => now(),
                        ]);

                    $this->movementService->createInitialBalanceMovement([
                        'item_id' => (int) $row->item_id,
                        'warehouse_id' => (int) $row->warehouse_id,
                        'quantity' => $currentQty,
                        'unit_price' => $candidatePrice,
                        'date' => $date,
                        'source_reference' => $sourceReference,
                        'notes' => 'Reparación de saldo inicial desde warehouse_items sin PP',
                    ]);
                });

                $repaired++;
            } catch (\Throwable $e) {
                $failed++;
                $this->error("FAIL [warehouse_item_id={$row->id}] item={$row->item_id} wh={$row->warehouse_id}: {$e->getMessage()}");
            }
        }

        $this->newLine();
        $this->info('Resumen saneamiento:');
        $this->line("- Candidatos: {$rows->count()}");
        $this->line("- Elegibles: {$eligible}");
        $this->line("- Skip (con movimientos): {$skippedWithMovements}");
        $this->line("- Skip (sin costo): {$skippedWithoutCost}");
        $this->line("- Reparados: {$repaired}");
        $this->line("- Fallidos: {$failed}");

        if (!$apply) {
            $this->warn('No se aplicaron cambios. Ejecuta con --apply para confirmar la reparación.');
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function resolveCandidatePrice(mixed $row, bool $hasItemCostPrice): float
    {
        if (!is_object($row)) {
            return 0.0;
        }

        $lastUnitCost = isset($row->last_unit_cost) ? (float) $row->last_unit_cost : 0.0;
        if ($lastUnitCost > 0) {
            return $lastUnitCost;
        }

        if ($hasItemCostPrice && isset($row->item_id)) {
            $itemCostPrice = (float) (DB::table('items')->where('id', (int) $row->item_id)->value('cost_price') ?? 0);
            if ($itemCostPrice > 0) {
                return $itemCostPrice;
            }
        }

        return 0.0;
    }
}
