<?php

use App\Models\Income\SaleDocument;
use App\Models\Inventory\Warehouse;
use App\Models\Inventory\WarehouseItem;
use App\Models\MasterData\CategoryGroup;
use App\Models\MasterData\CategorySubGroup;
use App\Models\MasterData\Contact;
use App\Models\MasterData\Item;
use App\Models\Settings\Setting;
use App\Models\Settings\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $user = User::factory()->create();
    foreach ([
        'ver categorias', 'eliminar categorias',
        'ver contactos', 'eliminar contactos',
        'ver campos adicionales', 'eliminar campos adicionales',
        'ver bodegas', 'eliminar bodegas',
    ] as $permission) {
        $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }
    $this->actingAs($user);

    $this->group = CategoryGroup::create(['name' => 'Grupo Guard Test']);
    $this->subGroup = CategorySubGroup::create(['name' => 'Subgrupo Guard Test', 'category_group_id' => $this->group->id]);
});

function makeGuardItem(int $subGroupId, array $overrides = []): Item
{
    return Item::create(array_merge([
        'category_sub_group_id' => $subGroupId,
        'name' => 'Item Guard',
        'reference' => 'REF-GUARD-'.uniqid(),
        'description' => 'DESCRIPCION',
        'status' => true,
        'item_type' => 'product',
    ], $overrides));
}

it('no permite eliminar un subgrupo que tiene ítems asociados', function () {
    makeGuardItem($this->subGroup->id);

    $this->deleteJson(route('categorias.subgroups.destroy', $this->subGroup))
        ->assertStatus(409);

    expect(CategorySubGroup::whereKey($this->subGroup->id)->exists())->toBeTrue();
});

it('elimina un subgrupo sin ítems asociados', function () {
    $this->deleteJson(route('categorias.subgroups.destroy', $this->subGroup))
        ->assertOk();

    expect(CategorySubGroup::whereKey($this->subGroup->id)->exists())->toBeFalse();
});

it('no permite eliminar un campo adicional que tiene valores en ítems', function () {
    $field = Setting::create([
        'kind' => Setting::KIND_FIELD,
        'key' => 'color-hilo-guard',
        'label' => 'Color Hilo Guard',
        'field_type' => 'text',
        'status' => true,
        'position' => 1,
    ]);
    makeGuardItem($this->subGroup->id, ['extra_fields' => ['Color Hilo Guard' => 'Rojo']]);

    $this->deleteJson(route('inventory.additional-fields.destroy', $field))
        ->assertStatus(409);

    expect(Setting::whereKey($field->id)->exists())->toBeTrue();
});

it('elimina un campo adicional sin valores en ítems', function () {
    $field = Setting::create([
        'kind' => Setting::KIND_FIELD,
        'key' => 'campo-libre-guard',
        'label' => 'Campo Libre Guard',
        'field_type' => 'text',
        'status' => true,
        'position' => 1,
    ]);

    $this->deleteJson(route('inventory.additional-fields.destroy', $field))
        ->assertNoContent();

    expect(Setting::whereKey($field->id)->exists())->toBeFalse();
});

it('no permite eliminar una bodega con stock o movimientos asociados', function () {
    $warehouse = Warehouse::create(['name' => 'Bodega Guard', 'observation' => 'x']);
    $item = makeGuardItem($this->subGroup->id);
    WarehouseItem::create(['warehouse_id' => $warehouse->id, 'item_id' => $item->id, 'quantity' => 5]);

    $this->deleteJson(route('warehouses.destroy', $warehouse))
        ->assertStatus(409);

    expect(Warehouse::whereKey($warehouse->id)->exists())->toBeTrue()
        ->and(WarehouseItem::where('warehouse_id', $warehouse->id)->count())->toBe(1);
});

it('elimina una bodega sin movimientos asociados', function () {
    $warehouse = Warehouse::create(['name' => 'Bodega Vacia Guard', 'observation' => 'x']);

    $this->deleteJson(route('warehouses.destroy', $warehouse))
        ->assertNoContent();

    expect(Warehouse::whereKey($warehouse->id)->exists())->toBeFalse();
});

it('no permite eliminar un contacto con facturas de venta asociadas', function () {
    $contact = Contact::factory()->create(['contact_type' => 'cliente']);
    SaleDocument::create([
        'document_type' => SaleDocument::TYPE_QUOTE,
        'document_number' => 'COT-GUARD-1',
        'contact_id' => $contact->id,
        'date' => '2026-01-15',
        'status' => 'borrador',
        'created_by_user_id' => auth()->id(),
    ]);

    $this->deleteJson(route('contactos.destroy', $contact))
        ->assertStatus(409);

    expect(Contact::whereKey($contact->id)->exists())->toBeTrue();
});
