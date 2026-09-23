<?php

use App\Models\Production\FabricBase;
use App\Models\Production\Machine;
use App\Models\Production\Operation;
use App\Models\Production\OperationSection;
use App\Models\Production\ProductSheet;
use App\Services\Production\OperationImportService;

/**
 * OperationImportService importa el catálogo de operaciones desde la
 * plantilla plana (CSV/XLSX). Usa una firma de deduplicación propia que sí
 * incluye `anexo` (a diferencia de OperationService), resuelve máquina y
 * sección contra catálogos existentes, y deja `product_sheet_id_reference`
 * en null + `raw_product_sheet_reference` cuando el código de ficha del
 * archivo no matchea ninguna sábana existente.
 */
function importRow(
    string $action = 'CERRAR',
    string $garmentPart = 'TIRO',
    ?string $anexo = null,
    string $section = '1- PREPARACION',
    string $sam = '0.50',
    ?string $measure = null,
    string $machine = 'MPP',
    ?string $quantity = null,
    ?string $factorOperario = null,
    ?string $fichaRef = null,
    string $activo = '',
): array {
    $row = array_fill(0, 16, '');
    $row[2] = $action;
    $row[3] = $garmentPart;
    $row[4] = $anexo ?? '';
    $row[5] = $section;
    $row[6] = $measure ?? '';
    $row[7] = $machine;
    $row[8] = $sam;
    $row[9] = $quantity ?? '';
    $row[12] = $factorOperario ?? '';
    $row[13] = $fichaRef ?? '';
    $row[15] = $activo;

    return $row;
}

beforeEach(function () {
    OperationSection::create(['name' => 'PREPARACION', 'type' => 'PREPARACION', 'is_active' => true]);
    OperationSection::create(['name' => 'ENSAMBLE', 'type' => 'ENSAMBLE', 'is_active' => true]);
    OperationSection::create(['name' => 'TERMINACION', 'type' => 'TERMINACION', 'is_active' => true]);

    $this->service = app(OperationImportService::class);
});

it('imports a valid row in apply mode', function () {
    $summary = $this->service->importRows([importRow(sam: '0.68')], true);

    expect($summary['imported'])->toBe(1)
        ->and($summary['errors'])->toBe([]);

    $operation = Operation::first();
    expect($operation)->not->toBeNull()
        ->and($operation->action)->toBe('CERRAR')
        ->and($operation->garment_part)->toBe('TIRO')
        ->and($operation->section->name)->toBe('PREPARACION')
        ->and((float) $operation->sam_base)->toBe(0.68)
        ->and($operation->quantity)->toBe(1)
        ->and((float) $operation->factor_operario)->toBe(100.0)
        ->and($operation->is_active)->toBeTrue()
        ->and($operation->codigo)->toStartWith('OP-');
});

it('does not write to the database in dry-run mode', function () {
    $summary = $this->service->importRows([importRow()], false);

    expect($summary['imported'])->toBe(1);
    expect(Operation::count())->toBe(0);
});

it('normalizes comma decimals in sam_base and measure', function () {
    $this->service->importRows([importRow(sam: '1,75', measure: '30,5')], true);

    $operation = Operation::first();
    expect((float) $operation->sam_base)->toBe(1.75)
        ->and((float) $operation->measure)->toBe(30.5);
});

it('defaults quantity to 1 and factor_operario to 100 when blank', function () {
    $this->service->importRows([importRow(quantity: '', factorOperario: '')], true);

    $operation = Operation::first();
    expect($operation->quantity)->toBe(1)
        ->and((float) $operation->factor_operario)->toBe(100.0);
});

it('treats a blank ACTIVO column as active', function () {
    $this->service->importRows([importRow(activo: '')], true);

    expect(Operation::first()->is_active)->toBeTrue();
});

it('skips a row with a corrupted sam_base as an error, not a crash', function () {
    $summary = $this->service->importRows([importRow(sam: '0.30.3')], true);

    expect($summary['imported'])->toBe(0)
        ->and($summary['errors'])->toHaveCount(1)
        ->and(Operation::count())->toBe(0);
});

it('deduplicates identical rows within the same file', function () {
    $summary = $this->service->importRows([
        importRow(sam: '0.50'),
        importRow(sam: '0.50'),
    ], true);

    expect($summary['imported'])->toBe(1)
        ->and($summary['duplicated_in_file'])->toBe(1)
        ->and(Operation::count())->toBe(1);
});

it('skips a row that duplicates an operation already in the database', function () {
    Operation::create([
        'action' => 'CERRAR',
        'garment_part' => 'TIRO',
        'section_id' => OperationSection::where('name', 'PREPARACION')->value('id'),
        'measure' => 0,
        'quantity' => 1,
        'description' => 'CERRAR TIRO',
        'sam_base' => 0.50,
        'factor_operario' => 100,
        'is_active' => true,
    ]);

    $summary = $this->service->importRows([importRow(sam: '0.50')], true);

    expect($summary['imported'])->toBe(0)
        ->and($summary['duplicated_in_db'])->toBe(1)
        ->and(Operation::count())->toBe(1);
});

it('does not treat rows that only differ by anexo as duplicates', function () {
    $summary = $this->service->importRows([
        importRow(garmentPart: 'TIRO', anexo: 'DELANTERO', sam: '0.50'),
        importRow(garmentPart: 'TIRO', anexo: 'TRASERO', sam: '0.50'),
    ], true);

    expect($summary['imported'])->toBe(2)
        ->and($summary['duplicated_in_file'])->toBe(0)
        ->and(Operation::count())->toBe(2);

    $anexos = Operation::pluck('anexo')->sort()->values()->all();
    expect($anexos)->toBe(['DELANTERO', 'TRASERO']);
});

it('creates a new machine automatically when its code is unknown', function () {
    expect(Machine::where('code', 'FUSIONAD')->exists())->toBeFalse();

    $summary = $this->service->importRows([importRow(machine: 'fusionad')], true);

    $machine = Machine::where('code', 'FUSIONAD')->first();
    expect($machine)->not->toBeNull()
        ->and($machine->name)->toBe('FUSIONAD')
        ->and((float) $machine->supplement_factor)->toBe(1.0)
        ->and($summary['machines_created'])->toHaveCount(1);

    expect(Operation::first()->machine_id_reference)->toBe($machine->id);
});

it('reuses an existing machine by code instead of creating a duplicate', function () {
    $machine = Machine::create(['code' => 'MPP', 'name' => 'Plana pespunte', 'supplement_factor' => 1.1, 'is_active' => true]);

    $summary = $this->service->importRows([importRow(machine: 'MPP')], true);

    expect($summary['machines_created'])->toBe([])
        ->and(Machine::count())->toBe(1)
        ->and(Operation::first()->machine_id_reference)->toBe($machine->id);
});

it('resolves product_sheet_id_reference against sample_reference/product_reference', function () {
    $fabricBase = FabricBase::create(['codigo' => 'FB1', 'nombre' => 'Base de prueba', 'factor' => 1.0, 'is_active' => true]);

    $sheet = ProductSheet::create([
        'section_id' => 1,
        'group_id' => 1,
        'brand_id' => 1,
        'world_id' => 1,
        'fabric_base_id' => $fabricBase->id,
        'type' => 'producto',
        'billing_price' => 1000,
        'selling_price' => 1200,
        'sizes' => ['M'],
        'woven_product' => false,
        'description' => 'Ficha de prueba',
        'sample_reference' => '301640',
        'product_reference' => 'PROD-301640',
        'processes' => ['Corte'],
        'status' => 'complete',
    ]);

    $this->service->importRows([importRow(fichaRef: '301640')], true);

    $operation = Operation::first();
    expect($operation->product_sheet_id_reference)->toBe($sheet->id)
        ->and($operation->raw_product_sheet_reference)->toBeNull();
});

it('leaves product_sheet_id_reference null and keeps the raw code when it cannot be resolved', function () {
    $summary = $this->service->importRows([importRow(fichaRef: 'MU480')], true);

    $operation = Operation::first();
    expect($operation->product_sheet_id_reference)->toBeNull()
        ->and($operation->raw_product_sheet_reference)->toBe('MU480')
        ->and($summary['unresolved_product_sheets'])->toHaveCount(1);
});

it('strips the numeric prefix from the section column before matching', function () {
    $this->service->importRows([importRow(section: '2- ENSAMBLE')], true);

    expect(Operation::first()->section->name)->toBe('ENSAMBLE');
});

it('reports an error and skips the row when the section is unknown', function () {
    $summary = $this->service->importRows([importRow(section: '9- INEXISTENTE')], true);

    expect($summary['imported'])->toBe(0)
        ->and($summary['errors'])->toHaveCount(1)
        ->and(Operation::count())->toBe(0);
});
