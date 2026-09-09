<?php

use App\Models\Production\FabricBase;
use App\Models\Production\FabricRollProgrammingLine;
use App\Models\Production\ProductionOrder;
use App\Models\Production\ProductSheet;
use App\Models\Production\TechnicalSheet;
use App\Services\Production\TechnicalDataSheetRollsStore;

/**
 * Fase 15 del refactor de materiales: TechnicalDataSheetRollsStore dejó de leer/
 * escribir technical_sheet_rolls.lines (JSON) y ahora opera sobre
 * fabric_roll_programming_lines, conservando el mismo contrato {params, lines} hacia
 * TechnicalDataSheetRollsController (que no se tocó). Estos tests cubren el store
 * directamente: ida y vuelta de datos, y que escribir de nuevo reemplaza el conjunto
 * de líneas (borra las que ya no vienen), igual que hacía el JSON completo antes.
 */
function makeRollsStoreProductionOrder(string $suffix): ProductionOrder
{
    $fabricBase = FabricBase::create([
        'codigo' => 'BT-ROLLS-STORE-'.$suffix,
        'nombre' => 'BASE DE PRUEBA ROLLS STORE '.$suffix,
        'factor' => 1.0,
        'is_active' => true,
    ]);

    $productSheet = ProductSheet::create([
        'section_id' => 1,
        'group_id' => 1,
        'brand_id' => 1,
        'world_id' => 1,
        'fabric_base_id' => $fabricBase->id,
        'type' => 'producto',
        'sizes' => ['M'],
        'woven_product' => false,
        'sample_reference' => 'MUESTRA-ROLLS-STORE-'.$suffix,
        'product_reference' => 'PRODUCTO-ROLLS-STORE-'.$suffix,
        'processes' => ['Corte'],
        'status' => 'complete',
    ]);

    return ProductionOrder::create([
        'product_sheet_id' => $productSheet->id,
        'op_code' => 'OP-ROLLS-STORE-'.$suffix,
        'season' => '1',
        'destination_id' => 1,
        'units' => 10,
        'status' => 'active',
        'source' => 'manual',
    ]);
}

it('writes and reads back params and lines through fabric_roll_programming_lines', function () {
    $order = makeRollsStoreProductionOrder('rw');
    $store = app(TechnicalDataSheetRollsStore::class);

    $store->write($order->id, [
        'params' => ['trazo' => 0.75, 'defaultExtra' => 0.05],
        'lines' => [
            [
                'type' => 'PRINCIPAL',
                'fabricName' => 'JERSEY 24',
                'consumptionBase' => 1.5,
                'pricePerMeter' => 12000,
                'programmedMeters' => 30,
                'extraMeters' => 2,
                'rollId' => null,
            ],
        ],
    ]);

    $payload = $store->read($order->id);

    expect($payload['params']['trazo'])->toBe(0.75)
        ->and($payload['params']['defaultExtra'])->toBe(0.05)
        ->and($payload['lines'])->toHaveCount(1);

    $line = $payload['lines'][0];
    expect($line['id'])->toBeString()
        ->and($line['fabricName'])->toBe('JERSEY 24')
        ->and($line['programmedMeters'])->toBe(30.0)
        ->and($line['consumptionBase'])->toBe(1.5);

    $sheet = TechnicalSheet::where('production_order_id', $order->id)->first();
    expect(FabricRollProgrammingLine::where('technical_sheet_id', $sheet->id)->count())->toBe(1);
});

it('replaces the full line set on a second write, deleting lines no longer present', function () {
    $order = makeRollsStoreProductionOrder('replace');
    $store = app(TechnicalDataSheetRollsStore::class);

    $store->write($order->id, [
        'params' => ['trazo' => 0.8, 'defaultExtra' => 0.03],
        'lines' => [
            ['type' => 'PRINCIPAL', 'fabricName' => 'TELA A', 'consumptionBase' => 1, 'pricePerMeter' => 1000, 'programmedMeters' => 10],
            ['type' => 'SECUNDARIA', 'fabricName' => 'TELA B', 'consumptionBase' => 1, 'pricePerMeter' => 1000, 'programmedMeters' => 20],
        ],
    ]);

    $firstRead = $store->read($order->id);
    expect($firstRead['lines'])->toHaveCount(2);
    $keepId = $firstRead['lines'][0]['id'];

    // Segunda escritura: solo la primera línea (con su id existente) + una línea nueva.
    // La segunda línea original (TELA B) no viene en este payload y debe desaparecer.
    $store->write($order->id, [
        'params' => ['trazo' => 0.8, 'defaultExtra' => 0.03],
        'lines' => [
            ['id' => $keepId, 'type' => 'PRINCIPAL', 'fabricName' => 'TELA A', 'consumptionBase' => 1, 'pricePerMeter' => 1000, 'programmedMeters' => 15],
            ['type' => 'PRINCIPAL', 'fabricName' => 'TELA C', 'consumptionBase' => 1, 'pricePerMeter' => 1000, 'programmedMeters' => 5],
        ],
    ]);

    $secondRead = $store->read($order->id);
    $names = collect($secondRead['lines'])->pluck('fabricName')->sort()->values()->all();

    expect($secondRead['lines'])->toHaveCount(2)
        ->and($names)->toBe(['TELA A', 'TELA C']);

    $sheet = TechnicalSheet::where('production_order_id', $order->id)->first();
    expect(FabricRollProgrammingLine::where('technical_sheet_id', $sheet->id)->count())->toBe(2);

    $keptLine = collect($secondRead['lines'])->firstWhere('id', $keepId);
    expect($keptLine)->not->toBeNull()
        ->and($keptLine['programmedMeters'])->toBe(15.0);
});

it('returns the default payload for an order with no rolls programmed yet', function () {
    $order = makeRollsStoreProductionOrder('default');
    $payload = app(TechnicalDataSheetRollsStore::class)->read($order->id);

    expect($payload['params']['trazo'])->toBe(0.8)
        ->and($payload['params']['defaultExtra'])->toBe(0.03)
        ->and($payload['lines'])->toBe([]);
});
