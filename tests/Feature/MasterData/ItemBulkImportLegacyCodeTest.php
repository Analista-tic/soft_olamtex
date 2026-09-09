<?php

use App\Models\Inventory\Warehouse;
use App\Models\Inventory\WarehouseItem;
use App\Models\MasterData\CategoryGroup;
use App\Models\MasterData\CategorySubGroup;
use App\Models\MasterData\Item;
use App\Services\MasterData\ItemExcelService;
use App\Services\MasterData\ItemService;

/**
 * Fase 14 del refactor de materiales: los ítems ganan `legacy_code` (trazabilidad
 * con el sistema anterior) y la carga masiva puede usarlo para matching además del
 * N° ITEM, con un checklist opcional (`fields_to_update`) que restringe qué campos
 * se tocan al actualizar filas que ya coinciden con un ítem existente.
 */
function buildBulkImportRows(array $overrides = []): array
{
    $headers = app(ItemExcelService::class)->importTemplateHeaders();
    $letters = range('A', 'Z');

    $headerRow = [];
    foreach ($headers as $i => $header) {
        $headerRow[$letters[$i]] = $header;
    }

    $defaults = [
        'TIPO DE ITEM' => 'Producto',
        'NOMBRE' => '',
        'GRUPO' => '',
        'SUBGRUPO' => '',
        'IMPUESTO (%)' => '',
        'BODEGA' => '',
        'PRECIO' => '',
        'CANTIDAD' => '',
        'CANTIDAD MIN' => '',
        'CANTIDAD MAX' => '',
        'UNIDAD DE MEDIDA' => '',
        'CODIGO DE BARRAS' => '',
        'REFERENCIA' => 'REF-'.uniqid(),
        'DESCRIPCION' => 'DESCRIPCION ORIGINAL',
        'MOVIMIENTO NEGATIVO' => 'NO',
        'CAMPOS ADICIONALES' => '',
        'VALORES ADICIONALES' => '',
        'COLOR' => '',
        'BASE TELA' => '',
        'BASE HILAZA' => '',
        'ESTADO' => 'Activo',
        'CODIGO SISTEMA ANTERIOR' => '',
    ];

    $rowData = array_merge($defaults, $overrides);

    $dataRow = [];
    foreach ($headers as $i => $header) {
        $dataRow[$letters[$i]] = $rowData[$header] ?? '';
    }

    return [1 => $headerRow, 2 => $dataRow];
}

beforeEach(function () {
    $this->group = CategoryGroup::create(['name' => 'Grupo Bulk Legacy Test']);
    $this->subGroup = CategorySubGroup::create(['name' => 'Subgrupo Bulk Legacy Test', 'category_group_id' => $this->group->id]);
});

it('sets legacy_code on items inserted through bulk upload', function () {
    $rows = buildBulkImportRows([
        'SUBGRUPO' => $this->subGroup->name,
        'CODIGO SISTEMA ANTERIOR' => 'OLD-100',
    ]);

    $result = app(ItemService::class)->processBulkUploadArray($rows);

    expect($result['inserted'])->toBe(1)
        ->and($result['errors'])->toBe([]);

    $item = Item::where('category_sub_group_id', $this->subGroup->id)->first();
    expect($item->legacy_code)->toBe('OLD-100');
});

it('matches an existing item by legacy_code when there is no N° ITEM column', function () {
    $item = Item::create([
        'category_sub_group_id' => $this->subGroup->id,
        'name' => 'Item Preexistente',
        'reference' => 'REF-PRE-EXISTENTE',
        'legacy_code' => 'OLD-200',
        'description' => 'DESCRIPCION VIEJA',
        'status' => true,
        'item_type' => 'product',
    ]);

    $rows = buildBulkImportRows([
        'SUBGRUPO' => $this->subGroup->name,
        'REFERENCIA' => 'REF-PRE-EXISTENTE',
        'DESCRIPCION' => 'DESCRIPCION ACTUALIZADA',
        'CODIGO SISTEMA ANTERIOR' => 'OLD-200',
    ]);

    $result = app(ItemService::class)->processBulkUploadArray($rows);

    expect($result['inserted'])->toBe(0)
        ->and($result['updated'])->toBe(1)
        ->and(Item::count())->toBe(1)
        ->and($item->fresh()->description)->toBe('DESCRIPCION ACTUALIZADA');
});

it('only applies the fields checked in fields_to_update on a legacy_code match', function () {
    $item = Item::create([
        'category_sub_group_id' => $this->subGroup->id,
        'name' => 'Item Restringido',
        'reference' => 'REF-RESTRINGIDA',
        'legacy_code' => 'OLD-300',
        'description' => 'DESCRIPCION SIN TOCAR',
        'bar_code' => 'BAR-ORIGINAL',
        'status' => true,
        'item_type' => 'product',
    ]);

    $rows = buildBulkImportRows([
        'SUBGRUPO' => $this->subGroup->name,
        'REFERENCIA' => 'REF-RESTRINGIDA',
        'DESCRIPCION' => 'DESCRIPCION QUE NO DEBE APLICARSE',
        'CODIGO DE BARRAS' => 'BAR-NUEVO',
        'CODIGO SISTEMA ANTERIOR' => 'OLD-300',
    ]);

    $result = app(ItemService::class)->processBulkUploadArray($rows, ['codigo_barras']);

    expect($result['updated'])->toBe(1);

    $fresh = $item->fresh();
    expect($fresh->bar_code)->toBe('BAR-NUEVO')
        ->and($fresh->description)->toBe('DESCRIPCION SIN TOCAR');
});

it('does not touch warehouse stock on update when bodega_stock is excluded from fields_to_update', function () {
    $warehouse = Warehouse::create(['name' => 'Bodega Bulk Legacy Test', 'observation' => '']);
    $item = Item::create([
        'category_sub_group_id' => $this->subGroup->id,
        'name' => 'Item Con Stock',
        'reference' => 'REF-CON-STOCK',
        'legacy_code' => 'OLD-400',
        'description' => 'DESCRIPCION',
        'status' => true,
        'item_type' => 'product',
    ]);
    WarehouseItem::create([
        'item_id' => $item->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 10,
        'stock_min' => 1,
        'stock_max' => 100,
    ]);

    $rows = buildBulkImportRows([
        'SUBGRUPO' => $this->subGroup->name,
        'REFERENCIA' => 'REF-CON-STOCK',
        'BODEGA' => $warehouse->name,
        'CANTIDAD' => '999',
        'PRECIO' => '50',
        'CODIGO SISTEMA ANTERIOR' => 'OLD-400',
    ]);

    $result = app(ItemService::class)->processBulkUploadArray($rows, ['descripcion']);

    expect($result['updated'])->toBe(1);

    $warehouseItem = WarehouseItem::where('item_id', $item->id)->first();
    expect((float) $warehouseItem->quantity)->toBe(10.0);
});

it('accumulates warehouse stock across rows for the same item instead of replacing it', function () {
    $this->actingAs(App\Models\Settings\User::factory()->create());

    $warehouseA = Warehouse::create(['name' => 'Bodega A Bulk Legacy Test', 'observation' => '']);
    $warehouseB = Warehouse::create(['name' => 'Bodega B Bulk Legacy Test', 'observation' => '']);

    $firstRow = buildBulkImportRows([
        'SUBGRUPO' => $this->subGroup->name,
        'REFERENCIA' => 'REF-MULTI-BODEGA',
        'BODEGA' => $warehouseA->name,
        'CANTIDAD' => '10',
        'PRECIO' => '100',
        'CODIGO SISTEMA ANTERIOR' => 'OLD-600',
    ]);
    $firstResult = app(ItemService::class)->processBulkUploadArray($firstRow);
    expect($firstResult['inserted'])->toBe(1)->and($firstResult['errors'])->toBe([]);

    // Misma fila del histórico, otra bodega: mismo legacy_code hace match por
    // actualización — no debe borrar el stock que la primera fila dejó en Bodega A.
    $secondRow = buildBulkImportRows([
        'SUBGRUPO' => $this->subGroup->name,
        'REFERENCIA' => 'REF-MULTI-BODEGA',
        'BODEGA' => $warehouseB->name,
        'CANTIDAD' => '5',
        'PRECIO' => '50',
        'CODIGO SISTEMA ANTERIOR' => 'OLD-600',
    ]);
    $secondResult = app(ItemService::class)->processBulkUploadArray($secondRow);
    expect($secondResult['updated'])->toBe(1)->and($secondResult['errors'])->toBe([]);

    $item = Item::where('legacy_code', 'OLD-600')->firstOrFail();
    $stockByWarehouse = WarehouseItem::where('item_id', $item->id)->pluck('quantity', 'warehouse_id');

    expect($stockByWarehouse)->toHaveCount(2)
        ->and((float) $stockByWarehouse[$warehouseA->id])->toBe(10.0)
        ->and((float) $stockByWarehouse[$warehouseB->id])->toBe(5.0);
});

it('an empty fields_to_update list means no field is applied to a matched update', function () {
    $item = Item::create([
        'category_sub_group_id' => $this->subGroup->id,
        'name' => 'Item Sin Cambios',
        'reference' => 'REF-SIN-CAMBIOS',
        'legacy_code' => 'OLD-500',
        'description' => 'DESCRIPCION QUE PERMANECE',
        'status' => true,
        'item_type' => 'product',
    ]);

    $rows = buildBulkImportRows([
        'SUBGRUPO' => $this->subGroup->name,
        'REFERENCIA' => 'REF-SIN-CAMBIOS',
        'DESCRIPCION' => 'DESCRIPCION QUE NO DEBE APLICARSE',
        'CODIGO SISTEMA ANTERIOR' => 'OLD-500',
    ]);

    $result = app(ItemService::class)->processBulkUploadArray($rows, []);

    expect($result['updated'])->toBe(1)
        ->and($item->fresh()->description)->toBe('DESCRIPCION QUE PERMANECE');
});
