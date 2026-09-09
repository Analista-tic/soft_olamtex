<?php

use App\Models\Inventory\Warehouse;
use App\Models\Inventory\WarehouseItem;
use App\Models\MasterData\CategoryGroup;
use App\Models\MasterData\CategorySubGroup;
use App\Models\MasterData\Item;
use App\Models\Settings\User;
use App\Models\Textil\FabricRoll;
use App\Models\Textil\FabricRollAdjustment;
use App\Services\Textil\FabricRollService;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;

function createRollTestItemWithoutWarehouse(string $suffix): Item
{
    $group = CategoryGroup::firstOrCreate(['name' => 'MATERIA PRIMA MOVEMENT TEST']);
    $subGroup = CategorySubGroup::firstOrCreate([
        'name' => 'Tela Movement Test',
        'category_group_id' => $group->id,
    ]);

    return Item::create([
        'category_sub_group_id' => $subGroup->id,
        'name' => 'Item sin bodega '.$suffix,
        'description' => 'Item de prueba sin bodega asignada '.$suffix,
        'status' => true,
    ]);
}

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    $this->service = app(FabricRollService::class);
});

it('blocks relinking a roll with available quantity to an item without a warehouse, without leaving partial state', function () {
    $item = createRollTestItemWithoutWarehouse('relink');

    $roll = FabricRoll::create([
        'roll_number' => 'RL-NOWH-1',
        'item_id' => null,
        'quantity' => 100,
        'actual_output' => 20,
        'entry_date' => now()->toDateString(),
        'approval_status' => FabricRoll::APPROVAL_APPROVED,
    ]);

    expect(fn () => $this->service->relinkMainFabric($roll, $item->id))
        ->toThrow(ValidationException::class);

    $roll->refresh();
    expect($roll->item_id)->toBeNull()
        ->and(FabricRollAdjustment::where('fabric_roll_id', $roll->id)->exists())->toBeFalse();
});

it('blocks a salida discharge on a roll linked to an item without a warehouse, without leaving partial state', function () {
    $item = createRollTestItemWithoutWarehouse('discharge');

    $roll = FabricRoll::create([
        'roll_number' => 'RL-NOWH-2',
        'item_id' => $item->id,
        'quantity' => 50,
        'actual_output' => 0,
        'entry_date' => now()->toDateString(),
        'approval_status' => FabricRoll::APPROVAL_APPROVED,
    ]);

    expect(fn () => $this->service->discharge($roll, 'salida', 10, 'salida de prueba', null, null))
        ->toThrow(ValidationException::class);

    $roll->refresh();
    expect((float) $roll->actual_output)->toBe(0.0)
        ->and(FabricRollAdjustment::where('fabric_roll_id', $roll->id)->exists())->toBeFalse();
});

it('blocks a partial consumption discharge on a roll linked to an item without a warehouse, without leaving partial state', function () {
    $item = createRollTestItemWithoutWarehouse('partial-consumption');

    $roll = FabricRoll::create([
        'roll_number' => 'RL-NOWH-3',
        'item_id' => $item->id,
        'quantity' => 50,
        'actual_output' => 0,
        'entry_date' => now()->toDateString(),
        'approval_status' => FabricRoll::APPROVAL_APPROVED,
    ]);

    expect(fn () => $this->service->discharge($roll, 'salida', 15, 'consumo parcial', null, null))
        ->toThrow(ValidationException::class);

    $roll->refresh();
    expect((float) $roll->actual_output)->toBe(0.0);
});

it('still creates the adjustment and movement when the item has a warehouse (regression)', function () {
    $item = createRollTestItemWithoutWarehouse('with-warehouse');
    $warehouse = Warehouse::create(['name' => 'Bodega Movement Test', 'observation' => 'Bodega de prueba']);
    WarehouseItem::create(['warehouse_id' => $warehouse->id, 'item_id' => $item->id, 'quantity' => 1000]);

    $roll = FabricRoll::create([
        'roll_number' => 'RL-WH-1',
        'item_id' => $item->id,
        'quantity' => 50,
        'actual_output' => 0,
        'entry_date' => now()->toDateString(),
        'approval_status' => FabricRoll::APPROVAL_APPROVED,
    ]);

    $this->service->discharge($roll, 'salida', 10, 'salida con bodega', null, null);

    $adjustment = FabricRollAdjustment::where('fabric_roll_id', $roll->id)->firstOrFail();
    expect($adjustment->inventory_movement_id)->not->toBeNull();
});

it('eager loads the linked inventory movement in the adjustments history report', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('ver telas rollos', 'web'));
    $this->actingAs($user);

    $item = createRollTestItemWithoutWarehouse('history');
    $warehouse = Warehouse::create(['name' => 'Bodega History Test', 'observation' => 'Bodega de prueba']);
    WarehouseItem::create(['warehouse_id' => $warehouse->id, 'item_id' => $item->id, 'quantity' => 1000]);

    $roll = FabricRoll::create([
        'roll_number' => 'RL-HIST-1',
        'item_id' => $item->id,
        'quantity' => 50,
        'actual_output' => 0,
        'entry_date' => now()->toDateString(),
        'approval_status' => FabricRoll::APPROVAL_APPROVED,
    ]);

    $this->service->discharge($roll, 'salida', 10, 'salida para historial', null, null);

    $response = $this->getJson(route('kardex-telas.reportes.historial-ajustes', ['roll_number' => 'RL-HIST-1']));

    $response->assertOk();
    $row = collect($response->json('data'))->firstOrFail();
    expect($row['inventory_movement'])->not->toBeNull()
        ->and((float) $row['inventory_movement']['quantity'])->toBe(10.0);
});
