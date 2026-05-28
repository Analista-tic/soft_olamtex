<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Textil\FabricRollController;

// === ROLLOS DE TELA ===
Route::prefix('rollos-tela')->name('fabric-rolls.')->middleware('permission:ver telas rollos')->group(function () {
    Route::get('/', [App\Http\Controllers\Textil\FabricRollController::class, 'index'])->name('index');
    Route::get('/crear', [App\Http\Controllers\Textil\FabricRollController::class, 'create'])->name('create');
    Route::get('/data', [App\Http\Controllers\Textil\FabricRollController::class, 'getData'])->name('getData');
    Route::get('/next-roll-number', [App\Http\Controllers\Textil\FabricRollController::class, 'nextRollNumber'])->name('next-roll-number');
    Route::post('/', [App\Http\Controllers\Textil\FabricRollController::class, 'store'])->name('store')->middleware('permission:crear telas rollos');
    Route::get('/{fabricRoll}', [App\Http\Controllers\Textil\FabricRollController::class, 'show'])->name('show');
    Route::get('/{fabricRoll}/editar', [App\Http\Controllers\Textil\FabricRollController::class, 'edit'])->name('edit');
    Route::put('/{fabricRoll}', [App\Http\Controllers\Textil\FabricRollController::class, 'update'])->name('update')->middleware('permission:editar telas rollos');
    Route::delete('/{fabricRoll}', [App\Http\Controllers\Textil\FabricRollController::class, 'destroy'])->name('destroy')->middleware('permission:eliminar telas rollos');
    Route::patch('/{fabricRoll}/update-output', [App\Http\Controllers\Textil\FabricRollController::class, 'updateOutput'])->name('update-output')->middleware('permission:editar telas rollos');
    Route::patch('/{fabricRoll}/discharge', [App\Http\Controllers\Textil\FabricRollController::class, 'discharge'])->name('discharge')->middleware('permission:editar telas rollos');
});

// Ruta para descargar la plantilla de importación de rollos (archivo en storage/app/public)
Route::get('/fabric-rolls/descargar-plantilla', [FabricRollController::class, 'downloadTemplate'])
    ->name('fabric-rolls.download-template')
    ->middleware('permission:ver telas rollos');

Route::post('/api/fabric-rolls/import', [App\Http\Controllers\Textil\FabricRollController::class, 'import'])
    ->middleware('permission:fabric_rolls.import')
    ->name('fabric-rolls.import');

// === KARDEX TELAS ===
Route::prefix('kardex-telas')->name('kardex-telas.')->middleware('permission:ver telas rollos')->group(function () {
    Route::get('/', [App\Http\Controllers\Textil\FabricRollController::class, 'kardex'])->name('index');
    Route::get('/inventario-rollos', [App\Http\Controllers\Textil\FabricRollController::class, 'inventoryRolls'])->name('inventario-rollos');
    Route::get('/inventario-rollos/movimientos', [App\Http\Controllers\Textil\FabricRollController::class, 'movementsPage'])
        ->middleware('permission:ver telas rollos')
        ->name('inventario-rollos.movimientos');
    Route::get('/inventario-rollos/data', [App\Http\Controllers\Textil\FabricRollController::class, 'inventoryRollsData'])->name('inventario-rollos.data');
    Route::get('/inventario-rollos/filter-options', [App\Http\Controllers\Textil\FabricRollController::class, 'inventoryFilterOptions'])->name('inventario-rollos.filter-options');
    Route::patch('/inventario-rollos/{fabricRoll}/vincular-tela-principal', [App\Http\Controllers\Textil\FabricRollController::class, 'relinkMainFabric'])
        ->middleware('permission:editar telas rollos')
        ->name('inventario-rollos.relink-main-fabric');
    Route::get('/inventario-rollos/plantilla-cantidades', [App\Http\Controllers\Textil\FabricRollController::class, 'downloadQuantitiesTemplate'])
        ->middleware('permission:editar telas rollos')
        ->name('inventario-rollos.plantilla-cantidades');
    Route::post('/inventario-rollos/importar-cantidades', [App\Http\Controllers\Textil\FabricRollController::class, 'importQuantities'])
        ->middleware('permission:editar telas rollos')
        ->name('inventario-rollos.importar-cantidades');
    Route::post('/inventario-rollos/importar-csv', [App\Http\Controllers\Textil\FabricRollController::class, 'importFromCsv'])
        ->middleware('permission:editar telas rollos')
        ->name('inventario-rollos.importar-csv');
    Route::get('/reportes/sin-relacion', [App\Http\Controllers\Textil\FabricRollController::class, 'reportSinRelacion'])
        ->middleware('permission:ver telas rollos')
        ->name('reportes.sin-relacion');
    Route::get('/reportes/historial-ajustes', [App\Http\Controllers\Textil\FabricRollController::class, 'reportHistorialAjustes'])
        ->middleware('permission:ver telas rollos')
        ->name('reportes.historial-ajustes');
    Route::get('/descargue-orden', [App\Http\Controllers\Textil\FabricRollController::class, 'downloadOrders'])
        ->middleware('permission:ver descargue telas orden')
        ->name('descargue-orden');
    Route::get('/descargue-orden/descarga', [App\Http\Controllers\Textil\FabricRollController::class, 'downloadWindow'])
        ->middleware('permission:ver descargue telas orden')
        ->name('descargue-orden.descarga');
    Route::get('/exportar-rollos', [App\Http\Controllers\Textil\FabricRollController::class, 'exportRolls'])->name('exportar-rollos');

    Route::prefix('recepcion')->name('reception.')->group(function () {
        Route::get('/', [App\Http\Controllers\Textil\FabricRollController::class, 'reception'])->name('index');
        Route::get('/data', [App\Http\Controllers\Textil\FabricRollController::class, 'receptionData'])->name('data');
        Route::get('/facturas/{purchaseDocument}', [App\Http\Controllers\Textil\FabricRollController::class, 'receptionInvoice'])->name('invoice');
        Route::get('/{invoice}', [App\Http\Controllers\Textil\FabricRollController::class, 'receptionShow'])->name('show');
        Route::patch('/{invoice}/aprobar', [App\Http\Controllers\Textil\FabricRollController::class, 'approveReception'])
            ->middleware('permission:aprobar recepcion tela')
            ->name('approve');
        Route::patch('/{invoice}/rechazar', [App\Http\Controllers\Textil\FabricRollController::class, 'rejectReception'])
            ->middleware('permission:rechazar recepcion tela')
            ->name('reject');
    });

    Route::post('/rollos', [App\Http\Controllers\Textil\FabricRollController::class, 'storeFromReception'])->name('rollos.store')->middleware('permission:crear telas rollos');
    Route::get('/rollos/next-roll-number', [App\Http\Controllers\Textil\FabricRollController::class, 'nextRollNumber'])->name('rollos.next-roll-number');
    Route::get('/rollos/item/{itemId}', [App\Http\Controllers\Textil\FabricRollController::class, 'getByInvoiceItem'])->name('rollos.by-item');
    Route::put('/rollos/{fabricRoll}', [App\Http\Controllers\Textil\FabricRollController::class, 'update'])->name('rollos.update')->middleware('permission:editar telas rollos');
    Route::delete('/rollos/{fabricRoll}', [App\Http\Controllers\Textil\FabricRollController::class, 'destroy'])->name('rollos.delete')->middleware('permission:eliminar telas rollos');
});
