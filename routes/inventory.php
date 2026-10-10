<?php

use App\Http\Controllers\Inventory\InventoryAdjustmentController;
use App\Http\Controllers\Inventory\InventoryMovementController;
use App\Http\Controllers\Inventory\InventoryRequestController;
use App\Http\Controllers\Inventory\ItemInventoryController;
use App\Http\Controllers\Inventory\WarehouseController;
use App\Http\Controllers\Inventory\WarehouseItemStockController;
use App\Http\Controllers\Inventory\WarehouseLocationController;
use App\Http\Controllers\Inventory\WarehouseTransferController;
use App\Http\Controllers\MasterData\AdditionalFieldController;
use App\Http\Controllers\MasterData\ItemController;
use Illuminate\Support\Facades\Route;

// === LOOKUPS DE CATALOGOS PARA REFERENCIA ===
Route::get('catalogos/campos-adicionales/options', [AdditionalFieldController::class, 'options'])->name('catalogos.additional-fields.options');
Route::get('catalogos/campos-adicionales/fuentes', [AdditionalFieldController::class, 'catalogSources'])->name('catalogos.additional-fields.catalog-sources');

// === UBICACIONES DE BODEGA ===
Route::prefix('ubicaciones-bodega')->name('warehouse-locations.')->group(function () {
    Route::get('/', [WarehouseLocationController::class, 'index'])->name('index');
    Route::post('/', [WarehouseLocationController::class, 'store'])->name('store');
    Route::put('/{warehouseLocation}', [WarehouseLocationController::class, 'update'])->name('update');
    Route::delete('/{warehouseLocation}', [WarehouseLocationController::class, 'destroy'])->name('destroy');
});

// === BODEGAS ===
Route::prefix('bodegas')->name('warehouses.')->group(function () {
    Route::get('/', [WarehouseController::class, 'index'])->name('index');
    Route::get('/data', [WarehouseController::class, 'getData'])->name('getData');
    Route::get('/list', [WarehouseController::class, 'list'])->name('list');
    Route::post('/', [WarehouseController::class, 'store'])->name('store');
    Route::get('/{warehouse}', [WarehouseController::class, 'show'])->name('show');
    Route::get('/{id}/warehouses', [ItemController::class, 'getWarehouses'])->name('warehouses');
    Route::put('/{warehouse}', [WarehouseController::class, 'update'])->name('update');
    Route::delete('/{warehouse}', [WarehouseController::class, 'destroy'])->name('destroy');
});

// === TRANSFERENCIAS DE BODEGA ===
Route::prefix('transferencias-bodegas')->name('warehouse-transfers.')->middleware('permission:ver transferencias bodegas')->group(function () {
    Route::get('/', [WarehouseTransferController::class, 'index'])->name('index');
    Route::get('/data', [WarehouseTransferController::class, 'getData'])->name('data');
    Route::get('/{transfer}', [WarehouseTransferController::class, 'show'])->name('show');
    Route::post('/', [WarehouseTransferController::class, 'store'])->name('store')->middleware('permission:crear transferencias bodegas');
    Route::put('/{transfer}', [WarehouseTransferController::class, 'update'])->name('update')->middleware('permission:editar transferencias bodegas');
    Route::delete('/{transfer}', [WarehouseTransferController::class, 'destroy'])->name('destroy')->middleware('permission:eliminar transferencias bodegas');
});

// === INVENTARIO DE ÍTEMS ===
Route::get('inventario/item', [ItemController::class, 'listView'])->name('listado');
Route::get('inventario/item/formulario-avanzado', [ItemController::class, 'advancedForm'])
    ->name('inventario.item.formulario-avanzado');
Route::get('inventario/ajuste-inventario', [ItemController::class, 'adjustmentView'])
    ->name('inventario.ajuste-inventario')
    ->middleware('permission:ver ajustes inventario');

// === SOLICITUDES INTERNAS ===
Route::prefix('inventario/solicitudes-internas')->name('inventory-requests.')->group(function () {
    Route::get('/', [InventoryRequestController::class, 'index'])->name('index');
    Route::get('/data', [InventoryRequestController::class, 'getData'])->name('data');
    Route::get('/{inventoryRequest}', [InventoryRequestController::class, 'show'])->name('show');
    Route::post('/', [InventoryRequestController::class, 'store'])->name('store');
    Route::put('/{inventoryRequest}', [InventoryRequestController::class, 'update'])->name('update');
    Route::post('/{inventoryRequest}/deliver', [InventoryRequestController::class, 'deliver'])->name('deliver')->middleware('permission:entregar solicitudes insumos');
    Route::post('/{inventoryRequest}/reject', [InventoryRequestController::class, 'reject'])->name('reject')->middleware('permission:rechazar solicitudes insumos');
});

// === INVENTARIO GENERAL ===
Route::prefix('inventario-item')->name('inventario-item.')->group(function () {
    Route::get('/', [ItemInventoryController::class, 'index'])->name('index');
    Route::get('/data', [ItemInventoryController::class, 'getData'])->name('data');
});
Route::get('/warehouse-items', [WarehouseItemStockController::class, 'index']);

// === CAMPOS ADICIONALES ===
Route::prefix('campos-adicionales')->name('inventory.additional-fields.')->middleware('permission:ver campos adicionales')->group(function () {
    Route::get('/', [AdditionalFieldController::class, 'index'])->name('index');
    Route::get('/options', [AdditionalFieldController::class, 'options'])->name('options');
    Route::get('/data', [AdditionalFieldController::class, 'getData'])->name('data');
    Route::post('/', [AdditionalFieldController::class, 'store'])->name('store')->middleware('permission:crear campos adicionales');
    Route::post('/reorder', [AdditionalFieldController::class, 'reorder'])->name('reorder')->middleware('permission:editar campos adicionales');
    Route::get('/{additionalField}', [AdditionalFieldController::class, 'show'])->name('show');
    Route::put('/{additionalField}', [AdditionalFieldController::class, 'update'])->name('update')->middleware('permission:editar campos adicionales');
    Route::delete('/{additionalField}', [AdditionalFieldController::class, 'destroy'])->name('destroy')->middleware('permission:eliminar campos adicionales');
});

// === AJUSTES DE INVENTARIO ===
Route::prefix('ajustes-inventario')->name('ajustes-inventario.')->middleware('permission:ver ajustes inventario')->group(function () {
    Route::get('/data', [InventoryAdjustmentController::class, 'getData'])->name('data');
    Route::get('/', [InventoryAdjustmentController::class, 'index'])->name('index');
    Route::get('/movement-reasons', [InventoryAdjustmentController::class, 'movementReasons'])->name('movement-reasons');
    Route::get('/{id}', [InventoryAdjustmentController::class, 'show'])->name('show');
    Route::post('/', [InventoryAdjustmentController::class, 'store'])->name('store')->middleware('permission:crear ajustes inventario');
    Route::put('/{id}', [InventoryAdjustmentController::class, 'update'])->name('update')->middleware('permission:editar ajustes inventario');
    Route::delete('/{id}', [InventoryAdjustmentController::class, 'destroy'])->name('destroy')->middleware('permission:eliminar ajustes inventario');
});

// === MOVIMIENTOS DE INVENTARIO ===
Route::prefix('movimientos-inventario')->name('movimientos-inventario.')->group(function () {
    Route::get('/', [InventoryMovementController::class, 'index'])->name('index');
    Route::get('/data', [InventoryMovementController::class, 'getData'])->name('data');
    Route::get('/item/{itemId}', [InventoryMovementController::class, 'getItemMovements'])->name('item-movements');
    Route::get('/weighted-price', [InventoryMovementController::class, 'getCurrentWeightedPrice'])->name('weighted-price');
    Route::post('/transfer', [InventoryMovementController::class, 'storeTransfer'])->name('transfer');
    Route::post('/', [InventoryMovementController::class, 'store'])->name('store');
    Route::get('/{id}', [InventoryMovementController::class, 'show'])->name('show');
    Route::delete('/{id}', [InventoryMovementController::class, 'destroy'])->name('destroy')->middleware('permission:eliminar movimiento inventario');
});
