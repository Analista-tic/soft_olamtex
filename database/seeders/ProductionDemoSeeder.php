<?php

namespace Database\Seeders;

use App\Models\Production\ProductionOrder;
use App\Models\Production\ProductSheet;
use App\Services\Production\ProductionOrderService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProductionDemoSeeder extends Seeder
{
    private const SHEETS = [
        [
            'sheet' => [
                'section_id' => 14,
                'group_id' => 1,
                'brand_id' => 7,
                'world_id' => 1,
                'fabric_base_id' => 1,
                'type' => 'producto',
                'status' => 'complete',
                'woven_product' => false,
                'sample_reference' => 'OV-DEMO-001',
                'product_reference' => 'OV-DEMO-001',
                'version' => 1,
                'is_enabled' => true,
                'billing_price' => 20000,
                'selling_price' => 69900,
                'sizes' => ['S', 'M', 'L'],
                'description' => 'Overol dama demo',
                'processes' => ['I&D', 'Corte', 'Confección', 'Terminación'],
            ],
            'orders' => [
                [
                    'season' => 'MARZO',
                    'destination_id' => 1,
                    'priority' => 'A',
                    'units' => 200,
                ],
                [
                    'season' => 'JUNIO',
                    'destination_id' => 7,
                    'priority' => 'B',
                    'units' => 150,
                ],
            ],
        ],
        [
            'sheet' => [
                'section_id' => 4,
                'group_id' => 2,
                'brand_id' => 1,
                'world_id' => 1,
                'fabric_base_id' => 13,
                'type' => 'producto',
                'status' => 'complete',
                'woven_product' => false,
                'sample_reference' => 'CAM-DEMO-001',
                'product_reference' => 'CAM-DEMO-001',
                'version' => 1,
                'is_enabled' => true,
                'billing_price' => 15000,
                'selling_price' => 49900,
                'sizes' => ['XS', 'S', 'M', 'L', 'XL'],
                'description' => 'Camiseta dama demo',
                'processes' => ['I&D', 'Corte', 'Confección', 'Terminación'],
            ],
            'orders' => [
                [
                    'season' => 'ABRIL',
                    'destination_id' => 3,
                    'priority' => 'B',
                    'units' => 300,
                ],
                [
                    'season' => 'AGOSTO',
                    'destination_id' => 2,
                    'priority' => 'C',
                    'units' => 100,
                ],
            ],
        ],
        [
            'sheet' => [
                'section_id' => 15,
                'group_id' => 1,
                'brand_id' => 4,
                'world_id' => 2,
                'fabric_base_id' => 2,
                'type' => 'producto',
                'status' => 'complete',
                'woven_product' => false,
                'sample_reference' => 'PAN-DEMO-001',
                'product_reference' => 'PAN-DEMO-001',
                'version' => 1,
                'is_enabled' => true,
                'billing_price' => 25000,
                'selling_price' => 89900,
                'sizes' => ['S', 'M', 'L', 'XL'],
                'description' => 'Pantalón hombre demo',
                'processes' => ['I&D', 'Corte', 'Confección', 'Terminación'],
            ],
            'orders' => [
                [
                    'season' => 'MAYO',
                    'destination_id' => 4,
                    'priority' => 'A',
                    'units' => 250,
                ],
                [
                    'season' => 'SEPTIEMBRE',
                    'destination_id' => 1,
                    'priority' => 'D',
                    'units' => 80,
                ],
            ],
        ],
    ];

    public function run(): void
    {
        $service = $this->resolveProductionOrderService();

        DB::transaction(function () use ($service) {
            foreach (self::SHEETS as $definition) {
                $this->seedSheetWithOrders($definition['sheet'], $definition['orders'], $service);
            }
        });
    }

    private function seedSheetWithOrders(array $sheetData, array $orders, ?ProductionOrderService $service): void
    {
        $sheet = ProductSheet::updateOrCreate(
            [
                'sample_reference' => $sheetData['sample_reference'],
                'version' => 1,
            ],
            $sheetData,
        );

        $this->command?->info(sprintf(
            'ProductSheet %s: %s',
            $sheetData['sample_reference'],
            $sheet->wasRecentlyCreated ? 'creada' : 'actualizada',
        ));

        foreach ($orders as $orderData) {
            $order = ProductionOrder::updateOrCreate(
                [
                    'product_sheet_id' => $sheet->id,
                    'season' => $orderData['season'],
                    'destination_id' => $orderData['destination_id'],
                ],
                [
                    'priority' => $orderData['priority'],
                    'units' => $orderData['units'],
                    'estimated_delivery_date' => null,
                    'ecommerce_units' => null,
                    'status' => 'complete',
                    'current_process' => config('product-sheet.batch_control_processes.0', 'I&D'),
                    'source' => 'manual',
                ],
            );

            if ($service && blank($order->op_code)) {
                $order->op_code = $service->nextOpCode(
                    $sheet->type ?? 'muestra',
                    (bool) $sheet->woven_product,
                );
                $order->save();
            }

            $this->command?->info(sprintf(
                '  ProductionOrder %s/%s: %s%s',
                $sheetData['sample_reference'],
                $orderData['season'],
                $order->wasRecentlyCreated ? 'creada' : 'actualizada',
                $order->op_code ? " ({$order->op_code})" : '',
            ));
        }
    }

    private function resolveProductionOrderService(): ?ProductionOrderService
    {
        if (! class_exists(ProductionOrderService::class) || ! method_exists(ProductionOrderService::class, 'nextOpCode')) {
            return null;
        }

        return app(ProductionOrderService::class);
    }
}
