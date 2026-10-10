<?php

use App\Models\MasterData\CategoryGroup;
use App\Models\MasterData\CategorySubGroup;
use App\Models\MasterData\Color;
use App\Models\MasterData\Item;
use App\Models\Settings\User;
use Spatie\Permission\Models\Permission;

/**
 * Fase 13 del refactor de materiales: al crear un ítem manualmente se puede elegir
 * referencia automática (consecutivo(5)+código de grupo(2)+código de subgrupo(2)+
 * código de color(3)) o manual (texto libre de siempre, con la misma validación de
 * unicidad). Es exclusivo del alta manual: nunca compite con el patrón ref-color-talla
 * de productos terminados generados desde curvas/remisión (flujo aparte, no tocado).
 */
beforeEach(function () {
    $group = CategoryGroup::firstOrCreate(['name' => 'MATERIA PRIMA AUTO REF TEST'], ['code' => '09']);
    if ($group->code !== '09') {
        $group->update(['code' => '09']);
    }

    $this->subGroup = CategorySubGroup::updateOrCreate(
        ['name' => 'Subgrupo Auto Ref Test', 'category_group_id' => $group->id],
        ['code' => '05'],
    );
    $this->group = $group;

    $this->color = Color::create(['name' => 'ROJO AUTO REF TEST', 'code' => '77']);

    $user = User::factory()->create();
    foreach (['ver catalogo items', 'crear items', 'editar items', 'crear referencia manual item'] as $permission) {
        $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }
    $this->user = $user;
    $this->actingAs($user);
});

it('keeps manual reference working exactly as before when reference_mode is manual', function () {
    $response = $this->postJson(route('item.store'), [
        'category_sub_group_id' => $this->subGroup->id,
        'color_id' => $this->color->id,
        'reference' => 'REF-MANUAL-'.uniqid(),
        'reference_mode' => 'manual',
        'description' => 'Item de prueba manual',
        'tax' => 0,
        'negative_movement' => false,
        'item_type' => 'product',
    ]);

    $response->assertCreated();
    expect($response->json('reference'))->toStartWith('REF-MANUAL-');
});

it('generates an automatic reference with the consecutive+group+subgroup+color format', function () {
    $response = $this->postJson(route('item.store'), [
        'category_sub_group_id' => $this->subGroup->id,
        'color_id' => $this->color->id,
        'reference_mode' => 'auto',
        'description' => 'Item de prueba automatico',
        'tax' => 0,
        'negative_movement' => false,
        'item_type' => 'product',
    ]);

    $response->assertCreated();
    $reference = $response->json('reference');

    expect($reference)->toHaveLength(12)
        ->and(substr($reference, 5, 2))->toBe('09')
        ->and(substr($reference, 7, 2))->toBe('05')
        ->and(substr($reference, 9, 3))->toBe('077');
});

it('assigns different consecutive numbers to two automatic references created back to back', function () {
    $first = $this->postJson(route('item.store'), [
        'category_sub_group_id' => $this->subGroup->id,
        'color_id' => $this->color->id,
        'reference_mode' => 'auto',
        'description' => 'Item auto 1',
        'tax' => 0,
        'negative_movement' => false,
        'item_type' => 'product',
    ])->json('reference');

    $second = $this->postJson(route('item.store'), [
        'category_sub_group_id' => $this->subGroup->id,
        'color_id' => $this->color->id,
        'reference_mode' => 'auto',
        'description' => 'Item auto 2',
        'tax' => 0,
        'negative_movement' => false,
        'item_type' => 'product',
    ])->json('reference');

    expect($first)->not->toBe($second)
        ->and(substr($first, 0, 5))->not->toBe(substr($second, 0, 5));
});

it('rejects an automatic reference when the subgroup has no code assigned', function () {
    $subGroupWithoutCode = CategorySubGroup::create([
        'name' => 'Subgrupo Sin Codigo Test',
        'category_group_id' => $this->group->id,
    ]);

    $response = $this->postJson(route('item.store'), [
        'category_sub_group_id' => $subGroupWithoutCode->id,
        'color_id' => $this->color->id,
        'reference_mode' => 'auto',
        'description' => 'Item sin codigo',
        'tax' => 0,
        'negative_movement' => false,
        'item_type' => 'product',
    ]);

    $response->assertStatus(422);
    expect($response->json('errors.reference_mode.0'))->toContain('código del subgrupo');
});

it('rejects an automatic reference when no color was selected', function () {
    $response = $this->postJson(route('item.store'), [
        'category_sub_group_id' => $this->subGroup->id,
        'reference_mode' => 'auto',
        'description' => 'Item sin color',
        'tax' => 0,
        'negative_movement' => false,
        'item_type' => 'product',
    ]);

    // El color es obligatorio para todo ítem (lo exige la validación antes de generar la referencia).
    $response->assertStatus(422)->assertJsonValidationErrors('color_id');
    expect(\App\Models\MasterData\Item::where('description', 'Item sin color')->exists())->toBeFalse();
});

it('never regenerates the reference on update even if reference_mode=auto is sent', function () {
    $item = Item::create([
        'category_sub_group_id' => $this->subGroup->id,
        'name' => 'ITEM EXISTENTE AUTO REF TEST',
        'description' => 'Item existente',
        'reference' => 'REF-YA-EXISTE',
        'color_id' => $this->color->id,
        'status' => true,
        'tax' => 0,
    ]);

    $response = $this->putJson(route('item.update', $item->id), [
        'category_sub_group_id' => $this->subGroup->id,
        'color_id' => $this->color->id,
        'reference' => 'REF-YA-EXISTE',
        'reference_mode' => 'auto',
        'description' => 'Item existente editado',
        'tax' => 0,
        'negative_movement' => false,
        'item_type' => 'product',
    ]);

    $response->assertOk();
    expect($item->fresh()->reference)->toBe('REF-YA-EXISTE');
});

it('generates an automatic reference for an item using a color just created inline (no code sent by the client)', function () {
    foreach (['ver colores', 'crear colores'] as $permission) {
        $this->user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }

    $colorResponse = $this->postJson('/colors', [
        'name' => 'VERDE RECIEN CREADO TEST',
    ]);
    $colorResponse->assertCreated();
    $newColorId = $colorResponse->json('id');

    $response = $this->postJson(route('item.store'), [
        'category_sub_group_id' => $this->subGroup->id,
        'color_id' => $newColorId,
        'reference_mode' => 'auto',
        'description' => 'Item con color recien creado',
        'tax' => 0,
        'negative_movement' => false,
        'item_type' => 'product',
    ]);

    $response->assertCreated();
    $reference = $response->json('reference');

    expect($reference)->toHaveLength(12);
    expect(substr($reference, 9, 3))->toBe(Color::find($newColorId)->code);
});

it('rejects a manual reference from a user without the manual-reference permission', function () {
    $userWithoutPermission = User::factory()->create();
    foreach (['ver catalogo items', 'crear items'] as $permission) {
        $userWithoutPermission->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }
    $this->actingAs($userWithoutPermission);

    $response = $this->postJson(route('item.store'), [
        'category_sub_group_id' => $this->subGroup->id,
        'color_id' => $this->color->id,
        'reference' => 'REF-MANUAL-SIN-PERMISO-'.uniqid(),
        'reference_mode' => 'manual',
        'description' => 'Item de prueba sin permiso',
        'tax' => 0,
        'negative_movement' => false,
        'item_type' => 'product',
    ]);

    $response->assertStatus(422);
    expect($response->json('errors.reference_mode.0'))->toContain('permiso');
});

it('defaults to automatic reference when reference_mode is omitted, even for a user with manual permission', function () {
    $response = $this->postJson(route('item.store'), [
        'category_sub_group_id' => $this->subGroup->id,
        'color_id' => $this->color->id,
        'description' => 'Item sin reference_mode explicito',
        'tax' => 0,
        'negative_movement' => false,
        'item_type' => 'product',
    ]);

    $response->assertCreated();
    $reference = $response->json('reference');

    expect($reference)->toHaveLength(12)
        ->and(substr($reference, 5, 2))->toBe('09')
        ->and(substr($reference, 7, 2))->toBe('05')
        ->and(substr($reference, 9, 3))->toBe('077');
});

it('generates an automatic reference for items with the NO APLICA color and leaves it out of the name', function () {
    $notApplicable = Color::where('code', Color::NOT_APPLICABLE_CODE)->firstOrFail();

    $response = $this->postJson(route('item.store'), [
        'category_sub_group_id' => $this->subGroup->id,
        'color_id' => $notApplicable->id,
        'reference_mode' => 'auto',
        'description' => 'Item sin color',
        'tax' => 0,
        'negative_movement' => false,
        'item_type' => 'product',
    ]);

    $response->assertCreated();

    expect(substr($response->json('reference'), 9, 3))->toBe('000')
        ->and($response->json('name'))->not->toContain('NO APLICA');
});
