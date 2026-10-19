<?php

use App\Models\Inventory\InventoryRequest;
use App\Models\Inventory\Warehouse;
use App\Models\Inventory\WarehouseItem;
use App\Models\MasterData\CategoryGroup;
use App\Models\MasterData\CategorySubGroup;
use App\Models\MasterData\Item;
use App\Models\Settings\User;
use Spatie\Permission\Models\Permission;

/**
 * La solicitud interna no puede pedir más que el stock total; lo que falte se pide por compra.
 * El motivo de rechazo se guarda y llega en el detalle.
 */
beforeEach(function () {
    $this->user = User::factory()->create();
    foreach (['ver todos solicitudes insumos', 'rechazar solicitudes insumos'] as $permission) {
        $this->user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }
    $this->actingAs($this->user);

    $group = CategoryGroup::firstOrCreate(['name' => 'INSUMOS SOLICITUD TEST']);
    $subGroup = CategorySubGroup::create(['name' => 'Insumos Solicitud Test', 'category_group_id' => $group->id]);
    $this->item = Item::create([
        'category_sub_group_id' => $subGroup->id,
        'name' => 'ENCINTADOR SOLICITUD',
        'description' => 'Item de prueba',
        'status' => true,
        'tracks_inventory' => true,
    ]);
    foreach (['A', 'B'] as $name) {
        $warehouse = Warehouse::create(['name' => "Bodega {$name} Solicitud", 'observation' => 'Prueba']);
        WarehouseItem::create(['item_id' => $this->item->id, 'warehouse_id' => $warehouse->id, 'quantity' => 1, 'stock_min' => 0, 'stock_max' => 0, 'weighted_average_price' => 100, 'total_cost' => 100]);
    }
});

function inventoryRequestStockPayload(int $itemId, float $quantity): array
{
    return [
        'date' => now()->toDateString(),
        'observation' => 'Para producción',
        'items' => [['item_id' => $itemId, 'quantity' => $quantity]],
    ];
}

it('accepts a request up to the total stock across warehouses', function () {
    $this->postJson(route('inventory-requests.store'), inventoryRequestStockPayload($this->item->id, 2))->assertCreated();
});

it('refuses a request above the total stock and says what to do with the rest', function () {
    $response = $this->postJson(route('inventory-requests.store'), inventoryRequestStockPayload($this->item->id, 3))->assertUnprocessable();

    expect($response->json('errors.items.0'))->toContain('Solo hay 2')->toContain('solicitud de compra');
    expect(InventoryRequest::count())->toBe(0);
});

it('adds repeated lines of the same item before comparing against the stock', function () {
    $payload = inventoryRequestStockPayload($this->item->id, 2);
    $payload['items'][] = ['item_id' => $this->item->id, 'quantity' => 1];

    $this->postJson(route('inventory-requests.store'), $payload)->assertUnprocessable();
});

it('does not limit free-text items that have no stock to compare', function () {
    $payload = inventoryRequestStockPayload($this->item->id, 1);
    $payload['items'] = [['item_name' => 'Cinta rara sin catálogo', 'quantity' => 50]];

    $this->postJson(route('inventory-requests.store'), $payload)->assertCreated();
});

it('returns the rejection reason, who rejected and when in the request detail', function () {
    $created = $this->postJson(route('inventory-requests.store'), inventoryRequestStockPayload($this->item->id, 1))->assertCreated();
    $id = $created->json('data.id');

    $this->postJson(route('inventory-requests.reject', $id), ['reason' => 'No hay presupuesto este mes'])->assertOk();

    $this->getJson(route('inventory-requests.show', $id))
        ->assertOk()
        ->assertJsonPath('data.status', 'rechazado')
        ->assertJsonPath('data.rejection_reason', 'No hay presupuesto este mes')
        ->assertJsonPath('data.rejected_by.id', $this->user->id)
        ->assertJsonPath('data.rejected_at', fn ($value) => ! empty($value));
});
