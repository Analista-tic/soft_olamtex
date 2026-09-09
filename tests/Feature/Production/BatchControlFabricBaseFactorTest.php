<?php

use App\Models\Production\FabricBase;
use App\Models\Production\Operation;
use App\Models\Production\ProductionOrder;
use App\Models\Production\ProductSheet;
use App\Models\Production\TechnicalSheet;
use App\Models\Production\TechnicalSheetOperation;
use App\Models\Settings\User;

it('applies the fabric base factor to the persisted SAM when syncing operations', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $fabricBase = FabricBase::create([
        'codigo' => 'BT-TEST',
        'nombre' => 'BASE DE PRUEBA',
        'factor' => 1.5,
        'is_active' => true,
    ]);

    $productSheet = ProductSheet::create([
        'section_id' => 1,
        'group_id' => 1,
        'brand_id' => 1,
        'world_id' => 1,
        'fabric_base_id' => $fabricBase->id,
        'type' => 'producto',
        'billing_price' => 1000,
        'selling_price' => 1200,
        'sizes' => ['M'],
        'woven_product' => false,
        'description' => 'Ficha de prueba factor base tela',
        'sample_reference' => 'MUESTRA-FACTOR-001',
        'product_reference' => 'PRODUCTO-FACTOR-001',
        'processes' => ['Corte', 'Confección'],
        'status' => 'complete',
    ]);

    $catalogOperation = Operation::create([
        'action' => 'CERRAR',
        'garment_part' => 'BOLSILLO',
        'is_active' => true,
    ]);

    $order = ProductionOrder::create([
        'product_sheet_id' => $productSheet->id,
        'op_code' => 'OP-FACTOR-001',
        'season' => '1',
        'destination_id' => 1,
        'priority' => 'alta',
        'units' => 10,
        'estimated_delivery_date' => '2026-03-30',
        'status' => 'active',
        'current_process' => 'Corte',
        'source' => 'manual',
    ]);

    $response = $this->putJson(route('control-de-lotes.sync-operations', $order), [
        'operations' => [
            [
                'operation_id' => $catalogOperation->id,
                'machine_id' => null,
                'section_id' => null,
                'sequence' => 1,
                'optimal_time' => 1.0,
                'machine_supplement_factor' => 1.0,
                'operator_efficiency_factor' => 1.0,
                'quantity' => 1,
                'fabric_base_id' => $fabricBase->id,
            ],
        ],
    ]);

    $response->assertOk();

    $technicalSheet = TechnicalSheet::where('production_order_id', $order->id)->firstOrFail();
    $operation = TechnicalSheetOperation::where('technical_sheet_id', $technicalSheet->id)->firstOrFail();

    expect($operation->fabric_base_factor)->toBe(1.5)
        ->and($operation->sam_total_unitario)->toBe(1.5);
});
