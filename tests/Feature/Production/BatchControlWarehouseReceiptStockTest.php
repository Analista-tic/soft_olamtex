<?php

use App\Models\Inventory\InventoryMovement;
use App\Models\Inventory\Warehouse;
use App\Models\Inventory\WarehouseItem;
use App\Models\Production\BatchControlWarehouseReceipt;
use App\Models\Production\ProductionOrder;
use App\Models\Production\ProductSheet;
use App\Models\Settings\AuditLog;
use App\Models\Settings\Setting;
use App\Models\Settings\User;

/**
 * La remisión de producto terminado a bodega debe mover existencias por el
 * kardex (InventoryMovement), no escribiendo warehouse_items directamente,
 * para que quede trazabilidad y auditoría.
 */
beforeEach(function () {
    $this->withoutMiddleware([
        \Illuminate\Auth\Middleware\Authenticate::class,
        \Illuminate\Auth\Middleware\EnsureEmailIsVerified::class,
        \Spatie\Permission\Middleware\PermissionMiddleware::class,
    ]);
    $this->actingAs(User::factory()->create());

    Setting::create([
        'kind' => 'parameter',
        'key' => 'sections',
        'label' => 'Secciones',
        'value' => [['id' => 1, 'name' => 'JOGGER']],
        'status' => true,
    ]);

    $sheet = ProductSheet::create([
        'section_id' => 1,
        'group_id' => 1,
        'brand_id' => 1,
        'world_id' => 1,
        'type' => 'producto',
        'billing_price' => 1000,
        'selling_price' => 1200,
        'sizes' => ['S', 'M'],
        'woven_product' => false,
        'description' => 'Jogger de prueba',
        'sample_reference' => 'MUESTRA-' . uniqid(),
        'product_reference' => 'PRODUCTO-' . uniqid(),
        'processes' => [],
        'status' => 'complete',
    ]);

    $this->order = ProductionOrder::create([
        'product_sheet_id' => $sheet->id,
        'op_code' => 'OP-' . uniqid(),
        'season' => '1',
        'destination_id' => 1,
        'priority' => 'alta',
        'units' => 10,
        'estimated_delivery_date' => '2026-03-30',
        'status' => 'active',
        'current_process' => 'Bodega',
        'source' => 'manual',
    ]);

    $this->warehouse = Warehouse::create(['name' => 'Bodega PT', 'observation' => 'Pruebas']);
});

function remisionPayload(int $warehouseId, int $s, int $m): array
{
    return [
        'imperfect_curves' => [['color' => 'NEGRO', 'S' => 0, 'M' => 0, 'total' => 0]],
        'remision_curves' => [['color' => 'NEGRO', 'S' => $s, 'M' => $m, 'total' => $s + $m]],
        'warehouse_id' => $warehouseId,
    ];
}

it('moves finished product stock through the kardex and reverts previous remissions', function () {
    $url = "/control-de-lotes/{$this->order->id}/bodega-recepcion";

    $this->postJson($url, remisionPayload($this->warehouse->id, 3, 2))->assertOk();

    expect((float) WarehouseItem::query()->where('warehouse_id', $this->warehouse->id)->sum('quantity'))->toBe(5.0)
        ->and(InventoryMovement::query()->where('source_type', 'batch_control_warehouse_receipt')->count())->toBe(2);

    // Re-guardar la remisión revierte la anterior y aplica la nueva
    $this->postJson($url, remisionPayload($this->warehouse->id, 1, 1))->assertOk();

    expect((float) WarehouseItem::query()->where('warehouse_id', $this->warehouse->id)->sum('quantity'))->toBe(2.0)
        ->and(InventoryMovement::query()->where('source_type', 'batch_control_warehouse_receipt')->count())->toBe(6)
        ->and(AuditLog::query()->where('auditable_type', BatchControlWarehouseReceipt::class)->exists())->toBeTrue()
        ->and(AuditLog::query()->where('auditable_type', InventoryMovement::class)->count())->toBeGreaterThanOrEqual(6);
});
