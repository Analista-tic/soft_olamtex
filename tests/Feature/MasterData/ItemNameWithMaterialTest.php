<?php

use App\Models\MasterData\CategoryGroup;
use App\Models\MasterData\CategorySubGroup;
use App\Models\MasterData\Color;
use App\Models\MasterData\FabricName;
use App\Models\MasterData\Item;
use App\Models\Production\FabricBase;
use App\Models\Settings\User;
use App\Services\MasterData\ItemService;
use Spatie\Permission\Models\Permission;

/**
 * Fase 4 del refactor de materiales: el nombre del ítem incluye ahora el material
 * entre el subgrupo y los extras — antes fabric_name_id/yarn_name_id no participaban
 * en el nombre en absoluto. Para tela el segmento es "nombre (base)"; para el resto
 * de materiales (ej. hilaza) se mantiene "base nombre" sin paréntesis.
 */
beforeEach(function () {
    $group = CategoryGroup::firstOrCreate(['name' => 'MATERIA PRIMA NAME TEST']);
    $this->fabricSubGroup = CategorySubGroup::updateOrCreate(
        ['name' => 'Tela Name Test', 'category_group_id' => $group->id],
        ['material_kind' => 'fabric'],
    );
    $this->otherSubGroup = CategorySubGroup::updateOrCreate(
        ['name' => 'Boton Name Test', 'category_group_id' => $group->id],
        ['material_kind' => null],
    );

    $this->fabricBase = FabricBase::create([
        'codigo' => 'BT-NAME-TEST',
        'nombre' => 'ALGODÓN',
        'factor' => 1.0,
        'is_active' => true,
    ]);
    $this->fabricName = FabricName::create([
        'nombre' => 'MILE',
        'fabric_base_id' => $this->fabricBase->id,
        'is_active' => true,
    ]);

    $this->color = Color::create(['name' => 'AZUL REY']);

    $user = User::factory()->create();
    foreach (['ver catalogo items', 'crear items', 'editar items', 'crear referencia manual item'] as $permission) {
        $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }
    $this->actingAs($user);
});

it('includes the material base and name between the subgroup and the color', function () {
    $response = $this->postJson(route('item.store'), [
        'category_sub_group_id' => $this->fabricSubGroup->id,
        'fabric_name_id' => $this->fabricName->id,
        'color_id' => $this->color->id,
        'reference' => 'REF-NAME-'.uniqid(),
        'reference_mode' => 'manual',
        'description' => 'Item de prueba',
        'tax' => 0,
        'negative_movement' => false,
        'item_type' => 'product',
    ]);

    $response->assertCreated();

    $item = Item::where('fabric_name_id', $this->fabricName->id)->firstOrFail();
    expect($item->name)->toBe('TELA NAME TEST MILE (ALGODÓN) AZUL REY');
});

it('keeps the previous name shape for an item of an unrelated subgroup', function () {
    $response = $this->postJson(route('item.store'), [
        'category_sub_group_id' => $this->otherSubGroup->id,
        'color_id' => $this->color->id,
        'reference' => 'REF-NAME-OTHER-'.uniqid(),
        'reference_mode' => 'manual',
        'description' => 'Item de prueba',
        'tax' => 0,
        'negative_movement' => false,
        'item_type' => 'product',
    ]);

    $response->assertCreated();

    $item = Item::where('reference', 'like', 'REF-NAME-OTHER-%')->firstOrFail();
    expect($item->name)->toBe('BOTON NAME TEST AZUL REY');
});

it('detects a duplicate name that only differs by the material through check-duplicate', function () {
    Item::create([
        'category_sub_group_id' => $this->fabricSubGroup->id,
        'name' => 'TELA NAME TEST MILE (ALGODÓN) AZUL REY',
        'description' => 'Item existente',
        'reference' => 'REF-DUP-EXISTING',
        'fabric_name_id' => $this->fabricName->id,
        'color_id' => $this->color->id,
        'status' => true,
        'tax' => 0,
    ]);

    $response = $this->getJson(route('item.check-duplicate', [
        'reference' => 'REF-DUP-NEW',
        'category_sub_group_id' => $this->fabricSubGroup->id,
        'fabric_name_id' => $this->fabricName->id,
        'color_id' => $this->color->id,
    ]));

    $response->assertOk();
    expect($response->json('duplicate_by_name'))->toBeTrue();
});

it('mirrors ItemService::buildName() through computeName() for the bulk-upload builder', function () {
    $name = app(ItemService::class)->computeName(
        $this->fabricSubGroup->id,
        [],
        $this->color->id,
        null,
        $this->fabricName->id,
        null,
    );

    expect($name)->toBe('TELA NAME TEST MILE (ALGODÓN) AZUL REY');
});
