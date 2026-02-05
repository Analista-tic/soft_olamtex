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

        // Items de ejemplo usando parámetros y campos definidos
        $items = [
            [
                'name' => 'Tela algodón azul',
                'reference' => 'TEL-ALG-AZUL',
                'bar_code' => 'TEL-ALG-AZUL',
                'description' => 'Tela de algodón color azul, ancho 1.5m',
                'unit_measure' => 'm',
                'subgroup_name' => 'Tela',
                'extra_fields' => json_encode([
                    'base_tela' => 'ALGODÓN',
                    'color' => 'AZUL',
                    'costo_operaciones' => 406,
                    'costo_hilo' => 140,
                ]),
            ],
            [
                'name' => 'Tela lino blanco',
                'reference' => 'TEL-LINO-BLN',
                'bar_code' => 'TEL-LINO-BLN',
                'description' => 'Tela de lino color blanco, ancho 1.6m',
                'unit_measure' => 'm',
                'subgroup_name' => 'Tela',
                'extra_fields' => json_encode([
                    'base_tela' => 'LINO',
                    'color' => 'BLANCO',
                    'costo_operaciones' => 406,
                    'costo_hilo' => 140,
                ]),
            ],
            [
                'name' => 'Hilo poliéster negro',
                'reference' => 'HIL-POLI-NGR',
                'bar_code' => 'HIL-POLI-NGR',
                'description' => 'Hilo poliéster 2000m color negro',
                'unit_measure' => 'und',
                'subgroup_name' => 'Hilaza',
                'extra_fields' => json_encode([
                    'base_hilaza' => 'POLIÉSTER',
                    'color' => 'NEGRO',
                    'costo_operaciones' => 406,
                    'costo_hilo' => 140,
                ]),
            ],
            [
                'name' => 'Botón plástico rojo',
                'reference' => 'BOT-PLAS-ROJ',
                'bar_code' => 'BOT-PLAS-ROJ',
                'description' => 'Botón plástico rojo 24mm',
                'unit_measure' => 'und',
                'subgroup_name' => 'Botón',
                'extra_fields' => json_encode([
                    'color' => 'ROJO',
                ]),
            ],
            [
                'name' => 'Etiqueta tejida blanca',
                'reference' => 'ETQ-TEJ-BLN',
                'bar_code' => 'ETQ-TEJ-BLN',
                'description' => 'Etiqueta tejida blanca con logo',
                'unit_measure' => 'und',
                'subgroup_name' => 'Etiqueta',
                'extra_fields' => null,
            ],
            [
                'name' => 'Cierre metálico plata',
                'reference' => 'CIE-MET-PLT',
                'bar_code' => 'CIE-MET-PLT',
                'description' => 'Cierre metálico color plata 15cm',
                'unit_measure' => 'und',
                'subgroup_name' => 'Cierre',
                'extra_fields' => null,
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
                    'extra_fields' => $it['extra_fields'] ?? null,
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
