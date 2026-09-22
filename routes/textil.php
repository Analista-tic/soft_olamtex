<?php

use App\Http\Controllers\Textil\FabricRollController;
use Illuminate\Support\Facades\Route;

// === ROLLOS DE TELA ===
Route::prefix('rollos-tela')->name('fabric-rolls.')->middleware('permission:ver telas rollos')->group(function () {
    Route::get('/', [FabricRollController::class, 'index'])->name('index');
    Route::get('/crear', [FabricRollController::class, 'create'])->name('create');
    Route::get('/data', [FabricRollController::class, 'getData'])->name('getData');
    Route::get('/next-roll-number', [FabricRollController::class, 'nextRollNumber'])->name('next-roll-number');
    Route::post('/', [FabricRollController::class, 'store'])->name('store')->middleware('permission:crear telas rollos');
    Route::get('/{fabricRoll}', [FabricRollController::class, 'show'])->name('show');
    Route::get('/{fabricRoll}/editar', [FabricRollController::class, 'edit'])->name('edit');
    Route::put('/{fabricRoll}', [FabricRollController::class, 'update'])->name('update')->middleware('permission:editar telas rollos');
    Route::delete('/{fabricRoll}', [FabricRollController::class, 'destroy'])->name('destroy')->middleware('permission:eliminar telas rollos');
    Route::patch('/{fabricRoll}/discharge', [FabricRollController::class, 'discharge'])->name('discharge')->middleware('permission:ajustar telas rollos');
    Route::patch('/{fabricRoll}/corregir-cantidad', [FabricRollController::class, 'correctQuantity'])->name('correct-quantity')->middleware('permission:ajustar telas rollos');
    Route::patch('/{fabricRoll}/actualizar-precio', [FabricRollController::class, 'updatePrice'])->name('update-price')->middleware('permission:ajustar telas rollos');
});

// Ruta para descargar la plantilla de importación de rollos (archivo en storage/app/public)
Route::get('/fabric-rolls/descargar-plantilla', [FabricRollController::class, 'downloadTemplate'])
    ->name('fabric-rolls.download-template')
    ->middleware('permission:ver telas rollos');

Route::post('/api/fabric-rolls/import', [FabricRollController::class, 'import'])
    ->middleware('permission:fabric_rolls.import')
    ->name('fabric-rolls.import');

// === KARDEX TELAS ===
Route::prefix('kardex-telas')->name('kardex-telas.')->middleware('permission:ver telas rollos')->group(function () {
    Route::get('/', [FabricRollController::class, 'kardex'])->name('index');
    Route::get('/inventario-rollos', [FabricRollController::class, 'inventoryRolls'])->name('inventario-rollos');
    Route::get('/inventario-rollos/movimientos', [FabricRollController::class, 'movementsPage'])
        ->middleware('permission:ver telas rollos')
        ->name('inventario-rollos.movimientos');
    Route::get('/programacion-rollos', [FabricRollController::class, 'rollProgrammingReportPage'])
        ->middleware('permission:ver telas rollos')
        ->name('programacion-rollos');
    Route::get('/programacion-rollos/data', [FabricRollController::class, 'rollProgrammingReportData'])
        ->middleware('permission:ver telas rollos')
        ->name('programacion-rollos.data');
    Route::get('/inventario-rollos/data', [FabricRollController::class, 'inventoryRollsData'])->name('inventario-rollos.data');
    Route::get('/inventario-rollos/filter-options', [FabricRollController::class, 'inventoryFilterOptions'])->name('inventario-rollos.filter-options');
    Route::patch('/inventario-rollos/{fabricRoll}/vincular-tela-principal', [FabricRollController::class, 'relinkMainFabric'])
        ->middleware('permission:editar telas rollos')
        ->name('inventario-rollos.relink-main-fabric');
    Route::get('/inventario-rollos/plantilla-cantidades', [FabricRollController::class, 'downloadQuantitiesTemplate'])
        ->middleware('permission:editar telas rollos')
        ->name('inventario-rollos.plantilla-cantidades');
    Route::post('/inventario-rollos/importar-cantidades', [FabricRollController::class, 'importQuantities'])
        ->middleware('permission:editar telas rollos')
        ->name('inventario-rollos.importar-cantidades');
    Route::post('/inventario-rollos/importar-csv', [FabricRollController::class, 'importFromCsv'])
        ->middleware('permission:editar telas rollos')
        ->name('inventario-rollos.importar-csv');
    Route::get('/reportes/sin-relacion', [FabricRollController::class, 'reportSinRelacion'])
        ->middleware('permission:ver telas rollos')
        ->name('reportes.sin-relacion');
    Route::get('/reportes/historial-ajustes', [FabricRollController::class, 'reportHistorialAjustes'])
        ->middleware('permission:ver telas rollos')
        ->name('reportes.historial-ajustes');
    Route::get('/descargue-orden', [FabricRollController::class, 'manualOrdersIndex'])
        ->middleware('permission:ver descargue telas orden')
        ->name('descargue-orden');
    Route::get('/descargue-orden/data', [FabricRollController::class, 'manualOrdersData'])
        ->middleware('permission:ver descargue telas orden')
        ->name('descargue-orden.data');
    Route::get('/descargue-orden/manual/{manualRollProgramming}/historial', [FabricRollController::class, 'manualRollHistory'])
        ->middleware('permission:ver descargue telas orden')
        ->name('descargue-orden.manual.history');
    Route::post('/descargue-orden/manual', [FabricRollController::class, 'programManualRoll'])
        ->middleware('permission:crear descargue telas orden')
        ->name('descargue-orden.manual.store');
    Route::post('/descargue-orden/manual/lote', [FabricRollController::class, 'programManualRollBatch'])
        ->middleware('permission:crear descargue telas orden')
        ->name('descargue-orden.manual.store-batch');
    Route::patch('/descargue-orden/manual/{manualRollProgramming}/descargar', [FabricRollController::class, 'dischargeManualRoll'])
        ->middleware('permission:descargar telas orden')
        ->name('descargue-orden.manual.discharge');
    Route::patch('/descargue-orden/manual/{manualRollProgramming}/descargues/{fabricRollAdjustment}', [FabricRollController::class, 'correctManualDischarge'])
        ->middleware('permission:corregir descargue telas orden')
        ->name('descargue-orden.manual.discharge.correct');
    Route::patch('/descargue-orden/manual/{manualRollProgramming}', [FabricRollController::class, 'updateManualRoll'])
        ->middleware('permission:editar descargue telas orden')
        ->name('descargue-orden.manual.update');
    Route::delete('/descargue-orden/manual/{manualRollProgramming}', [FabricRollController::class, 'destroyManualRoll'])
        ->middleware('permission:eliminar descargue telas orden')
        ->name('descargue-orden.manual.destroy');
    Route::get('/descargue-orden/{manualOrderCode}/lineas', [FabricRollController::class, 'manualOrderLines'])
        ->middleware('permission:ver descargue telas orden')
        ->name('descargue-orden.lines');
    Route::get('/descargue-orden/{manualOrderCode}', [FabricRollController::class, 'manualOrderShow'])
        ->middleware('permission:ver descargue telas orden')
        ->name('descargue-orden.show');
    Route::get('/exportar-rollos', [FabricRollController::class, 'exportRolls'])->name('exportar-rollos');

    Route::prefix('recepcion')->name('reception.')->group(function () {
        Route::get('/', [FabricRollController::class, 'reception'])->name('index');
        Route::get('/data', [FabricRollController::class, 'receptionData'])->name('data');
        Route::get('/facturas/{purchaseDocument}', [FabricRollController::class, 'receptionInvoice'])->name('invoice');
        Route::get('/{invoice}', [FabricRollController::class, 'receptionShow'])->name('show');
        Route::patch('/{invoice}/registrar', [FabricRollController::class, 'registerGeneralReception'])
            ->middleware('permission:aprobar recepcion tela')
            ->name('register');
        Route::patch('/{invoice}/aprobar', [FabricRollController::class, 'approveReception'])
            ->middleware('permission:aprobar recepcion tela')
            ->name('approve');
        Route::patch('/{invoice}/rechazar', [FabricRollController::class, 'rejectReception'])
            ->middleware('permission:rechazar recepcion tela')
            ->name('reject');
    });

    Route::post('/rollos', [FabricRollController::class, 'storeFromReception'])->name('rollos.store')->middleware('permission:crear telas rollos');
    Route::get('/rollos/next-roll-number', [FabricRollController::class, 'nextRollNumber'])->name('rollos.next-roll-number');
    Route::get('/rollos/item/{itemId}', [FabricRollController::class, 'getByInvoiceItem'])->name('rollos.by-item');
    Route::put('/rollos/{fabricRoll}', [FabricRollController::class, 'update'])->name('rollos.update')->middleware('permission:editar telas rollos');
    Route::delete('/rollos/{fabricRoll}', [FabricRollController::class, 'destroy'])->name('rollos.delete')->middleware('permission:eliminar telas rollos');
});
