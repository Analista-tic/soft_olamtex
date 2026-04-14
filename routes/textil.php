<?php

use Illuminate\Support\Facades\Route;

// === ROLLOS DE TELA ===
Route::prefix('rollos-tela')->name('fabric-rolls.')->group(function () {
    Route::get('/', [App\Http\Controllers\Textil\FabricRollController::class, 'index'])->name('index');
    Route::get('/crear', [App\Http\Controllers\Textil\FabricRollController::class, 'create'])->name('create');
    Route::get('/data', [App\Http\Controllers\Textil\FabricRollController::class, 'getData'])->name('getData');
    Route::post('/', [App\Http\Controllers\Textil\FabricRollController::class, 'store'])->name('store');
    Route::get('/{fabricRoll}', [App\Http\Controllers\Textil\FabricRollController::class, 'show'])->name('show');
    Route::get('/{fabricRoll}/editar', [App\Http\Controllers\Textil\FabricRollController::class, 'edit'])->name('edit');
    Route::put('/{fabricRoll}', [App\Http\Controllers\Textil\FabricRollController::class, 'update'])->name('update');
    Route::delete('/{fabricRoll}', [App\Http\Controllers\Textil\FabricRollController::class, 'destroy'])->name('destroy');
    Route::patch('/{fabricRoll}/update-output', [App\Http\Controllers\Textil\FabricRollController::class, 'updateOutput'])->name('update-output');
});

// === KARDEX TELAS ===
Route::prefix('kardex-telas')->name('kardex-telas.')->group(function () {
    Route::get('/', [App\Http\Controllers\Textil\FabricRollController::class, 'kardex'])->name('index');
    Route::get('/inventario-rollos', [App\Http\Controllers\Textil\FabricRollController::class, 'inventoryRolls'])->name('inventario-rollos');
    Route::get('/inventario-rollos/data', [App\Http\Controllers\Textil\FabricRollController::class, 'inventoryRollsData'])->name('inventario-rollos.data');
    Route::get('/descargue-orden', [App\Http\Controllers\Textil\FabricRollController::class, 'downloadOrders'])->name('descargue-orden');
    Route::get('/descargue-orden/descarga', [App\Http\Controllers\Textil\FabricRollController::class, 'downloadWindow'])->name('descargue-orden.descarga');
    Route::get('/exportar-rollos', [App\Http\Controllers\Textil\FabricRollController::class, 'exportRolls'])->name('exportar-rollos');

    Route::prefix('recepcion')->name('reception.')->group(function () {
        Route::get('/', [App\Http\Controllers\Textil\FabricRollController::class, 'reception'])->name('index');
        Route::get('/data', [App\Http\Controllers\Textil\FabricRollController::class, 'receptionData'])->name('data');
        Route::get('/facturas/{purchaseDocument}', [App\Http\Controllers\Textil\FabricRollController::class, 'receptionInvoice'])->name('invoice');
        Route::get('/{invoice}', [App\Http\Controllers\Textil\FabricRollController::class, 'receptionShow'])->name('show');
        Route::patch('/{invoice}/aprobar', [App\Http\Controllers\Textil\FabricRollController::class, 'approveReception'])->name('approve');
        Route::patch('/{invoice}/rechazar', [App\Http\Controllers\Textil\FabricRollController::class, 'rejectReception'])->name('reject');
    });

    Route::post('/rollos', [App\Http\Controllers\Textil\FabricRollController::class, 'storeFromReception'])->name('rollos.store');
    Route::get('/rollos/item/{itemId}', [App\Http\Controllers\Textil\FabricRollController::class, 'getByInvoiceItem'])->name('rollos.by-item');
    Route::put('/rollos/{fabricRoll}', [App\Http\Controllers\Textil\FabricRollController::class, 'update'])->name('rollos.update');
    Route::delete('/rollos/{fabricRoll}', [App\Http\Controllers\Textil\FabricRollController::class, 'destroy'])->name('rollos.delete');
});
