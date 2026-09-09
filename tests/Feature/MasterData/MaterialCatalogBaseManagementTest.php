<?php

use App\Models\Production\FabricBase;
use App\Models\Production\ProductSheet;
use App\Models\Production\YarnBase;
use App\Models\Settings\User;
use Spatie\Permission\Models\Permission;

/**
 * Las bases de tela/hilaza se gestionaban antes desde
 * produccion.operaciones.catalog-management (catalog=yarn-bases|fabric-bases), dentro
 * del diálogo de catálogos operativos de Producción. La Fase 2 del refactor de
 * materiales las movió a una pantalla única en Datos Maestros
 * (catalogo-materiales.*), para no dejar dos APIs gestionando la misma tabla.
 */
beforeEach(function () {
    $user = User::factory()->create();
    foreach (['ver catalogo materiales', 'crear catalogo materiales', 'editar catalogo materiales', 'eliminar catalogo materiales'] as $permission) {
        $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }
    $this->actingAs($user);
});

it('creates, updates and deletes a yarn base through the catalog endpoints', function () {
    $create = $this->postJson(route('catalogo-materiales.store', ['catalog' => 'yarn-bases']), [
        'nombre' => 'algodón reciclado',
        'is_active' => true,
    ]);

    $create->assertCreated();
    $yarnBaseId = $create->json('data.id');

    expect(YarnBase::query()->find($yarnBaseId))
        ->not->toBeNull()
        ->nombre->toBe('ALGODÓN RECICLADO');

    $update = $this->putJson(route('catalogo-materiales.update', ['catalog' => 'yarn-bases', 'id' => $yarnBaseId]), [
        'nombre' => 'algodón reciclado premium',
        'is_active' => false,
    ]);

    $update->assertOk();

    $yarnBase = YarnBase::query()->findOrFail($yarnBaseId);
    expect($yarnBase->nombre)->toBe('ALGODÓN RECICLADO PREMIUM')
        ->and($yarnBase->is_active)->toBeFalse();

    $delete = $this->deleteJson(route('catalogo-materiales.destroy', ['catalog' => 'yarn-bases', 'id' => $yarnBaseId]));

    $delete->assertOk();
    expect(YarnBase::query()->find($yarnBaseId))->toBeNull();
});

it('rejects creating a yarn base without a name', function () {
    $response = $this->postJson(route('catalogo-materiales.store', ['catalog' => 'yarn-bases']), [
        'nombre' => '',
    ]);

    $response->assertStatus(422);
});

it('does not allow deleting a yarn base that is in use by a product sheet', function () {
    $yarnBase = YarnBase::create([
        'codigo' => 'BH-TEST-USE',
        'nombre' => 'BASE EN USO',
        'is_active' => true,
    ]);

    ProductSheet::create([
        'section_id' => 1,
        'group_id' => 1,
        'brand_id' => 1,
        'world_id' => 1,
        'yarn_base_id' => $yarnBase->id,
        'type' => 'producto',
        'billing_price' => 1000,
        'selling_price' => 1200,
        'sizes' => ['M'],
        'woven_product' => false,
        'description' => 'Ficha de prueba uso hilaza',
        'sample_reference' => 'MUESTRA-YB-001',
        'product_reference' => 'PRODUCTO-YB-001',
        'processes' => ['Corte'],
        'status' => 'complete',
    ]);

    $delete = $this->deleteJson(route('catalogo-materiales.destroy', ['catalog' => 'yarn-bases', 'id' => $yarnBase->id]));

    $delete->assertStatus(422);
    expect(YarnBase::query()->find($yarnBase->id))->not->toBeNull();
});

it('exposes yarn bases in the catalog index when filtering by type', function () {
    YarnBase::create([
        'codigo' => 'BH-TEST-INDEX',
        'nombre' => 'BASE INDEX',
        'is_active' => true,
    ]);

    $response = $this->getJson(route('catalogo-materiales.index', ['type' => 'yarn']));

    $response->assertOk();
    expect(collect($response->json('data'))->pluck('nombre'))->toContain('BASE INDEX');
});

it('hides the fabric base factor from a user without the ver factor material permission', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('ver catalogo materiales', 'web'));
    $this->actingAs($user);

    FabricBase::create([
        'codigo' => 'BT-FACTOR-TEST',
        'nombre' => 'BASE FACTOR TEST',
        'factor' => 1.5,
        'is_active' => true,
    ]);

    $response = $this->getJson(route('catalogo-materiales.index', ['type' => 'fabric']));

    $response->assertOk();
    $base = collect($response->json('data'))->firstWhere('nombre', 'BASE FACTOR TEST');
    expect($base)->not->toBeNull()
        ->and(array_key_exists('factor', $base))->toBeFalse();
});

it('exposes the fabric base factor to a user with the ver factor material permission', function () {
    FabricBase::create([
        'codigo' => 'BT-FACTOR-TEST-2',
        'nombre' => 'BASE FACTOR TEST 2',
        'factor' => 1.5,
        'is_active' => true,
    ]);

    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('ver catalogo materiales', 'web'));
    $user->givePermissionTo(Permission::findOrCreate('ver factor material', 'web'));
    $this->actingAs($user);

    $response = $this->getJson(route('catalogo-materiales.index', ['type' => 'fabric']));

    $response->assertOk();
    $base = collect($response->json('data'))->firstWhere('nombre', 'BASE FACTOR TEST 2');
    expect($base['factor'])->toBe(1.5);
});

it('previews how many sheets a fabric base factor change would affect, without writing anything', function () {
    $fabricBase = FabricBase::create([
        'codigo' => 'BT-IMPACT-TEST',
        'nombre' => 'BASE IMPACT TEST',
        'factor' => 1.0,
        'is_active' => true,
    ]);

    ProductSheet::create([
        'section_id' => 1, 'group_id' => 1, 'brand_id' => 1, 'world_id' => 1,
        'fabric_base_id' => $fabricBase->id,
        'type' => 'producto', 'billing_price' => 1000, 'selling_price' => 1200,
        'sizes' => ['M'], 'woven_product' => false,
        'description' => 'Ficha de prueba impacto', 'sample_reference' => 'MUESTRA-IMPACT-001',
        'product_reference' => 'PRODUCTO-IMPACT-001', 'processes' => ['Corte'], 'status' => 'complete',
    ]);

    $response = $this->getJson(route('catalogo-materiales.factor-impact', ['fabricBase' => $fabricBase->id]));

    $response->assertOk();
    expect($response->json('data.sheets'))->toBe(1)
        ->and($response->json('data.order_operations'))->toBe(0)
        ->and($fabricBase->fresh()->factor)->toBe(1.0);
});

it('reports how many sheets and order operations were recalculated in the update response', function () {
    $fabricBase = FabricBase::create([
        'codigo' => 'BT-UPDATE-PROP-TEST',
        'nombre' => 'BASE UPDATE PROP TEST',
        'factor' => 1.0,
        'is_active' => true,
    ]);

    ProductSheet::create([
        'section_id' => 1, 'group_id' => 1, 'brand_id' => 1, 'world_id' => 1,
        'fabric_base_id' => $fabricBase->id,
        'type' => 'producto', 'billing_price' => 1000, 'selling_price' => 1200,
        'sizes' => ['M'], 'woven_product' => false,
        'description' => 'Ficha de prueba update prop', 'sample_reference' => 'MUESTRA-UPDATE-PROP-001',
        'product_reference' => 'PRODUCTO-UPDATE-PROP-001', 'processes' => ['Corte'], 'status' => 'complete',
    ]);

    $response = $this->putJson(route('catalogo-materiales.update', ['catalog' => 'fabric-bases', 'id' => $fabricBase->id]), [
        'nombre' => 'BASE UPDATE PROP TEST',
        'factor' => 1.75,
        'is_active' => true,
    ]);

    $response->assertOk();
    expect($response->json('factor_propagation.sheets'))->toBe(1)
        ->and($response->json('factor_propagation.order_operations'))->toBe(0)
        ->and($response->json('message'))->toContain('Se recalcularon');
});

it('reports no factor propagation in the update response when the factor did not change', function () {
    $fabricBase = FabricBase::create([
        'codigo' => 'BT-NO-PROP-TEST',
        'nombre' => 'BASE NO PROP TEST',
        'factor' => 1.0,
        'is_active' => true,
    ]);

    $response = $this->putJson(route('catalogo-materiales.update', ['catalog' => 'fabric-bases', 'id' => $fabricBase->id]), [
        'nombre' => 'BASE NO PROP TEST RENOMBRADA',
        'factor' => 1.0,
        'is_active' => true,
    ]);

    $response->assertOk();
    expect($response->json('factor_propagation'))->toBeNull();
});
