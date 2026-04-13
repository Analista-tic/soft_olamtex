<?php

use App\Http\Controllers\MasterData\CategoryGroupController;
use App\Http\Controllers\MasterData\CategorySubGroupController;
use App\Http\Controllers\MasterData\ContactController;
use App\Http\Controllers\MasterData\ItemController;
use App\Http\Controllers\MasterData\ParameterController;
use App\Http\Controllers\Production\SectionController;
use Illuminate\Support\Facades\Route;

// === CATEGORÍAS ===
Route::prefix('categorias')->name('categorias.')->group(function () {
    Route::get('grupos', [CategoryGroupController::class, 'getData'])->name('groups.getData');
    Route::post('grupos', [CategoryGroupController::class, 'store'])->name('groups.store');
    Route::get('subgrupos', [CategorySubGroupController::class, 'index'])->name('subgroups.create');
    Route::get('subgrupos-data', [CategorySubGroupController::class, 'getData'])->name('subgroups.getData');
    Route::post('subgrupos', [CategorySubGroupController::class, 'store'])->name('subgroups.store');
    Route::put('subgrupos/{subgroup}', [CategorySubGroupController::class, 'update'])->name('subgroups.update');
    Route::delete('subgrupos/{subgroup}', [CategorySubGroupController::class, 'destroy'])->name('subgroups.destroy');
    Route::delete('grupos/{group}', [CategoryGroupController::class, 'destroy'])->name('groups.destroy');
});

// === CONTACTOS ===
Route::prefix('contactos')->name('contactos.')->group(function () {
    Route::get('/', [ContactController::class, 'index'])->name('index');
    Route::get('/data', [ContactController::class, 'getData'])->name('getData');
    Route::post('/', [ContactController::class, 'store'])->name('store');
    Route::get('/{contact}', [ContactController::class, 'show'])->name('show');
    Route::put('/{contact}', [ContactController::class, 'update'])->name('update');
    Route::delete('/{contact}', [ContactController::class, 'destroy'])->name('destroy');
});

// === ÍTEMS ===
Route::prefix('item')->name('item.')->group(function () {
    Route::get('/descargar-plantilla', [ItemController::class, 'downloadTemplate'])->name('descargar-plantilla');
    Route::get('/exportar-excel', [ItemController::class, 'exportExcel'])->name('exportar-excel');
    Route::get('/', [ItemController::class, 'index'])->name('index');
    Route::get('/data', [ItemController::class, 'getData'])->name('data');
    Route::post('/carga-masiva', [ItemController::class, 'bulkUpload'])->name('carga-masiva');
    Route::get('/check-duplicate', [ItemController::class, 'checkDuplicate'])->name('check-duplicate');
    Route::get('/search', [ItemController::class, 'search'])->name('search');
    Route::get('/{id}', [ItemController::class, 'show'])->name('show');
    Route::post('/', [ItemController::class, 'store'])->name('store');
    Route::put('/{id}', [ItemController::class, 'update'])->name('update');
    Route::delete('/{id}', [ItemController::class, 'destroy'])->name('destroy');
    Route::post('/{id}/toggle-status', [ItemController::class, 'toggleStatus'])->name('toggle-status');
});

// === PARÁMETROS ===
Route::prefix('parametros')->name('parametros.')->group(function () {
    Route::get('/', [ParameterController::class, 'index'])->name('index');
    Route::get('/data', [ParameterController::class, 'data'])->name('data');
    Route::post('/', [ParameterController::class, 'store'])->name('store');
    Route::get('/{id}', [ParameterController::class, 'show'])->name('show');
    Route::put('/{id}', [ParameterController::class, 'update'])->name('update');
    Route::delete('/{id}', [ParameterController::class, 'destroy'])->name('destroy');
});

// === CATÁLOGOS PARA CAMPOS PARAMETRIZABLES ===
Route::get('catalogos/sections', [SectionController::class, 'data']);
