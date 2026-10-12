<?php

use App\Models\Production\Operation;
use App\Models\Production\ProductOperationList;
use App\Models\Production\ProductSheet;
use App\Models\Settings\User;
use App\Services\Production\ProductSheetStatusService;
use Spatie\Permission\Models\Permission;

/**
 * Una sola regla de "sábana completa": referencia, precio, tallas, foto y listado operacional completo.
 * La decide el servidor y el estado "incompleto" dice qué falta.
 */
beforeEach(function () {
    $user = User::factory()->create();
    foreach (['ver sabana productos', 'editar listados operacionales'] as $permission) {
        $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }
    $this->actingAs($user);
    $this->service = app(ProductSheetStatusService::class);
});

function completenessSheet(array $overrides = []): ProductSheet
{
    return ProductSheet::create(array_merge([
        'section_id' => 1, 'group_id' => 1, 'brand_id' => 1, 'world_id' => 1,
        'type' => 'producto', 'billing_price' => 1000, 'selling_price' => 1200,
        'sizes' => ['M' => true, 'L' => false], 'woven_product' => false,
        'description' => 'Ficha completitud',
        'sample_reference' => 'MUESTRA-CMP', 'product_reference' => 'PRODUCTO-CMP-'.uniqid(),
        'processes' => ['Corte'], 'status' => 'incomplete',
        'flat_sketch_path' => 'flat_sketches/foto.png',
    ], $overrides));
}

function completenessLine(ProductSheet $sheet): ProductOperationList
{
    $operation = Operation::create(['action' => 'PEGAR', 'garment_part' => 'CINTA', 'sam_base' => 1.0, 'quantity' => 1, 'is_active' => true]);

    return ProductOperationList::create([
        'product_sheet_id' => $sheet->id, 'operation_id' => $operation->id, 'sequence' => 1,
        'sam_base' => 1.0, 'sam' => 1.0, 'optimal_time' => 1.0,
        'operator_efficiency_factor' => 1.0, 'machine_supplement_factor' => 1.0, 'fabric_base_factor' => 1.0,
        'sam_total_unitario' => 1.0, 'sam_total_operacion' => 1.0, 'is_active' => true,
    ]);
}

it('lists everything that is missing for a bare sheet', function () {
    $sheet = completenessSheet(['selling_price' => 0, 'sizes' => ['M' => false], 'flat_sketch_path' => null, 'product_reference' => null]);

    expect($this->service->missingForCompletion($sheet))->toBe([
        'Referencia de producto', 'Precio de venta', 'Tallas', 'Foto', 'Listado operacional completo',
    ]);
});

it('is complete only when reference, price, sizes, photo and the completed listing are all there', function () {
    $sheet = completenessSheet();
    completenessLine($sheet);

    // Con líneas pero sin marcar el listado como completo todavía falta ese paso.
    expect($this->service->missingForCompletion($sheet->fresh()))->toBe(['Listado operacional completo']);

    $this->service->markOperationalListCompleted($sheet, auth()->id());

    expect($this->service->missingForCompletion($sheet->fresh()))->toBe([])
        ->and($sheet->fresh()->status)->toBe('complete');
});

it('goes back to incomplete when the photo disappears or a line changes', function () {
    $sheet = completenessSheet();
    $line = completenessLine($sheet);
    $this->service->markOperationalListCompleted($sheet, auth()->id());
    expect($sheet->fresh()->status)->toBe('complete');

    $this->service->syncForSheet($sheet->id);

    $fresh = $sheet->fresh();
    expect($fresh->status)->toBe('incomplete')
        ->and($fresh->operational_list_completed_at)->toBeNull()
        ->and($this->service->missingForCompletion($fresh))->toBe(['Listado operacional completo']);
});

it('ignores the status the client sends and exposes what is missing in the list', function () {
    $sheet = completenessSheet(['flat_sketch_path' => null]);
    completenessLine($sheet);
    $this->service->markOperationalListCompleted($sheet, auth()->id());

    $row = collect($this->getJson(route('produccion.product-sheets.getData', ['search' => $sheet->product_reference]))->assertOk()->json('products_sheets.data'))
        ->firstWhere('id', $sheet->id);

    expect($row['status'])->toBe('incomplete')
        ->and($row['missing_for_completion'])->toBe(['Foto'])
        ->and($row)->not->toHaveKey('sizes');
});
