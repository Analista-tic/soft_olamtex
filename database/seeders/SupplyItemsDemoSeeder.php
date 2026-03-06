<?php

namespace Database\Seeders;

use App\Models\Settings\User;
use App\Services\Inventory\InventoryMovementService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seeder de items de insumos (avíos) para producción.
 * Incluye: etiquetas, botones, cierres, elásticos, cordones, cintas, bolsas, marquillas.
 * Ejecutar después de CategorySeeder y ExampleDataSeeder.
 *
 * php artisan db:seed --class=SupplyItemsDemoSeeder
 */
class SupplyItemsDemoSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $tax19 = DB::table('taxes')->where('name', 'IVA 19%')->value('id');
        if (!$tax19) {
            $tax19 = DB::table('taxes')->insertGetId([
                'name' => 'IVA 19%',
                'percentage' => 19,
                'status' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $warehouseId = DB::table('warehouses')->where('name', 'Principal')->value('id')
            ?? DB::table('warehouses')->value('id');

        $userId = DB::table('users')->value('id');

        // Insumos de ejemplo por subgrupo (grupo Insumos en CategorySeeder)
        $supplyItems = [
            // Etiquetas
            ['reference' => 'ETQ-TEJ-BLN', 'name' => 'Etiqueta tejida blanca', 'subgroup' => 'Etiqueta', 'unit' => 'und', 'desc' => 'Etiqueta tejida blanca con logo empresa'],
            ['reference' => 'ETQ-CARE-001', 'name' => 'Etiqueta de cuidados', 'subgroup' => 'Etiqueta', 'unit' => 'und', 'desc' => 'Etiqueta de instrucciones de lavado y planchado'],
            ['reference' => 'ETQ-SIZE-001', 'name' => 'Etiqueta de talla', 'subgroup' => 'Etiqueta', 'unit' => 'und', 'desc' => 'Etiqueta indicadora de talla S/M/L/XL'],
            // Botones
            ['reference' => 'BOT-PLAS-24', 'name' => 'Botón plástico 24mm', 'subgroup' => 'Botón', 'unit' => 'und', 'desc' => 'Botón plástico estándar 24mm surtido colores'],
            ['reference' => 'BOT-MET-15', 'name' => 'Botón metálico 15mm', 'subgroup' => 'Botón', 'unit' => 'und', 'desc' => 'Botón metálico 15mm tipo jean'],
            ['reference' => 'BOT-MAD-PEQ', 'name' => 'Botón madera pequeño', 'subgroup' => 'Botón', 'unit' => 'und', 'desc' => 'Botón de madera 18mm para camisas'],
            // Cierres
            ['reference' => 'CIE-MET-15', 'name' => 'Cierre metálico 15cm', 'subgroup' => 'Cierre', 'unit' => 'und', 'desc' => 'Cierre metálico color plata 15cm'],
            ['reference' => 'CIE-MET-20', 'name' => 'Cierre metálico 20cm', 'subgroup' => 'Cierre', 'unit' => 'und', 'desc' => 'Cierre metálico color negro 20cm'],
            ['reference' => 'CIE-PLAS-18', 'name' => 'Cierre plástico 18cm', 'subgroup' => 'Cierre', 'unit' => 'und', 'desc' => 'Cierre plástico invisible 18cm'],
            // Elásticos
            ['reference' => 'ELA-PRET-25', 'name' => 'Elástico pretina 25mm', 'subgroup' => 'Elástico', 'unit' => 'm', 'desc' => 'Elástico para pretina 25mm ancho'],
            ['reference' => 'ELA-CUEL-12', 'name' => 'Elástico puño 12mm', 'subgroup' => 'Elástico', 'unit' => 'm', 'desc' => 'Elástico para puños 12mm'],
            // Cintas
            ['reference' => 'CIN-BIIS-25', 'name' => 'Cinta sesgada 25mm', 'subgroup' => 'Cinta', 'unit' => 'm', 'desc' => 'Cinta sesgada blanca 25mm'],
            ['reference' => 'CIN-REF-20', 'name' => 'Cinta reflectiva 20mm', 'subgroup' => 'Cinta', 'unit' => 'm', 'desc' => 'Cinta reflectiva para ropa de trabajo'],
            // Cordones
            ['reference' => 'COR-RED-4', 'name' => 'Cordón redondo 4mm', 'subgroup' => 'Cordón', 'unit' => 'm', 'desc' => 'Cordón redondo algodón 4mm'],
            ['reference' => 'COR-PLAN-12', 'name' => 'Cordón plano 12mm', 'subgroup' => 'Cordón', 'unit' => 'm', 'desc' => 'Cordón plano para capuchas'],
            // Bolsas
            ['reference' => 'BOL-POL-40', 'name' => 'Bolsa polietileno 40x50', 'subgroup' => 'Bolsa', 'unit' => 'und', 'desc' => 'Bolsa polietileno para empaque 40x50cm'],
            ['reference' => 'BOL-TELA-M', 'name' => 'Bolsa tela M', 'subgroup' => 'Bolsa', 'unit' => 'und', 'desc' => 'Bolsa de tela reutilizable tamaño mediano'],
            // Marquillas
            ['reference' => 'MAR-HILA-001', 'name' => 'Marquilla hiladora', 'subgroup' => 'Marquilla', 'unit' => 'und', 'desc' => 'Marquilla con composición de hilatura'],
            ['reference' => 'MAR-PREC-001', 'name' => 'Marquilla de precio', 'subgroup' => 'Marquilla', 'unit' => 'und', 'desc' => 'Marquilla removible para precio'],
        ];

        $subGroupCache = [];

        foreach ($supplyItems as $item) {
            $exists = DB::table('items')->where('reference', $item['reference'])->exists();
            if ($exists) {
                continue;
            }

            $subGroupId = $subGroupCache[$item['subgroup']] ?? null;
            if (!$subGroupId) {
                $subGroupId = DB::table('category_sub_groups')
                    ->join('category_groups', 'category_sub_groups.category_group_id', '=', 'category_groups.id')
                    ->where('category_sub_groups.name', $item['subgroup'])
                    ->where('category_groups.name', 'Insumos')
                    ->value('category_sub_groups.id');

                if (!$subGroupId) {
                    continue;
                }
                $subGroupCache[$item['subgroup']] = $subGroupId;
            }

            $payload = [
                'category_sub_group_id' => $subGroupId,
                'tax_id' => $tax19,
                'name' => $item['name'],
                'reference' => $item['reference'],
                'img' => null,
                'bar_code' => $item['reference'],
                'negative_movement' => false,
                'description' => $item['desc'],
                'extra_fields' => null,
                'unit_measure' => $item['unit'],
                'status' => true,
                'item_type' => 'product',
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (Schema::hasColumn('items', 'weighted_average_price')) {
                $payload['weighted_average_price'] = 0;
            }

            $itemId = DB::table('items')->insertGetId($payload);

            if ($warehouseId) {
                DB::table('warehouse_items')->insert([
                    'warehouse_id' => $warehouseId,
                    'item_id' => $itemId,
                    'quantity' => 0,
                    'stock_min' => 50,
                    'stock_max' => 500,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        // Stock inicial vía movimientos para algunos insumos representativos
        if (Schema::hasTable('inventory_movements')) {
            $seedUser = User::find($userId);
            $representativeRefs = ['ETQ-TEJ-BLN', 'BOT-PLAS-24', 'CIE-MET-15', 'ELA-PRET-25', 'COR-RED-4'];
            $quantities = [500, 2000, 100, 200, 150];
            $prices = [150.00, 25.00, 1200.00, 800.00, 350.00];

            if ($seedUser) {
                $movementService = app(InventoryMovementService::class);
                foreach ($representativeRefs as $idx => $ref) {
                    $itemId = DB::table('items')->where('reference', $ref)->value('id');
                    if (!$itemId) {
                        continue;
                    }

                    $qty = $quantities[$idx] ?? 100;
                    $price = $prices[$idx] ?? 500.00;

                    $movementService->createMovement([
                        'item_id' => $itemId,
                        'warehouse_id' => $warehouseId,
                        'type' => 'entrada',
                        'quantity' => $qty,
                        'unit_price' => $price,
                        'notes' => 'Stock inicial seeder insumos',
                        'created_by_user_id' => $userId,
                    ]);
                }
            }
        }

        $this->command->info('SupplyItemsDemoSeeder: Items de insumos (etiquetas, botones, cierres, elásticos, etc.) creados.');
    }
}
