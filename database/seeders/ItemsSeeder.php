<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ItemsSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        // Obtener referencias necesarias
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

        // Items con diferentes subgrupos
        $items = [
            // ITEMS DE TELA
            [
                'name' => 'Tela drill azul',
                'reference' => 'TEL-DRILL-AZUL',
                'bar_code' => 'TEL-DRILL-AZUL',
                'description' => 'Tela drill color azul, ancho 1.5m',
                'unit_measure' => 'm',
                'subgroup_name' => 'Tela',
            ],
            [
                'name' => 'Tela sarga gris',
                'reference' => 'TEL-SARGA-GRS',
                'bar_code' => 'TEL-SARGA-GRS',
                'description' => 'Tela sarga color gris, ancho 1.6m',
                'unit_measure' => 'm',
                'subgroup_name' => 'Tela',
            ],
            // ITEMS NO-TELA (otros insumos)
            [
                'name' => 'Hilo poliéster blanco',
                'reference' => 'HIL-POLI-BLN',
                'bar_code' => 'HIL-POLI-BLN',
                'description' => 'Hilo poliéster 2000m color blanco',
                'unit_measure' => 'und',
                'subgroup_name' => 'Hilaza',
            ],
            [
                'name' => 'Botón plástico 24mm',
                'reference' => 'BOT-PLAS-24',
                'bar_code' => 'BOT-PLAS-24',
                'description' => 'Botón plástico transparente 24mm',
                'unit_measure' => 'und',
                'subgroup_name' => 'Botón',
            ],
            [
                'name' => 'Etiqueta tejida blanca',
                'reference' => 'ETQ-TEJ-BLN',
                'bar_code' => 'ETQ-TEJ-BLN',
                'description' => 'Etiqueta tejida blanca con logo',
                'unit_measure' => 'und',
                'subgroup_name' => 'Etiqueta',
            ],
            [
                'name' => 'Cierre plata 15cm',
                'reference' => 'CIE-PLT-15',
                'bar_code' => 'CIE-PLT-15',
                'description' => 'Cierre metálico plata 15cm',
                'unit_measure' => 'und',
                'subgroup_name' => 'Cierre',
            ],
        ];

        foreach ($items as $it) {
            $exists = DB::table('items')->where('reference', $it['reference'])->exists();
            if (!$exists) {
                // Obtener subgrupo por nombre
                $subGroupId = DB::table('category_sub_groups')
                    ->where('name', $it['subgroup_name'])
                    ->value('id');
                
                if (!$subGroupId) {
                    continue; // Skip si no existe el subgrupo
                }

                $itemId = DB::table('items')->insertGetId([
                    'category_sub_group_id' => $subGroupId,
                    'tax_id' => $tax19,
                    'name' => $it['name'],
                    'reference' => $it['reference'],
                    'img' => null,
                    'bar_code' => $it['bar_code'],
                    'negative_movement' => false,
                    'description' => $it['description'],
                    'extra_fields' => null,
                    'unit_measure' => $it['unit_measure'],
                    'weighted_average_price' => 0,
                    'status' => true,
                    'item_type' => 'product',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                // Vincular a bodega principal con stock inicial
                $warehouseId = DB::table('warehouses')->where('name', 'Principal')->value('id')
                    ?? DB::table('warehouses')->value('id');

                if ($warehouseId) {
                    $wiExists = DB::table('warehouse_items')
                        ->where('warehouse_id', $warehouseId)
                        ->where('item_id', $itemId)
                        ->exists();
                    if (!$wiExists) {
                        DB::table('warehouse_items')->insert([
                            'warehouse_id' => $warehouseId,
                            'item_id' => $itemId,
                            'quantity' => 100,
                            'stock_min' => 10,
                            'stock_max' => 200,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    }
                }
            }
        }
    }
}
