<?php

use App\Http\Controllers\Inventory\InventoryAdjustmentController;
use App\Http\Controllers\Inventory\InventoryMovementController;
use App\Http\Controllers\Inventory\InventoryRequestController;
use App\Http\Controllers\Inventory\ItemInventoryController;
use App\Http\Controllers\Inventory\WarehouseController;
use App\Http\Controllers\Inventory\WarehouseItemStockController;
use App\Http\Controllers\Inventory\WarehouseTransferController;
use App\Http\Controllers\MasterData\AdditionalFieldController;
use App\Http\Controllers\MasterData\ItemController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// === BODEGAS ===
Route::prefix('bodegas')->name('warehouses.')->group(function () {
    Route::get('/', [WarehouseController::class, 'index'])->name('index');
    Route::get('/data', [WarehouseController::class, 'getData'])->name('getData');
    Route::post('/', [WarehouseController::class, 'store'])->name('store');
    Route::get('/{warehouse}', [WarehouseController::class, 'show'])->name('show');
    Route::get('/{id}/warehouses', [ItemController::class, 'getWarehouses'])->name('warehouses');
    Route::put('/{warehouse}', [WarehouseController::class, 'update'])->name('update');
    Route::delete('/{warehouse}', [WarehouseController::class, 'destroy'])->name('destroy');
});

// === TRANSFERENCIAS DE BODEGA ===
Route::prefix('transferencias-bodegas')->name('warehouse-transfers.')->group(function () {
    Route::get('/', [WarehouseTransferController::class, 'index'])->name('index');
    Route::get('/data', [WarehouseTransferController::class, 'getData'])->name('data');
    Route::get('/{transfer}', [WarehouseTransferController::class, 'show'])->name('show');
    Route::post('/', [WarehouseTransferController::class, 'store'])->name('store');
    Route::put('/{transfer}', [WarehouseTransferController::class, 'update'])->name('update');
    Route::delete('/{transfer}', [WarehouseTransferController::class, 'destroy'])->name('destroy');
});

// === INVENTARIO DE ÍTEMS ===
Route::get('inventario/item', [ItemController::class, 'listView'])->name('listado');
Route::get('inventario/item/formulario-avanzado', [ItemController::class, 'advancedForm'])
    ->name('inventario.item.formulario-avanzado');
Route::get('inventario/ajuste-inventario', [ItemController::class, 'adjustmentView'])->name('inventario.ajuste-inventario');
Route::get('inventario/ajuste-inventario/crear', function () {
    return Inertia::render('Inventory/Articles/InventoryAdjustmentForm');
})->name('inventario.ajuste-inventario.create');

// === SOLICITUDES INTERNAS ===
Route::prefix('inventario/solicitudes-internas')->name('inventory-requests.')->group(function () {
    Route::get('/', [InventoryRequestController::class, 'index'])->name('index');
    Route::get('/data', [InventoryRequestController::class, 'getData'])->name('data');
    Route::get('/next-number', [InventoryRequestController::class, 'nextNumber'])->name('next-number');
    Route::get('/{inventoryRequest}', [InventoryRequestController::class, 'show'])->name('show');
    Route::post('/', [InventoryRequestController::class, 'store'])->name('store');
    Route::put('/{inventoryRequest}', [InventoryRequestController::class, 'update'])->name('update');
    Route::post('/{inventoryRequest}/deliver', [InventoryRequestController::class, 'deliver'])->name('deliver');
    Route::post('/{inventoryRequest}/reject', [InventoryRequestController::class, 'reject'])->name('reject');
});

// === INVENTARIO GENERAL ===
Route::prefix('inventario-item')->name('inventario-item.')->group(function () {
    Route::get('/', [ItemInventoryController::class, 'index'])->name('index');
    Route::get('/data', [ItemInventoryController::class, 'getData'])->name('data');
});
Route::get('/warehouse-items', [WarehouseItemStockController::class, 'index']);

// === CAMPOS ADICIONALES ===
Route::prefix('campos-adicionales')->name('inventory.additional-fields.')->group(function () {
    Route::get('/', [AdditionalFieldController::class, 'index'])->name('index');
    Route::get('/options', [AdditionalFieldController::class, 'options'])->name('options');
    Route::get('/data', [AdditionalFieldController::class, 'getData'])->name('data');
    Route::post('/', [AdditionalFieldController::class, 'store'])->name('store');
    Route::post('/reorder', [AdditionalFieldController::class, 'reorder'])->name('reorder');
    Route::get('/{additionalField}', [AdditionalFieldController::class, 'show'])->name('show');
    Route::put('/{additionalField}', [AdditionalFieldController::class, 'update'])->name('update');
    Route::delete('/{additionalField}', [AdditionalFieldController::class, 'destroy'])->name('destroy');
});

// === AJUSTES DE INVENTARIO ===
Route::prefix('ajustes-inventario')->name('ajustes-inventario.')->group(function () {
    Route::get('/data', [InventoryAdjustmentController::class, 'getData'])->name('data');
    Route::get('/', [InventoryAdjustmentController::class, 'index'])->name('index');
    Route::get('/next-number', [InventoryAdjustmentController::class, 'nextNumber'])->name('next-number');
    Route::get('/movement-reasons', [InventoryAdjustmentController::class, 'movementReasons'])->name('movement-reasons');
    Route::get('/{id}', [InventoryAdjustmentController::class, 'show'])->name('show');
    Route::post('/', [InventoryAdjustmentController::class, 'store'])->name('store');
    Route::put('/{id}', [InventoryAdjustmentController::class, 'update'])->name('update');
    Route::delete('/{id}', [InventoryAdjustmentController::class, 'destroy'])->name('destroy');
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
    Route::delete('/{id}', [InventoryMovementController::class, 'destroy'])->name('destroy');
});
