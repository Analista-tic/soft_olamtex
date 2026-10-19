<?php

use App\Models\Inventory\Warehouse;
use App\Models\Inventory\WarehouseItem;
use App\Models\MasterData\CategoryGroup;
use App\Models\MasterData\CategorySubGroup;
use App\Models\MasterData\Item;
use App\Models\Production\FabricBase;
use App\Models\Production\FabricRollProgrammingLine;
use App\Models\Production\ManualRollProgramming;
use App\Models\Production\ProductionOrder;
use App\Models\Production\ProductionRollConsumption;
use App\Models\Production\ProductSheet;
use App\Models\Production\TechnicalSheet;
use App\Models\Settings\User;
use App\Models\Textil\FabricRoll;
use App\Models\Textil\FabricRollAdjustment;
use App\Models\Textil\FabricRollLengthDifference;
use App\Services\Textil\FabricRollService;
use App\Support\Textil\FabricRollBalance;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;

/**
 * Flujo de descargue de rollos: sobreconsumo con motivo, diferencias de metraje
 * (sobrante/faltante) trazables, corrección de descargues, bloqueo de edición tras
 * descargar y consumo de corte idempotente. Ver docs/textil/FLUJO_ROLLOS_STOCK_Y_DESCARGUE.md.
 */
beforeEach(function () {
    $user = User::factory()->create();
    foreach ([
        'ver telas rollos', 'ajustar telas rollos', 'editar telas rollos', 'eliminar telas rollos',
        'ver descargue telas orden', 'crear descargue telas orden', 'editar descargue telas orden',
        'eliminar descargue telas orden', 'descargar telas orden', 'corregir descargue telas orden',
        'registrar consumo tela corte',
    ] as $permission) {
        $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }
    $this->user = $user;
    $this->actingAs($user);
    $this->service = app(FabricRollService::class);
});

/** @return array{0: FabricRoll, 1: WarehouseItem} */
function flowRoll(string $rollNumber, float $quantity, bool $withItem = true): array
{
    $item = null;
    $stock = null;

    if ($withItem) {
        $group = CategoryGroup::firstOrCreate(['name' => 'MATERIA PRIMA FLOW TEST']);
        $subGroup = CategorySubGroup::firstOrCreate(['name' => 'Tela Flow Test', 'category_group_id' => $group->id]);
        $item = Item::create([
            'category_sub_group_id' => $subGroup->id,
            'name' => 'Item flow '.$rollNumber,
            'description' => 'Item de prueba del flujo de descargue',
            'status' => true,
        ]);
        $warehouse = Warehouse::create(['name' => 'Bodega Flow '.$rollNumber, 'observation' => 'Bodega de prueba']);
        $stock = WarehouseItem::create(['warehouse_id' => $warehouse->id, 'item_id' => $item->id, 'quantity' => 1000]);
    }

    $roll = FabricRoll::create([
        'roll_number' => $rollNumber,
        'item_id' => $item?->id,
        'quantity' => $quantity,
        'actual_output' => 0,
        'partial_output' => 0,
        'price_per_meter' => 5000,
        'entry_date' => now()->toDateString(),
        'approval_status' => FabricRoll::APPROVAL_APPROVED,
    ]);

    return [$roll, $stock];
}

function flowProgram(FabricRoll $roll, float $meters, string $code = 'OP-FLOW'): ManualRollProgramming
{
    return app(FabricRollService::class)->programManual($roll->id, $code, $meters);
}

function flowOrder(string $suffix): ProductionOrder
{
    $fabricBase = FabricBase::create(['codigo' => 'BT-FLOW-'.$suffix, 'nombre' => 'BASE FLOW '.$suffix, 'factor' => 1.0, 'is_active' => true]);
    $sheet = ProductSheet::create([
        'section_id' => 1, 'group_id' => 1, 'brand_id' => 1, 'world_id' => 1,
        'fabric_base_id' => $fabricBase->id, 'type' => 'producto', 'sizes' => ['M'],
        'woven_product' => false, 'sample_reference' => 'MUESTRA-FLOW-'.$suffix,
        'product_reference' => 'PRODUCTO-FLOW-'.$suffix, 'processes' => ['Corte'], 'status' => 'complete',
    ]);
    $order = ProductionOrder::create([
        'product_sheet_id' => $sheet->id, 'op_code' => 'OP-FLOW-'.$suffix, 'season' => '1',
        'destination_id' => 1, 'units' => 10, 'status' => 'active', 'source' => 'manual',
    ]);
    TechnicalSheet::create(['production_order_id' => $order->id, 'status' => TechnicalSheet::STATUS_DRAFT]);

    return $order;
}

function flowCutLine(FabricRoll $roll, float $consumption, bool $last = false): array
{
    return ['id' => (string) Str::uuid(), 'roll_code' => $roll->roll_number, 'total_consumption' => $consumption, 'ultimo_descargue' => $last];
}

// ─── Sobreconsumo sobre lo programado ────────────────────────────────────────

it('allows discharging more than programmed with a reason, taking the excess from the free stock', function () {
    [$roll, $stock] = flowRoll('RL-FLOW-1', 100);
    $line = flowProgram($roll, 50);

    $this->patchJson(route('kardex-telas.descargue-orden.manual.discharge', $line->id), [
        'quantity' => 55,
        'reason' => 'El trazo consumió más de lo calculado',
    ])->assertOk();

    $roll->refresh();
    $line->refresh();

    expect((float) $roll->actual_output)->toEqual(55.0)
        ->and((float) $roll->partial_output)->toEqual(0.0)
        ->and((float) $roll->quantity)->toEqual(100.0)
        ->and((float) $line->consumed_meters)->toEqual(55.0)
        ->and($line->overconsumed_meters)->toEqual(5.0)
        ->and(FabricRollLengthDifference::count())->toBe(0)
        ->and((float) $stock->fresh()->quantity)->toEqual(945.0);
});

it('rejects an overconsumption without reason and leaves everything untouched', function () {
    [$roll] = flowRoll('RL-FLOW-2', 100);
    $line = flowProgram($roll, 50);

    $this->patchJson(route('kardex-telas.descargue-orden.manual.discharge', $line->id), ['quantity' => 55])
        ->assertStatus(422)
        ->assertJsonValidationErrors('reason');

    expect((float) $roll->fresh()->actual_output)->toEqual(0.0)
        ->and((float) $line->fresh()->consumed_meters)->toEqual(0.0);
});

it('never takes the excess from reservations of other orders', function () {
    [$roll] = flowRoll('RL-FLOW-3', 100);
    $lineA = flowProgram($roll, 50, 'OP-A');
    flowProgram($roll, 40, 'OP-B');

    // Libre = 100 − 0 − 90 = 10: el exceso de 5 sale de ahí, la reserva de OP-B queda intacta.
    $this->service->dischargeManual($lineA, 55, 'Sobreconsumo del trazo');

    $roll->refresh();
    expect((float) $roll->partial_output)->toEqual(40.0)
        ->and(FabricRollBalance::free($roll))->toEqual(5.0)
        ->and(FabricRollLengthDifference::count())->toBe(0);
});

// ─── Sobrante de metraje (rollo más largo) ───────────────────────────────────

it('records a traceable surplus when the discharge exceeds the roll real stock, without touching the received quantity', function () {
    [$roll, $stock] = flowRoll('RL-FLOW-4', 100);
    $line = flowProgram($roll, 100);

    $response = $this->patchJson(route('kardex-telas.descargue-orden.manual.discharge', $line->id), [
        'quantity' => 103,
        'reason' => 'El rollo traía más metros de los registrados',
    ])->assertOk();

    $roll->refresh();
    $difference = FabricRollLengthDifference::firstOrFail();
    $discharge = FabricRollAdjustment::where('type', 'salida')->where('fabric_roll_id', $roll->id)->firstOrFail();

    expect((float) $roll->received_quantity)->toEqual(100.0)
        ->and((float) $roll->quantity)->toEqual(100.0)
        ->and((float) $roll->length_adjustment_meters)->toEqual(3.0)
        ->and((float) $difference->quantity_before)->toEqual(100.0)
        ->and((float) $difference->quantity_after)->toEqual(103.0)
        ->and((float) $roll->actual_output)->toEqual(103.0)
        ->and(FabricRollBalance::real($roll))->toEqual(0.0)
        ->and($roll->length_review_status)->toBe(FabricRoll::LENGTH_REVIEW_PENDING)
        ->and($difference->type)->toBe(FabricRollLengthDifference::TYPE_SURPLUS)
        ->and((float) $difference->meters)->toEqual(3.0)
        ->and($difference->source_adjustment_id)->toBe($discharge->id)
        ->and($difference->inventory_movement_id)->not->toBeNull()
        ->and($difference->manual_roll_programming_id)->toBe($line->id)
        // Kardex: +3 de sobrante − 103 de consumo.
        ->and((float) $stock->fresh()->quantity)->toEqual(900.0);

    expect($response->json('message'))->toContain('sobrante de metraje de 3');
});

it('makes a length difference immutable except for its review', function () {
    [$roll] = flowRoll('RL-FLOW-5', 10);
    $line = flowProgram($roll, 10);
    $this->service->dischargeManual($line, 12, 'Rollo más largo');

    $difference = FabricRollLengthDifference::firstOrFail();

    expect(fn () => $difference->update(['meters' => 1]))->toThrow(LogicException::class)
        ->and(fn () => $difference->delete())->toThrow(LogicException::class);
});

// ─── Rollo terminado (faltante) ──────────────────────────────────────────────

it('finishes a roll: records the remaining stock as shortage and releases every pending reservation', function () {
    [$roll, $stock] = flowRoll('RL-FLOW-6', 100);
    $lineA = flowProgram($roll, 40, 'OP-A');
    $lineB = flowProgram($roll, 30, 'OP-B');

    $this->patchJson(route('kardex-telas.descargue-orden.manual.discharge', $lineA->id), [
        'quantity' => 30,
        'reason' => 'El rollo se acabó antes',
        'finish_roll' => true,
    ])->assertOk();

    $roll->refresh();
    $shortage = FabricRollLengthDifference::where('type', FabricRollLengthDifference::TYPE_SHORTAGE)->firstOrFail();

    expect((float) $roll->actual_output)->toEqual(30.0)
        ->and((float) $roll->quantity)->toEqual(100.0)
        ->and((float) $roll->length_adjustment_meters)->toEqual(-70.0)
        ->and((float) $roll->received_quantity)->toEqual(100.0)
        ->and((float) $roll->partial_output)->toEqual(0.0)
        ->and(FabricRollBalance::real($roll))->toEqual(0.0)
        ->and((float) $shortage->meters)->toEqual(70.0)
        ->and($shortage->released_reservations)->toHaveCount(2)
        ->and($lineA->fresh()->remaining_meters)->toEqual(0.0)
        ->and($lineB->fresh()->remaining_meters)->toEqual(0.0)
        ->and((float) $lineB->fresh()->released_meters)->toEqual(30.0)
        // El faltante no es consumo de la orden.
        ->and((float) ProductionRollConsumption::where('fabric_roll_id', $roll->id)->sum('meters_consumed'))->toEqual(30.0)
        // Kardex: −30 de consumo − 70 de faltante.
        ->and((float) $stock->fresh()->quantity)->toEqual(900.0);
});

it('requires a reason to finish a roll', function () {
    [$roll] = flowRoll('RL-FLOW-7', 100);
    $line = flowProgram($roll, 40);

    expect(fn () => $this->service->dischargeManual($line, 10, null, true))->toThrow(ValidationException::class);
    expect((float) $roll->fresh()->actual_output)->toEqual(0.0);
});

// ─── Corrección de descargues ────────────────────────────────────────────────

it('corrects the same discharge twice starting from its current quantity (no double application)', function () {
    [$roll, $stock] = flowRoll('RL-FLOW-8', 100);
    $line = flowProgram($roll, 40);
    $this->service->dischargeManual($line, 15);
    $discharge = FabricRollAdjustment::where('type', 'salida')->where('fabric_roll_id', $roll->id)->firstOrFail();

    $this->patchJson(route('kardex-telas.descargue-orden.manual.discharge.correct', [$line->id, $discharge->id]), ['quantity' => 20])->assertOk();
    $this->patchJson(route('kardex-telas.descargue-orden.manual.discharge.correct', [$line->id, $discharge->id]), ['quantity' => 18])->assertOk();

    $roll->refresh();
    $line->refresh();

    expect((float) $roll->actual_output)->toEqual(18.0)
        ->and((float) $roll->partial_output)->toEqual(22.0)
        ->and((float) $line->consumed_meters)->toEqual(18.0)
        ->and((float) $discharge->fresh()->quantity)->toEqual(18.0)
        ->and($discharge->corrections()->count())->toBe(2)
        ->and((float) ProductionRollConsumption::where('fabric_roll_adjustment_id', $discharge->id)->value('meters_consumed'))->toEqual(18.0)
        ->and((float) $stock->fresh()->quantity)->toEqual(982.0);

    $history = $this->getJson(route('kardex-telas.descargue-orden.manual.history', $line->id))->json('data');
    expect((float) $history[0]['quantity'])->toEqual(18.0)
        ->and($history[0]['corrections_count'])->toBe(2);
});

it('allows a correction above what was programmed only with a reason', function () {
    [$roll] = flowRoll('RL-FLOW-9', 100);
    $line = flowProgram($roll, 20);
    $this->service->dischargeManual($line, 10);
    $discharge = FabricRollAdjustment::where('type', 'salida')->where('fabric_roll_id', $roll->id)->firstOrFail();

    $url = route('kardex-telas.descargue-orden.manual.discharge.correct', [$line->id, $discharge->id]);
    $this->patchJson($url, ['quantity' => 25])->assertStatus(422)->assertJsonValidationErrors('reason');
    $this->patchJson($url, ['quantity' => 25, 'reason' => 'Se cortó una capa más'])->assertOk();

    expect($line->fresh()->overconsumed_meters)->toEqual(5.0)
        ->and((float) $roll->fresh()->partial_output)->toEqual(0.0);
});

it('reverses the surplus a discharge had recorded when a correction lowers its consumption', function () {
    [$roll, $stock] = flowRoll('RL-FLOW-10', 100);
    $line = flowProgram($roll, 100);
    $this->service->dischargeManual($line, 103, 'Rollo más largo');
    $discharge = FabricRollAdjustment::where('type', 'salida')->where('fabric_roll_id', $roll->id)->firstOrFail();

    $this->service->correctManualDischarge($discharge, 100, null, $line);

    $roll->refresh();
    $surplus = FabricRollLengthDifference::where('type', FabricRollLengthDifference::TYPE_SURPLUS)->firstOrFail();
    $reversal = FabricRollLengthDifference::where('reverses_id', $surplus->id)->firstOrFail();

    expect((float) $reversal->meters)->toEqual(3.0)
        ->and($reversal->type)->toBe(FabricRollLengthDifference::TYPE_SHORTAGE)
        ->and((float) $roll->quantity)->toEqual(100.0)
        ->and((float) $roll->length_adjustment_meters)->toEqual(0.0)
        ->and((float) $roll->actual_output)->toEqual(100.0)
        ->and((float) $stock->fresh()->quantity)->toEqual(900.0);
});

it('rejects correcting a discharge through another programming line', function () {
    [$roll] = flowRoll('RL-FLOW-11', 100);
    $lineA = flowProgram($roll, 30, 'OP-A');
    $lineB = flowProgram($roll, 30, 'OP-B');
    $this->service->dischargeManual($lineA, 10);
    $discharge = FabricRollAdjustment::where('type', 'salida')->where('fabric_roll_id', $roll->id)->firstOrFail();

    $this->patchJson(route('kardex-telas.descargue-orden.manual.discharge.correct', [$lineB->id, $discharge->id]), ['quantity' => 12])
        ->assertStatus(422);

    expect((float) $discharge->fresh()->quantity)->toEqual(10.0);
});

// ─── Revisión de diferencias de metraje ──────────────────────────────────────

it('lets only users with the review permission mark a length difference as reviewed', function () {
    [$roll] = flowRoll('RL-FLOW-12', 10);
    $line = flowProgram($roll, 10);
    $this->service->dischargeManual($line, 12, 'Rollo más largo');
    $difference = FabricRollLengthDifference::firstOrFail();

    $this->getJson(route('kardex-telas.diferencias-metraje.data'))
        ->assertOk()
        ->assertJsonPath('data.0.roll_number', 'RL-FLOW-12');

    $url = route('kardex-telas.diferencias-metraje.review', $difference->id);
    $this->patchJson($url, ['review_notes' => 'Verificado con el proveedor'])->assertForbidden();

    $this->user->givePermissionTo(Permission::findOrCreate('revisar diferencias metraje telas', 'web'));
    $this->patchJson($url, ['review_notes' => 'Verificado con el proveedor'])->assertOk();

    expect($difference->fresh()->reviewed_at)->not->toBeNull()
        ->and($roll->fresh()->length_review_status)->toBe(FabricRoll::LENGTH_REVIEW_REVIEWED);

    $this->patchJson($url)->assertStatus(422);
});

// ─── Bloqueo de edición tras descargue ───────────────────────────────────────

it('locks direct edits of a roll once it has discharges', function () {
    [$roll] = flowRoll('RL-FLOW-13', 100);
    [$otherRoll] = flowRoll('RL-FLOW-13B', 10);
    $line = flowProgram($roll, 20);
    $this->service->dischargeManual($line, 5);

    expect($this->service->hasDischarges($roll))->toBeTrue();

    $this->patchJson(route('fabric-rolls.update-price', $roll->id), ['price_per_meter' => 9000])->assertStatus(422);
    $this->patchJson(route('fabric-rolls.correct-quantity', $roll->id), ['new_quantity' => 90, 'reason' => 'x'])->assertStatus(422);
    $this->patchJson(route('kardex-telas.inventario-rollos.relink-main-fabric', $roll->id), ['item_id' => $otherRoll->item_id])->assertStatus(422);
    $this->deleteJson(route('fabric-rolls.destroy', $roll->id))->assertStatus(422);

    $import = $this->service->importQuantities([
        ['row' => 2, 'roll_number' => 'RL-FLOW-13', 'quantity' => 80, 'partial_output' => 0, 'real_availability' => 80, 'item_reference' => '', 'batch' => ''],
    ], 'IMP-FLOW');
    expect($import['updated'])->toBe(0)
        ->and($import['errors'][0]['message'])->toContain('descargues');

    // Siguen permitidas las operaciones trazables.
    $this->patchJson(route('fabric-rolls.discharge', $roll->id), ['type' => 'entrada', 'quantity' => 2, 'reason' => 'Ajuste trazable'])->assertOk();

    $roll->refresh();
    expect((float) $roll->price_per_meter)->toEqual(5000.0)
        ->and((float) $roll->quantity)->toEqual(102.0)
        ->and($roll->trashed())->toBeFalse();
});

it('keeps a roll without discharges editable and moves the kardex with the real stock on import', function () {
    [$roll, $stock] = flowRoll('RL-FLOW-14', 100);

    $result = $this->service->importQuantities([
        ['row' => 2, 'roll_number' => 'RL-FLOW-14', 'quantity' => 100, 'partial_output' => 0, 'real_availability' => 60, 'item_reference' => '', 'batch' => ''],
    ], 'IMP-FLOW-2');

    // Cambia actual_output (0 → 40) sin cambiar la cantidad: antes no movía el kardex.
    expect($result['updated'])->toBe(1)
        ->and((float) $roll->fresh()->actual_output)->toEqual(40.0)
        ->and((float) $stock->fresh()->quantity)->toEqual(960.0);
});

// ─── Ajuste general y rollos no recibidos ────────────────────────────────────

it('does not let a general adjustment take meters reserved for orders', function () {
    [$roll] = flowRoll('RL-FLOW-15', 100);
    flowProgram($roll, 90);

    $this->patchJson(route('fabric-rolls.discharge', $roll->id), ['type' => 'salida', 'quantity' => 20, 'reason' => 'Muestra'])
        ->assertStatus(422);
    $this->patchJson(route('fabric-rolls.discharge', $roll->id), ['type' => 'salida', 'quantity' => 10, 'reason' => 'Muestra'])
        ->assertOk();

    expect((float) $roll->fresh()->actual_output)->toEqual(10.0);
});

it('does not operate a roll whose reception is still pending', function () {
    [$roll] = flowRoll('RL-FLOW-16', 100);
    $roll->update(['approval_status' => FabricRoll::APPROVAL_PENDING]);

    expect(fn () => $this->service->programManual($roll->id, 'OP-PEND', 10))->toThrow(ValidationException::class)
        ->and(fn () => $this->service->discharge($roll, 'entrada', 5, 'x', null, null))->toThrow(ValidationException::class);
});

// ─── Corte (ficha técnica) ───────────────────────────────────────────────────

it('saves cut consumption idempotently, releasing the sheet reservation once from the database', function () {
    [$roll, $stock] = flowRoll('RL-FLOW-17', 100);
    $order = flowOrder('cut');
    $sheet = TechnicalSheet::where('production_order_id', $order->id)->firstOrFail();
    $sheetLine = FabricRollProgrammingLine::create([
        'technical_sheet_id' => $sheet->id, 'legacy_uuid' => (string) Str::uuid(), 'type' => 'PRINCIPAL',
        'fabric_roll_id' => $roll->id, 'fabric_name' => 'JERSEY', 'consumption_base' => 1,
        'price_per_meter' => 5000, 'programmed_meters' => 50,
    ]);
    $roll->update(['partial_output' => 50]);
    // Consumo de otro flujo en la misma OP: el corte no debe borrarlo.
    ProductionRollConsumption::create(['production_order_id' => $order->id, 'fabric_roll_id' => $roll->id, 'meters_consumed' => 1, 'source' => ProductionRollConsumption::SOURCE_ADJUSTMENT]);

    $url = route('control-de-lotes.save-cut-fabric-consumption', $order->id);
    $this->putJson($url, ['lines' => [flowCutLine($roll, 45)]])->assertOk();
    $this->putJson($url, ['lines' => [flowCutLine($roll, 45)]])->assertOk();

    $roll->refresh();
    expect((float) $roll->actual_output)->toEqual(45.0)
        ->and((float) $roll->partial_output)->toEqual(0.0)
        ->and((float) $sheetLine->fresh()->released_meters)->toEqual(50.0)
        ->and(FabricRollBalance::committedByRoll([$roll->id]))->toBe([])
        ->and((float) $stock->fresh()->quantity)->toEqual(955.0)
        ->and(ProductionRollConsumption::where('production_order_id', $order->id)->where('source', 'adjustment')->exists())->toBeTrue();

    // Baja el consumo: devuelve 5 m al rollo y NO vuelve a liberar la reserva.
    $this->putJson($url, ['lines' => [flowCutLine($roll, 40)]])->assertOk();
    $roll->refresh();
    expect((float) $roll->actual_output)->toEqual(40.0)
        ->and((float) $roll->partial_output)->toEqual(0.0)
        ->and((float) $stock->fresh()->quantity)->toEqual(960.0);

    // Último descargue: lo que el sistema todavía tiene es faltante.
    $this->putJson($url, ['lines' => [flowCutLine($roll, 40, true)]])->assertOk();
    $roll->refresh();
    expect((float) $roll->quantity)->toEqual(100.0)
        ->and((float) $roll->length_adjustment_meters)->toEqual(-60.0)
        ->and(FabricRollBalance::real($roll))->toEqual(0.0)
        ->and((float) FabricRollLengthDifference::where('type', 'faltante')->value('meters'))->toEqual(60.0);
});

it('rejects a cut consumption for a roll code that does not exist instead of ignoring it', function () {
    $order = flowOrder('cut-unknown');

    $this->putJson(route('control-de-lotes.save-cut-fabric-consumption', $order->id), [
        'lines' => [['id' => 'x', 'roll_code' => 'RL-NO-EXISTE', 'total_consumption' => 10]],
    ])->assertStatus(422);
});

// ─── Ficha técnica tras el corte ─────────────────────────────────────────────

it('does not release a sheet reservation twice when the line is deleted after the cut released it', function () {
    [$roll] = flowRoll('RL-FLOW-18', 100);
    $order = flowOrder('sheet');
    $sheet = TechnicalSheet::where('production_order_id', $order->id)->firstOrFail();
    $uuid = (string) Str::uuid();
    FabricRollProgrammingLine::create([
        'technical_sheet_id' => $sheet->id, 'legacy_uuid' => $uuid, 'type' => 'PRINCIPAL',
        'fabric_roll_id' => $roll->id, 'fabric_name' => 'JERSEY', 'consumption_base' => 1,
        'price_per_meter' => 5000, 'programmed_meters' => 50, 'released_meters' => 50,
    ]);
    // Otra reserva viva en el rollo (manual) que no debe perderse.
    flowProgram($roll, 20);

    $this->deleteJson(route('produccion.ficha-tecnica.rolls.lines.destroy', [$order->id, $uuid]))->assertOk();

    expect((float) $roll->fresh()->partial_output)->toEqual(20.0);
});

it('reports the committed reservation of sheet lines as programmed minus released', function () {
    [$roll] = flowRoll('RL-FLOW-19', 100);
    $order = flowOrder('committed');
    $sheet = TechnicalSheet::where('production_order_id', $order->id)->firstOrFail();
    FabricRollProgrammingLine::create([
        'technical_sheet_id' => $sheet->id, 'legacy_uuid' => (string) Str::uuid(), 'type' => 'PRINCIPAL',
        'fabric_roll_id' => $roll->id, 'fabric_name' => 'JERSEY', 'consumption_base' => 1,
        'price_per_meter' => 5000, 'programmed_meters' => 50, 'released_meters' => 30,
    ]);

    expect(FabricRollBalance::committedByRoll([$roll->id]))->toBe([$roll->id => 20.0])
        ->and(FabricRollBalance::sheetPendingByRollForOrder($order->id))->toBe([$roll->id => 20.0]);
});

// ─── Auditoría de saldos (datos existentes) ──────────────────────────────────

it('repairs, with a traceable adjustment, a discharge that the old code corrected twice against its original quantity', function () {
    [$roll] = flowRoll('RL-FLOW-20', 100, withItem: false);
    $line = flowProgram($roll, 40);
    $this->service->dischargeManual($line, 15);
    $discharge = FabricRollAdjustment::where('type', 'salida')->where('fabric_roll_id', $roll->id)->firstOrFail();

    // Estado que dejó el código anterior: 15 → 20 (+5) y luego 15 → 18 (+3 otra vez contra el original).
    foreach (['15 m -> 20 m', '15 m -> 18 m'] as $change) {
        FabricRollAdjustment::create([
            'fabric_roll_id' => $roll->id, 'user_id' => $this->user->id, 'type' => 'correction',
            'quantity_before' => 100, 'quantity_after' => 100,
            'reason' => "Corrección de descargue #{$discharge->id}: {$change}",
            'reference' => "manual_programming::{$line->id}", 'corrects_adjustment_id' => $discharge->id,
        ]);
    }
    $discharge->update(['quantity' => 18]);
    $roll->update(['actual_output' => 23, 'partial_output' => 17]);
    $line->update(['consumed_meters' => 23]);

    $this->artisan('textil:audit-roll-balances')->assertSuccessful();
    expect((float) $roll->fresh()->actual_output)->toEqual(23.0);

    $this->artisan('textil:audit-roll-balances', ['--apply' => true])->assertSuccessful();

    $roll->refresh();
    expect((float) $roll->actual_output)->toEqual(18.0)
        ->and((float) $line->fresh()->consumed_meters)->toEqual(18.0)
        ->and((float) $roll->partial_output)->toEqual(22.0)
        ->and(FabricRollAdjustment::where('corrects_adjustment_id', $discharge->id)->where('reason', 'like', 'Auditoría de saldos:%')->count())->toBe(1);

    // Idempotente: una segunda pasada no repara otra vez.
    $this->artisan('textil:audit-roll-balances', ['--apply' => true])->assertSuccessful();
    expect((float) $roll->fresh()->actual_output)->toEqual(18.0);
});

// ─── La cantidad del rollo no cambia al descargar (sobrante/faltante aparte) ──

it('keeps quantity untouched and stores the over-discharge in length_adjustment_meters, with a coherent kardex and balances', function () {
    [$roll, $stock] = flowRoll('RL-FLOW-QTY-1', 100);
    $line = flowProgram($roll, 100);

    // Primer descargue dentro de lo registrado, segundo con sobreconsumo de 11 m.
    $this->service->dischargeManual($line, 90);
    $this->service->dischargeManual($line, 21, 'Rollo más largo');

    $roll->refresh();
    $entradas = (float) \App\Models\Inventory\InventoryMovement::where('type', 'entrada')
        ->where('source_type', FabricRoll::class)->where('source_id', $roll->id)->sum('quantity');

    expect((float) $roll->quantity)->toEqual(100.0)
        ->and((float) $roll->length_adjustment_meters)->toEqual(11.0)
        ->and((float) $roll->actual_output)->toEqual(111.0)
        // real = quantity + ajuste − consumido
        ->and(FabricRollBalance::real($roll))->toEqual(0.0)
        ->and(FabricRollBalance::free($roll))->toEqual(0.0)
        ->and(FabricRollBalance::length($roll))->toEqual(111.0)
        ->and($entradas)->toEqual(11.0)
        // Kardex: +11 de sobrante − 111 de consumo.
        ->and((float) $stock->fresh()->quantity)->toEqual(900.0);

    // El evento de ajuste y la diferencia guardan el metraje vigente (cantidad + ajuste).
    $adjustment = FabricRollAdjustment::where('type', 'salida')->where('fabric_roll_id', $roll->id)->orderByDesc('id')->firstOrFail();
    expect((float) $adjustment->quantity_before)->toEqual(100.0)
        ->and((float) $adjustment->quantity_after)->toEqual(111.0);
});

it('lets the sums of the roll balance include the adjustment in SQL and in the inventory listing', function () {
    [$roll] = flowRoll('RL-FLOW-QTY-2', 100);
    $line = flowProgram($roll, 100);
    $this->service->dischargeManual($line, 105, 'Rollo más largo');

    $row = \Illuminate\Support\Facades\DB::table('fabric_rolls')->selectRaw(FabricRollBalance::sqlReal().' AS real_m, '.FabricRollBalance::sqlFree().' AS free_m')->where('id', $roll->id)->first();
    expect((float) $row->real_m)->toEqual(0.0)
        ->and((float) $row->free_m)->toEqual(0.0);

    // Un faltante deja libre/real consistentes con el ajuste negativo.
    [$roll2] = flowRoll('RL-FLOW-QTY-3', 100);
    $line2 = flowProgram($roll2, 50, 'OP-QTY-3');
    $this->service->dischargeManual($line2, 50, 'Último', true);
    $roll2->refresh();

    expect((float) $roll2->quantity)->toEqual(100.0)
        ->and((float) $roll2->length_adjustment_meters)->toEqual(-50.0)
        ->and(FabricRollBalance::real($roll2))->toEqual(0.0)
        ->and(FabricRollBalance::realFromValues(100.0, 50.0, -50.0))->toEqual(0.0);
});

it('corrects a discharge up and down without ever changing the roll quantity (surplus and its reversal)', function () {
    [$roll, $stock] = flowRoll('RL-FLOW-QTY-4', 100);
    $line = flowProgram($roll, 100);
    $this->service->dischargeManual($line, 100);
    $discharge = FabricRollAdjustment::where('type', 'salida')->where('fabric_roll_id', $roll->id)->firstOrFail();

    // Hacia arriba: 100 -> 107 excede el rollo en 7 m.
    $this->service->correctManualDischarge($discharge, 107, 'Se cortó de más', $line);
    $roll->refresh();
    expect((float) $roll->quantity)->toEqual(100.0)
        ->and((float) $roll->length_adjustment_meters)->toEqual(7.0)
        ->and(FabricRollBalance::real($roll))->toEqual(0.0);

    // Hacia abajo: 107 -> 102 revierte 5 m del sobrante.
    $this->service->correctManualDischarge($discharge->fresh(), 102, null, $line);
    $roll->refresh();
    expect((float) $roll->quantity)->toEqual(100.0)
        ->and((float) $roll->length_adjustment_meters)->toEqual(2.0)
        ->and(FabricRollBalance::real($roll))->toEqual(0.0);

    // Hasta 100: el sobrante desaparece por completo.
    $this->service->correctManualDischarge($discharge->fresh(), 100, null, $line);
    $roll->refresh();
    expect((float) $roll->quantity)->toEqual(100.0)
        ->and((float) $roll->length_adjustment_meters)->toEqual(0.0)
        ->and((float) $roll->actual_output)->toEqual(100.0)
        ->and((float) $stock->fresh()->quantity)->toEqual(900.0);
});

it('exposes length_adjustment_meters in the inventory endpoint and keeps the quantity as received', function () {
    [$roll] = flowRoll('RL-FLOW-QTY-5', 100);
    $line = flowProgram($roll, 100);
    $this->service->dischargeManual($line, 104, 'Rollo más largo');

    $data = $this->getJson(route('kardex-telas.inventario-rollos.data', ['search' => 'RL-FLOW-QTY-5']))->assertOk()->json('data');

    expect($data)->toHaveCount(1)
        ->and((float) $data[0]['roll_qty_value'])->toEqual(100.0)
        ->and((float) $data[0]['length_adjustment_meters'])->toEqual(4.0)
        ->and((float) $data[0]['real_availability_value'])->toEqual(0.0);
});

it('regularizes a roll consumed beyond its meters as a surplus, leaving it at zero and in the kardex', function () {
    // Dato heredado: 24 m vigentes con 43 m consumidos (real -19).
    [$roll, $stock] = flowRoll('RL-NEG-AUD', 24);
    $roll->update(['actual_output' => 43]);

    expect(FabricRollBalance::real($roll->fresh()))->toBe(-19.0);

    $difference = $this->service->regularizeOverConsumption($roll->fresh(), 'Auditoría de saldos');

    $roll = $roll->fresh();
    expect(FabricRollBalance::real($roll))->toBe(0.0)
        ->and((float) $roll->length_adjustment_meters)->toBe(19.0)
        ->and((float) $roll->quantity)->toBe(24.0)
        ->and($roll->length_review_status)->toBe(FabricRoll::LENGTH_REVIEW_PENDING)
        ->and($difference->type)->toBe(FabricRollLengthDifference::TYPE_SURPLUS)
        ->and((float) $difference->meters)->toBe(19.0)
        ->and($difference->inventory_movement_id)->not->toBeNull();

    // Repetirlo no vuelve a sumar: ya no hay real negativo.
    expect($this->service->regularizeOverConsumption($roll, 'otra vez'))->toBeNull()
        ->and((float) $roll->fresh()->length_adjustment_meters)->toBe(19.0);
});

it('applies the roll audit so negative rolls are regularized by the rule, not by hand', function () {
    [$roll] = flowRoll('RL-NEG-CMD', 10);
    $roll->update(['actual_output' => 14]);

    $this->artisan('textil:audit-roll-balances', ['--roll' => 'RL-NEG-CMD'])->assertSuccessful();
    expect(FabricRollBalance::real($roll->fresh()))->toBe(-4.0);

    $this->artisan('textil:audit-roll-balances', ['--roll' => 'RL-NEG-CMD', '--apply' => true])->assertSuccessful();
    expect(FabricRollBalance::real($roll->fresh()))->toBe(0.0)
        ->and(FabricRollLengthDifference::where('fabric_roll_id', $roll->id)->count())->toBe(1);
});

it('records a surplus when a manual discharge takes reserved meters the roll no longer physically has', function () {
    // Rollo de 24 m con 22 ya consumidos (quedan 2) pero 20 m aún reservados por una programación manual:
    // la reserva quedó mayor que lo real (caso que dejó el rollo en -19 antes de esta regla).
    [$roll] = flowRoll('RL-RESERVA-FANTASMA', 24);
    $line = flowProgram($roll, 20, 'OP-FANTASMA');
    $roll->refresh()->update(['actual_output' => 22]);

    $this->service->dischargeManual($line, 10, 'Descargue contra reserva sin tela física');

    $roll = $roll->fresh();
    expect(FabricRollBalance::real($roll))->toBeGreaterThanOrEqual(0.0)
        ->and(FabricRollLengthDifference::where('fabric_roll_id', $roll->id)->where('type', FabricRollLengthDifference::TYPE_SURPLUS)->exists())->toBeTrue();
});
