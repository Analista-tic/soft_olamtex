<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ExampleDataSeeder extends Seeder
{
    public function run(): void
    {
        // Insertar warehouse de ejemplo si no existe
        $warehouse = DB::table('warehouses')->where('name', 'Principal')->first();
        $warehouseId = $warehouse ? $warehouse->id : DB::table('warehouses')->insertGetId([
            'name' => 'Principal',
            'observation' => 'Almacén principal',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Insertar category_group de ejemplo si no existe
        $categoryGroup = DB::table('category_groups')->where('name', 'Grupo Ejemplo')->first();
        $categoryGroupId = $categoryGroup ? $categoryGroup->id : DB::table('category_groups')->insertGetId([
            'name' => 'Grupo Ejemplo',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Insertar category_sub_group de ejemplo si no existe
        $categorySubGroup = DB::table('category_sub_groups')->where('name', 'Subgrupo Ejemplo')->first();
        $categorySubGroupId = $categorySubGroup ? $categorySubGroup->id : DB::table('category_sub_groups')->insertGetId([
            'name' => 'Subgrupo Ejemplo',
            'category_group_id' => $categoryGroupId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Insertar usuario de ejemplo
        $user = DB::table('users')->where('email', 'ejemplo@correo.com')->first();
        $userId = $user ? $user->id : DB::table('users')->insertGetId([
            'name' => 'Ejemplo Usuario',
            'email' => 'ejemplo@correo.com',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'remember_token' => Str::random(10),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Insertar proveedor de ejemplo si no existe
        $provider = DB::table('contacts')->where('id_number', '900000001')->first();
        $providerId = $provider ? $provider->id : DB::table('contacts')->insertGetId([
            'contact_type' => 'proveedor',
            'id_type' => 'NIT',
            'id_number' => '900000001',
            'dv' => null,
            'full_name' => 'Proveedor Ejemplo',
            'person_type' => 'jurídica',
            'groups' => null,
            'municipality' => null,
            'department' => null,
            'address' => null,
            'email' => 'proveedor@ejemplo.com',
            'phone' => null,
            'additional_contacts' => null,
            'branches' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Insertar impuestos (taxes) de ejemplo si no existen
        $tax19 = DB::table('taxes')->where('name', 'IVA 19%')->first();
        $tax19Id = $tax19 ? $tax19->id : DB::table('taxes')->insertGetId([
            'name' => 'IVA 19%',
            'percentage' => 19,
            'status' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        $tax0 = DB::table('taxes')->where('name', 'Exento')->first();
        $tax0Id = $tax0 ? $tax0->id : DB::table('taxes')->insertGetId([
            'name' => 'Exento',
            'percentage' => 0,
            'status' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Insertar retenciones (retentions) de ejemplo si no existen
        DB::table('retentions')->updateOrInsert(
            ['name' => 'Retefuente', 'type' => 'fuente'],
            [
                'percent' => 2.5,
                'status' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
        DB::table('retentions')->updateOrInsert(
            ['name' => 'ReteICA', 'type' => 'ica'],
            [
                'percent' => 1.0,
                'status' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        // Insertar artículo de ejemplo (ajustado a los campos reales)
        $item = DB::table('items')->where('bar_code', 'ART-001')->first();
        $itemId = $item ? $item->id : DB::table('items')->insertGetId([
            'category_sub_group_id' => $categorySubGroupId,
            'tax_id' => $tax19Id, // Relación con tabla taxes
            'name' => 'Artículo de prueba',
            'reference' => 'ART-001',
            'img' => null,
            'bar_code' => 'ART-001',
            'negative_movement' => false,
            'description' => 'Descripción de artículo de prueba',
            'extra_fields' => null,
            'status' => true,
            // 'unit_measure' => 'm', // Descomenta solo si existe la columna en la BD
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Insertar relación en warehouse_item para stock y bodega
        DB::table('warehouse_items')->insert([
            'warehouse_id' => $warehouseId,
            'item_id' => $itemId,
            'quantity' => 100,
            'stock_min' => 10,
            'stock_max' => 200,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Generar documento ejemplo (solicitud + orden) usando la tabla unificada
        $requestDocumentId = DB::table('purchase_documents')->insertGetId([
            'document_number' => 'SOL-001',
            'document_type' => 'purchase_request',
            'contact_id' => $providerId,
            'date' => now()->subDays(5)->toDateString(),
            'delivery_date' => now()->addDays(10)->toDateString(),
            'description' => 'Solicitud de compra generada por el seeder de ejemplo',
            'created_by_user_id' => $userId,
            'status' => 'approved',
            'requested_at' => now()->subDays(5),
            'approved_at' => now()->subDays(4),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $orderDocumentId = DB::table('purchase_documents')->insertGetId([
            'document_number' => 'ORD-001',
            'document_type' => 'purchase_order',
            'parent_document_id' => $requestDocumentId,
            'contact_id' => $providerId,
            'date' => now()->subDays(3)->toDateString(),
            'delivery_date' => now()->addDays(7)->toDateString(),
            'description' => 'Orden de compra vinculada a la solicitud de ejemplo',
            'created_by_user_id' => $userId,
            'executed_by_user_id' => $userId,
            'status' => 'in_process',
            'supplier_order_number' => 'ORD-2025-001',
            'ordered_at' => now()->subDays(3),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('purchase_document_items')->insert([
            'purchase_document_id' => $orderDocumentId,
            'item_id' => $itemId,
            'quantity' => 10,
            'price' => 1200.00,
            'tax' => 19,
            'total' => 12000.00,
            'total_tax' => 2280.00,
            'supplier_code' => 'PRV-ART-001',
            'supplier_color' => 'Rojo',
            'order_width' => 1.5,
            'description' => 'Línea de documento de ejemplo',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // // Insertar orden de compra de ejemplo
        // $purchaseOrderId = DB::table('purchase_orders')->insertGetId([
        //     'contact_id' => $providerId,
        //     'date' => now(),
        //     'delivery_date' => now(),
        //     'supplier_order_number'=> 'ORD-001',
        //     'created_at' => now(),
        //     'updated_at' => now(),
        // ]);

        // // Insertar ítem de orden de compra de ejemplo
        // DB::table('purchase_order_items')->insert([
        //     'purchase_order_id' => $purchaseOrderId,
        //     'item_id' => $itemId,
        //     'price' => 1000.00,
        //     'tax' => 0,
        //     'quantity' => 1,
        //     'description' => 'Ítem de prueba',
        //     'total' => 1000.00,
        //     'created_at' => now(),
        //     'updated_at' => now(),
        // ]);
        // Factura de compra consolidada en la tabla unificada
        $invoiceDocumentId = DB::table('purchase_documents')->insertGetId([
            'document_number' => 'FAC-001',
            'document_type' => 'purchase_invoice',
            'parent_document_id' => $orderDocumentId,
            'contact_id' => $providerId,
            'date' => now()->subDays(1)->toDateString(),
            'delivery_date' => now()->toDateString(),
            'description' => 'Factura de compra vinculada a la orden de ejemplo',
            'created_by_user_id' => $userId,
            'executed_by_user_id' => $userId,
            'status' => 'pending',
            'approval_status' => 'pending',
            'supplier_invoice_number' => 'FAC-2025-001',
            'withholdings' => json_encode([
                ['name' => 'Retefuente', 'percentage' => 2.5, 'base_amount' => 12000.00],
            ]),
            'reason' => 'Diferencia minima verificada',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('purchase_document_items')->insert([
            'purchase_document_id' => $invoiceDocumentId,
            'item_id' => $itemId,
            'quantity' => 10,
            'price' => 1200.00,
            'tax' => 19,
            'total' => 12000.00,
            'total_tax' => 2280.00,
            'supplier_code' => 'PRV-ART-001',
            'supplier_color' => 'Rojo',
            'order_width' => 1.5,
            'description' => 'Item facturado asociado a la orden',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        // Ajuste de inventario de ejemplo
        $adjustmentNumber = 'AJU-001';
        $adjustmentId = DB::table('inventory_adjustments')->where('document_number', $adjustmentNumber)->value('id');
        if (!$adjustmentId) {
            $subtotal = 1250.00 * 4;
            $iva = $subtotal * 0.19;
            $adjustmentId = DB::table('inventory_adjustments')->insertGetId([
                'document_number' => $adjustmentNumber,
                'date' => now()->subDays(3)->toDateString(),
                'type' => 'entrada',
                'movement_reason' => 'Inventario inicial',
                'observation' => 'Ingreso inicial generado por el seeder',
                'production_order' => 'PROD-INV-001',
                'subtotal' => $subtotal,
                'iva' => $iva,
                'total' => $subtotal + $iva,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('inventory_adjustment_lines')->insert([
                'inventory_adjustment_id' => $adjustmentId,
                'item_id' => $itemId,
                'warehouse_id' => $warehouseId,
                'price' => 1250.00,
                'tax' => 19,
                'quantity' => 4,
                'description' => 'Ajuste por recepción inicial',
                'line_total' => 1250.00 * 4,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Secciones para listados operacionales
        $operationSections = [
            ['name' => 'Delantero', 'type' => 'confección'],
            ['name' => 'Trasero', 'type' => 'confección'],
            ['name' => 'Pretina', 'type' => 'confección'],
            ['name' => 'Ensamble', 'type' => 'confección'],
            ['name' => 'Terminacion', 'type' => 'presentación'],
            ['name' => 'Presentacion', 'type' => 'presentación'],
        ];
        foreach ($operationSections as $section) {
            DB::table('operation_sections')->updateOrInsert(
                ['name' => $section['name'], 'type' => $section['type']],
                ['is_active' => true, 'created_at' => now(), 'updated_at' => now()]
            );
        }

        // Máquinas
        $machines = [
            ['code' => 'MF5H', 'name' => 'Fileteadora 5 hilos'],
            ['code' => 'MF3H', 'name' => 'Fileteadora 3 hilos'],
            ['code' => 'MPP', 'name' => 'Máquina plana pespunte'],
            ['code' => 'PLANC', 'name' => 'Plancha'],
            ['code' => 'OFM', 'name' => 'Ojaladora de franela manual'],
            ['code' => 'MASH', 'name' => 'Máquina atracadora semiautomática horizontal'],
            ['code' => 'COLL', 'name' => 'Collaretera'],
        ];
        foreach ($machines as $machine) {
            DB::table('machines')->updateOrInsert(
                ['code' => $machine['code']],
                ['name' => $machine['name'], 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]
            );
        }

        // Operaciones
        $operations = [
            ['action' => 'CERRAR', 'garment_part' => 'TIRO DELANTERO'],
            ['action' => 'CERRAR', 'garment_part' => 'TIRO TRASERO'],
            ['action' => 'PLANCHAR', 'garment_part' => 'BOLSILLO TRASERO'],
            ['action' => 'PLANCHAR', 'garment_part' => 'BOLSILLO PARCHE TRASERO'],
            ['action' => 'ARMAR', 'garment_part' => 'PRETINA'],
            ['action' => 'COSER', 'garment_part' => 'BOLSILLO DELANTERO'],
            ['action' => 'FILETEAR', 'garment_part' => 'COSTADOS'],
            ['action' => 'PEGAR', 'garment_part' => 'PRETINA'],
            ['action' => 'HACER', 'garment_part' => 'OJALES'],
            ['action' => 'PEGAR', 'garment_part' => 'BOTONES'],
            ['action' => 'ASENTAR', 'garment_part' => 'ELASTICO PRETINA INCORPORADA'],
            ['action' => 'ASENTAR', 'garment_part' => 'LOMO BOLSILLO DELANTERO'],
            ['action' => 'ASENTAR', 'garment_part' => 'PRENDIDA DE TAPA'],
            ['action' => 'ASENTAR', 'garment_part' => 'PRETINA ANTIFLUIDA'],
            ['action' => 'ATRACAR', 'garment_part' => 'CORDON TUBULAR BOTAS'],
            ['action' => 'ATRACAR', 'garment_part' => 'CORDON TUBULAR CINTURA'],
            ['action' => 'ATRACAR', 'garment_part' => 'HILADILLA'],
            ['action' => 'CERRAR', 'garment_part' => 'BOLSILLO BOCA OJAL'],
            ['action' => 'CERRAR', 'garment_part' => 'BOLSILLO DELANTERO CURVO CON MALLA'],
        ];
        foreach ($operations as $operation) {
            DB::table('operations')->updateOrInsert(
                ['action' => $operation['action'], 'garment_part' => $operation['garment_part']],
                ['is_active' => true, 'created_at' => now(), 'updated_at' => now()]
            );
        }

        // Fichas de producto (ProductSheets)
        // Obtener IDs desde la tabla parameters (ya no insertamos en tablas de catálogos)
        $sectionsParam = DB::table('parameters')->where('nombre', 'sections')->first();
        $sectionId = $sectionsParam ? json_decode($sectionsParam->valor, true)[0]['id'] : 1;

        $groupsParam = DB::table('parameters')->where('nombre', 'groups')->first();
        $groupId = $groupsParam ? json_decode($groupsParam->valor, true)[0]['id'] : 1;

        $brandsParam = DB::table('parameters')->where('nombre', 'brands')->first();
        $brandId = $brandsParam ? json_decode($brandsParam->valor, true)[0]['id'] : 1;

        $worldsParam = DB::table('parameters')->where('nombre', 'worlds')->first();
        $worldId = $worldsParam ? json_decode($worldsParam->valor, true)[0]['id'] : 1;

        $fabricBasesParam = DB::table('parameters')->where('nombre', 'fabric_bases')->first();
        $fabricBaseId = $fabricBasesParam ? json_decode($fabricBasesParam->valor, true)[0]['id'] : 1;

        // Product Sheets - Usar updateOrInsert para evitar duplicados
        DB::table('product_sheets')->updateOrInsert(
            ['reference' => 'MUESTRA-001'],
            [
                'section_id' => $sectionId,
                'group_id' => $groupId,
                'brand_id' => $brandId,
                'world_id' => $worldId,
                'fabric_base_id' => $fabricBaseId,
                'yarn_base_id' => null,
                'description' => 'Pantalón jean básico - Muestra',
                'billing_price' => null,
                'selling_price' => null,
                'sizes' => json_encode(['28' => true, '30' => true, '32' => true, '34' => true, '36' => true]),
                'woven_product' => 0,
                'processes' => json_encode([]),
                'status' => 'incomplete',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        DB::table('product_sheets')->updateOrInsert(
            ['reference' => 'PROD-001'],
            [
                'section_id' => $sectionId,
                'group_id' => $groupId,
                'brand_id' => $brandId,
                'world_id' => $worldId,
                'fabric_base_id' => $fabricBaseId,
                'yarn_base_id' => null,
                'description' => 'Pantalón jean clásico - Producción',
                'billing_price' => null,
                'selling_price' => null,
                'sizes' => json_encode(['S' => true, 'M' => true, 'L' => true, 'XL' => true]),
                'woven_product' => 0,
                'processes' => json_encode([]),
                'status' => 'incomplete',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        // Programador (Programmer)
        // Obtener el ID de la ficha de producto recién creada
        $productSheetId = DB::table('product_sheets')->where('reference', 'MUESTRA-001')->value('id');

        // Obtener el ID del destino desde parameters
        $destinationsParam = DB::table('parameters')->where('nombre', 'destinations')->first();
        $destinationId = $destinationsParam ? json_decode($destinationsParam->valor, true)[0]['id'] : 1;

        DB::table('programmers')->insert([
            [
                'product_sheet_id' => $productSheetId,
                'season' => 'ENERO',
                'destination_id' => $destinationId,
                'priority' => 'A',
                'units' => 500,
                'ecommerce_units' => null,
                'status' => 'incomplete',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'product_sheet_id' => $productSheetId,
                'season' => 'FEBRERO',
                'destination_id' => $destinationId,
                'priority' => 'B',
                'units' => 300,
                'ecommerce_units' => json_encode(['28' => 50, '30' => 80, '32' => 100, '34' => 80, '36' => 50]),
                'status' => 'complete',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        // ========== KARDEX DE TELAS - FACTURAS Y ROLLOS ==========
        
        // Crear proveedor de tela si no existe
        $fabricProvider = DB::table('contacts')->where('id_number', '900000002')->first();
        $fabricProviderId = $fabricProvider ? $fabricProvider->id : DB::table('contacts')->insertGetId([
            'contact_type' => 'proveedor',
            'id_type' => 'NIT',
            'id_number' => '900000002',
            'dv' => '5',
            'full_name' => 'Textiles del Valle S.A.S',
            'person_type' => 'jurídica',
            'groups' => null,
            'municipality' => 'Medellín',
            'department' => 'Antioquia',
            'address' => 'Calle 50 # 45-67',
            'email' => 'ventas@texvalle.com',
            'phone' => '3001234567',
            'additional_contacts' => null,
            'branches' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Crear factura de tela 1 usando purchase_documents (evitar duplicados)
        $fabricInvoice1 = DB::table('purchase_documents')
            ->where('document_number', 'FT-2026-001')
            ->where('document_type', 'purchase_invoice')
            ->first();
            
        if (!$fabricInvoice1) {
            $fabricInvoice1Id = DB::table('purchase_documents')->insertGetId([
                'document_number' => 'FT-2026-001',
                'document_type' => 'purchase_invoice',
                'contact_id' => $fabricProviderId,
                'date' => now()->subDays(15)->toDateString(),
                'delivery_date' => now()->subDays(15)->toDateString(),
                'description' => '[KARDEX-TELA] Factura de tela LANA Beige para producción de enero',
                'created_by_user_id' => $userId,
                'status' => 'pending',
                'supplier_invoice_number' => 'FT-2026-001',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Crear item de documento para la tela LANA Beige
            $docItem1 = DB::table('purchase_document_items')->insertGetId([
                'purchase_document_id' => $fabricInvoice1Id,
                'item_id' => $itemId,
                'quantity' => 600, // Total metros (suma de los 3 rollos)
                'price' => 50.00,
                'tax' => 19,
                'total' => 30000.00,
                'total_tax' => 5700.00,
                'supplier_code' => 'LANA-BEIGE-001',
                'supplier_color' => 'VERDE',
                'order_width' => 2.00,
                'description' => 'Tela LANA Beige Genérica Químicos LÍNEA 3',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Crear rollos para la factura 1 (LANA Beige)
            $rolls1 = [
                ['roll_number' => 'RL01', 'width' => 2.00, 'quantity' => 300.00, 'location' => 'PRINCIPAL', 'description' => 'Tela LANA Beige - Rollo 1'],
                ['roll_number' => 'RL02', 'width' => 2.00, 'quantity' => 200.00, 'location' => 'PRINCIPAL', 'description' => 'Tela LANA Beige - Rollo 2'],
                ['roll_number' => 'RL03', 'width' => 1.80, 'quantity' => 100.00, 'location' => 'PRINCIPAL', 'description' => 'Tela LANA Beige - Rollo 3'],
            ];

            foreach ($rolls1 as $rollData) {
                DB::table('fabric_rolls')->insert([
                    'roll_number' => $rollData['roll_number'],
                    'purchase_document_item_id' => $docItem1,
                    'quantity' => $rollData['quantity'],
                    'roll_width' => $rollData['width'],
                    'location' => $rollData['location'],
                    'description' => $rollData['description'],
                    'partial_output' => 0.00,
                    'actual_output' => 0.00,
                    'entry_date' => now()->subDays(15)->toDateString(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // Crear factura de tela 2 usando purchase_documents (evitar duplicados)
        $fabricInvoice2 = DB::table('purchase_documents')
            ->where('document_number', 'FT-2026-002')
            ->where('document_type', 'purchase_invoice')
            ->first();
            
        if (!$fabricInvoice2) {
            $fabricInvoice2Id = DB::table('purchase_documents')->insertGetId([
                'document_number' => 'FT-2026-002',
                'document_type' => 'purchase_invoice',
                'contact_id' => $fabricProviderId,
                'date' => now()->subDays(10)->toDateString(),
                'delivery_date' => now()->subDays(10)->toDateString(),
                'description' => '[KARDEX-TELA] Factura de tela ALGODÓN para línea premium',
                'created_by_user_id' => $userId,
                'status' => 'pending',
                'supplier_invoice_number' => 'FT-2026-002',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Crear item de documento para la tela ALGODÓN
            $docItem2 = DB::table('purchase_document_items')->insertGetId([
                'purchase_document_id' => $fabricInvoice2Id,
                'item_id' => $itemId,
                'quantity' => 430, // Total metros (250 + 180)
                'price' => 50.00,
                'tax' => 19,
                'total' => 21500.00,
                'total_tax' => 4085.00,
                'supplier_code' => 'ALGODON-001',
                'supplier_color' => 'AZUL',
                'order_width' => 1.50,
                'description' => 'Tela ALGODÓN 100% Premium LÍNEA 1',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Crear rollos para la factura 2 (ALGODÓN)
            $rolls2 = [
                ['roll_number' => 'RL04', 'width' => 1.50, 'quantity' => 250.00, 'location' => 'BODEGA 2', 'description' => 'Tela ALGODÓN Premium - Rollo 4'],
                ['roll_number' => 'RL05', 'width' => 1.50, 'quantity' => 180.00, 'location' => 'BODEGA 2', 'description' => 'Tela ALGODÓN Premium - Rollo 5'],
            ];

            foreach ($rolls2 as $rollData) {
                DB::table('fabric_rolls')->insert([
                    'roll_number' => $rollData['roll_number'],
                    'purchase_document_item_id' => $docItem2,
                    'quantity' => $rollData['quantity'],
                    'roll_width' => $rollData['width'],
                    'location' => $rollData['location'],
                    'description' => $rollData['description'],
                    'partial_output' => 0.00,
                    'actual_output' => 0.00,
                    'entry_date' => now()->subDays(10)->toDateString(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // Crear factura de tela 3 usando purchase_documents con descarga parcial (evitar duplicados)
        $fabricInvoice3 = DB::table('purchase_documents')
            ->where('document_number', 'FT-2026-003')
            ->where('document_type', 'purchase_invoice')
            ->first();
            
        if (!$fabricInvoice3) {
            $fabricInvoice3Id = DB::table('purchase_documents')->insertGetId([
                'document_number' => 'FT-2026-003',
                'document_type' => 'purchase_invoice',
                'contact_id' => $fabricProviderId,
                'date' => now()->subDays(5)->toDateString(),
                'delivery_date' => now()->subDays(5)->toDateString(),
                'description' => '[KARDEX-TELA] Factura de tela DENIM para jean clásico',
                'created_by_user_id' => $userId,
                'status' => 'pending',
                'supplier_invoice_number' => 'FT-2026-003',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Crear item de documento para la tela DENIM
            $docItem3 = DB::table('purchase_document_items')->insertGetId([
                'purchase_document_id' => $fabricInvoice3Id,
                'item_id' => $itemId,
                'quantity' => 750, // Total metros (400 + 350)
                'price' => 50.00,
                'tax' => 19,
                'total' => 37500.00,
                'total_tax' => 7125.00,
                'supplier_code' => 'DENIM-001',
                'supplier_color' => 'AZUL ÍNDIGO',
                'order_width' => 1.60,
                'description' => 'Tela DENIM Stretch LÍNEA 2',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Crear rollos para la factura 3 (DENIM) - algunos con descarga
            DB::table('fabric_rolls')->insert([
                [
                    'roll_number' => 'RL06',
                    'purchase_document_item_id' => $docItem3,
                    'quantity' => 400.00,
                    'roll_width' => 1.60,
                    'location' => 'PRINCIPAL',
                    'description' => 'Tela DENIM Stretch AZUL ÍNDIGO - Rollo 6',
                    'partial_output' => 150.00, // Ya se descargaron 150m
                    'actual_output' => 150.00,
                    'entry_date' => now()->subDays(5)->toDateString(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'roll_number' => 'RL07',
                    'purchase_document_item_id' => $docItem3,
                    'quantity' => 350.00,
                    'roll_width' => 1.60,
                    'location' => 'PRINCIPAL',
                    'description' => 'Tela DENIM Stretch NEGRO - Rollo 7',
                    'partial_output' => 0.00,
                    'actual_output' => 0.00,
                    'entry_date' => now()->subDays(5)->toDateString(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);
        }
    }
}

