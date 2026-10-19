<?php

use App\Http\Controllers\Production\ProgrammerController;
use App\Models\Production\FabricBase;
use App\Models\Production\FabricRollProgrammingLine;
use App\Models\Production\ManualRollProgramming;
use App\Models\Production\ProductionOrder;
use App\Models\Production\ProductSheet;
use App\Models\Production\TechnicalSheet;
use App\Models\Settings\User;
use App\Models\Textil\FabricRoll;
use App\Services\Textil\FabricRollService;
use Illuminate\Support\Str;

/**
 * ProgrammerController::resetTechnicalSheet() (invocado al cambiar la referencia de una
 * orden en I&D) recalcula partial_output de los rollos afectados sumando SOLO
 * fabric_roll_programming_lines — antes de este fix, eso borraba silenciosamente
 * cualquier reserva manual (manual_roll_programmings) que el mismo rollo tuviera,
 * aunque esa línea manual siguiera activa. Mismo bug que ya se corrigió en
 * RecalibrarPartialOutput y en FabricRollController::inventoryRollsData().
 */
it('keeps a manual reservation in partial_output when a technical sheet is reset', function () {
    $this->actingAs(User::factory()->create());

    $roll = FabricRoll::create([
        'roll_number' => 'RL-RESET-1',
        'item_id' => null,
        'quantity' => 100,
        'partial_output' => 0,
        'entry_date' => now()->toDateString(),
        'approval_status' => FabricRoll::APPROVAL_APPROVED,
    ]);

    // Reserva por ficha técnica (30 m) — esta es la que se va a borrar con el reset.
    $fabricBase = FabricBase::create([
        'codigo' => 'BT-RESET-1',
        'nombre' => 'BASE DE PRUEBA RESET',
        'factor' => 1.0,
        'is_active' => true,
    ]);
    $productSheet = ProductSheet::create([
        'section_id' => 1, 'group_id' => 1, 'brand_id' => 1, 'world_id' => 1,
        'fabric_base_id' => $fabricBase->id, 'type' => 'producto', 'sizes' => ['M'],
        'woven_product' => false,
        'sample_reference' => 'MUESTRA-RESET-1', 'product_reference' => 'PRODUCTO-RESET-1',
        'processes' => ['Corte'], 'status' => 'complete',
    ]);
    $order = ProductionOrder::create([
        'product_sheet_id' => $productSheet->id, 'op_code' => 'OP-RESET-1',
        'season' => '1', 'destination_id' => 1, 'units' => 10,
        'status' => 'active', 'source' => 'manual',
    ]);
    $sheet = TechnicalSheet::create(['production_order_id' => $order->id, 'status' => TechnicalSheet::STATUS_DRAFT]);
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
    // En el flujo real, TechnicalDataSheetRollsController::saveAll() sincroniza
    // partial_output al mismo tiempo que crea la línea — se replica a mano porque
    // este test crea la línea directo por Eloquent.
    $roll->update(['partial_output' => 30]);

    // Reserva manual (20 m) — independiente, no debe verse afectada por el reset de la
    // ficha técnica de arriba.
    app(FabricRollService::class)->programManual($roll->id, 'OP-RESET-MANUAL', 20);

    $roll->refresh();
    expect((float) $roll->partial_output)->toEqual(50.0); // 30 (ficha técnica) + 20 (manual)

    // Invocar resetTechnicalSheet() (privado) tal como lo dispara changeReference()/update()
    // al cambiar la referencia de una orden en I&D.
    $controller = app(ProgrammerController::class);
    $method = new ReflectionMethod(ProgrammerController::class, 'resetTechnicalSheet');
    $method->invoke($controller, $order->id);

    $roll->refresh();

    expect(FabricRollProgrammingLine::where('fabric_roll_id', $roll->id)->count())->toBe(0)
        ->and(ManualRollProgramming::where('fabric_roll_id', $roll->id)->count())->toBe(1)
        // La línea de ficha técnica se borró (30 m), pero la reserva manual (20 m) debe
        // seguir contando en partial_output — antes de este fix quedaba en 0.
        ->and((float) $roll->partial_output)->toEqual(20.0);
});
