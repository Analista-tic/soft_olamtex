<?php

namespace Database\Seeders;

use App\Models\Settings\User;
use App\Models\Settings\Setting;
use App\Services\Inventory\InventoryMovementService;
use App\Services\Inventory\WarehouseTransferService;
use App\Services\Production\ProductionOrderService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ExampleDataSeeder extends Seeder
{
    public function run(): void
    {
        // Usar subgrupo existente creado por CategorySeeder; en una BD limpia (p. ej. pruebas)
        // se crea el catálogo base primero, para no violar la FK de items.category_sub_group_id.
        $categorySubGroupId = DB::table('category_sub_groups')->where('name', 'General')->value('id');
        if ($categorySubGroupId === null) {
            $this->call(CategorySeeder::class);
            $categorySubGroupId = DB::table('category_sub_groups')->where('name', 'General')->value('id');
        }
        $categorySubGroupId ??= 1;

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
            'id_type' => 'nit',
            'id_number' => '900000001',
            'dv' => null,
            'full_name' => 'Proveedor Ejemplo',
            'person_type' => 'persona_juridica',
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

        // Usar impuestos existentes creados por TaxesSeeder; en una BD limpia se crean primero.
        $tax19Id = DB::table('taxes')->where('name', 'IVA 19%')->value('id');
        if ($tax19Id === null) {
            $this->call(TaxesSeeder::class);
            $tax19Id = DB::table('taxes')->where('name', 'IVA 19%')->value('id');
        }
        $tax19Id ??= 1;

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
            'approval_status' => null,
            'has_difference' => false,
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
        }

        // Fichas de producto (ProductSheets)
        // Obtener IDs desde settings (kind=parameter)
        $sectionPantalonId = $this->getParameterIdByName('sections', 'PANTALON', $this->getParameterFirstId('sections'));
        $sectionBuzoId = $this->getParameterIdByName('sections', 'BUZO', $sectionPantalonId);
        $groupLineaId = $this->getParameterIdByName('groups', 'LÍNEA', $this->getParameterFirstId('groups'));
        $brandEvaCastilloId = $this->getParameterIdByName('brands', 'EVA CASTILLO', $this->getParameterFirstId('brands'));
        $brandAmericanEastId = $this->getParameterIdByName('brands', 'AMERICAN EAST', $brandEvaCastilloId);
        $worldDamaId = $this->getParameterIdByName('worlds', 'DAMA', $this->getParameterFirstId('worlds'));
        $worldHombreId = $this->getParameterIdByName('worlds', 'HOMBRE', $worldDamaId);
        // fabric_bases ya no vive en settings (tabla dedicada) — se garantizan por nombre.
        $fabricBaseAlgodonId = \App\Models\Production\FabricBase::firstOrCreate(
            ['nombre' => 'ALGODÓN'],
            ['codigo' => 'BT-EX-001', 'factor' => 1.0, 'is_active' => true],
        )->id;
        $fabricBaseLanaId = \App\Models\Production\FabricBase::firstOrCreate(
            ['nombre' => 'LANA'],
            ['codigo' => 'BT-EX-002', 'factor' => 1.0, 'is_active' => true],
        )->id;

        // ========== PRODUCT SHEETS Y PRODUCTION ORDERS ==========
        // Se crean escenarios consistentes con la lógica del ERP:
        // - Muestras (type=muestra) generan ProductionOrders AUTO con OP MU-xxxx o MUT-xxxx
        // - Productos (type=producto) pueden tener ProductionOrders MANUALES con OP MF-xxxx o MFT-xxxx

        $orderService = app(ProductionOrderService::class);

        // Escenario A: ProductSheet tipo 'muestra', woven_product=false OP MU-xxxx (auto)
        DB::table('product_sheets')->updateOrInsert(
            ['sample_reference' => 'MUESTRA001'],
            [
                'section_id' => $sectionPantalonId,
                'group_id' => $groupLineaId,
                'brand_id' => $brandEvaCastilloId,
                'world_id' => $worldDamaId,
                'fabric_base_id' => $fabricBaseAlgodonId,
                'yarn_base_id' => null,
                'description' => 'Pantalón de muestra - línea dama',
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
                'section_id' => $sectionPantalonId,
                'group_id' => $groupLineaId,
                'brand_id' => $brandEvaCastilloId,
                'world_id' => $worldDamaId,
                'fabric_base_id' => $fabricBaseAlgodonId,
                'yarn_base_id' => null,
                'description' => 'Pantalón dama clásico - base MOMA',
                'flat_sketch_path' => null,
                'billing_price' => 46990.00,
                'selling_price' => 68990.00,
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
                'section_id' => $sectionBuzoId,
                'group_id' => $groupLineaId,
                'brand_id' => $brandAmericanEastId,
                'world_id' => $worldHombreId,
                'fabric_base_id' => $fabricBaseLanaId,
                'yarn_base_id' => null,
                'description' => 'Buzo hoodie cuello tortuga - base perchado',
                'flat_sketch_path' => null,
                'billing_price' => 43990.00,
                'selling_price' => 54990.00,
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
        $destinationId = $this->getParameterIdByName('destinations', 'OLAMTEX', $this->getParameterFirstId('destinations'));

        // Temporada fija de ejemplo para ambiente demo
        $seasonLabel = 'MAYO';

        // ProductionOrder AUTO para muestra (MU-xxxx) - simula creación desde Sábana de Productos
        // type=muestra, woven_product=false → MU
        $opCodeMuestra = $orderService->nextOpCode('muestra', false); // MU-0001

        DB::table('production_orders')->updateOrInsert(
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

        // ProductionOrder MANUAL para producto (MFT-xxxx) - creado desde formulario Programador
        // type=producto, woven_product=true → MFT
        $opCodeProducto = $orderService->nextOpCode('producto', true); // MFT-0001

        DB::table('production_orders')->updateOrInsert(
            ['product_sheet_id' => $productoSheetId, 'source' => 'manual'],
            [
                'op_code' => $opCodeProducto,
                'season' => $seasonLabel,
                'destination_id' => $destinationId,
                'priority' => 'D', // Ejemplo realista para línea de menor urgencia
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
        DB::table('production_orders')->updateOrInsert(
            ['op_code' => 'MF-0001'],
            [
                'product_sheet_id' => $productoMfSheetId,
                'op_code' => 'MF-0001',
                'season' => $seasonLabel,
                'destination_id' => $destinationId,
                'priority' => 'B',
                'units' => 300,
                'estimated_delivery_date' => null,
                'ecommerce_units' => json_encode(['30' => 80, '32' => 90, '34' => 80, '36' => 50]),
                'status' => 'complete',
                'source' => 'manual',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    private function getParameterIdByName(string $key, string $name, int $fallback = 1): int
    {
        $setting = Setting::where('kind', 'parameter')->where('key', $key)->first();
        if (! $setting) {
            return $fallback;
        }

        $items = $setting->value ?? [];
        if (! is_array($items) || empty($items)) {
            return $fallback;
        }

        $normalizedName = $this->normalizeParameterName($name);

        foreach ($items as $entry) {
            if (! is_array($entry) || ! isset($entry['id'], $entry['name'])) {
                continue;
            }

            if ($this->normalizeParameterName((string) $entry['name']) === $normalizedName) {
                return (int) $entry['id'];
            }
        }

        return $fallback;
    }

    private function normalizeParameterName(string $name): string
    {
        $upper = strtoupper(trim($name));

        return strtr($upper, [
            'Á' => 'A',
            'É' => 'E',
            'Í' => 'I',
            'Ó' => 'O',
            'Ú' => 'U',
        ]);
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
