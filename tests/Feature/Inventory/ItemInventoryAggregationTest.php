<?php

use App\Models\Inventory\Warehouse;
use App\Models\Inventory\WarehouseItem;
use App\Models\MasterData\CategoryGroup;
use App\Models\MasterData\CategorySubGroup;
use App\Models\MasterData\Item;
use App\Models\Settings\User;

/**
 * El inventario de items muestra una fila por ítem (suma de sus bodegas); con filtro de bodega solo esa bodega.
 */
beforeEach(function () {
    $this->actingAs(User::factory()->create());

    $group = CategoryGroup::firstOrCreate(['name' => 'INSUMOS AGREGADO TEST']);
    $subGroup = CategorySubGroup::create(['name' => 'Bolsas Agregado Test', 'category_group_id' => $group->id]);
    $this->item = Item::create([
        'category_sub_group_id' => $subGroup->id,
        'name' => 'BOLSA AGREGADO',
        'description' => 'Item de prueba',
        'status' => true,
        'tracks_inventory' => true,
    ]);
    $this->bodegaA = Warehouse::create(['name' => 'Bodega A Agregado', 'observation' => 'Prueba']);
    $this->bodegaB = Warehouse::create(['name' => 'Bodega B Agregado', 'observation' => 'Prueba']);

    WarehouseItem::create(['item_id' => $this->item->id, 'warehouse_id' => $this->bodegaA->id, 'quantity' => 10, 'stock_min' => 0, 'stock_max' => 0, 'weighted_average_price' => 10000, 'total_cost' => 100000]);
    WarehouseItem::create(['item_id' => $this->item->id, 'warehouse_id' => $this->bodegaB->id, 'quantity' => 30, 'stock_min' => 0, 'stock_max' => 0, 'weighted_average_price' => 20000, 'total_cost' => 600000]);
});

it('shows one row per item adding its warehouses', function () {
    $rows = collect($this->getJson(route('inventario-item.data', ['search' => 'BOLSA AGREGADO']))->assertOk()->json('data'));

    expect($rows)->toHaveCount(1);
    expect((float) $rows[0]['quantity'])->toBe(40.0)
        ->and((float) $rows[0]['total_cost'])->toBe(700000.0)
        ->and((float) $rows[0]['average_cost'])->toBe(17500.0)
        ->and($rows[0]['warehouse_name'])->toBe('Bodega A Agregado, Bodega B Agregado');
});

it('only adds the selected warehouse when filtering by warehouse', function () {
    $rows = collect($this->getJson(route('inventario-item.data', ['search' => 'BOLSA AGREGADO', 'warehouse_id' => $this->bodegaB->id]))->json('data'));

    expect($rows)->toHaveCount(1);
    expect((float) $rows[0]['quantity'])->toBe(30.0)
        ->and((float) $rows[0]['total_cost'])->toBe(600000.0)
        ->and($rows[0]['warehouse_name'])->toBe('Bodega B Agregado');
});

it('exports the same filtered rows to Excel', function () {
    $response = $this->get(route('inventario-item.exportar-excel', ['search' => 'BOLSA AGREGADO']));

    $response->assertOk();
    expect($response->headers->get('content-disposition'))->toContain('.xlsx');
});

it('treats as fabric only subgroups with the fabric material kind, not any name containing "tela"', function () {
    $entretela = Item::create([
        'category_sub_group_id' => CategorySubGroup::create([
            'name' => 'ENTRETELA Agregado',
            'category_group_id' => CategoryGroup::firstOrCreate(['name' => 'INSUMOS AGREGADO TEST'])->id,
        ])->id,
        'name' => 'ENTRETELA AGREGADO',
        'description' => 'Item de prueba',
        'status' => true,
        'tracks_inventory' => true,
    ]);
    WarehouseItem::create(['item_id' => $entretela->id, 'warehouse_id' => $this->bodegaA->id, 'quantity' => 7, 'stock_min' => 0, 'stock_max' => 0, 'weighted_average_price' => 100, 'total_cost' => 700]);

    $rows = collect($this->getJson(route('inventario-item.data', ['search' => 'ENTRETELA AGREGADO']))->json('data'));

    expect($rows)->toHaveCount(1);
    expect((float) $rows[0]['quantity'])->toBe(7.0)->and((bool) $rows[0]['is_fabric'])->toBeFalse();
});

it('writes the aggregated rows with their headers and numbers in the Excel file', function () {
    $response = $this->get(route('inventario-item.exportar-excel', ['search' => 'BOLSA AGREGADO']))->assertOk();

    $sheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($response->getFile()->getPathname())->getActiveSheet();

    expect($sheet->rangeToArray('A1:H1')[0])->toBe(['Ítem', 'Referencia', 'Bodega', 'Cantidad', 'Restringido', 'Estado', 'Costo promedio', 'Costo total'])
        ->and($sheet->getCell('A2')->getValue())->toBe('BOLSA AGREGADO')
        ->and($sheet->getCell('C2')->getValue())->toBe('Bodega A Agregado, Bodega B Agregado')
        ->and((float) $sheet->getCell('D2')->getValue())->toBe(40.0)
        ->and((float) $sheet->getCell('G2')->getValue())->toBe(17500.0)
        ->and((float) $sheet->getCell('H2')->getValue())->toBe(700000.0)
        ->and($sheet->getHighestRow())->toBe(2);
});

it('values a fabric item from its available rolls even when the warehouse has no cost', function () {
    $fabricGroup = CategorySubGroup::create([
        'name' => 'Tela Costo Rollos',
        'category_group_id' => CategoryGroup::firstOrCreate(['name' => 'INSUMOS AGREGADO TEST'])->id,
        'material_kind' => 'fabric',
    ]);
    $fabric = Item::create([
        'category_sub_group_id' => $fabricGroup->id,
        'name' => 'TELA COSTO ROLLOS',
        'description' => 'Item de prueba',
        'status' => true,
        'tracks_inventory' => true,
    ]);
    // La bodega tiene metros pero ningún costo (el caso real que mostraba $ 0).
    WarehouseItem::create(['item_id' => $fabric->id, 'warehouse_id' => $this->bodegaA->id, 'quantity' => 71, 'stock_min' => 0, 'stock_max' => 0]);

    foreach ([['RL-COSTO-1', 100, 1000, 20], ['RL-COSTO-2', 50, 4000, 0]] as [$number, $meters, $pricePerMeter, $used]) {
        \App\Models\Textil\FabricRoll::create([
            'roll_number' => $number, 'item_id' => $fabric->id, 'quantity' => $meters, 'price_per_meter' => $pricePerMeter,
            'actual_output' => $used, 'entry_date' => now()->toDateString(),
            'approval_status' => \App\Models\Textil\FabricRoll::APPROVAL_APPROVED,
            'tolerance_status' => \App\Models\Textil\FabricRoll::TOLERANCE_NONE,
        ]);
    }

    $row = collect($this->getJson(route('inventario-item.data', ['search' => 'TELA COSTO ROLLOS']))->assertOk()->json('data'))->first();

    // Disponible: 80 m × $1.000 + 50 m × $4.000 = $280.000 sobre 130 m.
    expect((float) $row['quantity'])->toBe(130.0)
        ->and((float) $row['total_cost'])->toBe(280000.0)
        ->and(round((float) $row['average_cost'], 2))->toBe(2153.85);
});
