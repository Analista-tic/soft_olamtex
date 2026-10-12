<?php

use App\Models\Production\Operation;
use App\Models\Production\ProductOperationList;
use App\Models\Production\ProductSheet;
use App\Models\Settings\User;
use App\Services\Production\ProductOperationListService;

/**
 * El SAM de la operación en el listado multiplica el SAM unitario por las repeticiones por pieza,
 * igual en cada pantalla (se guarda ya multiplicado).
 */
beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

function listingQuantitySheet(): ProductSheet
{
    return ProductSheet::create([
        'section_id' => 1, 'group_id' => 1, 'brand_id' => 1, 'world_id' => 1,
        'type' => 'producto', 'billing_price' => 1000, 'selling_price' => 1200,
        'sizes' => ['M'], 'woven_product' => false,
        'description' => 'Ficha prueba SAM cantidad',
        'sample_reference' => 'MUESTRA-SAMQ', 'product_reference' => 'PRODUCTO-SAMQ',
        'processes' => ['Corte'], 'status' => 'complete',
    ]);
}

it('multiplies the unit SAM by the operation quantity', function () {
    $operation = Operation::create(['action' => 'PEGAR', 'garment_part' => 'BOTON', 'quantity' => 3, 'is_active' => true]);
    $line = new ProductOperationList([
        'operation_id' => $operation->id, 'sam_base' => 0.5,
        'machine_supplement_factor' => 1.2, 'fabric_base_factor' => 1.0, 'operator_efficiency_factor' => 1.0,
    ]);

    expect($line->calculateSamTotalUnitario())->toEqual(0.6)
        ->and($line->calculateSamTotalOperacion())->toEqual(1.8);
});

it('treats an empty or zero quantity as one repetition', function () {
    expect(ProductOperationList::quantityOf(null))->toBe(1)
        ->and(ProductOperationList::quantityOf(0))->toBe(1)
        ->and(ProductOperationList::quantityOf('4'))->toBe(4);
});

it('stores the multiplied SAM when a line is updated through the service', function () {
    $sheet = listingQuantitySheet();
    $operation = Operation::create(['action' => 'PEGAR', 'garment_part' => 'OJAL', 'quantity' => 2, 'is_active' => true]);
    $line = ProductOperationList::create([
        'product_sheet_id' => $sheet->id, 'operation_id' => $operation->id, 'sequence' => 1,
        'sam_base' => 1.0, 'sam' => 1.0, 'optimal_time' => 1.0,
        'operator_efficiency_factor' => 1.0, 'machine_supplement_factor' => 1.0, 'fabric_base_factor' => 1.0,
        'sam_total_unitario' => 1.0, 'sam_total_operacion' => 1.0, 'is_active' => true,
    ]);

    $updated = app(ProductOperationListService::class)->update($line, ['notes' => 'recalcula']);

    expect((float) $updated->sam_total_unitario)->toEqual(1.0)
        ->and((float) $updated->sam_total_operacion)->toEqual(2.0);
});
