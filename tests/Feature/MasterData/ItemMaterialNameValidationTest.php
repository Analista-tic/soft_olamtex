<?php

use App\Models\MasterData\CategoryGroup;
use App\Models\MasterData\CategorySubGroup;
use App\Models\MasterData\FabricName;
use App\Models\MasterData\Item;
use App\Models\MasterData\YarnName;
use App\Models\Production\FabricBase;
use App\Models\Production\YarnBase;
use App\Models\Settings\User;
use App\Support\MasterData\MaterialClassifier;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    MaterialClassifier::forgetCache();

    $group = CategoryGroup::firstOrCreate(['name' => 'MATERIA PRIMA VALIDATION TEST']);
    $this->fabricSubGroup = CategorySubGroup::updateOrCreate(
        ['name' => 'Tela Validation Test', 'category_group_id' => $group->id],
        ['material_kind' => 'fabric'],
    );
    $this->yarnSubGroup = CategorySubGroup::updateOrCreate(
        ['name' => 'Hilaza Validation Test', 'category_group_id' => $group->id],
        ['material_kind' => 'yarn'],
    );
    $this->otherSubGroup = CategorySubGroup::updateOrCreate(
        ['name' => 'Insumo Validation Test', 'category_group_id' => $group->id],
        ['material_kind' => null],
    );

    $fabricBase = FabricBase::create([
        'codigo' => 'BT-VALID-TEST',
        'nombre' => 'ALGODÓN VALID TEST',
        'factor' => 1.0,
        'is_active' => true,
    ]);
    $this->fabricName = FabricName::create([
        'nombre' => 'NOMBRE TELA VALID',
        'fabric_base_id' => $fabricBase->id,
        'is_active' => true,
    ]);

    $yarnBase = YarnBase::create([
        'codigo' => 'BH-VALID-TEST',
        'nombre' => 'ALGODÓN HILAZA VALID TEST',
        'is_active' => true,
    ]);
    $this->yarnName = YarnName::create([
        'nombre' => 'NOMBRE HILAZA VALID',
        'yarn_base_id' => $yarnBase->id,
        'is_active' => true,
    ]);

    $user = User::factory()->create();
    foreach (['ver inventario telas', 'crear inventario telas', 'editar inventario telas', 'crear referencia manual item'] as $permission) {
        $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }
    $this->actingAs($user);
});

function baseValidationItemPayload(int $subGroupId): array
{
    return [
        'category_sub_group_id' => $subGroupId,
        'reference' => 'REF-VALID-'.uniqid(),
        'reference_mode' => 'manual',
        'description' => 'Item de prueba validacion',
        'tax' => 0,
        'negative_movement' => false,
        'item_type' => 'product',
    ];
}

it('rejects a fabric_name_id on an item whose subgroup is not Tela', function () {
    $payload = baseValidationItemPayload($this->yarnSubGroup->id);
    $payload['fabric_name_id'] = $this->fabricName->id;

    $response = $this->postJson(route('item.store'), $payload);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['fabric_name_id']);
});

it('rejects a yarn_name_id on an item whose subgroup is not Hilaza', function () {
    $payload = baseValidationItemPayload($this->fabricSubGroup->id);
    $payload['yarn_name_id'] = $this->yarnName->id;

    $response = $this->postJson(route('item.store'), $payload);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['yarn_name_id']);
});

it('rejects a fabric_name_id on an item with an unrelated subgroup', function () {
    $payload = baseValidationItemPayload($this->otherSubGroup->id);
    $payload['fabric_name_id'] = $this->fabricName->id;

    $response = $this->postJson(route('item.store'), $payload);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['fabric_name_id']);
});

it('accepts a fabric_name_id on an item whose subgroup is Tela', function () {
    $payload = baseValidationItemPayload($this->fabricSubGroup->id);
    $payload['fabric_name_id'] = $this->fabricName->id;

    $response = $this->postJson(route('item.store'), $payload);

    $response->assertCreated();

    $item = Item::where('reference', $payload['reference'])->firstOrFail();
    expect($item->fabric_name_id)->toBe($this->fabricName->id);
});

it('accepts a yarn_name_id on an item whose subgroup is Hilaza', function () {
    $payload = baseValidationItemPayload($this->yarnSubGroup->id);
    $payload['yarn_name_id'] = $this->yarnName->id;

    $response = $this->postJson(route('item.store'), $payload);

    $response->assertCreated();

    $item = Item::where('reference', $payload['reference'])->firstOrFail();
    expect($item->yarn_name_id)->toBe($this->yarnName->id);
});

it('clears fabric_name_id when an existing item changes to a non-fabric subgroup', function () {
    $item = Item::create([
        'category_sub_group_id' => $this->fabricSubGroup->id,
        'name' => 'Item to reclassify',
        'description' => 'Item de prueba',
        'reference' => 'REF-RECLASSIFY-'.uniqid(),
        'fabric_name_id' => $this->fabricName->id,
        'status' => true,
        'tax' => 0,
    ]);

    $payload = baseValidationItemPayload($this->otherSubGroup->id);
    $payload['reference'] = $item->reference;

    $response = $this->putJson(route('item.update', ['id' => $item->id]), $payload);

    $response->assertOk();

    $item->refresh();
    expect($item->fabric_name_id)->toBeNull();
});
