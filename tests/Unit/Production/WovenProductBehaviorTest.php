<?php

// Bootstrapear el app de Laravel para los tests que usan config() (normalizeProcesses)
uses(Tests\TestCase::class);

use App\Http\Requests\Production\BaseProductSheetRequest;
use App\Services\Settings\SettingService;
use Illuminate\Http\Request;

function makeWovenRequest(array $data): BaseProductSheetRequest
{
    $illuminateRequest = Request::create('/', 'POST', $data);

    $formRequest = new class(app(SettingService::class)) extends BaseProductSheetRequest {
        public function rules(): array { return []; }
    };

    $formRequest->initialize(
        $illuminateRequest->query->all(),
        $illuminateRequest->request->all(),
        $illuminateRequest->attributes->all(),
        $illuminateRequest->cookies->all(),
        $illuminateRequest->files->all(),
        $illuminateRequest->server->all(),
        $illuminateRequest->getContent() ?: null,
    );

    return $formRequest;
}

function callRequestMethod(string $method, array $requestData, array $methodArgs = []): mixed
{
    $request = makeWovenRequest($requestData);
    $ref = new ReflectionMethod($request, $method);
    return $ref->invokeArgs($request, $methodArgs);
}

it('[tejido] producto tejido (tipo=producto): requiresYarnBase = true', function () {
    expect(callRequestMethod('requiresYarnBase', ['woven_product' => true, 'type' => 'producto']))->toBeTrue();
})->group('woven_product');

it('[tejido] producto NO tejido (tipo=producto): requiresYarnBase = false', function () {
    expect(callRequestMethod('requiresYarnBase', ['woven_product' => false, 'type' => 'producto']))->toBeFalse();
})->group('woven_product');

it('[tejido] muestra tejida (tipo=muestra): requiresYarnBase = true', function () {
    expect(callRequestMethod('requiresYarnBase', ['woven_product' => true, 'type' => 'muestra']))->toBeTrue();
})->group('woven_product');

it('[tejido] muestra NO tejida (tipo=muestra): requiresYarnBase = false', function () {
    expect(callRequestMethod('requiresYarnBase', ['woven_product' => false, 'type' => 'muestra']))->toBeFalse();
})->group('woven_product');

it('[tejido] requiresYarnBase depende solo de woven_product, no del tipo', function () {
    expect(callRequestMethod('requiresYarnBase', ['woven_product' => true,  'type' => 'producto']))->toBeTrue()
        ->and(callRequestMethod('requiresYarnBase', ['woven_product' => true,  'type' => 'muestra']))->toBeTrue()
        ->and(callRequestMethod('requiresYarnBase', ['woven_product' => false, 'type' => 'producto']))->toBeFalse()
        ->and(callRequestMethod('requiresYarnBase', ['woven_product' => false, 'type' => 'muestra']))->toBeFalse();
})->group('woven_product');

it('[tejido] normalizeBoolean interpreta valores truthy', function (mixed $value) {
    expect(callRequestMethod('normalizeBoolean', [], [$value]))->toBeTrue();
})->with([
    'bool true'     => [true],
    'int 1'         => [1],
    'string "1"'    => ['1'],
    'string "true"' => ['true'],
    'string "on"'   => ['on'],
    'string "yes"'  => ['yes'],
    'array [1]'     => [[1]],
])->group('woven_product');

it('[tejido] normalizeBoolean interpreta valores falsy', function (mixed $value) {
    expect(callRequestMethod('normalizeBoolean', [], [$value]))->toBeFalse();
})->with([
    'bool false'     => [false],
    'int 0'          => [0],
    'string "0"'     => ['0'],
    'string "false"' => ['false'],
    'string "off"'   => ['off'],
    'string "no"'    => ['no'],
    'null'           => [null],
])->group('woven_product');

it('[tejido] normalizeProcesses preserva Tejido cuando está activo (producto)', function () {
    $json = json_encode(['Tejido' => true, 'Confección' => true]);
    $result = callRequestMethod('normalizeProcesses', [], [$json, 'producto']);
    expect($result)->toHaveKey('Tejido')->and($result['Tejido'])->toBeTrue();
})->group('woven_product');

it('[tejido] normalizeProcesses NO incluye Tejido cuando no lo envía el frontend', function () {
    $json = json_encode(['Confección' => true, 'Terminación' => true]);
    $result = callRequestMethod('normalizeProcesses', [], [$json, 'producto']);
    expect($result)->not->toHaveKey('Tejido');
})->group('woven_product');

it('[tejido] normalizeProcesses resuelve alias legacy Desarrollo de Muestra (tipo=producto)', function () {
    // "Desarrollo de muestras" solo es permitido en tipo=producto (ver config processes_allowed_by_type)
    $json = json_encode(['Desarrollo de Muestra' => true, 'Confección' => true]);
    $result = callRequestMethod('normalizeProcesses', [], [$json, 'producto']);
    expect($result)
        ->not->toHaveKey('Desarrollo de Muestra')
        ->toHaveKey('Desarrollo de muestras');
})->group('woven_product');

it('[tejido] normalizeProcesses filtra Desarrollo de muestras cuando tipo=muestra (no está permitido)', function () {
    // Config processes_allowed_by_type.muestra NO incluye "Desarrollo de muestras"
    $json = json_encode(['Desarrollo de Muestra' => true, 'Muestras' => true]);
    $result = callRequestMethod('normalizeProcesses', [], [$json, 'muestra']);
    expect($result)
        ->not->toHaveKey('Desarrollo de Muestra')
        ->not->toHaveKey('Desarrollo de muestras')
        ->toHaveKey('Muestras');
})->group('woven_product');
