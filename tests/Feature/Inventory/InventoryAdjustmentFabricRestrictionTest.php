<?php

use App\Models\Inventory\Warehouse;
use App\Models\MasterData\CategoryGroup;
use App\Models\MasterData\CategorySubGroup;
use App\Models\MasterData\Item;
use App\Models\Settings\User;

/**
 * Un ítem de tipo Tela no tiene inventario propio: su disponibilidad depende de los
 * rollos vinculados (FabricRoll), no de una cantidad en bodega. Ajustarlo desde el
 * módulo general de Ajuste de Inventario no tendría forma de saber qué rollo afectar
 * — ese ajuste debe hacerse desde Inventario tela por rollos.
 */
beforeEach(function () {
    $this->actingAs(User::factory()->create());

    $group = CategoryGroup::firstOrCreate(['name' => 'MATERIA PRIMA ADJUSTMENT TEST']);

    $this->fabricSubGroup = CategorySubGroup::create([
        'name' => 'Tela Adjustment Test',
        'category_group_id' => $group->id,
        'material_kind' => 'fabric',
    ]);

    $this->regularSubGroup = CategorySubGroup::create([
        'name' => 'Insumo Adjustment Test',
        'category_group_id' => $group->id,
        'material_kind' => null,
    ]);

    $this->warehouse = Warehouse::create(['name' => 'Bodega Adjustment Test', 'observation' => 'Bodega de prueba']);
});

function makeAdjustmentTestItem(int $categorySubGroupId, string $name): Item
{
    return Item::create([
        'category_sub_group_id' => $categorySubGroupId,
        'name' => $name,
        'description' => 'Item de prueba para ajuste de inventario',
        'status' => true,
        'tracks_inventory' => true,
    ]);
}

function adjustmentPayload(int $itemId, int $warehouseId): array
{
    return [
        // El formulario siempre envía un valor (autogenerado o manual); se fija aquí
        // para no ejercitar el caso de payload sin la clave en absoluto, que es un
        // problema aparte y preexistente en InventoryAdjustmentService::preparePayload().
        'document_number' => null,
        'date' => now()->toDateString(),
        'type' => 'entrada',
        'movement_reason' => 'entry_regularization',
        'lines' => [
            [
                'item_id' => $itemId,
                'warehouse_id' => $warehouseId,
                'price' => 1000,
                'tax' => 0,
                'quantity' => 5,
                'movement_reason' => 'entry_regularization',
                'description' => 'Ajuste de prueba',
            ],
        ],
    ];
}

it('rejects an inventory adjustment line for a fabric-type item', function () {
    $fabricItem = makeAdjustmentTestItem($this->fabricSubGroup->id, 'Tela de prueba');

    $response = $this->postJson(route('ajustes-inventario.store'), adjustmentPayload($fabricItem->id, $this->warehouse->id));

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['lines.0.item_id']);
    expect($response->json('errors')['lines.0.item_id'][0])->toContain('tipo Tela');
});

it('does not flag a non-fabric item with the fabric-type validation rule (regression)', function () {
    // No se llega a assertCreated(): crear el asiento contable de un ajuste requiere
    // configurar cuentas contables en el ítem, que es un requisito preexistente y
    // ajeno a esta regla — lo que importa verificar aquí es que la regla nueva
    // (MaterialClassifier::isFabricItem()) no bloquee items que no son tela.
    $regularItem = makeAdjustmentTestItem($this->regularSubGroup->id, 'Insumo de prueba');

    $response = $this->postJson(route('ajustes-inventario.store'), adjustmentPayload($regularItem->id, $this->warehouse->id));

    $response->assertJsonMissingValidationErrors(['lines.0.item_id']);
});
