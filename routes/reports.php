<?php

use App\Http\Controllers\Reports\ReportController;
use Illuminate\Support\Facades\Route;

Route::prefix('informes')->name('informes.')->group(function () {
    Route::get('/', [ReportController::class, 'index'])->name('index');
    Route::get('textil', [ReportController::class, 'textil'])->name('textil');
    Route::get('produccion', [ReportController::class, 'produccion'])->name('produccion');
    Route::get('inventario', [ReportController::class, 'inventario'])->name('inventario');
    Route::get('financiero', [ReportController::class, 'financiero'])->name('financiero');

    // Data endpoints
    Route::get('textil/data', [ReportController::class, 'textilData'])->name('textil.data');
    Route::get('produccion/data', [ReportController::class, 'produccionData'])->name('produccion.data');
    Route::get('produccion/detalle', [ReportController::class, 'produccionDetalleData'])->name('produccion.detalle');
    Route::get('produccion/consolidado', [ReportController::class, 'produccionConsolidadoData'])->name('produccion.consolidado');
    Route::get('inventario/data', [ReportController::class, 'inventarioData'])->name('inventario.data');
    Route::get('financiero/data', [ReportController::class, 'financieroData'])->name('financiero.data');

    // PDF endpoints
    Route::get('textil/pdf', [ReportController::class, 'textilPdf'])->name('textil.pdf');
    Route::get('produccion/pdf', [ReportController::class, 'produccionPdf'])->name('produccion.pdf');
    Route::get('inventario/pdf', [ReportController::class, 'inventarioPdf'])->name('inventario.pdf');
    Route::get('financiero/pdf', [ReportController::class, 'financieroPdf'])->name('financiero.pdf');
});
