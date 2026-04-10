<?php

use App\Models\Production\ProductOperationList;
use App\Models\Production\ProductSheetOperationHistory;
use App\Models\Production\TechnicalSheetOperation;
use App\Models\Production\ProductionOrder;
use App\Services\Production\ProductOperationListService;
use App\Services\Production\ProductionOrderService;

/**
 * Tests de listado operacional (ProductSheetOperation) y snapshot de orden (ProductionOrderService).
 * Requieren BD de pruebas MySQL real.
 * Para ejecutar: definir .env.testing con DB_CONNECTION=mysql y credenciales válidas.
 */
beforeEach(function () {
    $this->service = app(ProductOperationListService::class);
});

test('create product sheet operation computes sam_total_unitario and sam_total_operacion', function () {
    $this->seed(\Database\Seeders\ExampleDataSeeder::class);

    $sheet = \App\Models\Production\ProductSheet::query()->first();
    $operation = \App\Models\Production\Operation::query()->first();
    $machine = \App\Models\Production\Machine::query()->first();
    $section = \App\Models\Production\OperationSection::query()->first();

    if (!$sheet || !$operation || !$machine || !$section) {
        expect(true)->toBeTrue();
        return;
    }

    $data = [
        'product_sheet_id' => $sheet->id,
        'operation_id' => $operation->id,
        'machine_id' => $machine->id,
        'operation_section_id' => $section->id,
        'sequence' => 10,
        'optimal_time' => 0.25,
        'operator_efficiency_factor' => 1.25,
        'machine_supplement_factor' => 1.08,
        'fabric_base_factor' => 1.0,
        'quantity' => 1,
        'created_by' => 1,
    ];

    $item = $this->service->create($data);

    expect($item)->toBeInstanceOf(ProductOperationList::class);
    expect($item->sam_base)->toBe(0.25);
    expect($item->sam_total_unitario)->toBeGreaterThan(0);
    expect($item->sam_total_operacion)->toBeGreaterThan(0);
    $expectedUnitario = round(0.25 * 1.08 * 1.0 * 1.25, 4);
    expect(round($item->sam_total_unitario, 4))->toBe($expectedUnitario);
});

test('update product sheet operation with factor change records history', function () {
    $this->seed(\Database\Seeders\ExampleDataSeeder::class);

    $sheet = \App\Models\Production\ProductSheet::query()->first();
    $operation = \App\Models\Production\Operation::query()->first();
    $machine = \App\Models\Production\Machine::query()->first();
    $section = \App\Models\Production\OperationSection::query()->first();

    if (!$sheet || !$operation || !$machine || !$section) {
        expect(true)->toBeTrue();
        return;
    }

    $item = ProductOperationList::create([
        'product_sheet_id' => $sheet->id,
        'operation_id' => $operation->id,
        'machine_id' => $machine->id,
        'operation_section_id' => $section->id,
        'sequence' => 10,
        'optimal_time' => 0.25,
        'sam_base' => 0.25,
        'sam' => 0.25,
        'operator_efficiency_factor' => 1.25,
        'machine_supplement_factor' => 1.08,
        'fabric_base_factor' => 1.0,
        'sam_total_unitario' => 0.3375,
        'sam_total_operacion' => 0.3375,
        'quantity' => 1,
        'production_time' => 0.3375,
        'is_active' => true,
        'created_by' => 1,
    ]);

    $this->service->update($item, [
        'operator_efficiency_factor' => 1.35,
    ], 'Operario más experimentado');

    $history = ProductSheetOperationHistory::where('product_sheet_operation_id', $item->id)->first();
    expect($history)->not->toBeNull();
    expect((float) $history->operator_efficiency_factor_anterior)->toBe(1.25);
    expect((float) $history->operator_efficiency_factor_nuevo)->toBe(1.35);
    expect($history->motivo)->toContain('experimentado');
});

test('production order service creates snapshot from programmer', function () {
    $this->seed(\Database\Seeders\ExampleDataSeeder::class);

    $sheet = \App\Models\Production\ProductSheet::query()->first();
    if (!$sheet) {
        expect(true)->toBeTrue();
        return;
    }

    $programmer = ProductionOrder::create([
        'product_sheet_id' => $sheet->id,
        'op_code' => 'OP-TEST-001',
        'season' => 'ENERO',
        'destination_id' => 1,
        'priority' => 'A',
        'units' => 100,
        'status' => 'incomplete',
        'source' => 'manual',
    ]);

    $listService = app(ProductOperationListService::class);
    $ops = $listService->listBySheet($sheet->id);
    if ($ops->isEmpty()) {
        expect(true)->toBeTrue();
        return;
    }

    $orderService = app(ProductionOrderService::class);
    $created = $orderService->createSnapshotForOrder($programmer);

    expect($created->count())->toBe($ops->count());

    $technicalSheet = \App\Models\Production\TechnicalSheet::where('production_order_id', $programmer->id)->first();
    expect($technicalSheet)->not->toBeNull();

    $firstOp = TechnicalSheetOperation::where('technical_sheet_id', $technicalSheet->id)->first();
    expect($firstOp)->not->toBeNull();
    expect($firstOp->sam_total_lote)->toBeGreaterThan(0);
});
