<?php

use App\Models\Production\Machine;
use App\Models\Production\Operation;
use App\Models\Production\OperationSection;
use App\Models\Production\ProductSheet;
use App\Models\Settings\User;
use Spatie\Permission\Models\Permission;

/**
 * Pantalla de "operaciones sin ficha asignada": el importador masivo
 * (OperationImportService) deja product_sheet_id_reference=null y guarda el
 * código original en raw_product_sheet_reference cuando no logra resolver la
 * sábana. Cubre que el filtro `sin_ficha` del listado paginado aísle esas
 * filas, y que el endpoint de actualización normal de operaciones permita
 * asignarles la ficha real la primera vez (OperationService::update() solo
 * bloquea la reasignación cuando ya existía una referencia previa).
 */
function createSinFichaTestSection(): OperationSection
{
    return OperationSection::create(['name' => 'PREPARACION', 'type' => 'PREPARACION', 'is_active' => true]);
}

function createSinFichaTestMachine(): Machine
{
    return Machine::create(['code' => 'MPP', 'name' => 'Plana pespunte', 'supplement_factor' => 1.0, 'is_active' => true]);
}

function createSinFichaTestProductSheet(string $sampleReference = '301640'): ProductSheet
{
    return ProductSheet::create([
        'section_id' => 1,
        'group_id' => 1,
        'brand_id' => 1,
        'world_id' => 1,
        'type' => 'producto',
        'billing_price' => 1000,
        'selling_price' => 1200,
        'sizes' => ['M'],
        'woven_product' => false,
        'description' => 'Ficha de prueba',
        'sample_reference' => $sampleReference,
        'product_reference' => "PROD-{$sampleReference}",
        'processes' => ['Corte'],
        'status' => 'complete',
    ]);
}

function createSinFichaTestOperation(array $overrides = []): Operation
{
    $section = OperationSection::first() ?? createSinFichaTestSection();
    $machine = Machine::first() ?? createSinFichaTestMachine();

    return Operation::create(array_merge([
        'action' => 'CERRAR',
        'garment_part' => 'TIRO',
        'section_id' => $section->id,
        'measure' => 0,
        'quantity' => 1,
        'description' => 'CERRAR TIRO',
        'sam_base' => 0.50,
        'factor_operario' => 100,
        'product_sheet_id_reference' => null,
        'raw_product_sheet_reference' => null,
        'machine_id_reference' => $machine->id,
        'is_active' => true,
    ], $overrides));
}

function sinFichaTestUserWithEditPermission(): User
{
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('editar listados operacionales', 'web'));

    return $user;
}

beforeEach(function () {
    createSinFichaTestSection();
    createSinFichaTestMachine();
});

it('filters paginated operations to only those without a resolved product sheet', function () {
    $sheet = createSinFichaTestProductSheet();

    $withSheet = createSinFichaTestOperation([
        'garment_part' => 'PRETINA',
        'product_sheet_id_reference' => $sheet->id,
    ]);
    $withoutSheet = createSinFichaTestOperation([
        'garment_part' => 'BOLSILLO',
        'anexo' => 'DELANTERO',
        'raw_product_sheet_reference' => 'MU480',
    ]);

    $this->actingAs(User::factory()->create());

    $response = $this->getJson(route('produccion.operaciones.paginated', ['sin_ficha' => 1]));

    $response->assertOk();
    $ids = collect($response->json('data'))->pluck('id')->all();

    expect($ids)->toContain($withoutSheet->id)
        ->and($ids)->not->toContain($withSheet->id);

    $returned = collect($response->json('data'))->firstWhere('id', $withoutSheet->id);
    expect($returned['raw_product_sheet_reference'])->toBe('MU480');
});

it('allows assigning a product sheet to an operation that had none via the normal update endpoint', function () {
    $operation = createSinFichaTestOperation([
        'garment_part' => 'BOLSILLO',
        'raw_product_sheet_reference' => 'MU480',
    ]);
    $sheet = createSinFichaTestProductSheet();

    $this->actingAs(sinFichaTestUserWithEditPermission());

    $response = $this->putJson(route('produccion.operaciones.update', $operation), [
        'product_sheet_id_reference' => $sheet->id,
        'machine_id_reference' => $operation->machine_id_reference,
    ]);

    $response->assertOk();
    expect($operation->fresh()->product_sheet_id_reference)->toBe($sheet->id);
});

it('still blocks reassigning the product sheet once one is already set', function () {
    $firstSheet = createSinFichaTestProductSheet('301640');
    $secondSheet = createSinFichaTestProductSheet('301641');

    $operation = createSinFichaTestOperation([
        'garment_part' => 'CUELLO',
        'product_sheet_id_reference' => $firstSheet->id,
    ]);

    $this->actingAs(sinFichaTestUserWithEditPermission());

    $response = $this->putJson(route('produccion.operaciones.update', $operation), [
        'product_sheet_id_reference' => $secondSheet->id,
        'machine_id_reference' => $operation->machine_id_reference,
    ]);

    $response->assertOk();
    expect($operation->fresh()->product_sheet_id_reference)->toBe($firstSheet->id);
});
