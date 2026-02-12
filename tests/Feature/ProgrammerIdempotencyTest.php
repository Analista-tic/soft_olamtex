<?php

use App\Models\ProductSheet\ProductSheet;
use App\Models\ProductSheet\Programmer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

function createProductSheetForProgrammer(): ProductSheet
{
    return ProductSheet::create([
        'section_id' => 1,
        'group_id' => 1,
        'brand_id' => 1,
        'world_id' => 1,
        'fabric_base_id' => 1,
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

it('does not duplicate manual programmers on repeated store with same request id', function () {
    $this->withoutMiddleware();
    Cache::flush();

    $productSheet = createProductSheetForProgrammer();
    $requestId = (string) Str::uuid();

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

    $first = $this->postJson('/comercial/programador', $payload, ['X-Request-Id' => $requestId]);
    $second = $this->postJson('/comercial/programador', $payload, ['X-Request-Id' => $requestId]);

    $first->assertStatus(201);
    $second->assertStatus(201);

    expect(Programmer::query()->count())->toBe(1);
    expect($first->json('data.0.id'))->toBe($second->json('data.0.id'));
});

it('replays update response on repeated update with same request id', function () {
    $this->withoutMiddleware();
    Cache::flush();

    $productSheet = createProductSheetForProgrammer();
    $programmer = Programmer::create([
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

    $requestId = (string) Str::uuid();
    $payload = [
        'product_sheet_id' => $productSheet->id,
        'season' => 'FEBRERO',
        'destination_id' => 1,
        'priority' => 'B',
        'units' => 20,
        'ecommerce_units' => ['S' => 10, 'M' => 10],
    ];

    $first = $this->putJson("/comercial/programador/{$programmer->id}", $payload, ['X-Request-Id' => $requestId]);
    $second = $this->putJson("/comercial/programador/{$programmer->id}", $payload, ['X-Request-Id' => $requestId]);

    $first->assertStatus(200);
    $second->assertStatus(200);

    $programmer->refresh();

    expect($programmer->priority)->toBe('B');
    expect($programmer->units)->toBe(20);
    expect(Programmer::query()->count())->toBe(1);
    expect($first->json('data.id'))->toBe($second->json('data.id'));
});
