<?php

use App\Http\Controllers\MasterData\CategoryGroupController;
use App\Http\Controllers\MasterData\CategorySubGroupController;
use App\Http\Controllers\MasterData\ContactController;
use App\Http\Controllers\MasterData\ItemController;
use App\Http\Controllers\MasterData\ParameterController;
use App\Http\Controllers\Production\SectionController;
use Illuminate\Support\Facades\Route;

// === CATEGORÍAS ===
Route::prefix('categorias')->name('categorias.')->middleware('permission:ver categorias')->group(function () {
    Route::get('grupos', [CategoryGroupController::class, 'getData'])->name('groups.getData');
    Route::post('grupos', [CategoryGroupController::class, 'store'])->name('groups.store')->middleware('permission:crear categorias');
    Route::get('subgrupos', [CategorySubGroupController::class, 'index'])->name('subgroups.create');
    Route::get('subgrupos-data', [CategorySubGroupController::class, 'getData'])->name('subgroups.getData');
    Route::post('subgrupos', [CategorySubGroupController::class, 'store'])->name('subgroups.store')->middleware('permission:crear categorias');
    Route::put('subgrupos/{subgroup}', [CategorySubGroupController::class, 'update'])->name('subgroups.update')->middleware('permission:editar categorias');
    Route::delete('subgrupos/{subgroup}', [CategorySubGroupController::class, 'destroy'])->name('subgroups.destroy')->middleware('permission:eliminar categorias');
    Route::delete('grupos/{group}', [CategoryGroupController::class, 'destroy'])->name('groups.destroy')->middleware('permission:eliminar categorias');
});

// === CONTACTOS ===
Route::prefix('contactos')->name('contactos.')->middleware('permission:ver contactos')->group(function () {
    Route::get('/', [ContactController::class, 'index'])->name('index');
    Route::get('/data', [ContactController::class, 'getData'])->name('getData');
    Route::post('/', [ContactController::class, 'store'])->name('store')->middleware('permission:crear contactos');
    Route::get('/{contact}', [ContactController::class, 'show'])->name('show');
    Route::put('/{contact}', [ContactController::class, 'update'])->name('update')->middleware('permission:editar contactos');
    Route::delete('/{contact}', [ContactController::class, 'destroy'])->name('destroy')->middleware('permission:eliminar contactos');
});

// === ÍTEMS ===
Route::prefix('item')->name('item.')->group(function () {
    Route::get('/descargar-plantilla', [ItemController::class, 'downloadTemplate'])->name('descargar-plantilla');
    Route::get('/exportar-excel', [ItemController::class, 'exportExcel'])->name('exportar-excel')->middleware('permission:exportar items');
    Route::get('/', [ItemController::class, 'index'])->name('index');
    Route::get('/data', [ItemController::class, 'getData'])->name('data');
    Route::post('/carga-masiva', [ItemController::class, 'bulkUpload'])->name('carga-masiva')->middleware('permission:carga masiva items');
    Route::get('/check-duplicate', [ItemController::class, 'checkDuplicate'])->name('check-duplicate');
    Route::get('/search', [ItemController::class, 'search'])->name('search');
    Route::get('/{id}', [ItemController::class, 'show'])->name('show');
    Route::post('/', [ItemController::class, 'store'])->name('store');
    Route::put('/{id}', [ItemController::class, 'update'])->name('update');
    Route::delete('/{id}', [ItemController::class, 'destroy'])->name('destroy');
    Route::post('/{id}/toggle-status', [ItemController::class, 'toggleStatus'])->name('toggle-status')->middleware('permission:activar desactivar item');
});

// === PARÁMETROS ===
Route::prefix('parametros')->name('parametros.')->middleware('permission:ver parametros')->group(function () {
    Route::get('/', [ParameterController::class, 'index'])->name('index');
    Route::get('/data', [ParameterController::class, 'data'])->name('data');
    Route::post('/', [ParameterController::class, 'store'])->name('store')->middleware('permission:editar parametros');
    Route::get('/{id}', [ParameterController::class, 'show'])->name('show');
    Route::put('/{id}', [ParameterController::class, 'update'])->name('update')->middleware('permission:editar parametros');
    Route::delete('/{id}', [ParameterController::class, 'destroy'])->name('destroy')->middleware('permission:editar parametros');
});

// === CATÁLOGOS PARA CAMPOS PARAMETRIZABLES ===
Route::get('catalogos/sections', [SectionController::class, 'data']);
