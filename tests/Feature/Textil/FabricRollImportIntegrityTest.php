<?php

use App\Models\Inventory\Warehouse;
use App\Models\Inventory\WarehouseItem;
use App\Models\MasterData\CategoryGroup;
use App\Models\MasterData\CategorySubGroup;
use App\Models\MasterData\Item;
use App\Models\Settings\User;
use App\Models\Textil\FabricRoll;
use App\Models\Textil\FabricRollAdjustment;
use App\Services\Textil\FabricRollService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

function makeImportTestRoll(string $rollNumber, float $quantity = 100): FabricRoll
{
    $group = CategoryGroup::firstOrCreate(['name' => 'MATERIA PRIMA IMPORT TEST']);
    $subGroup = CategorySubGroup::firstOrCreate([
        'name' => 'Tela Import Test',
        'category_group_id' => $group->id,
    ]);

    $item = Item::create([
        'category_sub_group_id' => $subGroup->id,
        'name' => 'Item import test '.$rollNumber,
        'description' => 'Item de prueba de importación',
        'status' => true,
    ]);

    $warehouse = Warehouse::create(['name' => 'Bodega Import '.$rollNumber, 'observation' => 'Bodega de prueba']);
    WarehouseItem::create(['warehouse_id' => $warehouse->id, 'item_id' => $item->id, 'quantity' => 1000]);

    return FabricRoll::create([
        'roll_number' => $rollNumber,
        'item_id' => $item->id,
        'quantity' => $quantity,
        'actual_output' => 0,
        'entry_date' => now()->toDateString(),
        'approval_status' => FabricRoll::APPROVAL_APPROVED,
    ]);
}

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    $this->service = app(FabricRollService::class);
});

it('applies a quantity import row only once per roll', function () {
    $roll = makeImportTestRoll('RL-IMP-1', 100);

    $result = $this->service->importQuantities([
        ['row' => 2, 'roll_number' => 'RL-IMP-1', 'quantity' => 80, 'partial_output' => 0, 'real_availability' => 80, 'item_reference' => '', 'batch' => ''],
    ], 'IMP-TEST-1');

    expect($result['updated'])->toBe(1)
        ->and(FabricRollAdjustment::where('fabric_roll_id', $roll->id)->count())->toBe(1);
});

it('reports the real file row number even when empty rows were filtered out before', function () {
    // La fila 7 del Excel es la única con datos; el índice del array ya no la refleja.
    $result = $this->service->importQuantities([
        ['row' => 7, 'roll_number' => 'RL-NO-EXISTE', 'quantity' => 10, 'partial_output' => 0, 'real_availability' => 10, 'item_reference' => '', 'batch' => ''],
    ], 'IMP-TEST-2');

    expect($result['errors'][0]['row'])->toBe(7);
});

it('reports a duplicated roll number in the csv import instead of skipping it silently', function () {
    makeImportTestRoll('RL-CSV-DUP', 50);

    // Columnas del CSV histórico: 0 = N° rollo, 9 = cantidad, 14 = fecha.
    $row = array_fill(0, 15, '');
    $row[0] = 'RL-CSV-DUP';
    $row[9] = '30';
    $row[14] = '01/09/2026';

    $result = $this->service->importFromCsv([$row]);

    expect($result['inserted'])->toBe(0)
        ->and($result['skipped'])->toBe(1)
        ->and($result['errors'])->not->toBeEmpty()
        ->and($result['errors'][0]['message'])->toContain('RL-CSV-DUP');
});

it('reserves a different roll number on each concurrent reservation', function () {
    $first = $this->service->reserveRollNumber();
    $second = $this->service->reserveRollNumber();

    expect($first)->not->toBe($second);
});

it('ignores -DUP suffixes when seeding the sequence, instead of inflating it', function () {
    // El cálculo viejo concatenaba todos los dígitos y convertía RL100-DUP1 en 1001.
    DB::table('roll_number_sequences')->where('prefix', 'RL')->update(['last_number' => 0]);
    makeImportTestRoll('RL100', 10);
    makeImportTestRoll('RL100-DUP1', 10);

    $max = DB::table('fabric_rolls')
        ->whereRaw("roll_number REGEXP '^RL[0-9]+$'")
        ->selectRaw('MAX(CAST(SUBSTRING(roll_number, 3) AS UNSIGNED)) as max_num')
        ->value('max_num');

    expect((int) $max)->toBe(100);
});

it('falls back to a fresh sequence number when the previewed one was already taken', function () {
    makeImportTestRoll('RL500', 10);
    DB::table('roll_number_sequences')->where('prefix', 'RL')->update(['last_number' => 500]);

    // El formulario mostró RL500 como preview, pero alguien se adelantó y lo ocupó.
    $roll = $this->service->create([
        'roll_number' => 'RL500',
        'quantity' => 25,
        'entry_date' => now()->toDateString(),
    ]);

    expect($roll->roll_number)->toBe('RL501');
});

it('keeps a manually typed roll number untouched', function () {
    $roll = $this->service->create([
        'roll_number' => 'MANUAL-XYZ',
        'quantity' => 25,
        'entry_date' => now()->toDateString(),
    ]);

    expect($roll->roll_number)->toBe('MANUAL-XYZ');
});

it('no longer exposes the orphan update-output route', function () {
    expect(Route::has('fabric-rolls.update-output'))->toBeFalse();
});

it('rejects a quantity import row that would understate an active manual reservation', function () {
    $roll = makeImportTestRoll('RL-IMP-MANUAL', 100);
    $this->service->programManual($roll->id, 'OP-IMP-MANUAL', 40);

    // El archivo trae partial_output=10, pero el rollo tiene 40 m comprometidos por un
    // código manual activo — importarlo tal cual dejaría esa reserva sin respaldo.
    $result = $this->service->importQuantities([
        ['row' => 2, 'roll_number' => 'RL-IMP-MANUAL', 'quantity' => 100, 'partial_output' => 10, 'real_availability' => 90, 'item_reference' => '', 'batch' => ''],
    ], 'IMP-TEST-MANUAL');

    expect($result['updated'])->toBe(0)
        ->and($result['skipped'])->toBe(1)
        ->and($result['errors'][0]['message'])->toContain('40');

    $roll->refresh();
    expect((float) $roll->partial_output)->toEqual(40.0);
});

it('allows a quantity import row that respects the active manual reservation', function () {
    $roll = makeImportTestRoll('RL-IMP-MANUAL-2', 100);
    $this->service->programManual($roll->id, 'OP-IMP-MANUAL-2', 40);

    $result = $this->service->importQuantities([
        ['row' => 2, 'roll_number' => 'RL-IMP-MANUAL-2', 'quantity' => 100, 'partial_output' => 40, 'real_availability' => 60, 'item_reference' => '', 'batch' => ''],
    ], 'IMP-TEST-MANUAL-2');

    expect($result['updated'])->toBe(1);
});
