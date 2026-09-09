<?php

use App\Models\Production\FabricBase;
use App\Models\Production\Operation;
use App\Models\Production\ProductionOrder;
use App\Models\Production\ProductOperationList;
use App\Models\Production\ProductSheet;
use App\Models\Production\TechnicalSheet;
use App\Models\Production\TechnicalSheetOperation;
use App\Models\Production\TechnicalSheetOperationHistory;
use App\Models\Settings\User;
use App\Services\Production\FabricBaseService;

/**
 * Fase 6 del refactor de materiales: cambiar el factor de una base de tela
 * repropaga a las fichas (siempre) y a las operaciones de órdenes ya generadas
 * (solo si no tienen avance reportado — una operación con avance conserva el
 * factor con el que se midió su eficiencia).
 */
function createFabricFactorProductSheet(?int $fabricBaseId, string $suffix): ProductSheet
{
    return ProductSheet::create([
        'section_id' => 1,
        'group_id' => 1,
        'brand_id' => 1,
        'world_id' => 1,
        'fabric_base_id' => $fabricBaseId,
        'type' => 'producto',
        'billing_price' => 1000,
        'selling_price' => 1200,
        'sizes' => ['M'],
        'woven_product' => false,
        'description' => 'Ficha de prueba propagación '.$suffix,
        'sample_reference' => 'MUESTRA-PROP-'.$suffix,
        'product_reference' => 'PRODUCTO-PROP-'.$suffix,
        'processes' => ['Corte'],
        'status' => 'complete',
    ]);
}

beforeEach(function () {
    $this->actingAs(User::factory()->create());

    $this->fabricBase = FabricBase::create([
        'codigo' => 'BT-PROP-TEST',
        'nombre' => 'BASE PROPAGACION TEST',
        'factor' => 1.0,
        'is_active' => true,
    ]);

    $this->otherFabricBase = FabricBase::create([
        'codigo' => 'BT-PROP-OTHER',
        'nombre' => 'OTRA BASE PROPAGACION',
        'factor' => 2.0,
        'is_active' => true,
    ]);
});

it('recalculates product_operation_lists for sheets using the base, and leaves other bases untouched', function () {
    $affectedSheet = createFabricFactorProductSheet($this->fabricBase->id, 'A');
    $unrelatedSheet = createFabricFactorProductSheet($this->otherFabricBase->id, 'B');

    $catalogOperation = Operation::create(['action' => 'CERRAR', 'garment_part' => 'BOLSILLO', 'is_active' => true]);

    $affectedList = ProductOperationList::create([
        'product_sheet_id' => $affectedSheet->id,
        'operation_id' => $catalogOperation->id,
        'sequence' => 1,
        'sam_base' => 1.0,
        'sam' => 1.0,
        'optimal_time' => 1.0,
        'operator_efficiency_factor' => 1.0,
        'machine_supplement_factor' => 1.0,
        'fabric_base_factor' => 1.0,
        'sam_total_unitario' => 1.0,
        'sam_total_operacion' => 1.0,
        'is_active' => true,
    ]);

    $unrelatedList = ProductOperationList::create([
        'product_sheet_id' => $unrelatedSheet->id,
        'operation_id' => $catalogOperation->id,
        'sequence' => 1,
        'sam_base' => 1.0,
        'sam' => 1.0,
        'optimal_time' => 1.0,
        'operator_efficiency_factor' => 1.0,
        'machine_supplement_factor' => 1.0,
        'fabric_base_factor' => 2.0,
        'sam_total_unitario' => 2.0,
        'sam_total_operacion' => 2.0,
        'is_active' => true,
    ]);

    app(FabricBaseService::class)->update($this->fabricBase, ['nombre' => $this->fabricBase->nombre, 'factor' => 1.5, 'is_active' => true]);

    // ProductOperationList no castea fabric_base_factor a float (columna decimal
    // de BD, llega como string "1.5000") — se castea aquí, el valor lógico es 1.5.
    expect((float) $affectedList->fresh()->fabric_base_factor)->toBe(1.5)
        ->and((float) $unrelatedList->fresh()->fabric_base_factor)->toBe(2.0);
});

it('recalculates an order operation without progress but keeps the factor of one with progress', function () {
    $sheet = createFabricFactorProductSheet($this->fabricBase->id, 'ORD');
    $catalogOperation = Operation::create(['action' => 'CERRAR', 'garment_part' => 'BOLSILLO', 'is_active' => true]);

    $order = ProductionOrder::create([
        'product_sheet_id' => $sheet->id,
        'op_code' => 'OP-PROP-001',
        'season' => '1',
        'destination_id' => 1,
        'priority' => 'alta',
        'units' => 10,
        'estimated_delivery_date' => '2026-03-30',
        'status' => 'active',
        'current_process' => 'Corte',
        'source' => 'manual',
    ]);

    $this->putJson(route('control-de-lotes.sync-operations', $order), [
        'operations' => [
            [
                'operation_id' => $catalogOperation->id,
                'machine_id' => null,
                'section_id' => null,
                'sequence' => 1,
                'optimal_time' => 1.0,
                'machine_supplement_factor' => 1.0,
                'operator_efficiency_factor' => 1.0,
                'quantity' => 1,
                'fabric_base_id' => $this->fabricBase->id,
            ],
            [
                'operation_id' => $catalogOperation->id,
                'machine_id' => null,
                'section_id' => null,
                'sequence' => 2,
                'optimal_time' => 1.0,
                'machine_supplement_factor' => 1.0,
                'operator_efficiency_factor' => 1.0,
                'quantity' => 1,
                'fabric_base_id' => $this->fabricBase->id,
            ],
        ],
    ])->assertOk();

    $technicalSheet = TechnicalSheet::where('production_order_id', $order->id)->firstOrFail();
    $operations = TechnicalSheetOperation::where('technical_sheet_id', $technicalSheet->id)->orderBy('sequence')->get();
    $withProgress = $operations->firstWhere('sequence', 1);
    $withoutProgress = $operations->firstWhere('sequence', 2);

    // Ambas nacen con factor 1.0 (el factor original de la base al sincronizar).
    expect($withProgress->fabric_base_factor)->toBe(1.0)
        ->and($withoutProgress->fabric_base_factor)->toBe(1.0);

    $withProgress->update(['quantity_progressed' => 1, 'progressed_at' => now()]);

    app(FabricBaseService::class)->update($this->fabricBase, ['nombre' => $this->fabricBase->nombre, 'factor' => 1.8, 'is_active' => true]);

    expect($withProgress->fresh()->fabric_base_factor)->toBe(1.0)
        ->and($withoutProgress->fresh()->fabric_base_factor)->toBe(1.8);

    expect(TechnicalSheetOperationHistory::where('technical_sheet_operation_id', $withoutProgress->id)
        ->where('event_type', 'FABRIC_FACTOR_PROPAGATED')->exists())->toBeTrue()
        ->and(TechnicalSheetOperationHistory::where('technical_sheet_operation_id', $withProgress->id)
            ->where('event_type', 'FABRIC_FACTOR_PROPAGATED')->exists())->toBeFalse();
});

it('does not propagate anything when the factor stays the same', function () {
    $sheet = createFabricFactorProductSheet($this->fabricBase->id, 'NOCHANGE');
    $catalogOperation = Operation::create(['action' => 'CERRAR', 'garment_part' => 'BOLSILLO', 'is_active' => true]);

    $list = ProductOperationList::create([
        'product_sheet_id' => $sheet->id,
        'operation_id' => $catalogOperation->id,
        'sequence' => 1,
        'sam_base' => 1.0,
        'sam' => 1.0,
        'optimal_time' => 1.0,
        'operator_efficiency_factor' => 1.0,
        'machine_supplement_factor' => 1.0,
        'fabric_base_factor' => 1.0,
        'sam_total_unitario' => 1.0,
        'sam_total_operacion' => 1.0,
        'is_active' => true,
    ]);

    // Renombrar sin cambiar el factor no debe disparar ninguna repropagación.
    $service = app(FabricBaseService::class);
    $service->update($this->fabricBase, ['nombre' => 'BASE PROPAGACION TEST RENOMBRADA', 'factor' => 1.0, 'is_active' => true]);

    expect($service->lastFactorPropagation())->toBeNull()
        ->and((float) $list->fresh()->fabric_base_factor)->toBe(1.0);
});
