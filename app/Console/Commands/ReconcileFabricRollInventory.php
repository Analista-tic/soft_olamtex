<?php

namespace App\Console\Commands;

use App\Models\Inventory\WarehouseItem;
use App\Models\Textil\FabricRoll;
use App\Services\Inventory\InventoryMovementService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReconcileFabricRollInventory extends Command
{
    protected $signature = 'inventory:reconcile-fabric-rolls
        {--dry-run : Solo reporta diferencias sin crear movimientos}';

    protected $description = 'Reconcilia warehouse_items.quantity contra la suma real de metros disponibles en rollos aprobados para ítems de tela';

    public function __construct(private InventoryMovementService $movementService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $this->info($dryRun
            ? '🔍 Modo simulación (--dry-run): no se crearán movimientos.'
            : '⚙️  Aplicando reconciliación...'
        );

        // Obtener ítems de tela (sub-grupo contiene "tela") con rollos aprobados
        $fabricItems = DB::table('items')
            ->join('category_sub_groups', 'category_sub_groups.id', '=', 'items.category_sub_group_id')
            ->whereRaw("LOWER(category_sub_groups.name) LIKE '%tela%'")
            ->whereNull('items.deleted_at')
            ->select('items.id as item_id', 'items.name as item_name')
            ->get();

        if ($fabricItems->isEmpty()) {
            $this->info('No se encontraron ítems de tipo tela.');
            return Command::SUCCESS;
        }

        $this->info("Ítems de tela encontrados: {$fabricItems->count()}");

        $differences = [];
        $orphanRolls = [];

        // Calcular suma de metros disponibles por item_id desde fabric_rolls aprobados
        $rollSums = DB::table('fabric_rolls as fr')
            ->selectRaw('COALESCE(fr.item_id, pdi.item_id) AS item_id')
            ->selectRaw('SUM(fr.quantity + fr.length_adjustment_meters - fr.actual_output) AS roll_quantity')
            ->leftJoin('purchase_document_items as pdi', 'pdi.id', '=', 'fr.purchase_document_item_id')
            ->whereNull('fr.deleted_at')
            ->where('fr.approval_status', FabricRoll::APPROVAL_APPROVED)
            ->whereRaw('COALESCE(fr.item_id, pdi.item_id) IS NOT NULL')
            ->groupByRaw('COALESCE(fr.item_id, pdi.item_id)')
            ->pluck('roll_quantity', 'item_id');

        // Rollos huérfanos (sin item_id resolvible)
        $orphanCount = DB::table('fabric_rolls as fr')
            ->leftJoin('purchase_document_items as pdi', 'pdi.id', '=', 'fr.purchase_document_item_id')
            ->whereNull('fr.deleted_at')
            ->where('fr.approval_status', FabricRoll::APPROVAL_APPROVED)
            ->whereRaw('COALESCE(fr.item_id, pdi.item_id) IS NULL')
            ->count();

        if ($orphanCount > 0) {
            $this->warn("⚠️  {$orphanCount} rollo(s) aprobado(s) sin ítem vinculado (huérfanos). Vincúlalos manualmente.");
        }

        foreach ($fabricItems as $fabricItem) {
            $itemId = $fabricItem->item_id;
            $rollQuantity = (float) ($rollSums[$itemId] ?? 0);

            $warehouseItem = WarehouseItem::where('item_id', $itemId)->first();
            $wiQuantity = $warehouseItem ? (float) $warehouseItem->quantity : 0;

            $diff = $rollQuantity - $wiQuantity;

            if (abs($diff) <= 0.01) {
                continue;
            }

            $differences[] = [
                'item_id'      => $itemId,
                'item_name'    => $fabricItem->item_name,
                'roll_qty'     => $rollQuantity,
                'wi_qty'       => $wiQuantity,
                'diff'         => $diff,
                'warehouse_id' => $warehouseItem?->warehouse_id,
            ];
        }

        if (empty($differences)) {
            $this->info('✅ Todos los ítems de tela están reconciliados. No hay diferencias.');
            return Command::SUCCESS;
        }

        $this->table(
            ['Item ID', 'Nombre', 'Qty Rollos', 'Qty WH', 'Diferencia', 'Bodega'],
            array_map(fn ($d) => [
                $d['item_id'],
                $d['item_name'],
                number_format($d['roll_qty'], 2),
                number_format($d['wi_qty'], 2),
                number_format($d['diff'], 2),
                $d['warehouse_id'] ?? 'N/A',
            ], $differences)
        );

        if ($dryRun) {
            $this->warn("Se encontraron " . count($differences) . " diferencia(s). Ejecuta sin --dry-run para aplicar.");
            return Command::SUCCESS;
        }

        // Crear movimientos de reconciliación
        $applied = 0;
        foreach ($differences as $d) {
            if (!$d['warehouse_id']) {
                $this->warn("  ⚠️  Ítem #{$d['item_id']} ({$d['item_name']}): sin bodega asignada, omitido.");
                continue;
            }

            $qty = abs($d['diff']);
            $type = $d['diff'] > 0 ? 'entrada' : 'salida';

            // Obtener PP actual como costo unitario de referencia
            $currentPP = (float) WarehouseItem::where('item_id', $d['item_id'])
                ->where('warehouse_id', $d['warehouse_id'])
                ->value('weighted_average_price') ?? 0;

            if ($currentPP <= 0) {
                // Fallback: precio por metro del rollo más reciente
                $currentPP = (float) DB::table('fabric_rolls as fr')
                    ->leftJoin('purchase_document_items as pdi', 'pdi.id', '=', 'fr.purchase_document_item_id')
                    ->whereRaw('COALESCE(fr.item_id, pdi.item_id) = ?', [$d['item_id']])
                    ->whereNull('fr.deleted_at')
                    ->where('fr.approval_status', FabricRoll::APPROVAL_APPROVED)
                    ->orderByDesc('fr.id')
                    ->value(DB::raw('COALESCE(fr.price_per_meter, pdi.price, 0)')) ?? 0;
            }

            if ($currentPP <= 0) {
                $this->warn("  ⚠️  Ítem #{$d['item_id']} ({$d['item_name']}): costo promedio = 0, se usará 0.0001 como placeholder.");
                $currentPP = 0.0001;
            }

            try {
                $this->movementService->createMovement([
                    'item_id'          => $d['item_id'],
                    'warehouse_id'     => $d['warehouse_id'],
                    'type'             => $type,
                    'quantity'         => $qty,
                    'unit_price'       => $currentPP,
                    'date'             => now()->toDateString(),
                    'source_type'      => 'inventory_reconciliation',
                    'source_reference' => 'reconcile-fabric-rolls-' . now()->format('Ymd'),
                    'notes'            => 'Ajuste de reconciliación automática – inventario de rollos vs warehouse_items',
                ]);
                $applied++;
                $this->line("  ✔ Ítem #{$d['item_id']} ({$d['item_name']}): {$type} {$qty} mts aplicada.");
            } catch (\Throwable $e) {
                $this->error("  ✘ Ítem #{$d['item_id']}: {$e->getMessage()}");
            }
        }

        $this->info("Reconciliación completada. Movimientos aplicados: {$applied}/" . count($differences));

        return Command::SUCCESS;
    }
}
