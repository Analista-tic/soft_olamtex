<?php

use App\Models\Inventory\Warehouse;
use App\Models\Inventory\WarehouseItem;
use App\Models\MasterData\CategoryGroup;
use App\Models\MasterData\CategorySubGroup;
use App\Models\MasterData\Item;
use App\Models\Production\ManualRollProgramming;
use App\Models\Production\ProductionRollConsumption;
use App\Models\Settings\User;
use App\Models\Textil\FabricRoll;
use App\Models\Textil\FabricRollAdjustment;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionMatrixAdjustmentSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Programación/descargue de rollos por un código de orden escrito a mano — mientras
 * el flujo de Órdenes de Producción no está operativo. No depende de fabric_roll_
 * programming_lines/technical_sheets (motor de Corte, sin tocar en este flujo).
 */
beforeEach(function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('ver telas rollos', 'web'));
    $user->givePermissionTo(Permission::findOrCreate('ajustar telas rollos', 'web'));
    $user->givePermissionTo(Permission::findOrCreate('editar telas rollos', 'web'));
    $user->givePermissionTo(Permission::findOrCreate('ver descargue telas orden', 'web'));
    $user->givePermissionTo(Permission::findOrCreate('crear descargue telas orden', 'web'));
    $user->givePermissionTo(Permission::findOrCreate('editar descargue telas orden', 'web'));
    $user->givePermissionTo(Permission::findOrCreate('eliminar descargue telas orden', 'web'));
    $user->givePermissionTo(Permission::findOrCreate('descargar telas orden', 'web'));
    $user->givePermissionTo(Permission::findOrCreate('corregir descargue telas orden', 'web'));
    $this->actingAs($user);
});

function makeManualRoll(string $rollNumber, float $quantity, float $actualOutput = 0, float $partialOutput = 0): FabricRoll
{
    return FabricRoll::create([
        'roll_number' => $rollNumber,
        'item_id' => null,
        'quantity' => $quantity,
        'actual_output' => $actualOutput,
        'partial_output' => $partialOutput,
        'entry_date' => now()->toDateString(),
        'approval_status' => FabricRoll::APPROVAL_APPROVED,
    ]);
}

/**
 * Rollo con ítem + bodega vinculados — para probar el camino "con ítem" (crea
 * movimiento de inventario real además de la trazabilidad de rollo).
 */
function makeManualRollWithItem(string $rollNumber, float $quantity, float $actualOutput = 0, float $partialOutput = 0): FabricRoll
{
    $item = makeManualTestItem($rollNumber);
    $warehouse = Warehouse::create(['name' => 'Bodega Manual Test '.$rollNumber, 'observation' => 'Bodega de prueba']);
    WarehouseItem::create(['warehouse_id' => $warehouse->id, 'item_id' => $item->id, 'quantity' => 1000]);

    return FabricRoll::create([
        'roll_number' => $rollNumber,
        'item_id' => $item->id,
        'quantity' => $quantity,
        'actual_output' => $actualOutput,
        'partial_output' => $partialOutput,
        'entry_date' => now()->toDateString(),
        'approval_status' => FabricRoll::APPROVAL_APPROVED,
    ]);
}

function makeManualTestItem(string $suffix): Item
{
    $group = CategoryGroup::firstOrCreate(['name' => 'MATERIA PRIMA MANUAL TEST']);
    $subGroup = CategorySubGroup::firstOrCreate([
        'name' => 'Tela Manual Test',
        'category_group_id' => $group->id,
    ]);

    return Item::create([
        'category_sub_group_id' => $subGroup->id,
        'name' => 'Item manual '.$suffix,
        'description' => 'Item de prueba para descargue manual',
        'status' => true,
    ]);
}

it('programs a manual line and reserves partial_output respecting the physical ceiling', function () {
    $roll = makeManualRoll('RL-MANUAL-1', 100);

    $response = $this->postJson(route('kardex-telas.descargue-orden.manual.store'), [
        'fabric_roll_id' => $roll->id,
        'manual_order_code' => 'OP-A-MANO-001',
        'programmed_meters' => 40,
    ]);

    $response->assertCreated();
    $roll->refresh();
    expect((float) $roll->partial_output)->toEqual(40.0);
    expect(ManualRollProgramming::count())->toBe(1);
});

it('rejects programming more than what is physically available', function () {
    $roll = makeManualRoll('RL-MANUAL-2', 50, actualOutput: 10, partialOutput: 35);

    $response = $this->postJson(route('kardex-telas.descargue-orden.manual.store'), [
        'fabric_roll_id' => $roll->id,
        'manual_order_code' => 'OP-A-MANO-002',
        'programmed_meters' => 10,
    ]);

    // Techo real = 50 - 10 = 40; ya hay 35 reservados por otra línea, +10 = 45 > 40.
    $response->assertStatus(422);
    $roll->refresh();
    expect((float) $roll->partial_output)->toEqual(35.0);
});

it('creates a new independent line when the same roll is programmed again under the same order, instead of overwriting the total', function () {
    $roll = makeManualRoll('RL-MANUAL-3', 200);

    $this->postJson(route('kardex-telas.descargue-orden.manual.store'), [
        'fabric_roll_id' => $roll->id,
        'manual_order_code' => 'OP-A-MANO-003',
        'programmed_meters' => 40,
    ])->assertCreated();

    $this->postJson(route('kardex-telas.descargue-orden.manual.store'), [
        'fabric_roll_id' => $roll->id,
        'manual_order_code' => 'OP-A-MANO-003',
        'programmed_meters' => 60,
    ])->assertCreated();

    $roll->refresh();
    // Las dos programaciones se suman (40 + 60 = 100), no se sobrescriben: un mismo
    // rollo puede programarse más de una vez bajo la misma orden.
    expect((float) $roll->partial_output)->toEqual(100.0);
    expect(ManualRollProgramming::where('fabric_roll_id', $roll->id)->count())->toBe(2);

    $lines = ManualRollProgramming::where('fabric_roll_id', $roll->id)->orderBy('id')->get();
    expect((float) $lines[0]->programmed_meters)->toEqual(40.0)
        ->and((float) $lines[1]->programmed_meters)->toEqual(60.0);
});

it('partially discharges a manual line, releases partial_output proportionally and creates a movement', function () {
    $roll = makeManualRollWithItem('RL-MANUAL-4', 100);

    $this->postJson(route('kardex-telas.descargue-orden.manual.store'), [
        'fabric_roll_id' => $roll->id,
        'manual_order_code' => 'OP-A-MANO-004',
        'programmed_meters' => 40,
    ])->assertCreated();

    $line = ManualRollProgramming::first();

    $response = $this->patchJson(route('kardex-telas.descargue-orden.manual.discharge', $line->id), [
        'quantity' => 15,
    ]);

    $response->assertOk();
    $roll->refresh();
    $line->refresh();

    expect((float) $roll->actual_output)->toEqual(15.0)
        ->and((float) $roll->partial_output)->toEqual(25.0)
        ->and((float) $line->consumed_meters)->toEqual(15.0);

    expect(ProductionRollConsumption::where('fabric_roll_id', $roll->id)->whereNull('production_order_id')->exists())->toBeTrue();
});

it('rejects a discharge that exceeds what is pending to be programmed', function () {
    $roll = makeManualRoll('RL-MANUAL-5', 100);

    $this->postJson(route('kardex-telas.descargue-orden.manual.store'), [
        'fabric_roll_id' => $roll->id,
        'manual_order_code' => 'OP-A-MANO-005',
        'programmed_meters' => 20,
    ])->assertCreated();

    $line = ManualRollProgramming::first();

    $this->patchJson(route('kardex-telas.descargue-orden.manual.discharge', $line->id), [
        'quantity' => 25,
    ])->assertStatus(422);
});

it('allows completing the discharge in several partial calls, each traceable separately', function () {
    $roll = makeManualRollWithItem('RL-MANUAL-6', 100);

    $this->postJson(route('kardex-telas.descargue-orden.manual.store'), [
        'fabric_roll_id' => $roll->id,
        'manual_order_code' => 'OP-A-MANO-006',
        'programmed_meters' => 25,
    ])->assertCreated();

    $line = ManualRollProgramming::first();

    $this->patchJson(route('kardex-telas.descargue-orden.manual.discharge', $line->id), ['quantity' => 10])->assertOk();
    $this->patchJson(route('kardex-telas.descargue-orden.manual.discharge', $line->id), ['quantity' => 15])->assertOk();

    $line->refresh();
    expect((float) $line->consumed_meters)->toEqual(25.0);

    // Un tercer descargue ya no debe caber.
    $this->patchJson(route('kardex-telas.descargue-orden.manual.discharge', $line->id), ['quantity' => 1])->assertStatus(422);

    $history = $this->getJson(route('kardex-telas.descargue-orden.manual.history', $line->id));
    $history->assertOk();
    expect($history->json('data'))->toHaveCount(2);
});

it('programs several rolls at once under the same manual order code', function () {
    $rollA = makeManualRoll('RL-BATCH-A', 100);
    $rollB = makeManualRoll('RL-BATCH-B', 50);

    $response = $this->postJson(route('kardex-telas.descargue-orden.manual.store-batch'), [
        'manual_order_code' => 'OP-LOTE-001',
        'lines' => [
            ['fabric_roll_id' => $rollA->id, 'programmed_meters' => 30],
            ['fabric_roll_id' => $rollB->id, 'programmed_meters' => 20],
        ],
    ]);

    $response->assertCreated();
    $rollA->refresh();
    $rollB->refresh();

    expect((float) $rollA->partial_output)->toEqual(30.0)
        ->and((float) $rollB->partial_output)->toEqual(20.0)
        ->and(ManualRollProgramming::where('manual_order_code', 'OP-LOTE-001')->count())->toBe(2);
});

it('rejects the whole batch and reserves nothing if any roll in it lacks availability', function () {
    $rollA = makeManualRoll('RL-BATCH-C', 100);
    $rollB = makeManualRoll('RL-BATCH-D', 50, actualOutput: 45);

    $response = $this->postJson(route('kardex-telas.descargue-orden.manual.store-batch'), [
        'manual_order_code' => 'OP-LOTE-002',
        'lines' => [
            ['fabric_roll_id' => $rollA->id, 'programmed_meters' => 30],
            // rollB solo tiene 5 m reales disponibles (50 - 45), pide 20.
            ['fabric_roll_id' => $rollB->id, 'programmed_meters' => 20],
        ],
    ]);

    $response->assertStatus(422);
    $rollA->refresh();
    $rollB->refresh();

    expect((float) $rollA->partial_output)->toEqual(0.0)
        ->and((float) $rollB->partial_output)->toEqual(0.0)
        ->and(ManualRollProgramming::where('manual_order_code', 'OP-LOTE-002')->count())->toBe(0);
});

it('rejects a batch with the same roll repeated twice', function () {
    $roll = makeManualRoll('RL-BATCH-E', 100);

    $this->postJson(route('kardex-telas.descargue-orden.manual.store-batch'), [
        'manual_order_code' => 'OP-LOTE-003',
        'lines' => [
            ['fabric_roll_id' => $roll->id, 'programmed_meters' => 10],
            ['fabric_roll_id' => $roll->id, 'programmed_meters' => 20],
        ],
    ])->assertStatus(422);
});

// ─── Descargar sin ítem vinculado (el rollo conserva su trazabilidad; el
// inventario del ítem se reconcilia después, al vincularlo) ────────────────────

it('discharges a manual line on a roll with no item linked, without creating an inventory movement', function () {
    $roll = makeManualRoll('RL-NOITEM-1', 100);

    $this->postJson(route('kardex-telas.descargue-orden.manual.store'), [
        'fabric_roll_id' => $roll->id,
        'manual_order_code' => 'OP-NOITEM-1',
        'programmed_meters' => 40,
    ])->assertCreated();

    $line = ManualRollProgramming::first();

    $response = $this->patchJson(route('kardex-telas.descargue-orden.manual.discharge', $line->id), ['quantity' => 15]);
    $response->assertOk();

    $roll->refresh();
    $line->refresh();

    expect((float) $roll->actual_output)->toEqual(15.0)
        ->and((float) $roll->partial_output)->toEqual(25.0)
        ->and((float) $line->consumed_meters)->toEqual(15.0)
        ->and(ProductionRollConsumption::where('fabric_roll_id', $roll->id)->exists())->toBeTrue();

    $adjustment = FabricRollAdjustment::where('reference', "manual_programming::{$line->id}")
        ->where('type', 'salida')
        ->firstOrFail();
    expect($adjustment->inventory_movement_id)->toBeNull();

    // El evento de "programar" queda como 'correction', nunca como 'salida'.
    expect(FabricRollAdjustment::where('reference', "manual_programming::{$line->id}")->where('type', 'correction')->exists())->toBeTrue();
});

it('excludes the programming event from the discharge history, even without an item linked', function () {
    $roll = makeManualRoll('RL-NOITEM-2', 100);

    $this->postJson(route('kardex-telas.descargue-orden.manual.store'), [
        'fabric_roll_id' => $roll->id,
        'manual_order_code' => 'OP-NOITEM-2',
        'programmed_meters' => 40,
    ])->assertCreated();

    $line = ManualRollProgramming::first();

    $this->patchJson(route('kardex-telas.descargue-orden.manual.discharge', $line->id), ['quantity' => 10])->assertOk();
    $this->patchJson(route('kardex-telas.descargue-orden.manual.discharge', $line->id), ['quantity' => 15])->assertOk();

    $history = $this->getJson(route('kardex-telas.descargue-orden.manual.history', $line->id));
    $history->assertOk();
    expect($history->json('data'))->toHaveCount(2);
});

it('discharges a simple ajuste (salida) on a roll with no item linked', function () {
    $roll = makeManualRoll('RL-NOITEM-3', 100);

    $response = $this->patchJson(route('fabric-rolls.discharge', $roll->id), [
        'type' => 'salida',
        'quantity' => 20,
        'reason' => 'Prueba sin item',
    ]);

    $response->assertOk();
    $roll->refresh();
    expect((float) $roll->actual_output)->toEqual(20.0);
});

it('reconciles the item real inventory to the net availability once linked after unlinked discharges', function () {
    $roll = makeManualRoll('RL-RECONCILE-1', 100);

    $this->postJson(route('kardex-telas.descargue-orden.manual.store'), [
        'fabric_roll_id' => $roll->id,
        'manual_order_code' => 'OP-RECONCILE-1',
        'programmed_meters' => 40,
    ])->assertCreated();

    $line = ManualRollProgramming::first();
    $this->patchJson(route('kardex-telas.descargue-orden.manual.discharge', $line->id), ['quantity' => 30])->assertOk();

    $roll->refresh();
    expect((float) $roll->actual_output)->toEqual(30.0);

    $item = makeManualTestItem('reconcile-1');
    $warehouse = Warehouse::create(['name' => 'Bodega Reconcile Test', 'observation' => 'Bodega de prueba']);
    WarehouseItem::create(['warehouse_id' => $warehouse->id, 'item_id' => $item->id, 'quantity' => 0]);

    $this->patchJson(route('kardex-telas.inventario-rollos.relink-main-fabric', $roll->id), [
        'item_id' => $item->id,
    ])->assertOk();

    $warehouseItem = WarehouseItem::where('warehouse_id', $warehouse->id)->where('item_id', $item->id)->firstOrFail();

    // 100 (quantity) - 30 (actual_output ya consumido sin ítem) = 70 de disponibilidad
    // neta real, que es exactamente lo que debe entrar al inventario del ítem.
    expect((float) $warehouseItem->quantity)->toEqual(70.0);
});

// ─── Editar y eliminar una programación ya creada ──────────────────────────────

it('edits a manual programming line and adjusts partial_output by the delta', function () {
    $roll = makeManualRoll('RL-EDIT-1', 100);

    $this->postJson(route('kardex-telas.descargue-orden.manual.store'), [
        'fabric_roll_id' => $roll->id,
        'manual_order_code' => 'OP-EDIT-1',
        'programmed_meters' => 40,
    ])->assertCreated();

    $line = ManualRollProgramming::first();

    $response = $this->patchJson(route('kardex-telas.descargue-orden.manual.update', $line->id), [
        'programmed_meters' => 70,
        'notes' => 'Corrección de cantidad',
    ]);

    $response->assertOk();
    $roll->refresh();
    $line->refresh();

    expect((float) $roll->partial_output)->toEqual(70.0)
        ->and((float) $line->programmed_meters)->toEqual(70.0)
        ->and($line->notes)->toBe('Corrección de cantidad');
});

it('rejects editing a manual programming line below what has already been consumed', function () {
    $roll = makeManualRoll('RL-EDIT-2', 100);

    $this->postJson(route('kardex-telas.descargue-orden.manual.store'), [
        'fabric_roll_id' => $roll->id,
        'manual_order_code' => 'OP-EDIT-2',
        'programmed_meters' => 40,
    ])->assertCreated();

    $line = ManualRollProgramming::first();
    $this->patchJson(route('kardex-telas.descargue-orden.manual.discharge', $line->id), ['quantity' => 25])->assertOk();

    $this->patchJson(route('kardex-telas.descargue-orden.manual.update', $line->id), [
        'programmed_meters' => 20,
    ])->assertStatus(422);

    $line->refresh();
    expect((float) $line->programmed_meters)->toEqual(40.0);
});

it('requires the editar permission to update a manual programming line', function () {
    $limitedUser = User::factory()->create();
    $limitedUser->givePermissionTo(Permission::findOrCreate('ver telas rollos', 'web'));
    $limitedUser->givePermissionTo(Permission::findOrCreate('ver descargue telas orden', 'web'));
    $limitedUser->givePermissionTo(Permission::findOrCreate('crear descargue telas orden', 'web'));
    $this->actingAs($limitedUser);

    $roll = makeManualRoll('RL-EDIT-3', 100);

    $this->postJson(route('kardex-telas.descargue-orden.manual.store'), [
        'fabric_roll_id' => $roll->id,
        'manual_order_code' => 'OP-EDIT-3',
        'programmed_meters' => 40,
    ])->assertCreated();

    $line = ManualRollProgramming::first();

    $this->patchJson(route('kardex-telas.descargue-orden.manual.update', $line->id), [
        'programmed_meters' => 60,
    ])->assertStatus(403);
});

it('requires the eliminar permission to delete a manual programming line', function () {
    $limitedUser = User::factory()->create();
    $limitedUser->givePermissionTo(Permission::findOrCreate('ver telas rollos', 'web'));
    $limitedUser->givePermissionTo(Permission::findOrCreate('ver descargue telas orden', 'web'));
    $limitedUser->givePermissionTo(Permission::findOrCreate('crear descargue telas orden', 'web'));
    $this->actingAs($limitedUser);

    $roll = makeManualRoll('RL-DEL-1', 100);

    $this->postJson(route('kardex-telas.descargue-orden.manual.store'), [
        'fabric_roll_id' => $roll->id,
        'manual_order_code' => 'OP-DEL-1',
        'programmed_meters' => 40,
    ])->assertCreated();

    $line = ManualRollProgramming::first();

    $this->deleteJson(route('kardex-telas.descargue-orden.manual.destroy', $line->id))->assertStatus(403);

    expect(ManualRollProgramming::find($line->id))->not->toBeNull();
});

it('deletes a manual programming line when the user has the eliminar permission', function () {
    $roll = makeManualRoll('RL-DEL-2', 100);

    $this->postJson(route('kardex-telas.descargue-orden.manual.store'), [
        'fabric_roll_id' => $roll->id,
        'manual_order_code' => 'OP-DEL-2',
        'programmed_meters' => 40,
    ])->assertCreated();

    $line = ManualRollProgramming::first();

    $this->deleteJson(route('kardex-telas.descargue-orden.manual.destroy', $line->id))->assertOk();

    $roll->refresh();
    expect((float) $roll->partial_output)->toEqual(0.0);
    expect(ManualRollProgramming::find($line->id))->toBeNull();
});

// ─── Corregir un descargue ya registrado (evento individual del historial) ─────

it('corrects an already-registered discharge and adjusts the roll and the line accordingly', function () {
    $roll = makeManualRoll('RL-CORRECT-1', 100);

    $this->postJson(route('kardex-telas.descargue-orden.manual.store'), [
        'fabric_roll_id' => $roll->id,
        'manual_order_code' => 'OP-CORRECT-1',
        'programmed_meters' => 40,
    ])->assertCreated();

    $line = ManualRollProgramming::first();
    $this->patchJson(route('kardex-telas.descargue-orden.manual.discharge', $line->id), ['quantity' => 15])->assertOk();

    $discharge = FabricRollAdjustment::where('reference', "manual_programming::{$line->id}")->where('type', 'salida')->firstOrFail();

    $response = $this->patchJson(
        route('kardex-telas.descargue-orden.manual.discharge.correct', [$line->id, $discharge->id]),
        ['quantity' => 20]
    );

    $response->assertOk();
    $roll->refresh();
    $line->refresh();

    // Delta = 20 - 15 = +5: actual_output sube a 20, partial_output baja a 20
    // (40 programado - 20 consumido), y el consumo de la línea refleja la corrección.
    expect((float) $roll->actual_output)->toEqual(20.0)
        ->and((float) $roll->partial_output)->toEqual(20.0)
        ->and((float) $line->consumed_meters)->toEqual(20.0);
});

it('rejects a discharge correction that would exceed what is programmed', function () {
    $roll = makeManualRoll('RL-CORRECT-2', 100);

    $this->postJson(route('kardex-telas.descargue-orden.manual.store'), [
        'fabric_roll_id' => $roll->id,
        'manual_order_code' => 'OP-CORRECT-2',
        'programmed_meters' => 20,
    ])->assertCreated();

    $line = ManualRollProgramming::first();
    $this->patchJson(route('kardex-telas.descargue-orden.manual.discharge', $line->id), ['quantity' => 10])->assertOk();

    $discharge = FabricRollAdjustment::where('reference', "manual_programming::{$line->id}")->where('type', 'salida')->firstOrFail();

    $this->patchJson(
        route('kardex-telas.descargue-orden.manual.discharge.correct', [$line->id, $discharge->id]),
        ['quantity' => 25]
    )->assertStatus(422);

    $line->refresh();
    expect((float) $line->consumed_meters)->toEqual(10.0);
});

it('requires the corregir descargue permission to correct a discharge', function () {
    $limitedUser = User::factory()->create();
    $limitedUser->givePermissionTo(Permission::findOrCreate('ver telas rollos', 'web'));
    $limitedUser->givePermissionTo(Permission::findOrCreate('ver descargue telas orden', 'web'));
    $limitedUser->givePermissionTo(Permission::findOrCreate('crear descargue telas orden', 'web'));
    $limitedUser->givePermissionTo(Permission::findOrCreate('descargar telas orden', 'web'));

    $roll = makeManualRoll('RL-CORRECT-3', 100);
    $this->postJson(route('kardex-telas.descargue-orden.manual.store'), [
        'fabric_roll_id' => $roll->id,
        'manual_order_code' => 'OP-CORRECT-3',
        'programmed_meters' => 40,
    ])->assertCreated();

    $line = ManualRollProgramming::first();
    $this->patchJson(route('kardex-telas.descargue-orden.manual.discharge', $line->id), ['quantity' => 15])->assertOk();
    $discharge = FabricRollAdjustment::where('reference', "manual_programming::{$line->id}")->where('type', 'salida')->firstOrFail();

    $this->actingAs($limitedUser);

    $this->patchJson(
        route('kardex-telas.descargue-orden.manual.discharge.correct', [$line->id, $discharge->id]),
        ['quantity' => 20]
    )->assertStatus(403);
});

it('reports the correct discharge quantity in history even without an item linked (regression for null quantity)', function () {
    $roll = makeManualRoll('RL-CORRECT-4', 100);

    $this->postJson(route('kardex-telas.descargue-orden.manual.store'), [
        'fabric_roll_id' => $roll->id,
        'manual_order_code' => 'OP-CORRECT-4',
        'programmed_meters' => 40,
    ])->assertCreated();

    $line = ManualRollProgramming::first();
    $this->patchJson(route('kardex-telas.descargue-orden.manual.discharge', $line->id), ['quantity' => 12])->assertOk();

    $history = $this->getJson(route('kardex-telas.descargue-orden.manual.history', $line->id));
    $history->assertOk();
    expect((float) $history->json('data')[0]['quantity'])->toEqual(12.0);
});

// ─── Roles Diseño/Corte: separación entre programar/editar/eliminar y descargar ──

it('assigns the DISEÑO role permissions to program/edit/delete but not to discharge or correct', function () {
    // RolePermissionMatrixAdjustmentSeeder es un ajuste sobre una base ya sembrada:
    // referencia permisos ("ver empresa", etc.) que solo crea el bootstrap original.
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(PermissionSeeder::class);
    $this->seed(RolePermissionMatrixAdjustmentSeeder::class);

    $role = Role::findByName('DISEÑO', 'web');

    $permissions = $role->permissions->pluck('name');
    expect($permissions)->toContain('crear descargue telas orden')
        ->toContain('editar descargue telas orden')
        ->toContain('eliminar descargue telas orden')
        ->not->toContain('descargar telas orden')
        ->not->toContain('corregir descargue telas orden');
});

it('assigns the corte role permissions to discharge and correct but not to program/edit/delete', function () {
    // RolePermissionMatrixAdjustmentSeeder es un ajuste sobre una base ya sembrada:
    // referencia permisos ("ver empresa", etc.) que solo crea el bootstrap original.
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(PermissionSeeder::class);
    $this->seed(RolePermissionMatrixAdjustmentSeeder::class);

    $role = Role::findByName('corte', 'web');

    $permissions = $role->permissions->pluck('name');
    expect($permissions)->toContain('descargar telas orden')
        ->toContain('corregir descargue telas orden')
        ->not->toContain('crear descargue telas orden')
        ->not->toContain('editar descargue telas orden')
        ->not->toContain('eliminar descargue telas orden');
});
