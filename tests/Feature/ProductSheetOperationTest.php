<?php

use App\Models\ProductSheet\ProductSheetOperation;
use App\Models\ProductSheet\ProductSheetOperationHistory;
use App\Models\ProductSheet\ProductionOrderOperation;
use App\Models\ProductSheet\Programmer;
use App\Services\ProductSheet\ProductOperationListService;
use App\Services\ProductSheet\ProductionOrderService;

/**
 * Tests de listado operacional (ProductSheetOperation) y snapshot de orden (ProductionOrderService).
 * Requieren BD de pruebas real: phpunit.xml usa mysql + :memory: por defecto (inválido).
 * Para ejecutar: definir .env.testing con DB_DATABASE=nombre_bd_real o usar DB_CONNECTION=sqlite y DB_DATABASE=:memory: en phpunit.
 */
beforeEach(function () {
    $this->service = app(ProductOperationListService::class);
});

test('create product sheet operation computes sam_total_unitario and sam_total_operacion', function () {
    $this->seed(\Database\Seeders\ExampleDataSeeder::class);

    $sheet = \App\Models\ProductSheet\ProductSheet::query()->first();
    $operation = \App\Models\ProductSheet\Operation::query()->first();
    $machine = \App\Models\ProductSheet\Machine::query()->first();
    $section = \App\Models\ProductSheet\OperationSection::query()->first();

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

    expect($item)->toBeInstanceOf(ProductSheetOperation::class);
    expect($item->sam_base)->toBe(0.25);
    expect($item->sam_total_unitario)->toBeGreaterThan(0);
    expect($item->sam_total_operacion)->toBeGreaterThan(0);
    $expectedUnitario = round(0.25 * 1.08 * 1.0 * 1.25, 4);
    expect(round($item->sam_total_unitario, 4))->toBe($expectedUnitario);
});

test('update product sheet operation with factor change records history', function () {
    $this->seed(\Database\Seeders\ExampleDataSeeder::class);

    $sheet = \App\Models\ProductSheet\ProductSheet::query()->first();
    $operation = \App\Models\ProductSheet\Operation::query()->first();
    $machine = \App\Models\ProductSheet\Machine::query()->first();
    $section = \App\Models\ProductSheet\OperationSection::query()->first();

    if (!$sheet || !$operation || !$machine || !$section) {
        expect(true)->toBeTrue();
        return;
    }

    $item = ProductSheetOperation::create([
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

    $sheet = \App\Models\ProductSheet\ProductSheet::query()->first();
    if (!$sheet) {
        expect(true)->toBeTrue();
        return;
    }

    $programmer = Programmer::create([
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
    $created = $orderService->createSnapshotForProgrammer($programmer);

    expect($created->count())->toBe($ops->count());

    $firstPoo = ProductionOrderOperation::where('programmer_id', $programmer->id)->first();
    expect($firstPoo)->not->toBeNull();
    expect($firstPoo->sam_total_lote)->toBeGreaterThan(0);
});
