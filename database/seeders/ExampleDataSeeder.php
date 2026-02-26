<?php

namespace Database\Seeders;

use App\Models\Settings\User;
use App\Models\Settings\Setting;
use App\Services\Inventory\InventoryMovementService;
use App\Services\Inventory\WarehouseTransferService;
use App\Services\Production\ProgrammerService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
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

        // Insertar bodegas adicionales si no existen
        $warehouseSecondary = DB::table('warehouses')->where('name', 'Ferias')->first();
        $warehouseSecondaryId = $warehouseSecondary ? $warehouseSecondary->id : DB::table('warehouses')->insertGetId([
            'name' => 'Ferias',
            'observation' => 'Bodega secundaria',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $warehouseTertiary = DB::table('warehouses')->where('name', 'Local Comercial 1')->first();
        $warehouseTertiaryId = $warehouseTertiary ? $warehouseTertiary->id : DB::table('warehouses')->insertGetId([
            'name' => 'Local Comercial 1',
            'observation' => 'Punto de venta principal',
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

        // Insertar category_sub_group TELA para items de tela
        $telaSubGroup = DB::table('category_sub_groups')->where('name', 'Tela')->first();
        $telaSubGroupId = $telaSubGroup ? $telaSubGroup->id : DB::table('category_sub_groups')->insertGetId([
            'name' => 'Tela',
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

        // Crear items de TELA específicos
        $lanaBeige = DB::table('items')->where('bar_code', 'TELA-LANA-001')->first();
        $lanaId = $lanaBeige ? $lanaBeige->id : DB::table('items')->insertGetId([
            'category_sub_group_id' => $telaSubGroupId,
            'tax_id' => $tax19Id,
            'name' => 'Tela LANA Beige',
            'reference' => 'LANA-BEIGE',
            'img' => null,
            'bar_code' => 'TELA-LANA-001',
            'negative_movement' => false,
            'description' => 'Tela LANA Beige Genérica Químicos LÍNEA 3',
            'extra_fields' => null,
            'status' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $algodon = DB::table('items')->where('bar_code', 'TELA-ALGODON-001')->first();
        $algodonId = $algodon ? $algodon->id : DB::table('items')->insertGetId([
            'category_sub_group_id' => $telaSubGroupId,
            'tax_id' => $tax19Id,
            'name' => 'Tela ALGODÓN 100%',
            'reference' => 'ALGODON-100',
            'img' => null,
            'bar_code' => 'TELA-ALGODON-001',
            'negative_movement' => false,
            'description' => 'Tela ALGODÓN 100% Premium LÍNEA 1',
            'extra_fields' => null,
            'status' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Stock inicial vía movimientos de entrada (para tener precio ponderado y permitir transferencias)
        if (Schema::hasTable('inventory_movements')) {
            $seedUser = User::find($userId);
            if ($seedUser) {
                $movementService = app(InventoryMovementService::class);
                $baseNotes = 'Stock inicial seeder';
                $movementService->createMovement([
                    'item_id' => $itemId,
                    'warehouse_id' => $warehouseId,
                    'type' => 'entrada',
                    'quantity' => 100,
                    'unit_price' => 1200.00,
                    'notes' => $baseNotes,
                    'created_by_user_id' => $userId,
                ]);
                $movementService->createMovement([
                    'item_id' => $lanaId,
                    'warehouse_id' => $warehouseId,
                    'type' => 'entrada',
                    'quantity' => 250,
                    'unit_price' => 50.00,
                    'notes' => $baseNotes,
                    'created_by_user_id' => $userId,
                ]);
                $movementService->createMovement([
                    'item_id' => $algodonId,
                    'warehouse_id' => $warehouseId,
                    'type' => 'entrada',
                    'quantity' => 180,
                    'unit_price' => 50.00,
                    'notes' => $baseNotes,
                    'created_by_user_id' => $userId,
                ]);
                // Ajustar stock_min y stock_max en warehouse_items
                DB::table('warehouse_items')->where('item_id', $itemId)->where('warehouse_id', $warehouseId)->update(['stock_min' => 10, 'stock_max' => 200]);
                DB::table('warehouse_items')->where('item_id', $lanaId)->where('warehouse_id', $warehouseId)->update(['stock_min' => 10, 'stock_max' => 500]);
                DB::table('warehouse_items')->where('item_id', $algodonId)->where('warehouse_id', $warehouseId)->update(['stock_min' => 10, 'stock_max' => 400]);
            }
        } else {
            // Fallback si no existe tabla de movimientos
            DB::table('warehouse_items')->updateOrInsert(
                ['warehouse_id' => $warehouseId, 'item_id' => $itemId],
                ['quantity' => 100, 'stock_min' => 10, 'stock_max' => 200, 'created_at' => now(), 'updated_at' => now()]
            );
            DB::table('warehouse_items')->updateOrInsert(
                ['warehouse_id' => $warehouseId, 'item_id' => $lanaId],
                ['quantity' => 250, 'stock_min' => 10, 'stock_max' => 500, 'created_at' => now(), 'updated_at' => now()]
            );
            DB::table('warehouse_items')->updateOrInsert(
                ['warehouse_id' => $warehouseId, 'item_id' => $algodonId],
                ['quantity' => 180, 'stock_min' => 10, 'stock_max' => 400, 'created_at' => now(), 'updated_at' => now()]
            );
        }

        // Transferencias de bodega de ejemplo
        if (Schema::hasTable('warehouse_transfers') && Schema::hasTable('warehouse_transfer_items')) {
            $hasTransfers = DB::table('warehouse_transfers')->exists();
            $seedUser = User::find($userId);

            if (!$hasTransfers && $seedUser) {
                $transferService = new WarehouseTransferService(new InventoryMovementService());

                $transferService->create([
                    'date' => now()->subDays(7)->toDateString(),
                    'origin_warehouse_id' => $warehouseId,
                    'destination_warehouse_id' => $warehouseSecondaryId,
                    'observation' => 'Traslado inicial de prueba',
                    'items' => [
                        ['item_id' => $itemId, 'quantity' => 5],
                        ['item_id' => $lanaId, 'quantity' => 12],
                    ],
                ], $seedUser);

                $transferService->create([
                    'date' => now()->subDays(3)->toDateString(),
                    'origin_warehouse_id' => $warehouseId,
                    'destination_warehouse_id' => $warehouseTertiaryId,
                    'observation' => 'Reposición punto de venta',
                    'items' => [
                        ['item_id' => $algodonId, 'quantity' => 8],
                    ],
                ], $seedUser);
            }
        }

        // Generar documento ejemplo (solicitud + orden) usando la tabla unificada
        $requestDocumentId = DB::table('purchase_documents')->insertGetId([
            'document_number' => 'SOL-001',
            'document_type' => 'purchase_request',
            'contact_id' => $providerId,
            'date' => now()->subDays(5)->toDateString(),
            'description' => 'Solicitud de compra generada por el seeder de ejemplo. Destino: Producción.',
            'status' => 'approved',
            'approved_by_user_id' => $userId,
            'approved_at' => now()->subDays(4),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $orderDocumentId = DB::table('purchase_documents')->insertGetId([
            'document_number' => 'ORD-001',
            'document_type' => 'purchase_order',
            'parent_document_id' => null, // Se puede vincular a purchase_request mediante lógica de negocio
            'contact_id' => $providerId,
            'date' => now()->subDays(3)->toDateString(),
            'delivery_date' => now()->addDays(7)->toDateString(),
            'description' => 'Orden de compra vinculada a la solicitud de ejemplo',
            'created_by_user_id' => $userId,
            'executed_by_user_id' => $userId,
            'status' => 'approved', // Estados válidos: pending, approved, sent, invoiced, rejected
            'approved_by_user_id' => $userId,
            'approved_at' => now()->subDays(2),
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
            'status' => 'pending_receipt', // Estados válidos para invoices: pending_receipt, received, rejected
            'supplier_invoice_number' => 'FAC-2025-001',
            'withholdings' => json_encode([
                ['name' => 'Retefuente', 'percentage' => 2.5, 'base_amount' => 12000.00],
            ]),
            'reason' => 'Diferencia mínima verificada',
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
            ['name' => 'Cuello', 'type' => 'confección'],
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

        // Máquinas con factor de suplemento (conversión)
        $machines = [
            ['code' => 'PLN', 'name' => 'PLANA', 'supplement_factor' => 1.169],
            ['code' => 'MF5H', 'name' => 'FILETEADORA 5 HILOS', 'supplement_factor' => 1.145],
            ['code' => 'MF3H', 'name' => 'FILETEADORA 3 HILOS', 'supplement_factor' => 1.145],
            ['code' => 'MPP', 'name' => 'MÁQUINA PLANA PESPUNTE', 'supplement_factor' => 1.169],
            ['code' => 'PLANC', 'name' => 'PLANCHA', 'supplement_factor' => 1.2],
            ['code' => 'OFM', 'name' => 'OJALADORA DE FRANELA MANUAL', 'supplement_factor' => 1.086],
            ['code' => 'MASH', 'name' => 'MÁQUINA ATRACADORA SEMIAUTOMÁTICA HORIZONTAL', 'supplement_factor' => 1.0],
            ['code' => 'COLL', 'name' => 'COLLARETERA', 'supplement_factor' => 1.19],
            ['code' => 'P2A', 'name' => 'PLANA 2 AGUJAS', 'supplement_factor' => 1.18],
            ['code' => 'CDC', 'name' => 'CERRADORA DE CODO', 'supplement_factor' => 1.21],
            ['code' => 'PRES', 'name' => 'PRESILLADORA', 'supplement_factor' => 1.169],
            ['code' => 'MAN', 'name' => 'MANUAL', 'supplement_factor' => 1.086],
            ['code' => 'EMP', 'name' => 'EMPRETINADORA', 'supplement_factor' => 1.21],
            ['code' => 'FUS', 'name' => 'FUSIONADORA', 'supplement_factor' => 1.2],
            ['code' => 'BOT', 'name' => 'BOTONADORA', 'supplement_factor' => 1.169],
        ];
        foreach ($machines as $machine) {
            DB::table('machines')->updateOrInsert(
                ['code' => $machine['code']],
                [
                    'name' => $machine['name'],
                    'supplement_factor' => $machine['supplement_factor'],
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
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
        // Obtener IDs desde settings (kind=parameter)
        $sectionId = $this->getParameterFirstId('sections');
        $groupId = $this->getParameterFirstId('groups');
        $brandId = $this->getParameterFirstId('brands');
        $worldId = $this->getParameterFirstId('worlds');
        $fabricBaseId = $this->getParameterFirstId('fabric_bases');

        // ========== PRODUCT SHEETS Y PROGRAMMERS ==========
        // Se crean escenarios consistentes con la lógica del ERP:
        // - Muestras (type=muestra) generan Programmers AUTO con OP MU-xxxx o MUT-xxxx
        // - Productos (type=producto) pueden tener Programmers MANUALES con OP MF-xxxx o MFT-xxxx
        
        $programmerService = app(ProgrammerService::class);

        // Escenario A: ProductSheet tipo 'muestra', woven_product=false OP MU-xxxx (auto)
        DB::table('product_sheets')->updateOrInsert(
            ['sample_reference' => 'MUESTRA001'],
            [
                'section_id' => $sectionId,
                'group_id' => $groupId,
                'brand_id' => $brandId,
                'world_id' => $worldId,
                'fabric_base_id' => $fabricBaseId,
                'yarn_base_id' => null,
                'description' => 'Pantalón jean básico - Muestra NO tejida',
                'flat_sketch_path' => null,
                'billing_price' => null,
                'selling_price' => null,
                'sizes' => json_encode(['28' => true, '30' => true, '32' => true, '34' => true, '36' => true]),
                'woven_product' => false,
                'processes' => json_encode([]),
                'status' => 'incomplete',
                'type' => 'muestra',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        // Escenario B: ProductSheet tipo 'producto', woven_product=true OP MFT-xxxx (manual)
        DB::table('product_sheets')->updateOrInsert(
            ['product_reference' => 'PRODUCTO02'],
            [
                'section_id' => $sectionId,
                'group_id' => $groupId,
                'brand_id' => $brandId,
                'world_id' => $worldId,
                'fabric_base_id' => $fabricBaseId,
                'yarn_base_id' => null,
                'description' => 'Pantalón jean clásico - Producto TEJIDO',
                'flat_sketch_path' => null,
                'billing_price' => 45000.00,
                'selling_price' => 89000.00,
                'sizes' => json_encode(['S' => true, 'M' => true, 'L' => true, 'XL' => true]),
                'woven_product' => true,
                'processes' => json_encode([]),
                'status' => 'complete',
                'type' => 'producto',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        // Escenario C: ProductSheet tipo 'producto', woven_product=false OP MF-0001 (manual)
        DB::table('product_sheets')->updateOrInsert(
            ['product_reference' => 'PRODUCTO01'],
            [
                'section_id' => $sectionId,
                'group_id' => $groupId,
                'brand_id' => $brandId,
                'world_id' => $worldId,
                'fabric_base_id' => $fabricBaseId,
                'yarn_base_id' => null,
                'description' => 'Pantalón drill clásico - Producto NO tejido',
                'flat_sketch_path' => null,
                'billing_price' => 40000.00,
                'selling_price' => 76000.00,
                'sizes' => json_encode(['30' => true, '32' => true, '34' => true, '36' => true]),
                'woven_product' => false,
                'processes' => json_encode([]),
                'status' => 'complete',
                'type' => 'producto',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        // Obtener IDs de las fichas creadas
        $muestraSheetId = DB::table('product_sheets')->where('sample_reference', 'MUESTRA001')->value('id');
        $productoSheetId = DB::table('product_sheets')->where('product_reference', 'PRODUCTO02')->value('id');
        $productoMfSheetId = DB::table('product_sheets')->where('product_reference', 'PRODUCTO01')->value('id');

        // Obtener el ID del destino desde settings (kind=parameter)
        $destinationId = $this->getParameterFirstId('destinations');

        // Obtener mes actual para temporada (ej: FEBRERO)
        $seasonLabel = strtoupper(now()->locale('es')->monthName);

        // Programmer AUTO para muestra (MU-xxxx) - simula creación desde Sábana de Productos
        // type=muestra, woven_product=false → MU
        $opCodeMuestra = $programmerService->nextOpCode('muestra', false); // MU-0001
        
        DB::table('programmers')->updateOrInsert(
            ['product_sheet_id' => $muestraSheetId, 'source' => 'auto'],
            [
                'op_code' => $opCodeMuestra,
                'season' => $seasonLabel,
                'destination_id' => $destinationId,
                'priority' => null, // Auto-generados NO usan prioridad
                'units' => null, // Pendiente de completar
                'estimated_delivery_date' => null, // Pendiente de completar
                'ecommerce_units' => null,
                'status' => 'incomplete', // Incompleto: falta units y fecha
                'source' => 'auto',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        // Programmer MANUAL para producto (MFT-xxxx) - creado desde formulario Programador
        // type=producto, woven_product=true → MFT
        $opCodeProducto = $programmerService->nextOpCode('producto', true); // MFT-0001
        
        DB::table('programmers')->updateOrInsert(
            ['product_sheet_id' => $productoSheetId, 'source' => 'manual'],
            [
                'op_code' => $opCodeProducto,
                'season' => $seasonLabel,
                'destination_id' => $destinationId,
                'priority' => 'A', // Manuales SÍ usan prioridad
                'units' => 500, // Unidades programadas
                'estimated_delivery_date' => null, // Manuales NO usan fecha entrega
                'ecommerce_units' => json_encode(['S' => 100, 'M' => 150, 'L' => 150, 'XL' => 100]),
                'status' => 'complete', // Manuales: completo si units > 0
                'source' => 'manual',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        // Fixture explícito para Ficha técnica: MF-0001 con prioridad A
        DB::table('programmers')->updateOrInsert(
            ['op_code' => 'MF-0001'],
            [
                'product_sheet_id' => $productoMfSheetId,
                'op_code' => 'MF-0001',
                'season' => $seasonLabel,
                'destination_id' => $destinationId,
                'priority' => 'A',
                'units' => 300,
                'estimated_delivery_date' => null,
                'ecommerce_units' => json_encode(['30' => 80, '32' => 90, '34' => 80, '36' => 50]),
                'status' => 'complete',
                'source' => 'manual',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

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
                'status' => 'pending_receipt', // Estados válidos para invoices: pending_receipt, received, rejected
                'supplier_invoice_number' => 'FT-2026-001',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Crear item de documento para la tela LANA Beige
            $docItem1 = DB::table('purchase_document_items')->insertGetId([
                'purchase_document_id' => $fabricInvoice1Id,
                'item_id' => $lanaId,
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
                'status' => 'pending_receipt', // Estados válidos para invoices: pending_receipt, received, rejected
                'supplier_invoice_number' => 'FT-2026-002',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Crear item de documento para la tela ALGODÓN
            $docItem2 = DB::table('purchase_document_items')->insertGetId([
                'purchase_document_id' => $fabricInvoice2Id,
                'item_id' => $algodonId,
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
    }

    private function getParameterFirstId(string $name, int $fallback = 1): int
    {
        $setting = Setting::where('kind', 'parameter')->where('key', $name)->first();
        if (! $setting) {
            return $fallback;
        }

        $items = $setting->value ?? [];
        if (! is_array($items) || empty($items)) {
            return $fallback;
        }

        foreach ($items as $entry) {
            if (is_array($entry) && isset($entry['id'])) {
                return (int) $entry['id'];
            }
        }

        return $fallback;
    }
}
