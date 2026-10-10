<?php

use App\Models\Inventory\InventoryMovement;
use App\Models\Inventory\Warehouse;
use App\Models\Inventory\WarehouseItem;
use App\Models\Inventory\WarehouseLocation;
use App\Models\MasterData\CategoryGroup;
use App\Models\MasterData\CategorySubGroup;
use App\Models\MasterData\Color;
use App\Models\Settings\User;
use App\Services\Inventory\InventoryMovementService;
use Spatie\Permission\Models\Permission;

/**
 * La cantidad en bodega de un ítem solo se fija al crearlo (saldo inicial con movimiento
 * en el kárdex). Después solo cambia por movimientos: recepciones de compra, ajustes de
 * inventario, traslados. Editar el ítem solo puede tocar la configuración por bodega
 * (mínimo, máximo, ubicación) y nunca la cantidad ni el costo promedio.
 */
beforeEach(function () {
    $group = CategoryGroup::firstOrCreate(['name' => 'MATERIA PRIMA STOCK LOCK TEST']);
    $this->subGroup = CategorySubGroup::updateOrCreate(
        ['name' => 'Subgrupo Stock Lock Test', 'category_group_id' => $group->id],
    );

    $this->color = Color::create(['name' => 'AZUL STOCK LOCK TEST', 'code' => '78']);
    $this->warehouse = Warehouse::create(['name' => 'Bodega Stock Lock A', 'observation' => 'Prueba']);
    $this->otherWarehouse = Warehouse::create(['name' => 'Bodega Stock Lock B', 'observation' => 'Prueba']);
    $this->location = WarehouseLocation::create(['name' => 'STOCK-LOCK-A1', 'status' => true]);
    $this->otherLocation = WarehouseLocation::create(['name' => 'STOCK-LOCK-B1', 'status' => true]);

    $user = User::factory()->create();
    foreach (['ver catalogo items', 'crear items', 'editar items', 'crear referencia manual item'] as $permission) {
        $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }
    $this->actingAs($user);
});

function stockLockItemPayload(array $warehouses, array $overrides = []): array
{
    return array_merge([
        'category_sub_group_id' => test()->subGroup->id,
        'color_id' => test()->color->id,
        'reference' => 'REF-STOCK-LOCK-'.uniqid(),
        'reference_mode' => 'manual',
        'description' => 'Item de prueba bloqueo de saldo',
        'tax' => 0,
        'negative_movement' => false,
        'item_type' => 'product',
        'warehouses' => $warehouses,
    ], $overrides);
}

function stockLockWarehouseRow(int $warehouseId, int $locationId, array $overrides = []): array
{
    return array_merge([
        'warehouse_id' => $warehouseId,
        'quantity' => 0,
        'stock_min' => 1,
        'stock_max' => 100,
        'location_id' => $locationId,
    ], $overrides);
}

function createStockLockItemWithStock(float $quantity = 10, float $unitPrice = 2500): int
{
    $response = test()->postJson(route('item.store'), stockLockItemPayload([
        stockLockWarehouseRow(test()->warehouse->id, test()->location->id, [
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'initial_date' => now()->toDateString(),
        ]),
    ]));
    $response->assertCreated();

    return (int) $response->json('id');
}

function stockLockBalance(int $itemId, int $warehouseId): ?WarehouseItem
{
    return WarehouseItem::where('item_id', $itemId)->where('warehouse_id', $warehouseId)->first();
}

it('registers the quantity given at creation as an initial balance movement', function () {
    $itemId = createStockLockItemWithStock(10, 2500);

    $balance = stockLockBalance($itemId, $this->warehouse->id);
    expect((float) $balance->quantity)->toBe(10.0)
        ->and((float) $balance->weighted_average_price)->toBe(2500.0);

    $movement = InventoryMovement::where('item_id', $itemId)->sole();
    expect($movement->type)->toBe('entrada')
        ->and($movement->source_type)->toBe(InventoryMovementService::INITIAL_BALANCE_SOURCE_TYPE)
        ->and((float) $movement->quantity)->toBe(10.0);
});

it('creates a warehouse without initial balance when quantity is 0 and the unit cost is empty', function () {
    $response = $this->postJson(route('item.store'), stockLockItemPayload([
        stockLockWarehouseRow($this->warehouse->id, $this->location->id, ['quantity' => 0, 'unit_price' => null]),
    ]));

    $response->assertCreated();
    $itemId = (int) $response->json('id');
    expect((float) stockLockBalance($itemId, $this->warehouse->id)->quantity)->toBe(0.0)
        ->and(InventoryMovement::where('item_id', $itemId)->exists())->toBeFalse();
});

it('requires a unit cost greater than 0 when the initial quantity is greater than 0', function () {
    $response = $this->postJson(route('item.store'), stockLockItemPayload([
        stockLockWarehouseRow($this->warehouse->id, $this->location->id, ['quantity' => 5, 'unit_price' => null]),
    ]));

    $response->assertStatus(422);
    expect($response->json('errors.warehouses.0'))->toContain('costo unitario inicial');
});

it('rejects a negative initial quantity at creation', function () {
    $response = $this->postJson(route('item.store'), stockLockItemPayload([
        stockLockWarehouseRow($this->warehouse->id, $this->location->id, ['quantity' => -5, 'unit_price' => 1000]),
    ]));

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['warehouses']);
});

it('ignores the quantity sent when editing an item and keeps the balance and average cost', function () {
    $itemId = createStockLockItemWithStock(10, 2500);

    $response = $this->putJson(route('item.update', $itemId), stockLockItemPayload([
        stockLockWarehouseRow($this->warehouse->id, $this->otherLocation->id, [
            'quantity' => 999,
            'stock_min' => 5,
            'stock_max' => 50,
        ]),
    ], ['reference' => 'REF-STOCK-LOCK-EDIT-'.uniqid()]));

    $response->assertOk();

    $balance = stockLockBalance($itemId, $this->warehouse->id);
    expect((float) $balance->quantity)->toBe(10.0)
        ->and((float) $balance->weighted_average_price)->toBe(2500.0)
        ->and((float) $balance->stock_min)->toBe(5.0)
        ->and((float) $balance->stock_max)->toBe(50.0)
        ->and((int) $balance->location_id)->toBe($this->otherLocation->id)
        ->and(InventoryMovement::where('item_id', $itemId)->count())->toBe(1);
});

it('accepts warehouse rows without quantity when editing', function () {
    $itemId = createStockLockItemWithStock(10, 2500);

    $row = stockLockWarehouseRow($this->warehouse->id, $this->location->id, ['stock_min' => 2]);
    unset($row['quantity']);

    $response = $this->putJson(route('item.update', $itemId), stockLockItemPayload([$row], ['reference' => 'REF-STOCK-LOCK-NOQ-'.uniqid()]));

    $response->assertOk();
    expect((float) stockLockBalance($itemId, $this->warehouse->id)->quantity)->toBe(10.0);
});

it('creates a warehouse added while editing with zero quantity', function () {
    $itemId = createStockLockItemWithStock(10, 2500);

    $response = $this->putJson(route('item.update', $itemId), stockLockItemPayload([
        stockLockWarehouseRow($this->warehouse->id, $this->location->id),
        stockLockWarehouseRow($this->otherWarehouse->id, $this->otherLocation->id, ['quantity' => 50]),
    ], ['reference' => 'REF-STOCK-LOCK-ADD-'.uniqid()]));

    $response->assertOk();
    expect((float) stockLockBalance($itemId, $this->otherWarehouse->id)->quantity)->toBe(0.0)
        ->and((float) stockLockBalance($itemId, $this->warehouse->id)->quantity)->toBe(10.0);
});

it('blocks removing a warehouse that still has stock', function () {
    $itemId = createStockLockItemWithStock(10, 2500);

    $response = $this->putJson(route('item.update', $itemId), stockLockItemPayload([
        stockLockWarehouseRow($this->otherWarehouse->id, $this->otherLocation->id),
    ], ['reference' => 'REF-STOCK-LOCK-DEL-'.uniqid()]));

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['warehouses']);
    expect($response->json('errors.warehouses.0'))->toContain('Bodega Stock Lock A');

    $balance = stockLockBalance($itemId, $this->warehouse->id);
    expect($balance)->not->toBeNull()
        ->and((float) $balance->quantity)->toBe(10.0)
        ->and(stockLockBalance($itemId, $this->otherWarehouse->id))->toBeNull();
});

it('removes a warehouse without stock when editing', function () {
    $response = $this->postJson(route('item.store'), stockLockItemPayload([
        stockLockWarehouseRow($this->warehouse->id, $this->location->id),
        stockLockWarehouseRow($this->otherWarehouse->id, $this->otherLocation->id),
    ]));
    $response->assertCreated();
    $itemId = (int) $response->json('id');

    $response = $this->putJson(route('item.update', $itemId), stockLockItemPayload([
        stockLockWarehouseRow($this->warehouse->id, $this->location->id),
    ], ['reference' => 'REF-STOCK-LOCK-RM-'.uniqid()]));

    $response->assertOk();
    expect(stockLockBalance($itemId, $this->otherWarehouse->id))->toBeNull()
        ->and(stockLockBalance($itemId, $this->warehouse->id))->not->toBeNull();
});

it('rejects an initial quantity for a fabric-type item: its meters come from rolls', function () {
    $fabricSubGroup = CategorySubGroup::create([
        'name' => 'Tela Stock Lock Test',
        'category_group_id' => $this->subGroup->category_group_id,
        'material_kind' => 'fabric',
    ]);
    \App\Support\MasterData\MaterialClassifier::forgetCache();

    $response = $this->postJson(route('item.store'), stockLockItemPayload([
        stockLockWarehouseRow($this->warehouse->id, $this->location->id, [
            'quantity' => 10,
            'unit_price' => 2500,
            'initial_date' => now()->toDateString(),
        ]),
    ], ['category_sub_group_id' => $fabricSubGroup->id]));

    $response->assertStatus(422)->assertJsonValidationErrors(['warehouses']);
    expect(InventoryMovement::count())->toBe(0);

    // Con cantidad 0 sí se puede crear la ficha de la tela.
    $this->postJson(route('item.store'), stockLockItemPayload([
        stockLockWarehouseRow($this->warehouse->id, $this->location->id),
    ], ['category_sub_group_id' => $fabricSubGroup->id]))->assertCreated();
});
