<?php

use App\Services\Inventory\InventoryMovementService;
use App\Services\Inventory\InventoryRequestService;
use App\Services\Inventory\WarehouseTransferService;
use App\Support\DocumentSequence;
use Illuminate\Support\Facades\DB;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

/** Invoca un método protegido/privado de un servicio (los generadores no son públicos). */
function callGenerator(object $service, string $method): string
{
    $reflection = new ReflectionMethod($service, $method);

    return $reflection->invoke($service);
}

test('el mayor consecutivo se calcula como número y no como texto (999 -> 1000)', function () {
    expect(DocumentSequence::maxFromNumbers(['MOV-2026-999', 'MOV-2026-1000', 'MOV-2026-020'], 'MOV-2026'))->toBe(1000)
        ->and(DocumentSequence::maxFromNumbers(['FAC-T-0003', 'FAC-0002', 'FAC-T-0009'], 'FAC'))->toBe(2)
        ->and(DocumentSequence::maxFromNumbers([], 'FAC'))->toBe(0);
});

test('cada serie del contador avanza de forma independiente y nunca retrocede', function () {
    $a = fn () => DocumentSequence::next('prueba:a', fn () => 0);
    $b = fn () => DocumentSequence::next('prueba:b', fn () => 0);

    expect([$a(), $a(), $b(), $a()])->toBe([1, 2, 1, 3]);

    // Si la tabla ya trae un número mayor (importación), el contador lo respeta.
    expect(DocumentSequence::next('prueba:a', fn () => 40))->toBe(41)
        ->and(DocumentSequence::next('prueba:a', fn () => 5))->toBe(42);
});

test('las solicitudes internas, traslados y movimientos generan consecutivos únicos y crecientes', function () {
    $requests = app(InventoryRequestService::class);
    $transfers = app(WarehouseTransferService::class);
    $movements = app(InventoryMovementService::class);
    $year = now()->year;

    expect([callGenerator($requests, 'generateDocumentNumber'), callGenerator($requests, 'generateDocumentNumber')])->toBe(['SINT-001', 'SINT-002'])
        ->and([callGenerator($transfers, 'generateDocumentNumber'), callGenerator($transfers, 'generateDocumentNumber')])->toBe(['TR-001', 'TR-002'])
        ->and([callGenerator($movements, 'generateMovementNumber'), callGenerator($movements, 'generateMovementNumber')])->toBe(["MOV-{$year}-001", "MOV-{$year}-002"]);
});

test('una solicitud de insumo (SOL-INS) en la misma tabla no hace repetir el consecutivo SINT', function () {
    DB::table('inventory_requests')->insert([
        'document_number' => 'SOL-INS-' . now()->year . '-0001', 'date' => now(), 'status' => 'solicitado',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $requests = app(InventoryRequestService::class);

    expect(callGenerator($requests, 'generateDocumentNumber'))->toBe('SINT-001')
        ->and(callGenerator($requests, 'generateDocumentNumber'))->toBe('SINT-002');
});
