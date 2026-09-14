<?php

use App\Models\Production\FabricBase;
use App\Models\Production\ProductSheet;
use App\Services\Production\ProductSheetBulkImportService;
use App\Services\Production\ProductSheetTemplateSchema;
use App\Services\Settings\SettingService;
use Database\Seeders\SettingsFieldParametersSeeder;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

/**
 * Tests de humo para la carga masiva de sábanas de producto (ProductSheetBulkImportService).
 * Requieren BD de pruebas MySQL real (mismo patrón que ProductSheetOperationTest).
 */
function productSheetBulkImportTestFabricBaseName(): string
{
    return FabricBase::create([
        'codigo' => 'BT-TEST-' . uniqid(),
        'nombre' => 'TELA TEST ' . uniqid(),
        'factor' => 1.0,
        'is_active' => true,
    ])->nombre;
}

function productSheetBulkImportTestColumns(): array
{
    $settingService = app(SettingService::class);
    $columns = ProductSheetTemplateSchema::FIXED_COLUMNS;

    foreach ($settingService->getCatalogOptions('sizes')->pluck('name') as $size) {
        $columns[] = ['header' => ProductSheetTemplateSchema::sizeHeader($size)];
    }

    foreach (config('product-sheet.batch_control_processes', []) as $process) {
        $columns[] = ['header' => ProductSheetTemplateSchema::processHeader($process)];
    }

    return $columns;
}

/**
 * Construye el array en el mismo formato que produce
 * Worksheet::toArray(null, true, true, true) (fila 1 = encabezados,
 * datos desde fila 2, ambos indexados por letra de columna).
 *
 * @param array<int, array<string, string>> $dataRows Cada fila como [encabezado => valor]
 */
function productSheetBulkImportTestRows(array $dataRows): array
{
    $columns = productSheetBulkImportTestColumns();

    $headerRow = [];
    foreach ($columns as $idx => $col) {
        $headerRow[Coordinate::stringFromColumnIndex($idx + 1)] = $col['header'];
    }

    $rows = [1 => $headerRow];
    $rowNum = 2;
    foreach ($dataRows as $dataRow) {
        $row = [];
        foreach ($columns as $idx => $col) {
            $row[Coordinate::stringFromColumnIndex($idx + 1)] = $dataRow[$col['header']] ?? '';
        }
        $rows[$rowNum] = $row;
        $rowNum++;
    }

    return $rows;
}

beforeEach(function () {
    $this->seed(SettingsFieldParametersSeeder::class);
    $this->settingService = app(SettingService::class);
    $this->service = app(ProductSheetBulkImportService::class);
});

test('bulk import creates a new product sheet from a row without ID', function () {
    $section = $this->settingService->getCatalogOptions('sections')->first();
    $group = $this->settingService->getCatalogOptions('groups')->first();
    $brand = $this->settingService->getCatalogOptions('brands')->first();
    $world = $this->settingService->getCatalogOptions('worlds')->first();

    if (!$section || !$group || !$brand || !$world) {
        expect(true)->toBeTrue();
        return;
    }

    $fabricBase = FabricBase::create([
        'codigo' => 'BT-TEST-' . uniqid(),
        'nombre' => 'TELA TEST ' . uniqid(),
        'factor' => 1.0,
        'is_active' => true,
    ]);

    $productRef = 'TESTP-' . uniqid();

    $rows = productSheetBulkImportTestRows([
        [
            'TIPO *' => 'producto',
            'SECCIÓN *' => $section['name'],
            'GRUPO *' => $group['name'],
            'MARCA *' => $brand['name'],
            'MUNDO *' => $world['name'],
            'ES TEJIDO *' => 'NO',
            'BASE TEJIDO' => $fabricBase->nombre,
            'REF. PRODUCTO' => $productRef,
            'PRECIO VENTA *' => '100000',
            'DESCRIPCIÓN *' => 'Producto de prueba carga masiva',
            'HABILITADO' => 'SI',
        ],
    ]);

    $result = $this->service->processBulkUploadArray($rows);

    expect($result['errors'])->toBe([]);
    expect($result['inserted'])->toBe(1);
    expect($result['updated'])->toBe(0);

    $created = ProductSheet::where('product_reference', $productRef)->first();
    expect($created)->not->toBeNull();
    expect($created->status)->toBe('incomplete');
    expect($created->is_enabled)->toBeTrue();
    expect((int) $created->section_id)->toBe((int) $section['id']);
    expect((int) $created->fabric_base_id)->toBe($fabricBase->id);
});

test('bulk import updates an existing product sheet when row has ID', function () {
    $section = $this->settingService->getCatalogOptions('sections')->first();
    $group = $this->settingService->getCatalogOptions('groups')->first();
    $brand = $this->settingService->getCatalogOptions('brands')->first();
    $world = $this->settingService->getCatalogOptions('worlds')->first();

    if (!$section || !$group || !$brand || !$world) {
        expect(true)->toBeTrue();
        return;
    }

    $fabricBase = FabricBase::create([
        'codigo' => 'BT-TEST-' . uniqid(),
        'nombre' => 'TELA TEST ' . uniqid(),
        'factor' => 1.0,
        'is_active' => true,
    ]);

    $existing = ProductSheet::create([
        'type' => 'producto',
        'section_id' => $section['id'],
        'group_id' => $group['id'],
        'brand_id' => $brand['id'],
        'world_id' => $world['id'],
        'fabric_base_id' => $fabricBase->id,
        'woven_product' => false,
        'product_reference' => 'EXISTING-' . uniqid(),
        'selling_price' => 50000,
        'description' => 'Original',
        'sizes' => [],
        'processes' => [],
        'status' => 'incomplete',
        'version' => 1,
        'is_enabled' => true,
    ]);

    $rows = productSheetBulkImportTestRows([
        [
            'ID' => (string) $existing->id,
            'TIPO *' => 'producto',
            'SECCIÓN *' => $section['name'],
            'GRUPO *' => $group['name'],
            'MARCA *' => $brand['name'],
            'MUNDO *' => $world['name'],
            'ES TEJIDO *' => 'NO',
            'BASE TEJIDO' => $fabricBase->nombre,
            'REF. PRODUCTO' => $existing->product_reference,
            'PRECIO VENTA *' => '75000',
            'DESCRIPCIÓN *' => 'Actualizado por carga masiva',
            'HABILITADO' => 'SI',
        ],
    ]);

    $result = $this->service->processBulkUploadArray($rows);

    expect($result['errors'])->toBe([]);
    expect($result['inserted'])->toBe(0);
    expect($result['updated'])->toBe(1);

    $existing->refresh();
    expect($existing->description)->toBe('Actualizado por carga masiva');
    expect((float) $existing->selling_price)->toBe(75000.0);
});

test('bulk import reports an error for a row with an unknown section without aborting the batch', function () {
    $rows = productSheetBulkImportTestRows([
        [
            'TIPO *' => 'producto',
            'SECCIÓN *' => 'SECCION-INEXISTENTE-' . uniqid(),
            'GRUPO *' => 'GRUPO-INEXISTENTE-' . uniqid(),
            'MARCA *' => 'MARCA-INEXISTENTE-' . uniqid(),
            'MUNDO *' => 'MUNDO-INEXISTENTE-' . uniqid(),
            'ES TEJIDO *' => 'NO',
            'PRECIO VENTA *' => '1000',
            'DESCRIPCIÓN *' => 'Fila inválida',
        ],
    ]);

    $result = $this->service->processBulkUploadArray($rows);

    expect($result['inserted'])->toBe(0);
    expect($result['updated'])->toBe(0);
    expect($result['errors'])->not->toBe([]);
});

test('bulk import auto-creates a brand that has no close match in the catalog', function () {
    $brand = $this->settingService->getCatalogOptions('brands')->first();
    $section = $this->settingService->getCatalogOptions('sections')->first();
    $group = $this->settingService->getCatalogOptions('groups')->first();
    $world = $this->settingService->getCatalogOptions('worlds')->first();

    if (!$brand || !$section || !$group || !$world) {
        expect(true)->toBeTrue();
        return;
    }

    $newBrandName = 'MARCA NUEVA GENUINA ' . uniqid();
    $productRef = 'TESTBRAND-' . uniqid();

    $rows = productSheetBulkImportTestRows([
        [
            'TIPO *' => 'producto',
            'SECCIÓN *' => $section['name'],
            'GRUPO *' => $group['name'],
            'MARCA *' => $newBrandName,
            'MUNDO *' => $world['name'],
            'ES TEJIDO *' => 'NO',
            'BASE TEJIDO' => productSheetBulkImportTestFabricBaseName(),
            'REF. PRODUCTO' => $productRef,
            'PRECIO VENTA *' => '50000',
            'DESCRIPCIÓN *' => 'Producto con marca nueva',
        ],
    ]);

    $result = $this->service->processBulkUploadArray($rows);

    expect($result['errors'])->toBe([]);
    expect($result['inserted'])->toBe(1);
    expect($result['created_catalog_entries'])->toContain("MARCA: '{$newBrandName}'");

    $brands = $this->settingService->getCatalogOptions('brands');
    expect($brands->pluck('name'))->toContain($newBrandName);
});

test('bulk import rejects a brand name that looks like a typo of an existing one instead of creating a duplicate', function () {
    $brand = $this->settingService->getCatalogOptions('brands')->first();
    $section = $this->settingService->getCatalogOptions('sections')->first();
    $group = $this->settingService->getCatalogOptions('groups')->first();
    $world = $this->settingService->getCatalogOptions('worlds')->first();

    if (!$brand || !$section || !$group || !$world) {
        expect(true)->toBeTrue();
        return;
    }

    // Un typo de una letra sobre una marca real ya existente.
    $typo = $brand['name'] . 'X';

    $rows = productSheetBulkImportTestRows([
        [
            'TIPO *' => 'producto',
            'SECCIÓN *' => $section['name'],
            'GRUPO *' => $group['name'],
            'MARCA *' => $typo,
            'MUNDO *' => $world['name'],
            'ES TEJIDO *' => 'NO',
            'REF. PRODUCTO' => 'TESTTYPO-' . uniqid(),
            'PRECIO VENTA *' => '50000',
            'DESCRIPCIÓN *' => 'Producto con typo de marca',
        ],
    ]);

    $countBefore = $this->settingService->getCatalogOptions('brands')->count();
    $result = $this->service->processBulkUploadArray($rows);
    $countAfter = $this->settingService->getCatalogOptions('brands')->count();

    expect($result['inserted'])->toBe(0);
    expect($countAfter)->toBe($countBefore);
    expect(collect($result['errors'])->contains(fn ($e) => str_contains($e, $typo) && str_contains($e, $brand['name'])))->toBeTrue();
});

test('bulk import reuses a newly created catalog value across multiple rows in the same file', function () {
    $section = $this->settingService->getCatalogOptions('sections')->first();
    $group = $this->settingService->getCatalogOptions('groups')->first();
    $world = $this->settingService->getCatalogOptions('worlds')->first();

    if (!$section || !$group || !$world) {
        expect(true)->toBeTrue();
        return;
    }

    $newBrandName = 'MARCA REPETIDA ' . uniqid();

    $rows = productSheetBulkImportTestRows([
        [
            'TIPO *' => 'producto',
            'SECCIÓN *' => $section['name'],
            'GRUPO *' => $group['name'],
            'MARCA *' => $newBrandName,
            'MUNDO *' => $world['name'],
            'ES TEJIDO *' => 'NO',
            'BASE TEJIDO' => productSheetBulkImportTestFabricBaseName(),
            'REF. PRODUCTO' => 'TESTREUSE1-' . uniqid(),
            'PRECIO VENTA *' => '50000',
            'DESCRIPCIÓN *' => 'Fila 1',
        ],
        [
            'TIPO *' => 'producto',
            'SECCIÓN *' => $section['name'],
            'GRUPO *' => $group['name'],
            'MARCA *' => $newBrandName,
            'MUNDO *' => $world['name'],
            'ES TEJIDO *' => 'NO',
            'BASE TEJIDO' => productSheetBulkImportTestFabricBaseName(),
            'REF. PRODUCTO' => 'TESTREUSE2-' . uniqid(),
            'PRECIO VENTA *' => '50000',
            'DESCRIPCIÓN *' => 'Fila 2',
        ],
    ]);

    $result = $this->service->processBulkUploadArray($rows);

    expect($result['inserted'])->toBe(2);
    expect(
        collect($result['created_catalog_entries'])->filter(fn ($e) => $e === "MARCA: '{$newBrandName}'")->count()
    )->toBe(1);
});
