<?php

use App\Models\Expenses\PurchaseDocument;
use App\Models\Inventory\Warehouse;
use App\Models\Inventory\WarehouseItem;
use App\Models\MasterData\CategoryGroup;
use App\Models\MasterData\CategorySubGroup;
use App\Models\MasterData\Contact;
use App\Models\MasterData\Item;
use App\Models\Production\ProductionOrder;
use App\Models\Production\ProductSheet;
use App\Models\Settings\User;
use App\Models\Textil\FabricRoll;

function makeReportSortingItem(string $name): Item
{
    $group = CategoryGroup::firstOrCreate(['name' => 'MATERIA PRIMA REPORT SORTING TEST']);
    $subGroup = CategorySubGroup::firstOrCreate(['name' => 'Report Sorting Test', 'category_group_id' => $group->id]);

    return Item::create([
        'category_sub_group_id' => $subGroup->id,
        'name' => $name,
        'description' => $name,
        'status' => true,
    ]);
}

/**
 * Fase 11: los 4 informes (Financiero, Inventario, Textil, Producción) ordenaban
 * fijo por id desc. Ahora aceptan sort_by/sort_direction (whitelist por endpoint).
 */
beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('sorts the financial report by document_number ascending', function () {
    $contact = Contact::factory()->create();

    PurchaseDocument::create(['document_number' => 'FC-002', 'document_type' => 'purchase_invoice', 'contact_id' => $contact->id, 'date' => '2026-01-10', 'status' => 'pending']);
    PurchaseDocument::create(['document_number' => 'FC-001', 'document_type' => 'purchase_invoice', 'contact_id' => $contact->id, 'date' => '2026-01-15', 'status' => 'pending']);

    $response = $this->getJson(route('informes.financiero.data', ['sort_by' => 'document_number', 'sort_direction' => 'asc']));

    $response->assertOk();
    $numbers = collect($response->json('data.data'))->pluck('document_number');

    expect($numbers->all())->toBe(['FC-001', 'FC-002']);
});

it('sorts the inventory report by quantity descending', function () {
    $warehouse = Warehouse::create(['name' => 'Bodega Test', 'observation' => 'x']);
    $item = makeReportSortingItem('Item Bajo Stock');

    WarehouseItem::create(['item_id' => $item->id, 'warehouse_id' => $warehouse->id, 'quantity' => 5, 'stock_min' => 0, 'stock_max' => 100]);
    WarehouseItem::create(['item_id' => makeReportSortingItem('Item Alto Stock')->id, 'warehouse_id' => $warehouse->id, 'quantity' => 50, 'stock_min' => 0, 'stock_max' => 100]);

    $response = $this->getJson(route('informes.inventario.data', ['sort_by' => 'quantity', 'sort_direction' => 'desc']));

    $response->assertOk();
    $quantities = collect($response->json('data.data'))->pluck('quantity')->map(fn ($q) => (float) $q);

    expect($quantities->first())->toBe(50.0);
});

it('sorts the textile report by roll_number ascending', function () {
    $item = makeReportSortingItem('Item Rollo Test');

    FabricRoll::create(['roll_number' => 'RL-002', 'item_id' => $item->id, 'quantity' => 10, 'actual_output' => 0, 'partial_output' => 0, 'entry_date' => '2026-01-01']);
    FabricRoll::create(['roll_number' => 'RL-001', 'item_id' => $item->id, 'quantity' => 10, 'actual_output' => 0, 'partial_output' => 0, 'entry_date' => '2026-01-01']);

    $response = $this->getJson(route('informes.textil.data', ['sort_by' => 'roll_number', 'sort_direction' => 'asc']));

    $response->assertOk();
    $numbers = collect($response->json('data.data'))->pluck('roll_number');

    expect($numbers->all())->toBe(['RL-001', 'RL-002']);
});

it('sorts the production report by op_code ascending', function () {
    $sheet = ProductSheet::create([
        'section_id' => 1, 'group_id' => 1, 'brand_id' => 1, 'world_id' => 1,
        'type' => 'producto', 'billing_price' => 1000, 'selling_price' => 1200,
        'sizes' => ['M'], 'woven_product' => false, 'description' => 'x',
        'sample_reference' => 'MU-X', 'product_reference' => 'PR-X',
        'processes' => ['Corte'], 'status' => 'complete',
    ]);

    ProductionOrder::create(['product_sheet_id' => $sheet->id, 'op_code' => 'OP-002', 'season' => '1', 'destination_id' => 1, 'priority' => 'alta', 'units' => 10, 'estimated_delivery_date' => '2026-03-30', 'status' => 'active', 'current_process' => 'Corte', 'source' => 'manual']);
    ProductionOrder::create(['product_sheet_id' => $sheet->id, 'op_code' => 'OP-001', 'season' => '1', 'destination_id' => 1, 'priority' => 'alta', 'units' => 10, 'estimated_delivery_date' => '2026-03-30', 'status' => 'active', 'current_process' => 'Corte', 'source' => 'manual']);

    $response = $this->getJson(route('informes.produccion.data', ['sort_by' => 'op_code', 'sort_direction' => 'asc']));

    $response->assertOk();
    $codes = collect($response->json('data.data'))->pluck('op_code');

    expect($codes->all())->toBe(['OP-001', 'OP-002']);
});
