<?php

use App\Models\Production\ProductionOrder;
use App\Models\Production\ProductSheet;

it('persists batch control fields through mass update', function () {
    $productSheet = ProductSheet::create([
        'section_id' => 1,
        'group_id' => 1,
        'brand_id' => 1,
        'world_id' => 1,
        'fabric_base_id' => 1,
        'yarn_base_id' => 1,
        'type' => 'producto',
        'billing_price' => 1000,
        'selling_price' => 1200,
        'sizes' => ['M'],
        'woven_product' => false,
        'description' => 'Ficha de prueba',
        'sample_reference' => 'MUESTRA-PO-001',
        'product_reference' => 'PRODUCTO-PO-001',
        'processes' => ['Corte', 'Confección'],
        'status' => 'complete',
    ]);

    $order = ProductionOrder::create([
        'product_sheet_id' => $productSheet->id,
        'op_code' => 'OP-TEST-001',
        'season' => '1',
        'destination_id' => 1,
        'priority' => 'alta',
        'units' => 10,
        'estimated_delivery_date' => '2026-03-30',
        'status' => 'active',
        'current_process' => 'Corte',
        'source' => 'manual',
    ]);

    $order->update([
        'batch_priority' => 7,
        'process_estimated_dates' => [
            'Corte' => '2026-03-24 08:30',
            'Confección' => '2026-03-25 14:15',
        ],
    ]);

    $order->refresh();

    expect($order->batch_priority)->toBe(7)
        ->and($order->process_estimated_dates)->toBe([
            'Corte' => '2026-03-24 08:30',
            'Confección' => '2026-03-25 14:15',
        ]);
});