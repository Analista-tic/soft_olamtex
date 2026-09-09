<?php

use App\Models\Production\FabricBase;
use App\Models\Production\ProductSheet;
use App\Models\Production\ProductionOrder;
use Illuminate\Support\Str;

function createProductSheetForProgrammer(): ProductSheet
{
    $fabricBase = FabricBase::firstOrCreate(
        ['nombre' => 'BASE DE PRUEBA PROGRAMADOR'],
        ['codigo' => 'BT-PROG-001', 'factor' => 1.0, 'is_active' => true],
    );

    return ProductSheet::create([
        'section_id' => 1,
        'group_id' => 1,
        'brand_id' => 1,
        'world_id' => 1,
        'fabric_base_id' => $fabricBase->id,
        'yarn_base_id' => null,
        'type' => 'producto',
        'billing_price' => 10,
        'selling_price' => 12,
        'sizes' => ['S' => true, 'M' => true],
        'woven_product' => false,
        'description' => 'Test product sheet',
        'flat_sketch_path' => null,
        'sample_reference' => null,
        'product_reference' => 'PROD-' . Str::upper(Str::random(8)),
        'processes' => [],
        'operational_list_id' => null,
        'status' => 'complete',
    ]);
}

it('creates one production order per store request', function () {
    $this->withoutMiddleware();

    $productSheet = createProductSheetForProgrammer();

    $payload = [
        'lots' => [[
            'product_sheet_id' => $productSheet->id,
            'season' => 'ENERO',
            'destination_id' => 1,
            'priority' => 'A',
            'units' => 10,
            'ecommerce_units' => ['S' => 5, 'M' => 5],
            'status' => 'complete',
        ]],
    ];

    $response = $this->postJson('/comercial/programador', $payload);

    $response->assertStatus(201);
    expect(ProductionOrder::query()->count())->toBe(1);
    expect($response->json('data.0.id'))->toBeInt();
});

it('updates production order and returns current state', function () {
    $this->withoutMiddleware();

    $productSheet = createProductSheetForProgrammer();
    $programmer = ProductionOrder::create([
        'product_sheet_id' => $productSheet->id,
        'op_code' => 'MF-0001',
        'season' => 'ENERO',
        'destination_id' => 1,
        'priority' => 'A',
        'units' => 10,
        'estimated_delivery_date' => null,
        'ecommerce_units' => ['S' => 5, 'M' => 5],
        'status' => 'complete',
        'source' => 'manual',
    ]);

    $payload = [
        'product_sheet_id' => $productSheet->id,
        'season' => 'FEBRERO',
        'destination_id' => 1,
        'priority' => 'B',
        'units' => 20,
        'ecommerce_units' => ['S' => 10, 'M' => 10],
    ];

    $response = $this->putJson("/comercial/programador/{$programmer->id}", $payload);

    $response->assertStatus(200);

    $programmer->refresh();

    expect($programmer->priority)->toBe('B');
    expect($programmer->units)->toBe(20);
    expect(ProductionOrder::query()->count())->toBe(1);
    expect($response->json('data.id'))->toBe($programmer->id);
});
