<?php

use App\Models\MasterData\Item;
use App\Models\Production\ProductionOrder;
use App\Models\Production\ProductSheet;
use App\Models\Settings\Setting;
use App\Models\Settings\User;
use App\Services\Production\FinishedProductCatalogItemService;

/**
 * Tests de humo: el subgrupo de categoría del ítem de producto terminado se
 * resuelve desde section_id (campo estructurado de la sábana), nunca desde
 * la primera palabra del nombre libre del producto.
 */
function financialCatalogTestProductSheet(array $overrides = []): ProductSheet
{
    return ProductSheet::create(array_merge([
        'section_id' => 1,
        'group_id' => 1,
        'brand_id' => 1,
        'world_id' => 1,
        'type' => 'producto',
        'billing_price' => 1000,
        'selling_price' => 1200,
        'sizes' => ['S', 'M'],
        'woven_product' => false,
        'description' => 'Nombre libre de prueba',
        'sample_reference' => 'MUESTRA-' . uniqid(),
        'product_reference' => 'PRODUCTO-' . uniqid(),
        'processes' => [],
        'status' => 'complete',
    ], $overrides));
}

function financialCatalogTestOrder(ProductSheet $productSheet): ProductionOrder
{
    return ProductionOrder::create([
        'product_sheet_id' => $productSheet->id,
        'op_code' => 'OP-' . uniqid(),
        'season' => '1',
        'destination_id' => 1,
        'priority' => 'alta',
        'units' => 10,
        'estimated_delivery_date' => '2026-03-30',
        'status' => 'active',
        'current_process' => 'Corte',
        'source' => 'manual',
    ]);
}

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    $this->service = app(FinishedProductCatalogItemService::class);
});

test('resolves the subgroup from section_id even when the free-text name does not start with the section', function () {
    Setting::create([
        'kind' => 'parameter',
        'key' => 'sections',
        'label' => 'Secciones',
        'value' => [['id' => 1, 'name' => 'JOGGER']],
        'status' => true,
    ]);

    $productSheet = financialCatalogTestProductSheet([
        'description' => 'Nuevo jogger premium edición limitada',
    ]);
    $order = financialCatalogTestOrder($productSheet);

    $item = $this->service->ensureForProductionOrder($order);

    expect($item->categorySubGroup->name)->toBe('JOGGER');
});

test('resolves the subgroup from section_id for the old-style "SECTION WORLD" description too (non-regression)', function () {
    Setting::create([
        'kind' => 'parameter',
        'key' => 'sections',
        'label' => 'Secciones',
        'value' => [['id' => 1, 'name' => 'JOGGER']],
        'status' => true,
    ]);

    $productSheet = financialCatalogTestProductSheet([
        'description' => 'JOGGER HOMBRE',
    ]);
    $order = financialCatalogTestOrder($productSheet);

    $item = $this->service->ensureForProductionOrder($order);

    expect($item->categorySubGroup->name)->toBe('JOGGER');
});

test('the single item name/description equals the free-text product name as typed', function () {
    Setting::create([
        'kind' => 'parameter',
        'key' => 'sections',
        'label' => 'Secciones',
        'value' => [['id' => 1, 'name' => 'JOGGER']],
        'status' => true,
    ]);

    $freeText = 'Nuevo jogger premium edición limitada';
    $productSheet = financialCatalogTestProductSheet(['description' => $freeText]);
    $order = financialCatalogTestOrder($productSheet);

    $item = $this->service->ensureForProductionOrder($order);

    expect($item->name)->toBe($freeText)
        ->and($item->description)->toBe($freeText);
});

test('the per size/color item name is "SECTION COLOR SIZE" regardless of the free-text name', function () {
    Setting::create([
        'kind' => 'parameter',
        'key' => 'sections',
        'label' => 'Secciones',
        'value' => [['id' => 1, 'name' => 'JOGGER']],
        'status' => true,
    ]);

    $productSheet = financialCatalogTestProductSheet([
        'description' => 'Cualquier texto que el usuario haya escrito',
    ]);
    $order = financialCatalogTestOrder($productSheet);

    $result = $this->service->ensureItemsForCurves($order, [
        [
            'color' => 'ROJO',
            'color_code' => '',
            'S' => 5,
            'total' => 5,
        ],
    ]);

    expect($result)->toHaveCount(1);
    $itemId = array_key_first($result);
    $item = Item::findOrFail($itemId);

    expect($item->name)->toBe('JOGGER ROJO S');
});

test('falls back to the first word of the description when the section option cannot be resolved', function () {
    // A propósito no se siembra el parámetro "sections": simula un catálogo
    // desactualizado (la opción de la sección ya no existe en settings).
    $productSheet = financialCatalogTestProductSheet([
        'description' => 'PANTALON DEPORTIVO GRIS',
    ]);
    $order = financialCatalogTestOrder($productSheet);

    $item = $this->service->ensureForProductionOrder($order);

    expect($item->categorySubGroup->name)->toBe('PANTALON');
});
