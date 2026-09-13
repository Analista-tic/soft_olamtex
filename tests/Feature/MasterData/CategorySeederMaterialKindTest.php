<?php

use App\Models\MasterData\CategoryGroup;
use App\Models\MasterData\CategorySubGroup;
use App\Support\MasterData\MaterialClassifier;
use Database\Seeders\CategorySeeder;

/**
 * ItemFormContent.vue decide si el formulario de ítem muestra el selector de base de
 * tela o de hilaza mirando material_kind del subgrupo "TELA"/"HILAZA" (MATERIA PRIMA).
 * Antes CategorySeeder los insertaba sin material_kind, y la migración que debía
 * corregirlo (por id hardcodeado) corría antes de que estos subgrupos existieran, así
 * que el backfill nunca aplicaba en una instalación nueva — la condición de tela/hilaza
 * nunca aparecía en el formulario.
 */
it('classifies the seeded TELA and HILAZA subgroups under MATERIA PRIMA with the right material_kind', function () {
    MaterialClassifier::forgetCache();

    $this->seed(CategorySeeder::class);

    $materiaPrima = CategoryGroup::where('name', 'MATERIA PRIMA')->firstOrFail();
    $tela = CategorySubGroup::where('category_group_id', $materiaPrima->id)->where('name', 'TELA')->firstOrFail();
    $hilaza = CategorySubGroup::where('category_group_id', $materiaPrima->id)->where('name', 'HILAZA')->firstOrFail();

    expect($tela->material_kind)->toBe('fabric')
        ->and($hilaza->material_kind)->toBe('yarn')
        ->and(MaterialClassifier::isFabricSubgroup($tela))->toBeTrue()
        ->and(MaterialClassifier::isYarnSubgroup($hilaza))->toBeTrue();

    // Los subgrupos "HILAZA"/"HILO" de otros grupos (INSUMOS, etc.) no son
    // clasificación de materia prima y deben quedar sin tocar.
    $otherHilaza = CategorySubGroup::where('name', 'HILAZA')->where('category_group_id', '!=', $materiaPrima->id)->first();
    if ($otherHilaza) {
        expect($otherHilaza->material_kind)->toBeNull();
    }
});
