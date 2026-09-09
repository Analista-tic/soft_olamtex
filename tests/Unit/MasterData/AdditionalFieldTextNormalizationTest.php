<?php

use App\Services\Inventory\InventoryMovementService;
use App\Services\MasterData\AdditionalFieldService;
use App\Services\MasterData\ItemService;
use App\Support\TextNormalizer;

it('normalizes additional field text values to title case', function (string $input, string $expected) {
    expect(TextNormalizer::titleCaseWords($input))->toBe($expected);
})->with([
    ['negro', 'Negro'],
    ['NEGRO', 'Negro'],
    ['lInO', 'Lino'],
    ['ejemplo 1', 'Ejemplo 1'],
    ['EJEMPLO 2', 'Ejemplo 2'],
    ['eJeMpLo 3', 'Ejemplo 3'],
    ['naranja neón', 'Naranja Neón'],
    ['VERDE   CLARO', 'Verde Claro'],
    [' acrílico ', 'Acrílico'],
]);

it('normalizes item extra field values before persistence', function () {
    $service = new ItemService(new AdditionalFieldService(), new InventoryMovementService());

    expect($service->normalizeExtraFields([
        'Color' => 'VERDE   CLARO',
        'Material' => ' acrílico ',
    ]))->toBe([
        'Color' => 'Verde Claro',
        'Material' => 'Acrílico',
    ]);
});

it('normalizes select option values before persistence', function () {
    $service = new AdditionalFieldService();
    $normalizeOptions = Closure::bind(
        fn (mixed $options): ?array => $this->normalizeOptions($options),
        $service,
        AdditionalFieldService::class,
    );

    expect($normalizeOptions([
        'NEGRO',
        'naranja neón',
        'VERDE   CLARO',
        ' acrílico ',
        '',
    ]))->toBe([
        'Negro',
        'Naranja Neón',
        'Verde Claro',
        'Acrílico',
    ]);
});
