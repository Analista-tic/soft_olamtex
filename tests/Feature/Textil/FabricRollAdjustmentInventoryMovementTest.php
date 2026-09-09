<?php

use App\Models\Inventory\InventoryMovement;
use App\Models\Inventory\Warehouse;
use App\Models\Inventory\WarehouseItem;
use App\Models\MasterData\CategoryGroup;
use App\Models\MasterData\CategorySubGroup;
use App\Models\MasterData\Item;
use App\Models\Settings\User;
use App\Models\Textil\FabricRoll;
use App\Models\Textil\FabricRollAdjustment;
use App\Services\Textil\FabricRollService;

function createRollTestItemWithWarehouse(string $suffix): array
{
    $group = CategoryGroup::firstOrCreate(['name' => 'MATERIA PRIMA TEST']);
    $subGroup = CategorySubGroup::firstOrCreate([
        'name' => 'Tela Test',
        'category_group_id' => $group->id,
    ]);

    $item = Item::create([
        'category_sub_group_id' => $subGroup->id,
        'name' => 'Item roll test '.$suffix,
        'description' => 'Item de prueba para ajustes de rollo '.$suffix,
        'status' => true,
    ]);

    $warehouse = Warehouse::create([
        'name' => 'Bodega Test '.$suffix,
        'observation' => 'Bodega de prueba',
    ]);

    WarehouseItem::create([
        'warehouse_id' => $warehouse->id,
        'item_id' => $item->id,
        'quantity' => 1000,
    ]);

    return [$item, $warehouse];
}

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    $this->service = app(FabricRollService::class);
});

it('links the inventory movement created on the first fabric-to-item link', function () {
    [$item] = createRollTestItemWithWarehouse('relink');

    $roll = FabricRoll::create([
        'roll_number' => 'RL-RELINK-1',
        'item_id' => null,
        'quantity' => 100,
        'actual_output' => 20,
        'entry_date' => now()->toDateString(),
        'approval_status' => FabricRoll::APPROVAL_APPROVED,
    ]);

    $this->service->relinkMainFabric($roll, $item->id);

    $adjustment = FabricRollAdjustment::where('fabric_roll_id', $roll->id)->firstOrFail();

    expect($adjustment->inventory_movement_id)->not->toBeNull();

    $movement = InventoryMovement::findOrFail($adjustment->inventory_movement_id);
    expect((float) $movement->quantity)->toBe(80.0)
        ->and($movement->item_id)->toBe($item->id)
        ->and($movement->source_type)->toBe(FabricRoll::class)
        ->and($movement->source_id)->toBe($roll->id);
});

it('does not create an inventory movement when relinking without available quantity', function () {
    [$item] = createRollTestItemWithWarehouse('relink-empty');

    $roll = FabricRoll::create([
        'roll_number' => 'RL-RELINK-2',
        'item_id' => null,
        'quantity' => 20,
        'actual_output' => 20,
        'entry_date' => now()->toDateString(),
        'approval_status' => FabricRoll::APPROVAL_APPROVED,
    ]);

    $this->service->relinkMainFabric($roll, $item->id);

    $adjustment = FabricRollAdjustment::where('fabric_roll_id', $roll->id)->firstOrFail();

    expect($adjustment->inventory_movement_id)->toBeNull();
});

it('links each discharge adjustment to its own inventory movement even with overlapping roll numbers', function () {
    [$itemA] = createRollTestItemWithWarehouse('overlap-a');
    [$itemB] = createRollTestItemWithWarehouse('overlap-b');

    // "R-100" es substring de "R-1000": una búsqueda por LIKE en notas podría cruzarlos.
    $rollShort = FabricRoll::create([
        'roll_number' => 'R-100',
        'item_id' => $itemA->id,
        'quantity' => 50,
        'actual_output' => 0,
        'entry_date' => now()->toDateString(),
        'approval_status' => FabricRoll::APPROVAL_APPROVED,
    ]);

    $rollLong = FabricRoll::create([
        'roll_number' => 'R-1000',
        'item_id' => $itemB->id,
        'quantity' => 50,
        'actual_output' => 0,
        'entry_date' => now()->toDateString(),
        'approval_status' => FabricRoll::APPROVAL_APPROVED,
    ]);

    $this->service->discharge($rollLong, 'salida', 5, 'salida rollo largo', null, null);
    $this->service->discharge($rollShort, 'salida', 10, 'salida rollo corto', null, null);

    $shortAdjustment = FabricRollAdjustment::where('fabric_roll_id', $rollShort->id)->firstOrFail();
    $longAdjustment = FabricRollAdjustment::where('fabric_roll_id', $rollLong->id)->firstOrFail();

    $shortMovement = InventoryMovement::findOrFail($shortAdjustment->inventory_movement_id);
    $longMovement = InventoryMovement::findOrFail($longAdjustment->inventory_movement_id);

    expect((float) $shortMovement->quantity)->toBe(10.0)
        ->and($shortMovement->item_id)->toBe($itemA->id)
        ->and((float) $longMovement->quantity)->toBe(5.0)
        ->and($longMovement->item_id)->toBe($itemB->id);
});
