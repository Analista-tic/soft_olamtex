<?php

use App\Models\Inventory\InventoryMovement;
use App\Models\Inventory\Warehouse;
use App\Models\Inventory\WarehouseLocation;
use App\Models\MasterData\CategoryGroup;
use App\Models\MasterData\CategorySubGroup;
use App\Models\MasterData\Color;
use App\Models\MasterData\Item;
use App\Models\Production\ProductionOrder;
use App\Models\Production\ProductSheet;
use App\Models\Settings\User;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Spatie\Permission\Models\Permission;

/**
 * Salidas de insumos a OP (Control de Lotes > Salida Logística): quedan con la OP
 * en `source_reference`, se filtran en Movimientos de inventario y se exportan a
 * Excel con código de referencia, código legacy y OP para cargarlas al sistema anterior.
 */
beforeEach(function () {
    $group = CategoryGroup::firstOrCreate(['name' => 'MATERIA PRIMA EXPORT OP TEST']);
    $subGroup = CategorySubGroup::updateOrCreate(['name' => 'Subgrupo Export OP Test', 'category_group_id' => $group->id]);
    $color = Color::create(['name' => 'ROJO EXPORT OP TEST', 'code' => '91']);
    $this->warehouse = Warehouse::create(['name' => 'Bodega Export OP', 'observation' => 'Prueba']);
    $location = WarehouseLocation::create(['name' => 'EXPORT-OP-A1', 'status' => true]);

    $user = User::factory()->create();
    foreach (['ver catalogo items', 'crear items', 'crear referencia manual item', 'ver todos movimientos inventario'] as $permission) {
        $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }
    $this->actingAs($user);

    $itemId = $this->postJson(route('item.store'), [
        'category_sub_group_id' => $subGroup->id,
        'color_id' => $color->id,
        'reference' => 'REF-EXPORT-OP-001',
        'reference_mode' => 'manual',
        'description' => 'Botón de prueba export',
        'tax' => 0,
        'negative_movement' => false,
        'item_type' => 'product',
        'warehouses' => [[
            'warehouse_id' => $this->warehouse->id, 'quantity' => 20, 'unit_price' => 100,
            'initial_date' => now()->toDateString(), 'stock_min' => 1, 'stock_max' => 100, 'location_id' => $location->id,
        ]],
    ])->assertCreated()->json('id');
    $this->item = Item::findOrFail($itemId);
    $this->item->update(['legacy_code' => '000123']);

    $sheet = ProductSheet::create([
        'section_id' => 1, 'group_id' => 1, 'brand_id' => 1, 'world_id' => 1,
        'type' => 'producto', 'billing_price' => 1000, 'selling_price' => 1200,
        'sizes' => ['M'], 'woven_product' => false, 'description' => 'Ficha export OP',
        'sample_reference' => 'MUESTRA-EXP', 'product_reference' => 'PRODUCTO-EXP',
        'processes' => ['Corte'], 'status' => 'complete',
    ]);
    $this->order = ProductionOrder::create([
        'product_sheet_id' => $sheet->id, 'op_code' => 'MF-EXP-001', 'season' => '1', 'destination_id' => 1,
        'priority' => 'alta', 'units' => 10, 'estimated_delivery_date' => now()->addMonth()->toDateString(),
        'status' => 'active', 'current_process' => 'Logística', 'source' => 'manual',
    ]);

    $this->postJson(route('control-de-lotes.logistics-supply-exit', $this->order), [
        'lines' => [['item_id' => $this->item->id, 'warehouse_id' => $this->warehouse->id, 'quantity' => 3, 'supply_name' => 'Botón']],
    ])->assertOk();
});

it('stores the OP code as source_reference on logistics supply exits', function () {
    $exit = InventoryMovement::where('type', 'salida')->where('source_type', ProductionOrder::class)->sole();

    expect($exit->source_reference)->toBe('MF-EXP-001')
        ->and((float) $exit->quantity)->toBe(3.0);
});

it('filters movements by OP supply exits and by OP code', function () {
    $this->getJson(route('movimientos-inventario.data', ['origin' => 'op_supply_exit']))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.type', 'salida');

    $this->getJson(route('movimientos-inventario.data', ['op_code' => 'MF-EXP']))->assertOk()->assertJsonCount(1, 'data');
    $this->getJson(route('movimientos-inventario.data', ['op_code' => 'NO-EXISTE']))->assertOk()->assertJsonCount(0, 'data');
    $this->getJson(route('movimientos-inventario.data', ['search' => '000123']))->assertOk()->assertJsonPath('total', 2);
});

it('exports OP supply exits to excel with reference, legacy code and OP', function () {
    $response = $this->get(route('movimientos-inventario.export-excel', ['origin' => 'op_supply_exit']));
    $response->assertOk();

    $rows = IOFactory::load($response->getFile()->getPathname())->getActiveSheet()->toArray();

    expect($rows[0])->toBe(\App\Services\Inventory\InventoryMovementExcelService::HEADERS)
        ->and($rows)->toHaveCount(2)
        ->and($rows[1][2])->toBe('MF-EXP-001')
        ->and($rows[1][3])->toBe('REF-EXPORT-OP-001')
        ->and($rows[1][4])->toBe('000123')
        ->and($rows[1][7])->toBe('salida')
        ->and((float) $rows[1][8])->toBe(3.0);
});
