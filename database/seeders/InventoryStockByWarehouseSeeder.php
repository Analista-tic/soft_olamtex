<?php

namespace Database\Seeders;

use App\Services\Inventory\InventoryMovementService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class InventoryStockByWarehouseSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('🏭 Iniciando InventoryStockByWarehouseSeeder...');

        if (!Schema::hasTable('inventory_movements') || !Schema::hasTable('items') || !Schema::hasTable('warehouses')) {
            $this->command->warn('⚠️ Tablas requeridas no existen. Saltando seeder.');
            return;
        }

        $userId = DB::table('users')->value('id');
        if (!$userId) {
            $this->command->warn('⚠️ No hay usuarios en la BD. Saltando seeder.');
            return;
        }

        $this->command->info("✓ Usuario ID: {$userId}");

        $warehouseProfiles = [
            'Principal' => ['qty_multiplier' => 1.0, 'price_multiplier' => 1.0],
            'Ferias' => ['qty_multiplier' => 0.35, 'price_multiplier' => 1.03],
            'Local Comercial 1' => ['qty_multiplier' => 0.25, 'price_multiplier' => 1.05],
            'Secundario' => ['qty_multiplier' => 0.4, 'price_multiplier' => 1.02],
        ];

        $warehouseIds = [];
        foreach (array_keys($warehouseProfiles) as $warehouseName) {
            $warehouseId = DB::table('warehouses')->where('name', $warehouseName)->value('id');
            if ($warehouseId) {
                $warehouseIds[$warehouseName] = (int) $warehouseId;
                $this->command->info("  ✓ Bodega '{$warehouseName}' encontrada (ID: {$warehouseId})");
            } else {
                $this->command->warn("  ⚠️ Bodega '{$warehouseName}' no existe");
            }
        }

        if (empty($warehouseIds)) {
            $this->command->warn('⚠️ No se encontraron bodegas. Saltando seeder.');
            return;
        }

        $stockPlanByReference = [
            // Telas ItemsSeeder
            'TEL-ALG-AZUL' => ['quantity' => 200, 'unit_price' => 18500],
            'TEL-LINO-BLN' => ['quantity' => 150, 'unit_price' => 22000],
            'TEL-POLY-NGR' => ['quantity' => 180, 'unit_price' => 16000],
            'TEL-SEDA-RSA' => ['quantity' => 100, 'unit_price' => 35000],
            'TEL-ALG-VRD' => ['quantity' => 190, 'unit_price' => 19000],
            
            // Hilos ItemsSeeder
            'HIL-POLI-NGR' => ['quantity' => 300, 'unit_price' => 4500],
            'HIL-ALG-BLC' => ['quantity' => 350, 'unit_price' => 4200],
            'HIL-NYL-AZL' => ['quantity' => 250, 'unit_price' => 5000],
            
            // Botones ItemsSeeder
            'BOT-PLAS-ROJ' => ['quantity' => 5000, 'unit_price' => 150],
            'BOT-MAD-NAT' => ['quantity' => 3000, 'unit_price' => 250],
            
            // Etiquetas ItemsSeeder
            'ETQ-TEJ-BLN' => ['quantity' => 2000, 'unit_price' => 300],
            'ETQ-IMP-NGR' => ['quantity' => 2500, 'unit_price' => 200],
            
            // Cierres ItemsSeeder
            'CIE-MET-PLT' => ['quantity' => 1500, 'unit_price' => 800],
            'CIE-PLA-NGR' => ['quantity' => 1800, 'unit_price' => 600],
            
            // Telas FabricItemsDemoSeeder
            'TELA1' => ['quantity' => 250, 'unit_price' => 20000],
            'TELA2' => ['quantity' => 220, 'unit_price' => 25000],
            'TELA3' => ['quantity' => 200, 'unit_price' => 22500],
            'TELA4' => ['quantity' => 180, 'unit_price' => 28000],
            'TELA5' => ['quantity' => 150, 'unit_price' => 32000],
            'TELA6' => ['quantity' => 190, 'unit_price' => 21000],
            'TELA7' => ['quantity' => 210, 'unit_price' => 19500],
            'TELA8' => ['quantity' => 160, 'unit_price' => 26000],
            'TELA9' => ['quantity' => 140, 'unit_price' => 38000],
            'TELA10' => ['quantity' => 170, 'unit_price' => 30000],
            'TELA11' => ['quantity' => 200, 'unit_price' => 23000],
            'TELA12' => ['quantity' => 130, 'unit_price' => 33000],
            
            // Insumos SupplyItemsDemoSeeder
            'ETQ-CARE-001' => ['quantity' => 3000, 'unit_price' => 250],
            'ETQ-SIZE-001' => ['quantity' => 4000, 'unit_price' => 180],
            'BOT-PLAS-24' => ['quantity' => 6000, 'unit_price' => 120],
            'BOT-MET-15' => ['quantity' => 4000, 'unit_price' => 200],
            'BOT-MAD-PEQ' => ['quantity' => 2500, 'unit_price' => 280],
            'CIE-MET-15' => ['quantity' => 2000, 'unit_price' => 900],
            'CIE-MET-20' => ['quantity' => 1800, 'unit_price' => 1100],
            'CIE-PLAS-18' => ['quantity' => 2200, 'unit_price' => 650],
            'ELA-PRET-25' => ['quantity' => 500, 'unit_price' => 3500],
            'ELA-CUEL-12' => ['quantity' => 600, 'unit_price' => 2800],
            'CIN-BIIS-25' => ['quantity' => 400, 'unit_price' => 4000],
            'CIN-REF-20' => ['quantity' => 350, 'unit_price' => 5500],
            'COR-RED-4' => ['quantity' => 800, 'unit_price' => 2500],
            'COR-PLAN-12' => ['quantity' => 700, 'unit_price' => 3200],
            'BOL-POL-40' => ['quantity' => 5000, 'unit_price' => 100],
            'BOL-TELA-M' => ['quantity' => 1000, 'unit_price' => 1500],
            'MAR-HILA-001' => ['quantity' => 3500, 'unit_price' => 220],
            'MAR-PREC-001' => ['quantity' => 4500, 'unit_price' => 80],
        ];

        $this->command->info("📦 Procesando stock para " . count($stockPlanByReference) . " referencias en " . count($warehouseIds) . " bodegas...");

        $movementService = app(InventoryMovementService::class);
        $itemsProcessed = 0;
        $movementsCreated = 0;
        $itemsSkipped = 0;

        foreach ($stockPlanByReference as $reference => $base) {
            $itemId = DB::table('items')->where('reference', $reference)->value('id');
            if (!$itemId) {
                $this->command->warn("  ⚠️ Item '{$reference}' no existe en BD");
                continue;
            }

            $itemName = DB::table('items')->where('id', $itemId)->value('name');
            $this->command->info("  📦 {$reference} ({$itemName})");

            foreach ($warehouseIds as $warehouseName => $warehouseId) {
                $currentStock = (float) (DB::table('warehouse_items')
                    ->where('item_id', $itemId)
                    ->where('warehouse_id', $warehouseId)
                    ->value('quantity') ?? 0);

                if ($currentStock > 0) {
                    $this->command->line("    → {$warehouseName}: Ya tiene stock ({$currentStock} unidades) - omitiendo");
                    $itemsSkipped++;
                    continue;
                }

                $profile = $warehouseProfiles[$warehouseName];
                $seedQuantity = max(1, (int) round($base['quantity'] * $profile['qty_multiplier']));
                $seedUnitPrice = round($base['unit_price'] * $profile['price_multiplier'], 2);

                try {
                    $movementService->createMovement([
                        'item_id' => (int) $itemId,
                        'warehouse_id' => $warehouseId,
                        'type' => 'entrada',
                        'quantity' => $seedQuantity,
                        'unit_price' => $seedUnitPrice,
                        'notes' => 'Stock inicial por bodega (seeder)',
                        'created_by_user_id' => (int) $userId,
                    ]);

                    $stockMin = max(1, (int) floor($seedQuantity * 0.2));
                    $stockMax = max($seedQuantity + 1, (int) ceil($seedQuantity * 1.8));

                    DB::table('warehouse_items')
                        ->where('item_id', $itemId)
                        ->where('warehouse_id', $warehouseId)
                        ->update([
                            'stock_min' => $stockMin,
                            'stock_max' => $stockMax,
                            'updated_at' => now(),
                        ]);

                    $this->command->line("    → {$warehouseName}: ✓ Cargado {$seedQuantity} unids @ \${$seedUnitPrice}");
                    $movementsCreated++;
                } catch (\Throwable $e) {
                    $this->command->error("    → {$warehouseName}: ✗ Error: {$e->getMessage()}");
                    continue;
                }
            }
            $itemsProcessed++;
        }

        $this->command->info("✅ Proceso completado:");
        $this->command->info("   • Items procesados: {$itemsProcessed}");
        $this->command->info("   • Movimientos creados: {$movementsCreated}");
        $this->command->info("   • Items omitidos (ya con stock): {$itemsSkipped}");
    }
}
