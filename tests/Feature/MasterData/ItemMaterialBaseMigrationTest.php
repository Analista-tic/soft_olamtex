<?php

use App\Models\MasterData\CategoryGroup;
use App\Models\MasterData\CategorySubGroup;
use App\Models\MasterData\FabricName;
use App\Models\MasterData\Item;
use App\Models\Production\FabricBase;
use App\Support\MasterData\MaterialClassifier;

function makeMaterialBaseMigrationSubGroup(string $name): CategorySubGroup
{
    $group = CategoryGroup::firstOrCreate(['name' => 'MATERIA PRIMA MIGRATION TEST']);

    return CategorySubGroup::updateOrCreate(
        ['name' => $name, 'category_group_id' => $group->id],
        ['material_kind' => 'fabric'],
    );
}

beforeEach(function () {
    MaterialClassifier::forgetCache();
    $this->subGroup = makeMaterialBaseMigrationSubGroup('Tela Migration Test');

    $this->fabricBase = FabricBase::create([
        'codigo' => 'BT-MIGRATION-TEST',
        'nombre' => 'ALGODÓN MIGRATION TEST',
        'factor' => 1.2,
        'is_active' => true,
    ]);

    $this->fabricName = FabricName::create([
        'nombre' => 'JERSEY MIGRATION',
        'fabric_base_id' => $this->fabricBase->id,
        'is_active' => true,
    ]);
});

it('matches an item by exact normalized text against the fabric names catalog', function () {
    Item::create([
        'category_sub_group_id' => $this->subGroup->id,
        'name' => 'Item exact match',
        'description' => 'Item de prueba',
        'extra_fields' => ['Base tela' => 'JERSEY MIGRATION'],
        'status' => true,
    ]);

    $this->artisan('master-data:migrate-item-material-bases')
        ->expectsOutputToContain('[tela] Coincidencias: 1 (aplicado)')
        ->assertExitCode(0);

    $item = Item::where('name', 'Item exact match')->firstOrFail();
    expect($item->fabric_name_id)->toBe($this->fabricName->id);
});

it('matches an item regardless of accents, case and extra whitespace', function () {
    Item::create([
        'category_sub_group_id' => $this->subGroup->id,
        'name' => 'Item accent match',
        'description' => 'Item de prueba',
        'extra_fields' => ['Base tela' => '  jérsey   migration  '],
        'status' => true,
    ]);

    $this->artisan('master-data:migrate-item-material-bases')
        ->expectsOutputToContain('[tela] Coincidencias: 1 (aplicado)')
        ->assertExitCode(0);

    $item = Item::where('name', 'Item accent match')->firstOrFail();
    expect($item->fabric_name_id)->toBe($this->fabricName->id);
});

it('reports items with no catalog match instead of guessing, and never assigns them', function () {
    Item::create([
        'category_sub_group_id' => $this->subGroup->id,
        'name' => 'Item without match',
        'description' => 'Item de prueba',
        'extra_fields' => ['Base tela' => 'NOMBRE INEXISTENTE'],
        'status' => true,
    ]);

    $this->artisan('master-data:migrate-item-material-bases')
        ->expectsOutputToContain('[tela] Coincidencias: 0 (aplicado)')
        ->expectsOutputToContain('NOMBRE INEXISTENTE')
        ->assertExitCode(0);

    $item = Item::where('name', 'Item without match')->firstOrFail();
    expect($item->fabric_name_id)->toBeNull()
        ->and($item->extra_fields['Base tela'])->toBe('NOMBRE INEXISTENTE');
});

it('does not write anything in dry-run mode', function () {
    Item::create([
        'category_sub_group_id' => $this->subGroup->id,
        'name' => 'Item dry run',
        'description' => 'Item de prueba',
        'extra_fields' => ['Base tela' => 'JERSEY MIGRATION'],
        'status' => true,
    ]);

    $this->artisan('master-data:migrate-item-material-bases --dry-run')
        ->expectsOutputToContain('[tela] Coincidencias: 1 (dry-run, no se escribió nada)')
        ->assertExitCode(0);

    $item = Item::where('name', 'Item dry run')->firstOrFail();
    expect($item->fabric_name_id)->toBeNull();
});

it('moves the legacy value to extra_fields._legacy instead of leaving it duplicated', function () {
    Item::create([
        'category_sub_group_id' => $this->subGroup->id,
        'name' => 'Item legacy move',
        'description' => 'Item de prueba',
        'extra_fields' => ['Base tela' => 'JERSEY MIGRATION', 'Otro Campo' => 'Se conserva'],
        'status' => true,
    ]);

    $this->artisan('master-data:migrate-item-material-bases')
        ->expectsOutputToContain('[tela] Coincidencias: 1 (aplicado)')
        ->assertExitCode(0);

    $item = Item::where('name', 'Item legacy move')->firstOrFail();
    expect($item->fabric_name_id)->toBe($this->fabricName->id)
        ->and($item->extra_fields)->not->toHaveKey('Base tela')
        ->and($item->extra_fields['_legacy']['Base tela'])->toBe('JERSEY MIGRATION')
        ->and($item->extra_fields['Otro Campo'])->toBe('Se conserva');
});

it('preserves extra_fields._legacy when the item is edited from the form afterwards', function () {
    // El formulario nunca carga '_legacy' en su estado editable (es una clave
    // oculta), así que reenvía extra_fields sin ella en cualquier edición normal.
    // Sin el merge explícito en ItemService, el histórico se perdería aquí.
    $user = App\Models\Settings\User::factory()->create();
    foreach (['ver catalogo items', 'crear items', 'editar items'] as $permission) {
        $user->givePermissionTo(Spatie\Permission\Models\Permission::findOrCreate($permission, 'web'));
    }
    $this->actingAs($user);

    $item = Item::create([
        'category_sub_group_id' => $this->subGroup->id,
        'name' => 'Item legacy preserve',
        'description' => 'Item de prueba',
        'reference' => 'REF-LEGACY-PRESERVE-'.uniqid(),
        'fabric_name_id' => $this->fabricName->id,
        'extra_fields' => ['_legacy' => ['Base tela' => 'JERSEY MIGRATION']],
        'status' => true,
        'tax' => 0,
    ]);

    // Payload tal como lo mandaría el formulario real: sin '_legacy', porque nunca
    // lo mostró ni lo cargó en su estado.
    $response = $this->putJson(route('item.update', ['id' => $item->id]), [
        'category_sub_group_id' => $this->subGroup->id,
        'color_id' => \App\Models\MasterData\Color::firstOrCreate(['name' => 'COLOR MIGRACION MATERIAL TEST'])->id,
        'fabric_name_id' => $this->fabricName->id,
        'reference' => $item->reference,
        'description' => 'Item de prueba editado',
        'extra_fields' => [],
        'tax' => 0,
        'negative_movement' => false,
        'item_type' => 'product',
    ]);

    $response->assertOk();

    $item->refresh();
    expect($item->extra_fields['_legacy']['Base tela'])->toBe('JERSEY MIGRATION');
});

it('does not touch items outside the fabric/yarn classification', function () {
    $otherGroup = CategoryGroup::firstOrCreate(['name' => 'INSUMOS MIGRATION TEST']);
    $otherSubGroup = CategorySubGroup::firstOrCreate([
        'name' => 'Botón Migration Test',
        'category_group_id' => $otherGroup->id,
    ]);

    Item::create([
        'category_sub_group_id' => $otherSubGroup->id,
        'name' => 'Item boton',
        'description' => 'Item de prueba',
        'extra_fields' => ['Base tela' => 'JERSEY MIGRATION'],
        'status' => true,
    ]);

    $this->artisan('master-data:migrate-item-material-bases')
        ->expectsOutputToContain('[tela] Coincidencias: 0 (aplicado)')
        ->assertExitCode(0);

    $item = Item::where('name', 'Item boton')->firstOrFail();
    expect($item->fabric_name_id)->toBeNull();
});
