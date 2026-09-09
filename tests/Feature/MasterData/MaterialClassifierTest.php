<?php

use App\Models\MasterData\CategoryGroup;
use App\Models\MasterData\CategorySubGroup;
use App\Models\MasterData\Item;
use App\Support\MasterData\MaterialClassifier;

/**
 * Test de paridad (Fase 3 del refactor de materiales): antes la clasificación se leía
 * de config('master_data.fabric_subgroup_ids'/'yarn_subgroup_ids'); ahora se lee de la
 * columna category_sub_groups.material_kind. El comportamiento esperado no cambia —
 * este archivo prueba exactamente los mismos casos (Tela→fabric, Hilaza Materia
 * Prima→yarn, Hilo→ninguno, Hilaza Insumos duplicada→ninguno) contra datos reales en
 * vez de config simulada.
 */
beforeEach(function () {
    MaterialClassifier::forgetCache();

    $group = CategoryGroup::firstOrCreate(['name' => 'MATERIA PRIMA CLASSIFIER TEST']);

    $this->tela = CategorySubGroup::create(['name' => 'Tela Classifier Test', 'category_group_id' => $group->id, 'material_kind' => 'fabric']);
    $this->hilazaMateriaPrima = CategorySubGroup::create(['name' => 'Hilaza Classifier Test', 'category_group_id' => $group->id, 'material_kind' => 'yarn']);
    $this->hilo = CategorySubGroup::create(['name' => 'Hilo Classifier Test', 'category_group_id' => $group->id, 'material_kind' => null]);

    $insumosGroup = CategoryGroup::firstOrCreate(['name' => 'INSUMOS CLASSIFIER TEST']);
    $this->hilazaInsumos = CategorySubGroup::create(['name' => 'Hilaza Classifier Test', 'category_group_id' => $insumosGroup->id, 'material_kind' => null]);
});

it('classifies a subgroup with material_kind=fabric as fabric and nothing else', function () {
    expect(MaterialClassifier::isFabricSubgroup($this->tela))->toBeTrue()
        ->and(MaterialClassifier::isFabricSubgroup($this->hilazaMateriaPrima))->toBeFalse()
        ->and(MaterialClassifier::isFabricSubgroup($this->hilo))->toBeFalse()
        ->and(MaterialClassifier::isFabricSubgroup($this->hilazaInsumos))->toBeFalse()
        ->and(MaterialClassifier::isFabricSubgroup(null))->toBeFalse();
});

it('classifies a subgroup with material_kind=yarn as yarn, ignoring a duplicate name left as null', function () {
    expect(MaterialClassifier::isYarnSubgroup($this->hilazaMateriaPrima))->toBeTrue()
        ->and(MaterialClassifier::isYarnSubgroup($this->hilazaInsumos))->toBeFalse()
        ->and(MaterialClassifier::isYarnSubgroup($this->hilo))->toBeFalse()
        ->and(MaterialClassifier::isYarnSubgroup($this->tela))->toBeFalse()
        ->and(MaterialClassifier::isYarnSubgroup(null))->toBeFalse();
});

it('classifies items by their category_sub_group_id the same way', function () {
    $fabricItem = new Item(['category_sub_group_id' => $this->tela->id]);
    $yarnItem = new Item(['category_sub_group_id' => $this->hilazaMateriaPrima->id]);
    $otherItem = new Item(['category_sub_group_id' => $this->hilo->id]);

    expect(MaterialClassifier::isFabricItem($fabricItem))->toBeTrue()
        ->and(MaterialClassifier::isYarnItem($fabricItem))->toBeFalse()
        ->and(MaterialClassifier::isYarnItem($yarnItem))->toBeTrue()
        ->and(MaterialClassifier::isFabricItem($yarnItem))->toBeFalse()
        ->and(MaterialClassifier::isFabricItem($otherItem))->toBeFalse()
        ->and(MaterialClassifier::isYarnItem($otherItem))->toBeFalse()
        ->and(MaterialClassifier::isFabricItem(null))->toBeFalse();
});

it('exposes isFabric/isYarn helpers directly on the Item model', function () {
    $fabricItem = new Item(['category_sub_group_id' => $this->tela->id]);
    $yarnItem = new Item(['category_sub_group_id' => $this->hilazaMateriaPrima->id]);

    expect($fabricItem->isFabric())->toBeTrue()
        ->and($fabricItem->isYarn())->toBeFalse()
        ->and($yarnItem->isYarn())->toBeTrue()
        ->and($yarnItem->isFabric())->toBeFalse();
});

it('picks up a reclassification without restarting the process, once the cache is invalidated', function () {
    expect(MaterialClassifier::isFabricSubgroup($this->hilo))->toBeFalse();

    $this->hilo->update(['material_kind' => 'fabric']);
    MaterialClassifier::forgetCache();

    expect(MaterialClassifier::isFabricSubgroup($this->hilo->fresh()))->toBeTrue();
});
