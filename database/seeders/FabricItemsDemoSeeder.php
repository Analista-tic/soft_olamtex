<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seeder de items de tela con rollos (FabricRoll) y flujo de compra completo.
 * Incluye: solicitud de compra, orden de compra, factura y recepción.
 * Ejecutar después de ExampleDataSeeder (que crea categories, warehouses, users, taxes).
 *
 * php artisan db:seed --class=FabricItemsDemoSeeder
 */
class FabricItemsDemoSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        // Obtener IDs necesarios
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

        $telaSubGroupId = DB::table('category_sub_groups')->where('name', 'Tela')->value('id');
        if (!$telaSubGroupId) {
            $groupId = DB::table('category_groups')->where('name', 'Materia Prima')->value('id')
                ?? DB::table('category_groups')->value('id');
            $telaSubGroupId = DB::table('category_sub_groups')->insertGetId([
                'name' => 'Tela',
                'category_group_id' => $groupId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $warehouseId = DB::table('warehouses')->where('name', 'Principal')->value('id')
            ?? DB::table('warehouses')->value('id');

        $userId = DB::table('users')->value('id');

        // Proveedor CAMARBU
        $camarbuId = DB::table('contacts')->where('id_number', '900000100')->value('id');
        if (!$camarbuId) {
            $camarbuId = DB::table('contacts')->insertGetId([
                'contact_type' => 'proveedor',
                'id_type' => 'nit',
                'id_number' => '900000100',
                'dv' => '3',
                'full_name' => 'CAMARBU Textiles S.A.S',
                'person_type' => 'persona_juridica',
                'groups' => null,
                'municipality' => 'Bogotá',
                'department' => 'Cundinamarca',
                'address' => 'Carrera 68 # 90-25',
                'email' => 'ventas@camarbu.com.co',
                'phone' => '6013456789',
                'additional_contacts' => null,
                'branches' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // Items de tela (RIB SUPER, etc.)
        $fabricItems = [
            [
                'reference' => 'TELA1',
                'name' => 'RIB SUPER',
                'bar_code' => 'TELA-RIB-SUP-001',
                'description' => 'Tela RIB SUPER algodón/poliéster, ancho 1.60m, acabado suave',
                'unit_measure' => 'm',
                'extra' => ['base_tela' => 'RIBBED', 'color' => 'BLANCO', 'ancho' => '1.60'],
            ],
            [
                'reference' => 'TELA2',
                'name' => 'JERSEY INTERLOCK',
                'bar_code' => 'TELA-JER-INT-001',
                'description' => 'Tela JERSEY INTERLOCK 100% algodón peinado, ancho 1.80m',
                'unit_measure' => 'm',
                'extra' => ['base_tela' => 'JERSEY', 'color' => 'NEGRO', 'ancho' => '1.80'],
            ],
            [
                'reference' => 'TELA3',
                'name' => 'PIQUE MESH',
                'bar_code' => 'TELA-PIQ-MSH-001',
                'description' => 'Tela PIQUE MESH para polos, ancho 1.70m, gramaje 180',
                'unit_measure' => 'm',
                'extra' => ['base_tela' => 'PIQUE', 'color' => 'AZUL MARINO', 'ancho' => '1.70'],
            ],
            [
                'reference' => 'TELA4',
                'name' => 'FRENCH TERRY',
                'bar_code' => 'TELA-FRE-TER-001',
                'description' => 'Tela FRENCH TERRY algodón cepillado interior, ancho 1.75m',
                'unit_measure' => 'm',
                'extra' => ['base_tela' => 'FRENCH TERRY', 'color' => 'GRIS MELANGE', 'ancho' => '1.75'],
            ],
            [
                'reference' => 'TELA5',
                'name' => 'LYCRA SPANDEX',
                'bar_code' => 'TELA-LYC-SPN-001',
                'description' => 'Tela LYCRA SPANDEX elástica 4 vías, ancho 1.50m, gramaje 220',
                'unit_measure' => 'm',
                'extra' => ['base_tela' => 'LYCRA', 'color' => 'ROJO', 'ancho' => '1.50'],
            ],
            [
                'reference' => 'TELA6',
                'name' => 'POPELINA STRETCH',
                'bar_code' => 'TELA-POP-STR-001',
                'description' => 'Tela POPELINA STRETCH algodón/elastano, ancho 1.45m',
                'unit_measure' => 'm',
                'extra' => ['base_tela' => 'POPELINA', 'color' => 'VERDE OLIVA', 'ancho' => '1.45'],
            ],
            [
                'reference' => 'TELA7',
                'name' => 'DRILL ALGODON',
                'bar_code' => 'TELA-DRL-ALG-001',
                'description' => 'Tela DRILL resistente 100% algodón, ancho 1.50m',
                'unit_measure' => 'm',
                'extra' => ['base_tela' => 'DRILL', 'color' => 'CAQUI', 'ancho' => '1.50'],
            ],
            [
                'reference' => 'TELA8',
                'name' => 'DENIM STRETCH',
                'bar_code' => 'TELA-DEN-STR-001',
                'description' => 'Tela DENIM STRETCH elástico para jeans, ancho 1.55m',
                'unit_measure' => 'm',
                'extra' => ['base_tela' => 'DENIM', 'color' => 'AZUL INDIGO', 'ancho' => '1.55'],
            ],
            [
                'reference' => 'TELA9',
                'name' => 'SOFTSHELL',
                'bar_code' => 'TELA-SOF-SHL-001',
                'description' => 'Tela SOFTSHELL técnica impermeable, ancho 1.50m',
                'unit_measure' => 'm',
                'extra' => ['base_tela' => 'SOFTSHELL', 'color' => 'NEGRO', 'ancho' => '1.50'],
            ],
            [
                'reference' => 'TELA10',
                'name' => 'CANVAS ORGANICO',
                'bar_code' => 'TELA-CNV-ORG-001',
                'description' => 'Tela CANVAS orgánico pesado, ancho 1.40m',
                'unit_measure' => 'm',
                'extra' => ['base_tela' => 'CANVAS', 'color' => 'CRUDO', 'ancho' => '1.40'],
            ],
            [
                'reference' => 'TELA11',
                'name' => 'PIQUE POLO',
                'bar_code' => 'TELA-PIQ-POL-001',
                'description' => 'Tela PIQUE POLO algodón mercerizado, ancho 1.60m',
                'unit_measure' => 'm',
                'extra' => ['base_tela' => 'PIQUE', 'color' => 'BLANCO', 'ancho' => '1.60'],
            ],
            [
                'reference' => 'TELA12',
                'name' => 'LINO MEZCLA',
                'bar_code' => 'TELA-LIN-MEZ-001',
                'description' => 'Tela LINO MEZCLA lino/algodón, ancho 1.35m',
                'unit_measure' => 'm',
                'extra' => ['base_tela' => 'LINO', 'color' => 'BEIGE', 'ancho' => '1.35'],
            ],
        ];

        $itemIds = [];
        foreach ($fabricItems as $item) {
            $exists = DB::table('items')->where('reference', $item['reference'])->exists();
            if ($exists) {
                $itemIds[$item['reference']] = DB::table('items')->where('reference', $item['reference'])->value('id');
                continue;
            }

            $itemId = DB::table('items')->insertGetId([
                'category_sub_group_id' => $telaSubGroupId,
                'tax_id' => $tax19,
                'name' => $item['name'],
                'reference' => $item['reference'],
                'img' => null,
                'bar_code' => $item['bar_code'],
                'negative_movement' => false,
                'description' => $item['description'],
                'extra_fields' => json_encode($item['extra'] ?? []),
                'unit_measure' => $item['unit_measure'],
                'status' => true,
                'item_type' => 'product',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            if (Schema::hasColumn('items', 'weighted_average_price')) {
                DB::table('items')->where('id', $itemId)->update(['weighted_average_price' => 0]);
            }

            $itemIds[$item['reference']] = $itemId;

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

        // Flujo de compra: Solicitud -> Orden -> Factura
        $solicitudId = DB::table('purchase_documents')
            ->where('document_number', 'SOL-TELA-001')
            ->where('document_type', 'purchase_request')
            ->value('id');

        if (!$solicitudId) {
            $solicitudId = DB::table('purchase_documents')->insertGetId([
                'document_number' => 'SOL-TELA-001',
                'document_type' => 'purchase_request',
                'contact_id' => $camarbuId,
                'date' => $now->copy()->subDays(10)->toDateString(),
                'description' => 'Solicitud de compra de telas RIB SUPER, JERSEY INTERLOCK y PIQUE MESH para producción',
                'status' => 'approved',
                'approved_by_user_id' => $userId,
                'approved_at' => $now->copy()->subDays(9),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $ordenId = DB::table('purchase_documents')
            ->where('document_number', 'ORD-TELA-001')
            ->where('document_type', 'purchase_order')
            ->value('id');

        if (!$ordenId) {
            $ordenId = DB::table('purchase_documents')->insertGetId([
                'document_number' => 'ORD-TELA-001',
                'document_type' => 'purchase_order',
                'parent_document_id' => null,
                'contact_id' => $camarbuId,
                'date' => $now->copy()->subDays(8)->toDateString(),
                'delivery_date' => $now->copy()->subDays(3)->toDateString(),
                'description' => 'Orden de compra de telas para línea de producción - CAMARBU',
                'created_by_user_id' => $userId,
                'executed_by_user_id' => $userId,
                'status' => 'approved',
                'approved_by_user_id' => $userId,
                'approved_at' => $now->copy()->subDays(7),
                'supplier_order_number' => 'ORD-CAM-2026-001',
                'ordered_at' => $now->copy()->subDays(8),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $facturaId = DB::table('purchase_documents')
            ->where('document_number', 'FAC-TELA-CAM-001')
            ->where('document_type', 'purchase_invoice')
            ->value('id');

        if (!$facturaId) {
            $facturaId = DB::table('purchase_documents')->insertGetId([
                'document_number' => 'FAC-TELA-CAM-001',
                'document_type' => 'purchase_invoice',
                'parent_document_id' => $ordenId,
                'contact_id' => $camarbuId,
                'date' => $now->copy()->subDays(3)->toDateString(),
                'delivery_date' => $now->copy()->subDays(3)->toDateString(),
                'description' => 'Factura de compra tela - Recepción CAMARBU RIB SUPER y PIQUE MESH',
                'created_by_user_id' => $userId,
                'executed_by_user_id' => $userId,
                'status' => 'received',
                'supplier_invoice_number' => 'FAC-CAM-2026-001',
                'invoiced_at' => $now->copy()->subDays(3),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            // Ítems de factura con precios completos
            $facturaItems = [
                [
                    'item_ref' => 'TELA1',
                    'quantity' => 450,
                    'price' => 18500.00,
                    'supplier_code' => 'RIB-SUP-BCO-001',
                    'supplier_color' => 'Blanco',
                    'order_width' => 1.60,
                    'desc' => 'RIB SUPER algodón/poliéster ancho 1.60m',
                ],
                [
                    'item_ref' => 'TELA2',
                    'quantity' => 320,
                    'price' => 22000.00,
                    'supplier_code' => 'JER-INT-NGR-001',
                    'supplier_color' => 'Negro',
                    'order_width' => 1.80,
                    'desc' => 'JERSEY INTERLOCK 100% algodón peinado',
                ],
                [
                    'item_ref' => 'TELA3',
                    'quantity' => 280,
                    'price' => 19500.00,
                    'supplier_code' => 'PIQ-MSH-AZM-001',
                    'supplier_color' => 'Azul Marino',
                    'order_width' => 1.70,
                    'desc' => 'PIQUE MESH gramaje 180 para polos',
                ],
            ];

            foreach ($facturaItems as $line) {
                $itemId = $itemIds[$line['item_ref']] ?? null;
                if (!$itemId) {
                    continue;
                }

                $qty = $line['quantity'];
                $price = $line['price'];
                $tax = 19;
                $total = $qty * $price;
                $totalTax = $total * ($tax / 100);

                $docItemId = DB::table('purchase_document_items')->insertGetId([
                    'purchase_document_id' => $facturaId,
                    'item_id' => $itemId,
                    'quantity' => $qty,
                    'price' => $price,
                    'tax' => $tax,
                    'total' => $total,
                    'total_tax' => $totalTax,
                    'supplier_code' => $line['supplier_code'],
                    'supplier_color' => $line['supplier_color'],
                    'order_width' => $line['order_width'],
                    'description' => $line['desc'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                // FabricRolls por ítem
                $rollsByItem = [
                    'TELA1' => [
                        ['roll' => 'RL-T1-001', 'qty' => 150, 'width' => 1.60, 'loc' => 'Principal'],
                        ['roll' => 'RL-T1-002', 'qty' => 180, 'width' => 1.60, 'loc' => 'Principal'],
                        ['roll' => 'RL-T1-003', 'qty' => 120, 'width' => 1.60, 'loc' => 'Principal'],
                    ],
                    'TELA2' => [
                        ['roll' => 'RL-T2-001', 'qty' => 180, 'width' => 1.80, 'loc' => 'Principal'],
                        ['roll' => 'RL-T2-002', 'qty' => 140, 'width' => 1.80, 'loc' => 'Principal'],
                    ],
                    'TELA3' => [
                        ['roll' => 'RL-T3-001', 'qty' => 160, 'width' => 1.70, 'loc' => 'Principal'],
                        ['roll' => 'RL-T3-002', 'qty' => 120, 'width' => 1.70, 'loc' => 'Principal'],
                    ],
                ];

                $rolls = $rollsByItem[$line['item_ref']] ?? [];
                $entryDate = $now->copy()->subDays(3)->toDateString();

                foreach ($rolls as $r) {
                    $existsRoll = DB::table('fabric_rolls')->where('roll_number', $r['roll'])->exists();
                    if (!$existsRoll) {
                        DB::table('fabric_rolls')->insert([
                            'roll_number' => $r['roll'],
                            'purchase_document_item_id' => $docItemId,
                            'quantity' => $r['qty'],
                            'roll_width' => $r['width'],
                            'location' => $r['loc'],
                            'description' => $line['desc'] . ' - Rollo ' . $r['roll'],
                            'partial_output' => 0.00,
                            'actual_output' => 0.00,
                            'entry_date' => $entryDate,
                            'approval_status' => 'approved',
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    }
                }
            }
        }

        $this->command->info('FabricItemsDemoSeeder: Items de tela (RIB SUPER, JERSEY INTERLOCK, PIQUE MESH), flujo de compra y rollos creados.');
    }
}
