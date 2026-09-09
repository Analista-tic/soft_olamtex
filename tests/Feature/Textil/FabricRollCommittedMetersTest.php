<?php

use App\Models\Production\FabricBase;
use App\Models\Production\FabricRollProgrammingLine;
use App\Models\Production\ProductionOrder;
use App\Models\Production\ProductSheet;
use App\Models\Production\TechnicalSheet;
use App\Models\Settings\User;
use App\Models\Textil\FabricRoll;
use App\Services\Textil\FabricRollService;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

/**
 * Fase 15 del refactor de materiales: FabricRollController::getCommittedMetersByRollId()
 * dejó de recorrer el JSON technical_sheet_rolls.lines y ahora consulta
 * fabric_roll_programming_lines directamente. Este test cubre el endpoint público real
 * (ficha-tecnica.available-rolls) que lo usa para descontar disponibilidad.
 */
function makeCommittedMetersProductionOrder(string $suffix): ProductionOrder
{
    $fabricBase = FabricBase::create([
        'codigo' => 'BT-COMMITTED-'.$suffix,
        'nombre' => 'BASE DE PRUEBA COMMITTED '.$suffix,
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
        'sample_reference' => 'MUESTRA-COMMITTED-'.$suffix,
        'product_reference' => 'PRODUCTO-COMMITTED-'.$suffix,
        'processes' => ['Corte'],
        'status' => 'complete',
    ]);

    $order = ProductionOrder::create([
        'product_sheet_id' => $productSheet->id,
        'op_code' => 'OP-COMMITTED-'.$suffix,
        'season' => '1',
        'destination_id' => 1,
        'units' => 10,
        'status' => 'active',
        'source' => 'manual',
    ]);

    TechnicalSheet::create([
        'production_order_id' => $order->id,
        'status' => TechnicalSheet::STATUS_DRAFT,
    ]);

    return $order;
}

beforeEach(function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('ver telas rollos', 'web'));
    $this->actingAs($user);
});

it('discounts meters already programmed in a technical sheet from a roll availability', function () {
    $roll = FabricRoll::create([
        'roll_number' => 'RL-COMMITTED-1',
        'item_id' => null,
        'quantity' => 100,
        'actual_output' => 10,
        // En el flujo real, TechnicalDataSheetRollsController::saveAll() sincroniza
        // partial_output al mismo tiempo que crea la línea de programación — se replica
        // aquí a mano porque este test crea la línea directo por Eloquent.
        'partial_output' => 30,
        'entry_date' => now()->toDateString(),
        'approval_status' => FabricRoll::APPROVAL_APPROVED,
    ]);

    $order = makeCommittedMetersProductionOrder('discount');
    $sheet = TechnicalSheet::where('production_order_id', $order->id)->first();

    FabricRollProgrammingLine::create([
        'technical_sheet_id' => $sheet->id,
        'legacy_uuid' => (string) Str::uuid(),
        'type' => 'PRINCIPAL',
        'fabric_roll_id' => $roll->id,
        'fabric_name' => 'JERSEY 24',
        'consumption_base' => 1,
        'price_per_meter' => 1000,
        'programmed_meters' => 30,
    ]);

    $response = $this->getJson(route('produccion.ficha-tecnica.available-rolls'));
    $response->assertOk();

    $row = collect($response->json('data'))->firstWhere('id', $roll->id);

    expect($row)->not->toBeNull()
        // 100 (quantity) - 10 (actual_output) - 30 (programado) = 60
        ->and($row['partial_availability_value'])->toEqual(60.0);
});

it('excludes the current order own programmed meters when excludeOpId is passed', function () {
    $roll = FabricRoll::create([
        'roll_number' => 'RL-COMMITTED-2',
        'item_id' => null,
        'quantity' => 100,
        'actual_output' => 0,
        'partial_output' => 40,
        'entry_date' => now()->toDateString(),
        'approval_status' => FabricRoll::APPROVAL_APPROVED,
    ]);

    $order = makeCommittedMetersProductionOrder('exclude');
    $sheet = TechnicalSheet::where('production_order_id', $order->id)->first();

    FabricRollProgrammingLine::create([
        'technical_sheet_id' => $sheet->id,
        'legacy_uuid' => (string) Str::uuid(),
        'type' => 'PRINCIPAL',
        'fabric_roll_id' => $roll->id,
        'fabric_name' => 'JERSEY 24',
        'consumption_base' => 1,
        'price_per_meter' => 1000,
        'programmed_meters' => 40,
    ]);

    // Sin excludeOpId: se descuentan los 40 m programados por esta misma orden.
    $withoutExclude = $this->getJson(route('produccion.ficha-tecnica.available-rolls'));
    $rowWithout = collect($withoutExclude->json('data'))->firstWhere('id', $roll->id);
    expect($rowWithout['partial_availability_value'])->toEqual(60.0);

    // Con excludeOpId = la propia orden: no se descuenta su propia línea programada.
    $withExclude = $this->getJson(route('produccion.ficha-tecnica.available-rolls', ['excludeOpId' => $order->id]));
    $rowWith = collect($withExclude->json('data'))->firstWhere('id', $roll->id);
    expect($rowWith['partial_availability_value'])->toEqual(100.0);
});

it('discounts a manual roll programming reservation from "Inventario tela por rollos", not just ficha técnica ones', function () {
    // Reproduce el caso reportado: rollo con una reserva MANUAL (no de ficha técnica)
    // más una salida ya consumida de forma independiente. Antes de este fix, la
    // "Disponibilidad parcial" del listado de Inventario Textil solo descontaba
    // fabric_roll_programming_lines — ignoraba tanto manual_roll_programmings como la
    // propia columna partial_output — y terminaba mostrando el mismo valor que
    // "Disponibilidad real", como si nada estuviera reservado.
    $roll = FabricRoll::create([
        'roll_number' => 'RL-COMMITTED-3',
        'item_id' => null,
        'quantity' => 100,
        'actual_output' => 25,
        'entry_date' => now()->toDateString(),
        'approval_status' => FabricRoll::APPROVAL_APPROVED,
    ]);

    app(FabricRollService::class)->programManual($roll->id, 'OP-COMMITTED-MANUAL', 50);

    $response = $this->getJson(route('kardex-telas.inventario-rollos.data', ['search' => 'RL-COMMITTED-3']));
    $response->assertOk();

    $row = collect($response->json('data'))->firstWhere('roll', 'RL-COMMITTED-3');

    expect($row)->not->toBeNull()
        ->and((float) $row['partial_output_value'])->toEqual(50.0)
        // 100 (quantity) - 25 (actual_output) - 50 (reservado, manual) = 25
        ->and((float) $row['partial_availability_value'])->toEqual(25.0)
        ->and((float) $row['real_availability_value'])->toEqual(75.0);
});
