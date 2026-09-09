<?php

use App\Models\MasterData\CategoryGroup;
use App\Models\MasterData\CategorySubGroup;
use App\Models\MasterData\Color;
use App\Models\MasterData\Item;
use App\Models\Settings\User;
use App\Models\Textil\FabricRoll;
use Spatie\Permission\Models\Permission;

/**
 * El listado de "Inventario tela por rollos" muestra, por color, el nombre resuelto
 * desde el catálogo (colors.name vía el ítem vinculado) con prioridad sobre el texto
 * libre internal_color_name/supplier_color (ver FabricRollController::inventoryRollsData,
 * líneas 493-506). El buscador debía cruzar únicamente ese texto libre, dejando
 * imposible encontrar un rollo por el color que realmente se ve en pantalla — corregido
 * en FabricRollService::inventoryWithFilters().
 */
beforeEach(function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('ver telas rollos', 'web'));
    $this->actingAs($user);
});

function makeSearchTestItem(string $suffix, ?int $colorId = null): Item
{
    $group = CategoryGroup::firstOrCreate(['name' => 'MATERIA PRIMA SEARCH TEST']);
    $subGroup = CategorySubGroup::firstOrCreate([
        'name' => 'Tela Search Test',
        'category_group_id' => $group->id,
    ]);

    return Item::create([
        'category_sub_group_id' => $subGroup->id,
        'name' => 'JERSEY ALGODÓN '.$suffix,
        'description' => 'Item de prueba de búsqueda '.$suffix,
        'color_id' => $colorId,
        'status' => true,
    ]);
}

it('finds a roll by its catalog color name, not just the free-text color fields', function () {
    $color = Color::create(['name' => 'AZUL REY BUSQUEDA']);
    $item = makeSearchTestItem('color-catalogo', $color->id);

    $roll = FabricRoll::create([
        'roll_number' => 'RL-SEARCH-COLOR-1',
        'item_id' => $item->id,
        // El texto libre es deliberadamente distinto del color de catálogo, para
        // aislar que el match viene del catálogo y no de estas columnas.
        'internal_color_name' => 'texto libre sin relación',
        'quantity' => 100,
        'entry_date' => now()->toDateString(),
        'approval_status' => FabricRoll::APPROVAL_APPROVED,
    ]);

    $response = $this->getJson(route('kardex-telas.inventario-rollos.data', ['search' => 'AZUL REY BUSQUEDA']));
    $response->assertOk();

    $rollNumbers = collect($response->json('data'))->pluck('roll');
    expect($rollNumbers)->toContain($roll->roll_number);
});

it('finds a roll by its fabric description when it has no item or purchase document linked', function () {
    $roll = FabricRoll::create([
        'roll_number' => 'RL-SEARCH-DESC-1',
        'item_id' => null,
        'description' => 'DRIL ESPECIAL SIN ITEM VINCULADO',
        'quantity' => 100,
        'entry_date' => now()->toDateString(),
        'approval_status' => FabricRoll::APPROVAL_APPROVED,
    ]);

    $response = $this->getJson(route('kardex-telas.inventario-rollos.data', ['search' => 'DRIL ESPECIAL']));
    $response->assertOk();

    $rollNumbers = collect($response->json('data'))->pluck('roll');
    expect($rollNumbers)->toContain($roll->roll_number);
});
