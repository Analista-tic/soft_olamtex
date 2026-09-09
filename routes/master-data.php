<?php

use App\Http\Controllers\MasterData\CategoryGroupController;
use App\Http\Controllers\MasterData\CategorySubGroupController;
use App\Http\Controllers\MasterData\ColorController;
use App\Http\Controllers\MasterData\ContactController;
use App\Http\Controllers\MasterData\FabricNameController;
use App\Http\Controllers\MasterData\ItemController;
use App\Http\Controllers\MasterData\MaterialCatalogController;
use App\Http\Controllers\MasterData\ParameterController;
use App\Http\Controllers\MasterData\YarnNameController;
use App\Http\Controllers\Production\SectionController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// === LOOKUPS DE CATALOGOS PARA REFERENCIA ===
Route::prefix('catalogos/categorias')->name('catalogos.categorias.')->group(function () {
    Route::get('grupos', [CategoryGroupController::class, 'getData'])->name('groups');
    Route::get('subgrupos', [CategorySubGroupController::class, 'getData'])->name('subgroups');
});

// === CATEGORIAS ===
Route::prefix('categorias')->name('categorias.')->middleware('permission:ver categorias')->group(function () {
    Route::get('grupos', [CategoryGroupController::class, 'getData'])->name('groups.getData');
    Route::post('grupos', [CategoryGroupController::class, 'store'])->name('groups.store')->middleware('permission:crear categorias');
    Route::put('grupos/{group}', [CategoryGroupController::class, 'update'])->name('groups.update')->middleware('permission:editar categorias');
    Route::get('subgrupos', [CategorySubGroupController::class, 'index'])->name('subgroups.create');
    Route::get('subgrupos-data', [CategorySubGroupController::class, 'getData'])->name('subgroups.getData');
    Route::post('subgrupos', [CategorySubGroupController::class, 'store'])->name('subgroups.store')->middleware('permission:crear categorias');
    Route::put('subgrupos/{subgroup}', [CategorySubGroupController::class, 'update'])->name('subgroups.update')->middleware('permission:editar categorias');
    Route::delete('subgrupos/{subgroup}', [CategorySubGroupController::class, 'destroy'])->name('subgroups.destroy')->middleware('permission:eliminar categorias');
    Route::delete('grupos/{group}', [CategoryGroupController::class, 'destroy'])->name('groups.destroy')->middleware('permission:eliminar categorias');
});

// === CONTACTOS ===
Route::get('contactos/data', [ContactController::class, 'getData'])->name('contactos.getData');
Route::get('contactos/list', [ContactController::class, 'list'])->name('contactos.list');

Route::prefix('contactos')->name('contactos.')->middleware('permission:ver contactos')->group(function () {
    Route::get('/', [ContactController::class, 'index'])->name('index');
    Route::post('/', [ContactController::class, 'store'])->name('store')->middleware('permission:crear contactos');
    Route::get('/{contact}', [ContactController::class, 'show'])->name('show');
    Route::put('/{contact}', [ContactController::class, 'update'])->name('update')->middleware('permission:editar contactos');
    Route::delete('/{contact}', [ContactController::class, 'destroy'])->name('destroy')->middleware('permission:eliminar contactos');
});

// === ITEMS ===
// /data accesible también para roles que gestionan facturas (contabilidad, etc.)
Route::get('/item/data', [ItemController::class, 'getData'])
    ->name('item.data')
    ->middleware('permission:ver inventario telas|ver facturas compra|crear facturas compra');

Route::prefix('item')->name('item.')->middleware('permission:ver inventario telas')->group(function () {
    Route::get('/descargar-plantilla', [ItemController::class, 'downloadTemplate'])->name('descargar-plantilla');
    Route::get('/exportar-excel', [ItemController::class, 'exportExcel'])->name('exportar-excel')->middleware('permission:exportar items');
    Route::get('/', [ItemController::class, 'index'])->name('index');
    Route::post('/carga-masiva', [ItemController::class, 'bulkUpload'])->name('carga-masiva')->middleware('permission:carga masiva items');
    Route::get('/check-duplicate', [ItemController::class, 'checkDuplicate'])->name('check-duplicate');
    Route::get('/search', [ItemController::class, 'search'])->name('search');
    Route::get('/{id}', [ItemController::class, 'show'])->name('show');
    Route::post('/', [ItemController::class, 'store'])->name('store')->middleware('permission:crear inventario telas');
    Route::put('/{id}', [ItemController::class, 'update'])->name('update')->middleware('permission:editar inventario telas');
    Route::delete('/{id}', [ItemController::class, 'destroy'])->name('destroy')->middleware('permission:eliminar inventario telas');
    Route::post('/{id}/toggle-status', [ItemController::class, 'toggleStatus'])->name('toggle-status')->middleware('permission:activar desactivar item');
});

Route::middleware(['auth', 'permission:ver colores'])->group(function () {
    Route::get('/colors', [ColorController::class, 'index']);
    Route::post('/colors', [ColorController::class, 'store'])->middleware('permission:crear colores');
    Route::put('/colors/{color}', [ColorController::class, 'update'])->middleware('permission:editar colores');
    Route::delete('/colors/{color}', [ColorController::class, 'destroy'])->middleware('permission:eliminar colores');
});

// === PARAMETROS ===
Route::prefix('parametros')->name('parametros.')->middleware('permission:ver parametros')->group(function () {
    Route::get('/', [ParameterController::class, 'index'])->name('index');
    Route::get('/data', [ParameterController::class, 'data'])->name('data');
    Route::post('/', [ParameterController::class, 'store'])->name('store')->middleware('permission:editar parametros');
    Route::get('/{id}/deletion-check', [ParameterController::class, 'deletionCheck'])->name('deletion-check')->middleware('permission:editar parametros');
    Route::get('/{id}', [ParameterController::class, 'show'])->name('show');
    Route::put('/{id}', [ParameterController::class, 'update'])->name('update')->middleware('permission:editar parametros');
    Route::delete('/{id}', [ParameterController::class, 'destroy'])->name('destroy')->middleware('permission:editar parametros');
});

// === CATÁLOGO DE MATERIALES (bases y nombres de tela / hilaza) ===
// Pantalla única en Datos Maestros: consolida lo que antes vivía repartido entre el
// diálogo de catálogos operativos de Producción (bases) y esta pantalla (nombres).
Route::middleware(['auth', 'permission:ver catalogo materiales'])->group(function () {
    Route::get('nombres-materiales', fn () => Inertia::render('MasterData/MaterialCatalog/Index'))
        ->name('nombres-materiales.index');

    Route::prefix('catalogos/materiales-base')->name('catalogo-materiales.')->group(function () {
        Route::get('/', [MaterialCatalogController::class, 'index'])->name('index');
        Route::get('/fabric-bases/{fabricBase}/factor-impact', [MaterialCatalogController::class, 'factorImpact'])
            ->name('factor-impact')
            ->middleware('permission:editar catalogo materiales');
        Route::post('/{catalog}', [MaterialCatalogController::class, 'store'])->name('store')->middleware('permission:crear catalogo materiales');
        Route::put('/{catalog}/{id}', [MaterialCatalogController::class, 'update'])->name('update')->middleware('permission:editar catalogo materiales');
        Route::delete('/{catalog}/{id}', [MaterialCatalogController::class, 'destroy'])->name('destroy')->middleware('permission:eliminar catalogo materiales');
    });
});

// Nombres de tela/hilaza: mantienen sus permisos propios (ya existían antes de esta
// fase); la pantalla que los consume ahora vive en MaterialCatalog/Index.vue.
Route::middleware(['auth', 'permission:ver nombres materiales'])->group(function () {
    Route::prefix('nombres-tela')->name('nombres-tela.')->group(function () {
        Route::get('/', [FabricNameController::class, 'index'])->name('index');
        Route::get('/options', [FabricNameController::class, 'options'])->name('options');
        Route::get('/bases-disponibles', [FabricNameController::class, 'baseOptions'])->name('bases-disponibles');
        Route::post('/', [FabricNameController::class, 'store'])->middleware('permission:crear nombres materiales')->name('store');
        Route::put('/{fabricName}', [FabricNameController::class, 'update'])->middleware('permission:editar nombres materiales')->name('update');
        Route::delete('/{fabricName}', [FabricNameController::class, 'destroy'])->middleware('permission:eliminar nombres materiales')->name('destroy');
    });

    Route::prefix('nombres-hilaza')->name('nombres-hilaza.')->group(function () {
        Route::get('/', [YarnNameController::class, 'index'])->name('index');
        Route::get('/options', [YarnNameController::class, 'options'])->name('options');
        Route::get('/bases-disponibles', [YarnNameController::class, 'baseOptions'])->name('bases-disponibles');
        Route::post('/', [YarnNameController::class, 'store'])->middleware('permission:crear nombres materiales')->name('store');
        Route::put('/{yarnName}', [YarnNameController::class, 'update'])->middleware('permission:editar nombres materiales')->name('update');
        Route::delete('/{yarnName}', [YarnNameController::class, 'destroy'])->middleware('permission:eliminar nombres materiales')->name('destroy');
    });
});

// === CATALOGOS PARA CAMPOS PARAMETRIZABLES ===
Route::get('catalogos/sections', [SectionController::class, 'data']);
