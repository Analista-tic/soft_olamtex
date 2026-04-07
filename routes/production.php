<?php

use App\Http\Controllers\MasterData\ParameterController;
use App\Http\Controllers\Production\BatchControlController;
use App\Http\Controllers\Production\MachineController;
use App\Http\Controllers\Production\OperationController;
use App\Http\Controllers\Production\ProductionOrderController;
use App\Http\Controllers\Production\ProductOperationListController;
use App\Http\Controllers\Production\ProductSheetController;
use App\Http\Controllers\Production\ProgrammerController;
use App\Http\Controllers\Production\SectionController;
use App\Http\Controllers\Production\TechnicalDataSheetAdditionalInfoController;
use App\Http\Controllers\Production\TechnicalDataSheetController;
use App\Http\Controllers\Production\TechnicalDataSheetRollsController;
use App\Http\Controllers\Production\TechnicalDataSheetSuppliesController;
use App\Http\Controllers\Production\TechnicalDataSheetThreadsController;
use Illuminate\Support\Facades\Route;

// === PRODUCCIÓN ===
Route::prefix('produccion')->name('produccion.')->group(function () {
    Route::get('ficha-tecnica', [TechnicalDataSheetController::class, 'index'])
        ->name('ficha-tecnica.index');
    Route::get('ficha-tecnica/opciones', [TechnicalDataSheetController::class, 'options'])
        ->name('ficha-tecnica.options');
    Route::get('ficha-tecnica/{opId}/basica', [TechnicalDataSheetController::class, 'basicInfo'])
        ->name('ficha-tecnica.basicInfo');
    Route::get('ficha-tecnica/{opId}/listado-operacional', [TechnicalDataSheetController::class, 'operationalList'])
        ->name('ficha-tecnica.operational-list');
    Route::get('ficha-tecnica/{opId}/listado-operacional-detallado', [TechnicalDataSheetController::class, 'operationalListDetail'])
        ->name('ficha-tecnica.operational-list-detail');
    Route::get('ficha-tecnica/listados-operacionales/opciones', [TechnicalDataSheetController::class, 'operationalListOptions'])
        ->name('ficha-tecnica.operational-list-options');
    Route::post('ficha-tecnica/{opId}/listado-operacional', [TechnicalDataSheetController::class, 'assignOperationalList'])
        ->name('ficha-tecnica.assign-operational-list');
    Route::get('ficha-tecnica/{opId}/procesos', [TechnicalDataSheetController::class, 'processesSummary'])
        ->name('ficha-tecnica.processes-summary');
    Route::post('ficha-tecnica/{opId}/procesos', [TechnicalDataSheetController::class, 'saveProcesses'])
        ->name('ficha-tecnica.processes.save');
    Route::get('ficha-tecnica/{opId}/costos', [TechnicalDataSheetController::class, 'costsSummary'])
        ->name('ficha-tecnica.costs-summary');
    Route::get('ficha-tecnica/{opId}/imprimir', [TechnicalDataSheetController::class, 'printSummary'])
        ->name('ficha-tecnica.print-summary');
    Route::get('ficha-tecnica/{opId}/imprimir-h', [TechnicalDataSheetController::class, 'printSummaryH'])
        ->name('ficha-tecnica.print-summary-h');
    Route::get('ficha-tecnica/{opId}/materia-prima', [TechnicalDataSheetController::class, 'rawMaterialsSummary'])
        ->name('ficha-tecnica.raw-materials.index');
    Route::put('ficha-tecnica/{opId}/materia-prima', [TechnicalDataSheetController::class, 'saveRawMaterials'])
        ->name('ficha-tecnica.raw-materials.save');
    Route::get('ficha-tecnica/{opId}/insumos', [TechnicalDataSheetSuppliesController::class, 'index'])
        ->name('ficha-tecnica.supplies.index');
    Route::get('ficha-tecnica/insumos/opciones', [TechnicalDataSheetSuppliesController::class, 'itemOptions'])
        ->name('ficha-tecnica.supplies.item-options');
    Route::get('insumos/items/{itemId}/last-purchase', [TechnicalDataSheetSuppliesController::class, 'lastPurchaseForItem'])
        ->middleware('permission:ver gastos|ver sabana productos|ver programador')
        ->name('ficha-tecnica.supplies.last-purchase');
    Route::get('insumos/items/{itemId}/meta', [TechnicalDataSheetSuppliesController::class, 'itemMeta'])
        ->middleware('permission:ver gastos|ver sabana productos|ver programador')
        ->name('ficha-tecnica.supplies.item-meta');
    Route::post('ficha-tecnica/{opId}/insumos', [TechnicalDataSheetSuppliesController::class, 'upsert'])
        ->name('ficha-tecnica.supplies.upsert');
    Route::put('ficha-tecnica/{opId}/insumos', [TechnicalDataSheetSuppliesController::class, 'saveAll'])
        ->name('ficha-tecnica.supplies.save-all');
    Route::delete('ficha-tecnica/{opId}/insumos/{supplyId}', [TechnicalDataSheetSuppliesController::class, 'destroy'])
        ->name('ficha-tecnica.supplies.destroy');
    Route::get('ficha-tecnica/{opId}/rollos', [TechnicalDataSheetRollsController::class, 'index'])
        ->name('ficha-tecnica.rolls.index');
    Route::put('ficha-tecnica/{opId}/rollos', [TechnicalDataSheetRollsController::class, 'saveAll'])
        ->name('ficha-tecnica.rolls.save-all');
    Route::post('ficha-tecnica/{opId}/rollos/params', [TechnicalDataSheetRollsController::class, 'saveParams'])
        ->name('ficha-tecnica.rolls.params');
    Route::post('ficha-tecnica/{opId}/rollos/lines', [TechnicalDataSheetRollsController::class, 'upsertLine'])
        ->name('ficha-tecnica.rolls.lines.upsert');
    Route::delete('ficha-tecnica/{opId}/rollos/lines/{lineId}', [TechnicalDataSheetRollsController::class, 'destroyLine'])
        ->name('ficha-tecnica.rolls.lines.destroy');
    Route::get('ficha-tecnica/{opId}/hilos', [TechnicalDataSheetThreadsController::class, 'index'])
        ->name('ficha-tecnica.threads.index');
    Route::put('ficha-tecnica/{opId}/hilos', [TechnicalDataSheetThreadsController::class, 'saveAll'])
        ->name('ficha-tecnica.threads.save-all');
    Route::post('ficha-tecnica/{opId}/hilos/params', [TechnicalDataSheetThreadsController::class, 'saveParams'])
        ->name('ficha-tecnica.threads.params');
    Route::post('ficha-tecnica/{opId}/hilos/lines', [TechnicalDataSheetThreadsController::class, 'upsertLine'])
        ->name('ficha-tecnica.threads.lines.upsert');
    Route::delete('ficha-tecnica/{opId}/hilos/lines/{lineId}', [TechnicalDataSheetThreadsController::class, 'destroyLine'])
        ->name('ficha-tecnica.threads.lines.destroy');
    Route::get('ficha-tecnica/{opId}/info-adicional', [TechnicalDataSheetAdditionalInfoController::class, 'show'])
        ->name('ficha-tecnica.additional-info.show');
    Route::post('ficha-tecnica/{opId}/info-adicional', [TechnicalDataSheetAdditionalInfoController::class, 'store'])
        ->name('ficha-tecnica.additional-info.store');

    // Sábana de productos
    Route::get('product-sheets', [ProductSheetController::class, 'getData'])
        ->middleware('permission:ver sabana productos')
        ->name('product-sheets.getData');
    Route::get('sabana-productos', [ProductSheetController::class, 'index'])
        ->middleware('permission:ver sabana productos')
        ->name('sabana-productos.index');
    Route::post('sabana-productos', [ProductSheetController::class, 'store'])
        ->middleware('permission:crear sabana productos')
        ->name('sabana-productos.store');
    Route::get('sabana-productos/{sabana}', [ProductSheetController::class, 'show'])
        ->middleware('permission:ver sabana productos')
        ->name('sabana-productos.show');
    Route::put('sabana-productos/{sabana}', [ProductSheetController::class, 'update'])
        ->middleware('permission:editar sabana productos')
        ->name('sabana-productos.update');
    Route::post('sabana-productos/{sabana}/convert', [ProductSheetController::class, 'convertToProduct'])
        ->middleware('permission:editar sabana productos')
        ->name('sabana-productos.convertToProduct');
    Route::get('product-sheets/parameters', [ProductSheetController::class, 'parameters'])
        ->middleware('permission:ver sabana productos')
        ->name('product-sheets.parameters');
    Route::delete('sabana-productos/{sabana}', [ProductSheetController::class, 'destroy'])
        ->middleware('permission:eliminar sabana productos')
        ->name('sabana-productos.destroy');

    // Base de operaciones
    Route::get('base-operaciones', [OperationController::class, 'indexView'])
        ->middleware('permission:ver listados operacionales|ver sabana productos')
        ->name('base-operaciones.index');

    // Listados operacionales
    Route::get('listados-operacionales', [ProductOperationListController::class, 'indexView'])
        ->middleware('permission:ver listados operacionales|ver sabana productos')
        ->name('listados-operacionales.index');

    Route::prefix('listados-operacionales')->name('listados-operacionales.')->group(function () {
        Route::get('/data', [ProductOperationListController::class, 'index'])
            ->middleware('permission:ver listados operacionales|ver sabana productos')
            ->name('data');
        Route::get('/options', [ProductOperationListController::class, 'options'])
            ->middleware('permission:ver listados operacionales|ver sabana productos')
            ->name('options');
        Route::post('/', [ProductOperationListController::class, 'store'])
            ->middleware('permission:crear listados operacionales|crear sabana productos')
            ->name('store');
        Route::post('/bulk', [ProductOperationListController::class, 'storeBulk'])
            ->middleware('permission:crear listados operacionales|crear sabana productos')
            ->name('bulk');
        Route::put('/{listItem}', [ProductOperationListController::class, 'update'])
            ->middleware('permission:editar listados operacionales|editar sabana productos')
            ->name('update');
        Route::delete('/{listItem}', [ProductOperationListController::class, 'destroy'])
            ->middleware('permission:eliminar listados operacionales|eliminar sabana productos')
            ->name('destroy');
        Route::post('/bulk-delete', [ProductOperationListController::class, 'destroyBulk'])
            ->middleware('permission:eliminar listados operacionales|eliminar sabana productos')
            ->name('destroy-bulk');
        Route::post('/copiar-desde-producto', [ProductOperationListController::class, 'copyFromProduct'])
            ->middleware('permission:crear listados operacionales|crear sabana productos')
            ->name('copy-from-product');
        Route::get('/{listItem}/history', [ProductOperationListController::class, 'history'])
            ->middleware('permission:ver listados operacionales|ver sabana productos')
            ->name('history');
    });

    // Parámetros de producción
    Route::prefix('parametros')->name('parametros.')->group(function () {
        Route::get('/data', [ParameterController::class, 'data'])
            ->middleware('permission:ver listados operacionales|ver sabana productos')
            ->name('data');
    });

    // Secciones de operaciones
    Route::prefix('secciones')->name('secciones.')->group(function () {
        Route::get('/data', [SectionController::class, 'data'])->name('data');
        Route::post('/', [SectionController::class, 'store'])
            ->middleware('permission:crear listados operacionales|crear sabana productos')
            ->name('store');
    });

    // Operaciones
    Route::prefix('operaciones')->name('operaciones.')->group(function () {
        Route::get('/data', [OperationController::class, 'data'])->name('data');
        Route::get('/metadata', [OperationController::class, 'metadata'])->name('metadata');
        Route::get('/paginated', [OperationController::class, 'paginated'])->name('paginated');
        Route::post('/', [OperationController::class, 'store'])
            ->middleware('permission:crear listados operacionales|crear sabana productos')
            ->name('store');
        Route::put('/{operation}', [OperationController::class, 'update'])
            ->middleware('permission:editar listados operacionales|editar sabana productos')
            ->name('update');
        Route::delete('/{operation}', [OperationController::class, 'destroy'])
            ->middleware('permission:eliminar listados operacionales|eliminar sabana productos')
            ->name('destroy');
        Route::post('/{operation}/update-standard-time', [OperationController::class, 'updateStandardTime'])
            ->middleware('permission:editar listados operacionales|editar sabana productos')
            ->name('update-standard-time');
        Route::get('/{operation}/audit-log', [OperationController::class, 'auditLog'])
            ->middleware('permission:ver listados operacionales|ver sabana productos')
            ->name('audit-log');
    });

    // Máquinas
    Route::prefix('maquinas')->name('maquinas.')->group(function () {
        Route::get('/data', [MachineController::class, 'data'])->name('data');
        Route::post('/', [MachineController::class, 'store'])
            ->middleware('permission:crear listados operacionales|crear sabana productos')
            ->name('store');
    });
});

// Órdenes de producción
Route::get('produccion/ordenes-produccion', [ProductionOrderController::class, 'index'])
    ->middleware('permission:ver sabana productos')
    ->name('production.orders.index');
Route::get('produccion/ordenes-produccion/{order}/detalle', [ProductionOrderController::class, 'show'])
    ->middleware('permission:ver sabana productos')
    ->name('production.orders.show');

// Programador comercial
Route::prefix('comercial')->name('comercial.')->group(function () {
    Route::get('programmers', [ProgrammerController::class, 'getData'])
        ->middleware('permission:ver programador')
        ->name('programmers.getData');
    Route::get('programador', [ProgrammerController::class, 'index'])
        ->middleware('permission:ver programador')
        ->name('programador.index');
    Route::post('programador', [ProgrammerController::class, 'store'])
        ->middleware('permission:crear programador')
        ->name('programador.store');
    Route::get('programador/{programador}', [ProgrammerController::class, 'show'])
        ->middleware('permission:ver programador')
        ->name('programador.show');
    Route::put('programador/{programador}', [ProgrammerController::class, 'update'])
        ->middleware('permission:editar programador')
        ->name('programador.update');
    Route::delete('programador/{programador}', [ProgrammerController::class, 'destroy'])
        ->middleware('permission:eliminar programador')
        ->name('programador.destroy');
});

// Control de lotes
Route::prefix('control-de-lotes')->name('control-de-lotes.')->group(function () {
    Route::get('/', [BatchControlController::class, 'index'])->name('index');
    Route::get('/data', [BatchControlController::class, 'getData'])->name('data');
    Route::get('/opciones-operaciones', [BatchControlController::class, 'catalogOptions'])->name('catalog-options');
    Route::post('/{order}/entregar', [BatchControlController::class, 'deliver'])->name('deliver');
    Route::patch('/prioridades', [BatchControlController::class, 'updatePriorities'])->name('update-priorities');
    Route::patch('/{order}/fecha-estimada', [BatchControlController::class, 'updateEstimatedDate'])->name('update-estimated-date');
    Route::get('/{order}/historial', [BatchControlController::class, 'deliveryHistory'])->name('delivery-history');
    Route::get('/{order}/consumo-telas-corte', [BatchControlController::class, 'getCutFabricConsumption'])->name('cut-fabric-consumption');
    Route::put('/{order}/consumo-telas-corte', [BatchControlController::class, 'saveCutFabricConsumption'])->name('save-cut-fabric-consumption');

    // Novedades / Devoluciones
    Route::post('/{order}/novedades', [BatchControlController::class, 'reportNovelty'])->name('report-novelty');
    Route::patch('/{order}/novedades/{novelty}/resolver', [BatchControlController::class, 'resolveNovelty'])->name('resolve-novelty');
    Route::get('/{order}/novedades', [BatchControlController::class, 'novelties'])->name('novelties');

    // Operaciones de ficha técnica (muestras)
    Route::put('/{order}/operaciones', [BatchControlController::class, 'syncOperations'])->name('sync-operations');

    // Logística: salida de insumos
    Route::get('/{order}/logistica-insumos', [BatchControlController::class, 'getLogisticsSupplies'])->name('logistics-supplies');
    Route::post('/{order}/logistica-salida-insumos', [BatchControlController::class, 'processLogisticsSupplyExit'])->name('logistics-supply-exit');
});
