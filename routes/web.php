<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

use App\Http\Controllers\ProductSheet\ProductSheetController;
use App\Http\Controllers\ProductSheet\ProgrammerController;
use App\Http\Controllers\Settings\UserController;
use App\Http\Controllers\Settings\RoleController;

Route::get('/', fn() => redirect('login'))->name('home');

Route::get('dashboard', fn() => Inertia::render('Dashboard'))
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('user', function () {
        $user = Auth::user();
        return response()->json([
            'user' => $user,
            'roles' => $user->roles->pluck('name'),
            'permissions' => $user->getAllPermissions()->pluck('name'),
        ]);
    })->name('user');

    Route::get('profile', fn() => Inertia::render('Profile'))->name('profile');

    // === COMERCIAL ===
    Route::prefix('comercial')->name('comercial.')->group(function () {
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
        Route::delete('sabana-productos/{sabana}', [ProductSheetController::class, 'destroy'])
            ->middleware('permission:eliminar sabana productos')
            ->name('sabana-productos.destroy');
    });

    // === DISEÑO ===
    Route::prefix('diseno')->name('diseno.')->group(function () {
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

    // === CONFIGURACIÓN ===
    Route::get('configuracion', fn() => Inertia::render('Configuration/Index'))
        ->middleware('permission:ver usuarios')
        ->name('configuration');

    // === USUARIOS ===
    Route::get('users', [UserController::class, 'getData'])
        ->middleware('permission:ver usuarios')
        ->name('users.getData');

    Route::get('usuarios', [UserController::class, 'index'])
        ->middleware('permission:ver usuarios')
        ->name('usuarios.index');
    Route::post('usuarios', [UserController::class, 'store'])
        ->middleware('permission:crear usuarios')
        ->name('usuarios.store');
    Route::get('usuarios/{usuario}', [UserController::class, 'show'])
        ->middleware('permission:ver usuarios')
        ->name('usuarios.show');
    Route::put('usuarios/{usuario}', [UserController::class, 'update'])
        ->middleware('permission:editar usuarios')
        ->name('usuarios.update');
    Route::delete('usuarios/{usuario}', [UserController::class, 'destroy'])
        ->middleware('permission:eliminar usuarios')
        ->name('usuarios.destroy');

    // === ROLES ===
    Route::get('roles-data', [RoleController::class, 'getData'])
        ->middleware('permission:ver roles')
        ->name('roles.getData');

    Route::get('roles', [RoleController::class, 'index'])
        ->middleware('permission:ver roles')
        ->name('roles.index');
    Route::post('roles', [RoleController::class, 'store'])
        ->middleware('permission:crear roles')
        ->name('roles.store');
    Route::get('roles/{role}', [RoleController::class, 'show'])
        ->middleware('permission:ver roles')
        ->name('roles.show');
    Route::put('roles/{role}', [RoleController::class, 'update'])
        ->middleware('permission:editar roles')
        ->name('roles.update');
    Route::delete('roles/{role}', [RoleController::class, 'destroy'])
        ->middleware('permission:eliminar roles')
        ->name('roles.destroy');
});

require __DIR__ . '/settings.php';
require __DIR__ . '/auth.php';
