<?php

use App\Http\Controllers\ProductSheet\ProductSheetController;
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
    
    Route::prefix('comercial-diseno')->name('comercial-diseno.')->group(function () {
        Route::resource('sabana-productos', ProductSheetController::class)->names([
            'index' => 'sabana-productos.index',
            'create' => 'sabana-productos.create',
            'store' => 'sabana-productos.store',
            'show' => 'sabana-productos.show',
            'edit' => 'sabana-productos.edit',
            'update' => 'sabana-productos.update',
            'destroy' => 'sabana-productos.destroy',
        ]);
        Route::get('product-sheets', [ProductSheetController::class, 'getData'])->name('product-sheets.getData');
    });
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
