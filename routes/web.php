<?php

use App\Http\Controllers\ProductSheet\ProductSheetController;
use App\Http\Controllers\ProductSheet\ProgrammerController;
use App\Http\Controllers\Settings\UserController;
use App\Http\Controllers\Settings\RoleController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::get('dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth', 'verified'])->group(function () {
    // obtener informacion del usuario
    Route::get('user', function () {
        $user = Auth::user();
        return response()->json([
            'user' => $user,
            'roles' => $user->roles->pluck('name'), // Roles del usuario
            'permissions' => $user->getAllPermissions()->pluck('name'), // Permisos del usuario
        ]);
    })->name('user');

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

    Route::get('configuracion', function () {
        return Inertia::render('Configuration/Index');
    })->middleware(['auth', 'verified'])->name('configuration');

    Route::resource('usuarios', UserController::class);
    Route::get('users', [UserController::class, 'getData'])->name('users.getData');

    Route::resource('roles', RoleController::class);
    Route::get('roles-data', [RoleController::class, 'getData'])->name('roles.getData');
});


require __DIR__ . '/settings.php';
require __DIR__ . '/auth.php';
