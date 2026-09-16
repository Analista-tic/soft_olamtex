<?php

use App\Models\MasterData\CategoryGroup;
use App\Models\MasterData\CategorySubGroup;
use App\Models\MasterData\Item;
use App\Models\Settings\User;
use Spatie\Permission\Models\Permission;

/**
 * `legacy_code` (código del sistema Avansis) ya existía como columna, pero solo se
 * poblaba desde la carga masiva y en el formulario del ítem se mostraba en solo
 * lectura (y ni siquiera aparecía si estaba vacío). Ahora también se puede escribir
 * a mano al crear o editar un ítem, para vincular el código del sistema anterior sin
 * pasar por una importación.
 */
beforeEach(function () {
    $group = CategoryGroup::firstOrCreate(['name' => 'MATERIA PRIMA LEGACY CODE TEST']);
    $this->subGroup = CategorySubGroup::updateOrCreate(
        ['name' => 'Subgrupo Legacy Code Test', 'category_group_id' => $group->id],
    );

    $user = User::factory()->create();
    foreach (['ver inventario telas', 'crear inventario telas', 'editar inventario telas'] as $permission) {
        $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }
    $this->actingAs($user);
});

it('saves a manually entered legacy_code when creating an item', function () {
    $response = $this->postJson(route('item.store'), [
        'category_sub_group_id' => $this->subGroup->id,
        'legacy_code' => 'AVS-12345',
        'reference' => 'REF-LEGACY-'.uniqid(),
        'description' => 'Item con codigo Avansis manual',
        'tax' => 0,
        'negative_movement' => false,
        'item_type' => 'product',
    ]);

    $response->assertCreated();
    expect($response->json('legacy_code'))->toBe('AVS-12345');
});

it('updates the legacy_code on an existing item', function () {
    $item = Item::create([
        'category_sub_group_id' => $this->subGroup->id,
        'name' => 'ITEM EXISTENTE LEGACY CODE TEST',
        'description' => 'Item existente',
        'reference' => 'REF-LEGACY-UPDATE-'.uniqid(),
        'status' => true,
        'tax' => 0,
    ]);

    $response = $this->putJson(route('item.update', $item->id), [
        'category_sub_group_id' => $this->subGroup->id,
        'legacy_code' => 'AVS-99999',
        'reference' => $item->reference,
        'description' => 'Item existente editado',
        'tax' => 0,
        'negative_movement' => false,
        'item_type' => 'product',
    ]);

    $response->assertOk();
    expect($item->fresh()->legacy_code)->toBe('AVS-99999');
});
