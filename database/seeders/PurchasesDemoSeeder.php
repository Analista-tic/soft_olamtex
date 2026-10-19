<?php

namespace Database\Seeders;

use App\Models\Expenses\PurchaseDocument;
use App\Models\Expenses\PurchaseDocumentItem;
use App\Models\MasterData\Item;
use App\Models\MasterData\Contact;
use Illuminate\Database\Seeder;

class PurchasesDemoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * Este seeder genera datos de ejemplo que demuestran AMBOS casos:
     * 
     * CASO 1: Facturas de Compra TELA
     * - Facturas que contienen SOLO ítems de tela
     * - Aparecen en la interfaz "Facturas de Compra Tela"
     * - Tienen validación de telas (supplier_code, supplier_color, order_width)
     * 
     * CASO 2: Facturas de Compra GENERAL (NO-TELA)
     * - Facturas que contienen SOLO ítems que NO son tela
     * - Aparecen en la interfaz "Facturas de Compra" (regular)
     * - Sin validación de telas
     */
    public function run(): void
    {
        // Obtener proveedor
        $supplier = Contact::where('contact_type', 'proveedor')->first();
        if (!$supplier) {
            $this->command->warn('No hay proveedores. Creando uno de ejemplo...');
            $supplier = Contact::create([
                'contact_type' => 'proveedor',
                'id_type' => 'nit',
                'id_number' => '900123456',
                'dv' => '7',
                'full_name' => 'Proveedor Demo S.A.S.',
                'person_type' => 'persona_juridica',
                'email' => 'proveedor@example.com',
                'phone' => '555-1234',
                'address' => 'Calle Demo 123',
                'municipality' => 'Bogotá',
                'department' => 'Cundinamarca',
            ]);
        }

        // ========================================================================
        // CASO 1: FACTURAS DE COMPRA CON TELA
        // ========================================================================
        $this->command->info('');
        $this->command->info('═══════════════════════════════════════════════════════════');
        $this->command->info('CASO 1: Creando Facturas de Compra CON TELA');
        $this->command->info('═══════════════════════════════════════════════════════════');

        // Obtener items de tela
        $telas = Item::whereHas('categorySubGroup', function ($q) {
            $q->where('name', 'Tela');
        })->get();

        if ($telas->isEmpty()) {
            $this->command->warn('⚠️  No hay items de Tela. Asegúrate de que ItemsSeeder haya corrido.');
        } else {
            // Factura 1: Solo Tela (Drill Azul)
            $invoice1 = PurchaseDocument::create([
                'document_type' => PurchaseDocument::TYPE_INVOICE,
                'document_number' => 'FAC-TELA-001',
                'contact_id' => $supplier->id,
                'date' => now()->subDays(5),
                'status' => 'invoiced', // Estados válidos para invoices: pending_receipt, invoiced, partially_invoiced, rejected
                'invoiced_at' => now()->subDays(5),
                'supplier_invoice_number' => 'PROV-TELA-001',
            ]);

            // Item: Tela Drill Azul con validación
            PurchaseDocumentItem::create([
                'purchase_document_id' => $invoice1->id,
                'item_id' => $telas->first()->id,
                'quantity' => 50,
                'price' => 25000,
                'tax' => 19,
                'supplier_code' => 'DRILL-AZL-001', // Validación de tela
                'supplier_color' => 'Azul Rey',      // Validación de tela
                'order_width' => 1.50,               // Validación de tela (metros)
            ]);

            $this->command->info("✓ Factura FAC-TELA-001 creada (1x Tela Drill Azul)");

            // Factura 2: Múltiples Telas
            if ($telas->count() > 1) {
                $invoice2 = PurchaseDocument::create([
                    'document_type' => PurchaseDocument::TYPE_INVOICE,
                    'document_number' => 'FAC-TELA-002',
                    'contact_id' => $supplier->id,
                    'date' => now()->subDays(3),
                    'status' => 'pending_receipt', // Estados válidos para invoices: pending_receipt, received, rejected
                    'invoiced_at' => now()->subDays(3),
                    'supplier_invoice_number' => 'PROV-TELA-002',
                ]);

                // Item 1: Tela Drill
                PurchaseDocumentItem::create([
                    'purchase_document_id' => $invoice2->id,
                    'item_id' => $telas->first()->id,
                    'quantity' => 30,
                    'price' => 25000,
                    'tax' => 19,
                    'supplier_code' => 'DRILL-AZL-002',
                    'supplier_color' => 'Azul Marino',
                    'order_width' => 1.50,
                ]);

                // Item 2: Tela Sarga (si existe)
                $segundaTela = $telas->skip(1)->first();
                if ($segundaTela) {
                    PurchaseDocumentItem::create([
                        'purchase_document_id' => $invoice2->id,
                        'item_id' => $segundaTela->id,
                        'quantity' => 40,
                        'price' => 28000,
                        'tax' => 19,
                        'supplier_code' => 'SARGA-GRS-001',
                        'supplier_color' => 'Gris Claro',
                        'order_width' => 1.60,
                    ]);
                }

                $this->command->info("✓ Factura FAC-TELA-002 creada (múltiples telas)");
            }
        }

        // ========================================================================
        // CASO 2: FACTURAS DE COMPRA SIN TELA (GENERAL)
        // ========================================================================
        $this->command->info('');
        $this->command->info('═══════════════════════════════════════════════════════════');
        $this->command->info('CASO 2: Creando Facturas de Compra SIN TELA (General)');
        $this->command->info('═══════════════════════════════════════════════════════════');

        // Obtener items NO-tela
        $noTelaItems = Item::whereHas('categorySubGroup', function ($q) {
            $q->where('name', '!=', 'Tela');
        })->get();

        if ($noTelaItems->isEmpty()) {
            $this->command->warn('⚠️  No hay items sin Tela. Asegúrate de que ItemsSeeder haya creado items de Hilaza, Botón, etc.');
        } else {
            // Factura 3: Solo Accesorios (Hilos, Botones, etc)
            $invoice3 = PurchaseDocument::create([
                'document_type' => PurchaseDocument::TYPE_INVOICE,
                'document_number' => 'FAC-GENERAL-001',
                'contact_id' => $supplier->id,
                'date' => now()->subDays(2),
                'status' => 'invoiced', // Estados válidos para invoices: pending_receipt, invoiced, partially_invoiced, rejected
                'invoiced_at' => now()->subDays(2),
                'supplier_invoice_number' => 'PROV-GENERAL-001',
            ]);

            // Items variados (sin tela)
            foreach ($noTelaItems->take(2) as $item) {
                PurchaseDocumentItem::create([
                    'purchase_document_id' => $invoice3->id,
                    'item_id' => $item->id,
                    'quantity' => rand(50, 500),
                    'price' => rand(1000, 5000),
                    'tax' => 19,
                ]);
            }

            $this->command->info("✓ Factura FAC-GENERAL-001 creada (accesorios sin tela)");

            // Factura 4: Más accesorios
            if ($noTelaItems->count() > 2) {
                $invoice4 = PurchaseDocument::create([
                    'document_type' => PurchaseDocument::TYPE_INVOICE,
                    'document_number' => 'FAC-GENERAL-002',
                    'contact_id' => $supplier->id,
                    'date' => now()->subDay(),
                    'status' => 'pending_receipt', // Estados válidos para invoices: pending_receipt, received, rejected
                    'invoiced_at' => now()->subDay(),
                    'supplier_invoice_number' => 'PROV-GENERAL-002',
                ]);

                // Items variados
                foreach ($noTelaItems->skip(2)->take(2) as $item) {
                    PurchaseDocumentItem::create([
                        'purchase_document_id' => $invoice4->id,
                        'item_id' => $item->id,
                        'quantity' => rand(50, 500),
                        'price' => rand(1000, 5000),
                        'tax' => 19,
                    ]);
                }

                $this->command->info("✓ Factura FAC-GENERAL-002 creada (accesorios sin tela)");
            }
        }

        // ========================================================================
        // RESUMEN
        // ========================================================================
        $this->command->info('');
        $this->command->info('═══════════════════════════════════════════════════════════');
        $this->command->info('✅ Seeder completado');
        $this->command->info('');
        $this->command->info('📊 RESULTADOS:');
        $this->command->info('');
        $this->command->info('INTERFAZ "Facturas de Compra Tela" debe mostrar:');
        $this->command->info('  • FAC-TELA-001 (Tela Drill Azul)');
        $this->command->info('  • FAC-TELA-002 (Tela Drill + Tela Sarga)');
        $this->command->info('');
        $this->command->info('INTERFAZ "Facturas de Compra" (General) debe mostrar:');
        $this->command->info('  • FAC-GENERAL-001 (Hilos, Botones, etc)');
        $this->command->info('  • FAC-GENERAL-002 (Etiquetas, Cierres, etc)');
        $this->command->info('');
        $this->command->info('⚠️  IMPORTANTE:');
        $this->command->info('  • Cada factura debe aparecer SOLO en su interfaz correspondiente');
        $this->command->info('  • Facturas con tela → "Facturas de Compra Tela"');
        $this->command->info('  • Facturas sin tela → "Facturas de Compra"');
        $this->command->info('═══════════════════════════════════════════════════════════');
    }
}
