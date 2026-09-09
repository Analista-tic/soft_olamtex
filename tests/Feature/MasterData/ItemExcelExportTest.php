<?php

use App\Models\MasterData\CategoryGroup;
use App\Models\MasterData\CategorySubGroup;
use App\Models\MasterData\Item;
use App\Services\MasterData\ItemExcelService;

/**
 * exportRowForItem() no traía BASE TELA/BASE HILAZA (encontrado al implementar la
 * Fase 14, que agrega CÓDIGO SISTEMA ANTERIOR): el arreglo de la fila tenía 2
 * columnas menos que templateHeaders(), desalineando ESTADO bajo la columna BASE
 * TELA y dejando BASE HILAZA/ESTADO vacías en cualquier exportación. Se corrige
 * junto con la columna nueva para no exportar una fila desalineada otra vez.
 */
it('keeps exportRowForItem aligned with templateHeaders, including the new legacy_code column', function () {
    $group = CategoryGroup::create(['name' => 'Grupo Export Test']);
    $subGroup = CategorySubGroup::create(['name' => 'Subgrupo Export Test', 'category_group_id' => $group->id]);

    $item = Item::create([
        'category_sub_group_id' => $subGroup->id,
        'name' => 'Item Export Test',
        'reference' => 'REF-EXPORT-TEST',
        'legacy_code' => 'OLD-EXPORT-1',
        'description' => 'DESCRIPCION',
        'status' => true,
        'item_type' => 'product',
    ]);

    $service = app(ItemExcelService::class);
    $headers = $service->templateHeaders();
    $row = $service->exportRowForItem($item);

    // exportRowForItem() sí incluye el N° ITEM (item->id) como primera columna —
    // debe alinear 1:1 con templateHeaders() (N° ITEM + importTemplateHeaders()).
    expect($row)->toHaveCount(count($headers));

    $indexed = array_combine($headers, $row);

    expect($indexed['ESTADO'])->toBe('Activo')
        ->and($indexed['BASE TELA'])->toBe('')
        ->and($indexed['BASE HILAZA'])->toBe('')
        ->and($indexed['CODIGO SISTEMA ANTERIOR'])->toBe('OLD-EXPORT-1');
});
