<?php

use App\Models\Inventory\Warehouse;
use App\Models\Inventory\WarehouseItem;
use App\Models\MasterData\CategoryGroup;
use App\Models\MasterData\CategorySubGroup;
use App\Models\MasterData\Item;
use App\Models\Production\FabricBase;
use App\Models\Production\ProductionOrder;
use App\Models\Production\ProductionRollConsumption;
use App\Models\Production\ProductSheet;
use App\Models\Settings\User;
use App\Models\Textil\FabricRoll;
use App\Models\Textil\FabricRollAdjustment;
use App\Services\Textil\FabricRollService;
use Spatie\Permission\Models\Permission;

/**
 * El campo "Orden de producción" del Ajuste de rollo pedía el id interno de
 * production_orders, que el usuario nunca ve (solo el op_code visible en toda la UI,
 * ej. "MF-5005"). Ahora es un código de texto libre: se guarda siempre, y el id solo
 * se resuelve cuando existe una ProductionOrder con ese op_code.
 */
beforeEach(function () {
    $user = User::factory()->create();
    foreach (['ver telas rollos', 'ajustar telas rollos'] as $permission) {
        $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }
    $this->user = $user;
    $this->actingAs($user);
    $this->service = app(FabricRollService::class);
});

function orderCodeTestOrder(string $opCode): ProductionOrder
{
    $fabricBase = FabricBase::create(['codigo' => 'BT-OC-'.$opCode, 'nombre' => 'BASE '.$opCode, 'factor' => 1.0, 'is_active' => true]);
    $sheet = ProductSheet::create([
        'section_id' => 1, 'group_id' => 1, 'brand_id' => 1, 'world_id' => 1,
        'fabric_base_id' => $fabricBase->id, 'type' => 'producto', 'sizes' => ['M'],
        'woven_product' => false, 'sample_reference' => 'MUESTRA-'.$opCode,
        'product_reference' => 'PRODUCTO-'.$opCode, 'processes' => ['Corte'], 'status' => 'complete',
    ]);

    return ProductionOrder::create([
        'product_sheet_id' => $sheet->id, 'op_code' => $opCode, 'season' => '1',
        'destination_id' => 1, 'units' => 10, 'status' => 'active', 'source' => 'manual',
    ]);
}

function orderCodeTestRoll(string $rollNumber, float $quantity): array
{
    $group = CategoryGroup::firstOrCreate(['name' => 'MATERIA PRIMA ORDER CODE TEST']);
    $subGroup = CategorySubGroup::firstOrCreate(['name' => 'Tela Order Code Test', 'category_group_id' => $group->id]);
    $item = Item::create([
        'category_sub_group_id' => $subGroup->id,
        'name' => 'Item order code '.$rollNumber,
        'description' => 'Item de prueba',
        'status' => true,
    ]);
    $warehouse = Warehouse::create(['name' => 'Bodega Order Code '.$rollNumber, 'observation' => 'Bodega de prueba']);
    $stock = WarehouseItem::create(['warehouse_id' => $warehouse->id, 'item_id' => $item->id, 'quantity' => 1000]);

    $roll = FabricRoll::create([
        'roll_number' => $rollNumber,
        'item_id' => $item->id,
        'quantity' => $quantity,
        'actual_output' => 0,
        'partial_output' => 0,
        'price_per_meter' => 5000,
        'entry_date' => now()->toDateString(),
        'approval_status' => FabricRoll::APPROVAL_APPROVED,
    ]);

    return [$roll, $stock];
}

it('resolves the production order id from its visible op_code', function () {
    [$roll] = orderCodeTestRoll('RL-OC-1', 50);
    $order = orderCodeTestOrder('MF-5005');

    $this->patchJson(route('fabric-rolls.discharge', $roll->id), [
        'type' => 'salida',
        'quantity' => 10,
        'reason' => 'Prueba con código de orden existente',
        'production_order_code' => 'MF-5005',
    ])->assertOk();

    $adjustment = FabricRollAdjustment::where('fabric_roll_id', $roll->id)->firstOrFail();
    $consumption = ProductionRollConsumption::where('fabric_roll_id', $roll->id)->firstOrFail();

    expect($adjustment->production_order_code)->toBe('MF-5005')
        ->and($consumption->production_order_id)->toBe($order->id)
        ->and($consumption->production_order_code)->toBe('MF-5005');
});

it('accepts an order code that does not match any registered production order yet', function () {
    [$roll] = orderCodeTestRoll('RL-OC-2', 50);

    $this->patchJson(route('fabric-rolls.discharge', $roll->id), [
        'type' => 'salida',
        'quantity' => 10,
        'reason' => 'Prueba con código de lote aún no registrado',
        'production_order_code' => 'MF-9999',
    ])->assertOk();

    $adjustment = FabricRollAdjustment::where('fabric_roll_id', $roll->id)->firstOrFail();
    $consumption = ProductionRollConsumption::where('fabric_roll_id', $roll->id)->firstOrFail();

    expect($adjustment->production_order_code)->toBe('MF-9999')
        ->and($consumption->production_order_id)->toBeNull()
        ->and($consumption->production_order_code)->toBe('MF-9999');
});

it('leaves the order code null when the field is left empty', function () {
    [$roll] = orderCodeTestRoll('RL-OC-3', 50);

    $this->patchJson(route('fabric-rolls.discharge', $roll->id), [
        'type' => 'entrada',
        'quantity' => 10,
        'reason' => 'Entrada sin orden asociada',
    ])->assertOk();

    $adjustment = FabricRollAdjustment::where('fabric_roll_id', $roll->id)->firstOrFail();
    expect($adjustment->production_order_code)->toBeNull();
});
