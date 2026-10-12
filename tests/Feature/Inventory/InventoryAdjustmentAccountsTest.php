<?php

use App\Models\Finance\AccountingAccount;
use App\Models\Finance\JournalEntry;
use App\Models\Inventory\InventoryMovement;
use App\Models\Inventory\Warehouse;
use App\Models\MasterData\CategoryGroup;
use App\Models\MasterData\CategorySubGroup;
use App\Models\MasterData\Item;
use App\Models\Settings\User;
use Spatie\Permission\Models\Permission;

/**
 * Una regularización positiva (y toda salida) genera asiento contable, así que el ítem necesita
 * cuenta de inventario y de costo válidas. Se valida antes de tocar el kardex y el mensaje dice qué ítem arreglar.
 */
beforeEach(function () {
    $user = User::factory()->create();
    foreach (['ver ajustes inventario', 'crear ajustes inventario'] as $permission) {
        $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }
    $this->actingAs($user);

    $group = CategoryGroup::firstOrCreate(['name' => 'INSUMOS ADJUSTMENT ACCOUNTS']);
    $this->subGroup = CategorySubGroup::create([
        'name' => 'Bolsas Adjustment Accounts',
        'category_group_id' => $group->id,
        'material_kind' => null,
    ]);
    $this->warehouse = Warehouse::create(['name' => 'Bodega Accounts Test', 'observation' => 'Bodega de prueba']);
});

function accountsTestItem(int $subGroupId, array $overrides = []): Item
{
    return Item::create(array_merge([
        'category_sub_group_id' => $subGroupId,
        'name' => 'BOLSA UNICO',
        'description' => 'Item de prueba',
        'status' => true,
        'tracks_inventory' => true,
    ], $overrides));
}

function accountsTestAccount(string $code, string $type, bool $allowsEntries = true): AccountingAccount
{
    return AccountingAccount::create([
        'code' => $code,
        'name' => 'Cuenta '.$code,
        'account_type' => $type,
        'level' => 3,
        'allows_entries' => $allowsEntries,
        'is_active' => true,
    ]);
}

function accountsTestPayload(int $itemId, int $warehouseId, string $type = 'entrada', string $reason = 'entry_regularization'): array
{
    return [
        'document_number' => null,
        'date' => now()->toDateString(),
        'type' => $type,
        'movement_reason' => $reason,
        'lines' => [[
            'item_id' => $itemId,
            'warehouse_id' => $warehouseId,
            'price' => 1000,
            'tax' => 0,
            'quantity' => 5,
            'movement_reason' => $reason,
            'description' => 'Ajuste de prueba',
        ]],
    ];
}

it('saves the stock adjustment without a journal entry when the item has no accounting accounts', function () {
    $item = accountsTestItem($this->subGroup->id);

    $response = $this->postJson(route('ajustes-inventario.store'), accountsTestPayload($item->id, $this->warehouse->id));

    $response->assertCreated();
    expect($response->json('data.accounting_warning'))
        ->toContain('BOLSA UNICO')
        ->toContain('cuenta de inventario')
        ->toContain('cuenta de costo/gasto directo');
    expect(InventoryMovement::where('item_id', $item->id)->count())->toBe(1);
    expect(JournalEntry::count())->toBe(0);
});

it('saves the adjustment when the line description is empty', function () {
    $item = accountsTestItem($this->subGroup->id);
    $payload = accountsTestPayload($item->id, $this->warehouse->id);
    $payload['lines'][0]['description'] = null;

    $this->postJson(route('ajustes-inventario.store'), $payload)->assertCreated();

    $payload['lines'][0]['description'] = '';
    $this->postJson(route('ajustes-inventario.store'), $payload)->assertCreated();
});

it('also skips the journal entry when the configured accounts do not allow entries', function () {
    $item = accountsTestItem($this->subGroup->id, [
        'inventory_account_id' => accountsTestAccount('14', 'asset', false)->id,
        'cost_of_sale_account_id' => accountsTestAccount('61', 'cost', false)->id,
    ]);

    $this->postJson(route('ajustes-inventario.store'), accountsTestPayload($item->id, $this->warehouse->id))
        ->assertCreated()
        ->assertJsonPath('data.accounting_warning', fn ($w) => is_string($w));

    expect(JournalEntry::count())->toBe(0);
});

it('saves the positive regularization once the item has postable accounts', function () {
    $item = accountsTestItem($this->subGroup->id, [
        'inventory_account_id' => accountsTestAccount('1405', 'asset')->id,
        'cost_of_sale_account_id' => accountsTestAccount('6135', 'cost')->id,
    ]);

    $this->postJson(route('ajustes-inventario.store'), accountsTestPayload($item->id, $this->warehouse->id))
        ->assertCreated();

    expect(InventoryMovement::where('item_id', $item->id)->count())->toBe(1);
    expect(JournalEntry::count())->toBe(1);
});

it('suggests the weighted average price of the warehouse', function () {
    $item = accountsTestItem($this->subGroup->id, [
        'inventory_account_id' => accountsTestAccount('1405', 'asset')->id,
        'cost_of_sale_account_id' => accountsTestAccount('6135', 'cost')->id,
    ]);

    $this->postJson(route('ajustes-inventario.store'), accountsTestPayload($item->id, $this->warehouse->id))->assertCreated();

    $this->getJson(route('movimientos-inventario.weighted-price', ['item_id' => $item->id, 'warehouse_id' => $this->warehouse->id]))
        ->assertOk()
        ->assertJsonPath('weighted_price', 1000)
        ->assertJsonPath('current_stock', 5);
});

it('values an outflow adjustment at the weighted average whatever price the client sends', function () {
    $item = accountsTestItem($this->subGroup->id);

    $this->postJson(route('ajustes-inventario.store'), accountsTestPayload($item->id, $this->warehouse->id))->assertCreated();

    $payload = accountsTestPayload($item->id, $this->warehouse->id, 'salida', 'exit_regularization');
    $payload['lines'][0]['price'] = 1;
    $payload['lines'][0]['quantity'] = 2;

    $response = $this->postJson(route('ajustes-inventario.store'), $payload)->assertCreated();

    $line = \App\Models\Inventory\InventoryAdjustmentLine::where('inventory_adjustment_id', $response->json('data.id'))->first();
    expect((float) $line->price)->toBe(1000.0);
    expect(InventoryMovement::where('item_id', $item->id)->where('type', 'salida')->value('total_cost'))->toEqual(2000);
});

it('ignores a document number sent by the client when creating, so a repeated number never fails', function () {
    $item = accountsTestItem($this->subGroup->id);
    $payload = accountsTestPayload($item->id, $this->warehouse->id);

    $first = $this->postJson(route('ajustes-inventario.store'), $payload)->assertCreated();
    $payload['document_number'] = $first->json('data.document_number');
    $second = $this->postJson(route('ajustes-inventario.store'), $payload)->assertCreated();

    expect($second->json('data.document_number'))->not->toBe($first->json('data.document_number'));
});
