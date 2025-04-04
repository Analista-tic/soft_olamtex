<?php

use App\Http\Controllers\ProductSheet\ProductSheetController;
use App\Http\Controllers\ProductSheet\ProgrammerController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::get('dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('profile', function () {
        return Inertia::render('Profile');
    })->name('profile');
    
    Route::prefix('comercial')->name('comercial.')->group(function () {
        Route::resource('sabana-productos', ProductSheetController::class)->names([
            'index' => 'sabana-productos.index',
            'store' => 'sabana-productos.store',
            'show' => 'sabana-productos.show',
            'update' => 'sabana-productos.update',
            'destroy' => 'sabana-productos.destroy',
        ]);
        Route::get('product-sheets', [ProductSheetController::class, 'getData'])->name('product-sheets.getData');
    });
    
    Route::prefix('diseno')->name('diseno.')->group(function () {
        Route::resource('programador', ProgrammerController::class)->names([
            'index' => 'programador.index',
            'store' => 'programador.store',
            'show' => 'programador.show',
            'update' => 'programador.update',
            'destroy' => 'programador.destroy',
        ]);
        Route::get('programmers', [ProgrammerController::class, 'getData'])->name('programmers.getData');
    });
});


require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
