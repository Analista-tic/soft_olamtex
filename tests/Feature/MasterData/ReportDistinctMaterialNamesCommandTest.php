<?php

use App\Models\MasterData\CategoryGroup;
use App\Models\MasterData\CategorySubGroup;
use App\Models\MasterData\Item;
use App\Support\MasterData\MaterialClassifier;
use Illuminate\Support\Facades\DB;

beforeEach(fn () => MaterialClassifier::forgetCache());

it('reports distinct fabric names with usage counts and a base suggestion from roll history', function () {
    $group = CategoryGroup::firstOrCreate(['name' => 'MATERIA PRIMA TEST REPORT']);
    $subGroup = CategorySubGroup::updateOrCreate(
        ['name' => 'Tela Test Report', 'category_group_id' => $group->id],
        ['material_kind' => 'fabric'],
    );

    Item::create([
        'category_sub_group_id' => $subGroup->id,
        'name' => 'Item tela 1',
        'description' => 'Item de prueba',
        'extra_fields' => ['Base tela' => 'MILE'],
        'status' => true,
    ]);
    Item::create([
        'category_sub_group_id' => $subGroup->id,
        'name' => 'Item tela 2',
        'description' => 'Item de prueba',
        'extra_fields' => ['Base tela' => 'mile'],
        'status' => true,
    ]);
    Item::create([
        'category_sub_group_id' => $subGroup->id,
        'name' => 'Item tela 3',
        'description' => 'Item de prueba',
        'extra_fields' => ['Base tela' => 'Valentina'],
        'status' => true,
    ]);

    DB::table('fabric_rolls')->insert([
        'roll_number' => 'RL-REPORT-1',
        'description' => 'DENIM - MILE',
        'quantity' => 10,
        'entry_date' => now()->toDateString(),
        'approval_status' => 'approved',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->artisan('master-data:report-distinct-material-names')
        ->expectsOutputToContain('Nombres distintos de Tela encontrados (2):')
        ->assertExitCode(0);
});

it('reports nothing for a kind with no matching items', function () {
    // Sin ningún subgrupo con material_kind='fabric'/'yarn' en esta prueba, no hay
    // nada que clasificar como tela u hilaza — mismo efecto que antes se lograba
    // apuntando la config a ids inexistentes.
    $this->artisan('master-data:report-distinct-material-names')
        ->expectsOutputToContain('No hay valores de "Base tela"')
        ->expectsOutputToContain('No hay valores de "Base hilaza"')
        ->assertExitCode(0);
});
