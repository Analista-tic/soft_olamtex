<?php

use App\Models\Production\FabricBase;
use App\Models\Production\FabricRollProgrammingLine;
use App\Models\Production\ManualRollProgramming;
use App\Models\Production\ProductionOrder;
use App\Models\Production\ProductionRollConsumption;
use App\Models\Production\ProductSheet;
use App\Models\Production\TechnicalSheet;
use App\Models\Settings\User;
use App\Models\Textil\FabricRoll;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

/**
 * Fase 15 del refactor de materiales: pantalla de control "Programación de rollos"
 * (kardex-telas/programacion-rollos) — cruza fabric_roll_programming_lines (programado
 * por Diseño) con production_roll_consumptions (consumo real de Corte) para mostrar
 * programado/disponible/consumido real/diferencia por línea.
 */
function makeReportProductionOrder(string $suffix): ProductionOrder
{
    $fabricBase = FabricBase::create([
        'codigo' => 'BT-REPORT-'.$suffix,
        'nombre' => 'BASE DE PRUEBA REPORT '.$suffix,
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
        'sample_reference' => 'MUESTRA-REPORT-'.$suffix,
        'product_reference' => 'PRODUCTO-REPORT-'.$suffix,
        'processes' => ['Corte'],
        'status' => 'complete',
    ]);

    $order = ProductionOrder::create([
        'product_sheet_id' => $productSheet->id,
        'op_code' => 'OP-REPORT-'.$suffix,
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

it('reports programmed, available, real consumption and difference for a roll programming line', function () {
    $roll = FabricRoll::create([
        'roll_number' => 'RL-REPORT-1',
        'item_id' => null,
        'quantity' => 200,
        'actual_output' => 50,
        'entry_date' => now()->toDateString(),
        'approval_status' => FabricRoll::APPROVAL_APPROVED,
    ]);

    $order = makeReportProductionOrder('main');
    $sheet = TechnicalSheet::where('production_order_id', $order->id)->first();

    FabricRollProgrammingLine::create([
        'technical_sheet_id' => $sheet->id,
        'legacy_uuid' => (string) Str::uuid(),
        'type' => 'PRINCIPAL',
        'fabric_roll_id' => $roll->id,
        'fabric_name' => 'JERSEY 24',
        'color' => 'AZUL REY',
        'consumption_base' => 1,
        'price_per_meter' => 1000,
        'programmed_meters' => 80,
    ]);

    ProductionRollConsumption::create([
        'production_order_id' => $order->id,
        'fabric_roll_id' => $roll->id,
        'meters_consumed' => 65,
    ]);

    $response = $this->getJson(route('kardex-telas.programacion-rollos.data', ['op_code' => $order->op_code]));
    $response->assertOk();

    $rows = $response->json('data');
    expect($rows)->toHaveCount(1);

    $row = $rows[0];
    expect($row['op_code'])->toBe($order->op_code)
        ->and($row['roll_number'])->toBe('RL-REPORT-1')
        ->and($row['fabric_name'])->toBe('JERSEY 24')
        ->and((float) $row['programmed_meters'])->toEqual(80.0)
        // 200 (quantity) - 50 (actual_output) - 80 (comprometido total) = 70
        ->and((float) $row['available_meters'])->toEqual(70.0)
        ->and((float) $row['consumed_real_meters'])->toEqual(65.0)
        // diferencia = programado - consumido real = 80 - 65 = 15
        ->and((float) $row['difference_meters'])->toEqual(15.0);
});

it('filters by roll_number and supports sorting by difference_meters', function () {
    $rollA = FabricRoll::create([
        'roll_number' => 'RL-REPORT-A',
        'item_id' => null,
        'quantity' => 100,
        'actual_output' => 0,
        'entry_date' => now()->toDateString(),
        'approval_status' => FabricRoll::APPROVAL_APPROVED,
    ]);
    $rollB = FabricRoll::create([
        'roll_number' => 'RL-REPORT-B',
        'item_id' => null,
        'quantity' => 100,
        'actual_output' => 0,
        'entry_date' => now()->toDateString(),
        'approval_status' => FabricRoll::APPROVAL_APPROVED,
    ]);

    $orderA = makeReportProductionOrder('sortA');
    $sheetA = TechnicalSheet::where('production_order_id', $orderA->id)->first();
    FabricRollProgrammingLine::create([
        'technical_sheet_id' => $sheetA->id,
        'legacy_uuid' => (string) Str::uuid(),
        'type' => 'PRINCIPAL',
        'fabric_roll_id' => $rollA->id,
        'fabric_name' => 'TELA A',
        'consumption_base' => 1,
        'price_per_meter' => 1000,
        'programmed_meters' => 10,
    ]);

    $orderB = makeReportProductionOrder('sortB');
    $sheetB = TechnicalSheet::where('production_order_id', $orderB->id)->first();
    FabricRollProgrammingLine::create([
        'technical_sheet_id' => $sheetB->id,
        'legacy_uuid' => (string) Str::uuid(),
        'type' => 'PRINCIPAL',
        'fabric_roll_id' => $rollB->id,
        'fabric_name' => 'TELA B',
        'consumption_base' => 1,
        'price_per_meter' => 1000,
        'programmed_meters' => 90,
    ]);

    // Filtro por número de rollo: solo debe traer la línea del rollo B.
    $filtered = $this->getJson(route('kardex-telas.programacion-rollos.data', ['roll_number' => 'RL-REPORT-B']));
    $filteredRows = $filtered->json('data');
    expect($filteredRows)->toHaveCount(1)
        ->and($filteredRows[0]['roll_number'])->toBe('RL-REPORT-B');

    // Orden descendente por diferencia (sin consumo real, diferencia = programado): B (90) antes que A (10).
    $sorted = $this->getJson(route('kardex-telas.programacion-rollos.data', [
        'roll_number' => 'RL-REPORT-',
        'sort_by' => 'difference_meters',
        'sort_direction' => 'desc',
    ]));
    $sortedRows = $sorted->json('data');
    expect($sortedRows)->toHaveCount(2)
        ->and($sortedRows[0]['roll_number'])->toBe('RL-REPORT-B')
        ->and($sortedRows[1]['roll_number'])->toBe('RL-REPORT-A');
});

it('includes manual rows alongside order rows and discounts both reservations from available_meters', function () {
    $roll = FabricRoll::create([
        'roll_number' => 'RL-REPORT-MIX',
        'item_id' => null,
        'quantity' => 100,
        'actual_output' => 0,
        'partial_output' => 30,
        'entry_date' => now()->toDateString(),
        'approval_status' => FabricRoll::APPROVAL_APPROVED,
    ]);

    $order = makeReportProductionOrder('mix');
    $sheet = TechnicalSheet::where('production_order_id', $order->id)->first();

    FabricRollProgrammingLine::create([
        'technical_sheet_id' => $sheet->id,
        'legacy_uuid' => (string) Str::uuid(),
        'type' => 'PRINCIPAL',
        'fabric_roll_id' => $roll->id,
        'fabric_name' => 'TELA MIXTA',
        'consumption_base' => 1,
        'price_per_meter' => 1000,
        'programmed_meters' => 30,
    ]);

    ManualRollProgramming::create([
        'fabric_roll_id' => $roll->id,
        'manual_order_code' => 'OP-A-MANO-MIX',
        'programmed_meters' => 20,
        'consumed_meters' => 0,
    ]);

    $response = $this->getJson(route('kardex-telas.programacion-rollos.data', ['roll_number' => 'RL-REPORT-MIX']));
    $response->assertOk();

    $rows = $response->json('data');
    expect($rows)->toHaveCount(2);

    $sources = collect($rows)->pluck('source')->sort()->values()->all();
    expect($sources)->toBe(['manual', 'order']);

    $manualRow = collect($rows)->firstWhere('source', 'manual');
    $orderRow = collect($rows)->firstWhere('source', 'order');

    expect($manualRow['manual_order_code'])->toBe('OP-A-MANO-MIX')
        ->and($manualRow['op_code'])->toBeNull()
        ->and($orderRow['op_code'])->toBe($order->op_code)
        ->and($orderRow['manual_order_code'])->toBeNull();

    // Disponible = 100 (quantity) - 0 (actual_output) - 30 (comprometido OP) - 20 (comprometido manual) = 50.
    expect((float) $manualRow['available_meters'])->toEqual(50.0)
        ->and((float) $orderRow['available_meters'])->toEqual(50.0);
});
