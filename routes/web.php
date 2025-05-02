<?php

use App\Http\Controllers\Common\ContactController;
use App\Http\Controllers\Inventory\FabricOrderController;
use App\Http\Controllers\Settings\BaseFabricsController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

use App\Http\Controllers\ProductSheet\ProductSheetController;
use App\Http\Controllers\ProductSheet\ProgrammerController;
use App\Http\Controllers\Settings\UserController;
use App\Http\Controllers\Settings\RoleController;
use App\Http\Controllers\Settings\FabricProviderController;
use App\Http\Controllers\Common\CategoryGroupController;
use App\Http\Controllers\Common\CategorySubGroupController;

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

    // === INVENTARIO ===
    // Route::prefix('inventario')->name('inventario.')->group(function () {
    //     Route::prefix('telas')->name('telas.')->middleware('permission:ver pedidos telas')->group(function () {
    //         Route::get('/', [FabricOrderController::class, 'index'])->name('index'); // Vista principal (inventario)
    //         Route::get('/recepcion', [FabricOrderController::class, 'index'])->name('recepcion'); // Subvista de recepción
    //         Route::get('/pedidos', [FabricOrderController::class, 'index'])->name('pedidos'); // Subvista de pedidos
    //     });
    // });

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

    Route::prefix('categorias')->group(function () {
        // Rutas para CategoryGroup
        Route::post('grupos', [CategoryGroupController::class, 'store'])->name('groups.store');

        // Rutas para CategorySubGroup
        Route::get('subgrupos', [CategorySubGroupController::class, 'index'])->name('subgroups.create');
        Route::get('subgrupos-data', [CategorySubGroupController::class, 'getData'])->name('subgroups.getData');
        Route::post('subgrupos', [CategorySubGroupController::class, 'store'])->name('subgroups.store');
        Route::put('subgrupos/{subgroup}', [CategorySubGroupController::class, 'update'])->name('subgroups.update');
        Route::delete('subgrupos/{subgroup}', [CategorySubGroupController::class, 'destroy'])->name('subgroups.destroy'); // Nueva ruta para eliminar
    });

    // === CONTACTOS ===
    Route::prefix('contactos')->group(function () {
        Route::get('/', [ContactController::class, 'index'])->name('index');
        Route::get('/data', [ContactController::class, 'getData'])->name('getData'); // Asegurarse de que esta ruta exista
        Route::post('/', [ContactController::class, 'store'])->name('store');
        Route::get('/{contact}', [ContactController::class, 'show'])->name('show');
        Route::put('/{contact}', [ContactController::class, 'update'])->name('update');
        Route::delete('/{contact}', [ContactController::class, 'destroy'])->name('destroy');
    });
});

Route::get('/validar-nit/{nit}', function ($nit) {
    try {
        // Validar formato del NIT
        if (!preg_match('/^\d{5,15}$/', $nit)) {
            return response()->json(['error' => 'Formato de NIT inválido'], 400);
        }

        // Consultar RUES con timeout y headers
        $response = Http::timeout(20)
            ->withHeaders(['User-Agent' => 'Laravel RUES Client'])
            ->withoutVerifying()
            ->get("https://www.rues.org.co/RM/ConsultaNIT_json?nit=$nit");

        // Verificar errores HTTP
        if ($response->failed()) {
            return response()->json([
                'error' => 'RUES no respondió correctamente',
                'details' => $response->body() ?? 'Sin detalles',
            ], 502);
        }

        $data = $response->json();

        // Si el NIT no existe
        if (empty($data['NIT'])) {
            return response()->json(['error' => 'NIT no encontrado'], 404);
        }

        // Respuesta exitosa
        return response()->json([
            'nit' => $data['NIT'],
            'razon_social' => $data['RazonSocial'] ?? 'No disponible',
            'estado' => $data['Estado'] ?? 'No disponible',
            'camara_comercio' => $data['CamaraComercio'] ?? 'No disponible',
        ]);

    } catch (Exception $e) {
        return response()->json([
            'error' => 'Error interno al consultar RUES',
            'message' => $e->getMessage(), // Solo para desarrollo
        ], 500);
    }
});


require __DIR__ . '/settings.php';
require __DIR__ . '/auth.php';
