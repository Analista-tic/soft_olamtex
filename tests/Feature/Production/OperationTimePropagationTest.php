<?php

use App\Enums\EstatusOperacionOrden;
use App\Models\Production\Operation;
use App\Models\Production\ProductionOrder;
use App\Models\Production\ProductOperationList;
use App\Models\Production\ProductSheet;
use App\Models\Production\ProductSheetOperationHistory;
use App\Models\Production\TechnicalSheet;
use App\Models\Production\TechnicalSheetOperation;
use App\Models\Settings\User;
use App\Services\Production\OperationService;

/**
 * Al cambiar el tiempo (o la cantidad) de una operación, todos los listados que la usan se recalculan con
 * historial; las copias por orden de producción solo cambian mientras la orden sigue en I&D y sin avance.
 */
beforeEach(function () {
    $this->actingAs(User::factory()->create());
    $this->operation = Operation::create(['action' => 'PEGAR', 'garment_part' => 'PUNO', 'sam_base' => 0.55, 'quantity' => 1, 'is_active' => true]);
});

function propagationSheet(string $suffix): ProductSheet
{
    return ProductSheet::create([
        'section_id' => 1, 'group_id' => 1, 'brand_id' => 1, 'world_id' => 1,
        'type' => 'producto', 'billing_price' => 1000, 'selling_price' => 1200,
        'sizes' => ['M'], 'woven_product' => false,
        'description' => 'Ficha propagación tiempo '.$suffix,
        'sample_reference' => 'MUESTRA-TP-'.$suffix, 'product_reference' => 'PRODUCTO-TP-'.$suffix,
        'processes' => ['Corte'], 'status' => 'complete',
    ]);
}

function propagationLine(ProductSheet $sheet, Operation $operation): ProductOperationList
{
    return ProductOperationList::create([
        'product_sheet_id' => $sheet->id, 'operation_id' => $operation->id, 'sequence' => 1,
        'sam_base' => 0.55, 'sam' => 0.55, 'optimal_time' => 0.55,
        'operator_efficiency_factor' => 1.0, 'machine_supplement_factor' => 1.0, 'fabric_base_factor' => 1.0,
        'sam_total_unitario' => 0.55, 'sam_total_operacion' => 0.55, 'is_active' => true,
    ]);
}

function propagationOrderOperation(ProductSheet $sheet, ProductOperationList $line, string $process, string $code): TechnicalSheetOperation
{
    $order = ProductionOrder::create([
        'product_sheet_id' => $sheet->id, 'op_code' => $code, 'season' => '1', 'destination_id' => 1,
        'priority' => 'alta', 'units' => 10, 'estimated_delivery_date' => '2026-12-30',
        'status' => 'active', 'current_process' => $process, 'source' => 'manual',
    ]);
    $technicalSheet = TechnicalSheet::create(['production_order_id' => $order->id, 'status' => TechnicalSheet::STATUS_DRAFT]);

    return TechnicalSheetOperation::create([
        'technical_sheet_id' => $technicalSheet->id, 'product_sheet_operation_id' => $line->id,
        'sequence' => 1, 'operation_id' => $line->operation_id, 'quantity' => 1,
        'sam_base' => 0.55, 'machine_supplement_factor' => 1.0, 'fabric_base_factor' => 1.0, 'operator_efficiency_factor' => 1.0,
        'sam_total_unitario' => 0.55, 'sam_total_operacion' => 0.55, 'sam_total_lote' => 5.5,
        'quantity_progressed' => 0, 'status' => EstatusOperacionOrden::PENDIENTE,
    ]);
}

it('updates every listing that uses the operation and records the change in the line history', function () {
    $lineA = propagationLine(propagationSheet('A'), $this->operation);
    $lineB = propagationLine(propagationSheet('B'), $this->operation);

    app(OperationService::class)->update($this->operation, ['sam_base' => 0.40], 'Se midió de nuevo');

    foreach ([$lineA, $lineB] as $line) {
        $fresh = $line->fresh();
        expect((float) $fresh->sam_base)->toEqual(0.40)
            ->and((float) $fresh->optimal_time)->toEqual(0.40)
            ->and((float) $fresh->sam_total_unitario)->toEqual(0.40)
            ->and((float) $fresh->sam_total_operacion)->toEqual(0.40);

        $history = ProductSheetOperationHistory::where('product_sheet_operation_id', $line->id)->first();
        expect($history)->not->toBeNull()
            ->and((float) $history->sam_base_anterior)->toEqual(0.55)
            ->and((float) $history->sam_base_nuevo)->toEqual(0.40)
            ->and($history->motivo)->toBe('Se midió de nuevo');
    }
});

it('recalculates the totals when only the quantity changes', function () {
    $line = propagationLine(propagationSheet('Q'), $this->operation);

    app(OperationService::class)->update($this->operation, ['quantity' => 3]);

    expect((float) $line->fresh()->sam_total_operacion)->toEqual(1.65)
        ->and((float) $line->fresh()->sam_total_unitario)->toEqual(0.55);
});

it('does not touch listings when neither time nor quantity changes', function () {
    $line = propagationLine(propagationSheet('N'), $this->operation);

    app(OperationService::class)->update($this->operation, ['location' => 'Delantero']);

    expect(ProductSheetOperationHistory::where('product_sheet_operation_id', $line->id)->count())->toBe(0);
});

it('updates order copies still in I&D without progress and keeps the ones that already left', function () {
    $sheet = propagationSheet('OP');
    $line = propagationLine($sheet, $this->operation);
    $inDesign = propagationOrderOperation($sheet, $line, 'I&D', 'OP-TP-001');
    $released = propagationOrderOperation($sheet, $line, 'Corte', 'OP-TP-002');

    app(OperationService::class)->update($this->operation, ['sam_base' => 0.40]);

    expect((float) $inDesign->fresh()->sam_base)->toEqual(0.40)
        ->and((float) $inDesign->fresh()->sam_total_lote)->toEqual(4.0)
        ->and((float) $released->fresh()->sam_base)->toEqual(0.55)
        ->and((float) $released->fresh()->sam_total_lote)->toEqual(5.5);
});
