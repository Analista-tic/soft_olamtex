<?php

use App\Models\Production\Operation;
use App\Models\Production\ProductOperationList;
use App\Models\Production\ProductSheet;
use App\Models\Settings\User;
use App\Services\Production\ProductOperationListService;
use Spatie\Permission\Models\Permission;

/**
 * "Listado completo" es una marca manual (quién y cuándo), distinta de guardar; editar las líneas la quita.
 */
beforeEach(function () {
    $this->user = User::factory()->create();
    foreach (['ver listados operacionales', 'editar listados operacionales'] as $permission) {
        $this->user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }
    $this->actingAs($this->user);

    $this->sheet = ProductSheet::create([
        'section_id' => 1, 'group_id' => 1, 'brand_id' => 1, 'world_id' => 1,
        'type' => 'producto', 'billing_price' => 1000, 'selling_price' => 1200,
        'sizes' => ['M'], 'woven_product' => false,
        'description' => 'Ficha listado completo',
        'sample_reference' => 'MUESTRA-LC', 'product_reference' => 'PRODUCTO-LC',
        'processes' => ['Corte'], 'status' => 'incomplete',
    ]);
});

function completionLine(ProductSheet $sheet): ProductOperationList
{
    $operation = Operation::create(['action' => 'PEGAR', 'garment_part' => 'FORRO', 'sam_base' => 1.0, 'quantity' => 1, 'is_active' => true]);

    return ProductOperationList::create([
        'product_sheet_id' => $sheet->id, 'operation_id' => $operation->id, 'sequence' => 1,
        'sam_base' => 1.0, 'sam' => 1.0, 'optimal_time' => 1.0,
        'operator_efficiency_factor' => 1.0, 'machine_supplement_factor' => 1.0, 'fabric_base_factor' => 1.0,
        'sam_total_unitario' => 1.0, 'sam_total_operacion' => 1.0, 'is_active' => true,
    ]);
}

it('refuses to mark an empty listing as complete', function () {
    $this->postJson(route('produccion.listados-operacionales.complete', $this->sheet))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('operational_list');

    expect($this->sheet->fresh()->operational_list_completed_at)->toBeNull();
});

it('marks the listing as complete recording who and when, and can reopen it', function () {
    completionLine($this->sheet);

    $this->postJson(route('produccion.listados-operacionales.complete', $this->sheet))
        ->assertOk()
        ->assertJsonPath('data.completed', true)
        ->assertJsonPath('data.completed_by', $this->user->name);

    $sheet = $this->sheet->fresh();
    expect($sheet->operational_list_completed_at)->not->toBeNull()
        ->and($sheet->operational_list_completed_by_user_id)->toBe($this->user->id);

    $this->getJson(route('produccion.listados-operacionales.data', ['product_sheet_id' => $this->sheet->id]))
        ->assertOk()
        ->assertJsonPath('completion.completed', true);

    $this->deleteJson(route('produccion.listados-operacionales.reopen', $this->sheet))
        ->assertOk()
        ->assertJsonPath('data.completed', false);
    expect($this->sheet->fresh()->operational_list_completed_at)->toBeNull();
});

it('drops the completed mark when the lines change', function () {
    $line = completionLine($this->sheet);
    $this->postJson(route('produccion.listados-operacionales.complete', $this->sheet))->assertOk();

    app(ProductOperationListService::class)->update($line, ['notes' => 'cambió algo']);

    expect($this->sheet->fresh()->operational_list_completed_at)->toBeNull();
});
