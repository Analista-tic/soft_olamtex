<?php

use App\Services\Production\ProductionOrderPresenter;
use App\Support\Production\SizeOrder;

/**
 * Las tallas salen siempre en el mismo orden (XS, S, M, L, XL, XXL; luego numéricas), sin importar cómo las guarde MySQL.
 */
it('sorts letter sizes from smallest to largest, then numeric ones ascending', function () {
    expect(SizeOrder::sort(['XL', '2XL', 'M', '36', 'XS', 'S', 'L', '8', 'U', '10', 'XXL']))
        ->toBe(['XS', 'S', 'M', 'L', 'XL', '2XL', 'XXL', 'U', '8', '10', '36']);
});

it('puts unknown sizes last in alphabetical order', function () {
    expect(SizeOrder::sort(['UNICO ROJO', 'M', 'ESPECIAL', 'S']))->toBe(['S', 'M', 'ESPECIAL', 'UNICO ROJO']);
});

it('orders the sizes the way MySQL returns them when stored as a JSON object', function () {
    // MySQL reordena las claves del JSON (cortas primero): así llegan al presentador.
    $stored = ['L' => true, 'M' => true, 'S' => true, 'XL' => true, 'XS' => true, 'XXL' => true, '34' => false, '36' => true];

    expect((new ProductionOrderPresenter())->normalizeSizes($stored))->toBe(['XS', 'S', 'M', 'L', 'XL', 'XXL', '36']);
    expect((new ProductionOrderPresenter())->normalizeSizes('{"L":true,"S":true,"XS":true,"M":true}'))->toBe(['XS', 'S', 'M', 'L']);
});
