<?php

use App\Models\Production\Operation;
use App\Models\Production\ProductOperationList;
use App\Models\Production\ProductSheet;
use App\Models\Settings\User;
use Spatie\Permission\Models\Permission;

/**
 * Guardar el listado desde Ficha técnica / Control de lotes usa el mismo `sync` que la página de Listados:
 * con el id de cada línea se actualiza en su sitio (antes se borraba todo y el listado quedaba vacío).
 */
beforeEach(function () {
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('editar listados operacionales', 'web'));
    $this->actingAs($user);

    $this->sheet = ProductSheet::create([
        'section_id' => 1, 'group_id' => 1, 'brand_id' => 1, 'world_id' => 1,
        'type' => 'producto', 'billing_price' => 1000, 'selling_price' => 1200,
        'sizes' => ['M' => true], 'woven_product' => false, 'description' => 'Ficha sync',
        'sample_reference' => 'MUESTRA-SYNC', 'product_reference' => 'PRODUCTO-SYNC',
        'processes' => ['Corte'], 'status' => 'incomplete',
    ]);
    $this->operations = collect(['PEGAR', 'CERRAR'])->map(fn ($action) => Operation::create([
        'action' => $action, 'garment_part' => 'MANGA SYNC', 'sam_base' => 1.0, 'quantity' => 1, 'is_active' => true,
    ]));
    $this->lines = $this->operations->values()->map(fn ($operation, $i) => ProductOperationList::create([
        'product_sheet_id' => $this->sheet->id, 'operation_id' => $operation->id, 'sequence' => ($i + 1) * 10,
        'sam_base' => 1.0, 'sam' => 1.0, 'optimal_time' => 1.0,
        'operator_efficiency_factor' => 1.0, 'machine_supplement_factor' => 1.0, 'fabric_base_factor' => 1.0,
        'sam_total_unitario' => 1.0, 'sam_total_operacion' => 1.0, 'is_active' => true,
    ]));
});

it('updates the existing lines in place when their ids are sent', function () {
    $operations = $this->lines->map(fn ($line, $i) => [
        'id' => $line->id, 'product_sheet_id' => $this->sheet->id, 'operation_id' => $line->operation_id,
        'sequence' => ($i + 1) * 10, 'optimal_time' => 0.8, 'operator_efficiency_factor' => 1.0, 'machine_supplement_factor' => 1.0,
    ])->all();

    $this->postJson(route('produccion.listados-operacionales.sync'), ['product_sheet_id' => $this->sheet->id, 'operations' => $operations])
        ->assertOk();

    $ids = ProductOperationList::where('product_sheet_id', $this->sheet->id)->pluck('id')->sort()->values()->all();
    expect($ids)->toBe($this->lines->pluck('id')->sort()->values()->all())
        ->and((float) $this->lines->first()->fresh()->optimal_time)->toEqual(0.8);
});

it('refuses to sync an empty listing', function () {
    $this->postJson(route('produccion.listados-operacionales.sync'), ['product_sheet_id' => $this->sheet->id, 'operations' => []])
        ->assertUnprocessable();

    expect(ProductOperationList::where('product_sheet_id', $this->sheet->id)->count())->toBe(2);
});
