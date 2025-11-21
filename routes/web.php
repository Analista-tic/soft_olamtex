<?php

use App\Http\Controllers\Common\ContactController;
use App\Http\Controllers\Expenses\PurchaseInvoiceController;
use App\Http\Controllers\Expenses\PurchaseOrderController;
use App\Http\Controllers\ProductSheet\BrandController;
use App\Http\Controllers\ProductSheet\FabricBaseController;
use App\Http\Controllers\ProductSheet\GroupController;
use App\Http\Controllers\ProductSheet\SectionController;
use App\Http\Controllers\ProductSheet\WorldController;
use App\Http\Controllers\ProductSheet\YarnBaseController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

use App\Http\Controllers\ProductSheet\ProductSheetController;
use App\Http\Controllers\ProductSheet\ProgrammerController;
use App\Http\Controllers\Settings\UserController;
use App\Http\Controllers\Settings\RoleController;
use App\Http\Controllers\Common\CategoryGroupController;
use App\Http\Controllers\Common\CategorySubGroupController;
use App\Http\Controllers\Common\WarehouseController;
use App\Http\Controllers\Common\ColorController;
use App\Http\Controllers\Inventory\ArticleController;
use App\Http\Controllers\Settings\RetentionController;
use App\Http\Controllers\Common\TaxController;

// Página principal
Route::get('/', fn() => redirect('login'))->name('home');

// Dashboard
Route::get('dashboard', fn() => Inertia::render('Dashboard'))
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

// Rutas protegidas por autenticación y verificación
Route::middleware(['auth', 'verified'])->group(function () {
    // Usuario autenticado
    Route::get('user', function () {
        $user = Auth::user();
        return response()->json([
            'user' => $user,
            'roles' => $user->roles->pluck('name'),
            'permissions' => $user->getAllPermissions()->pluck('name'),
        ]);
    })->name('user');

    // Perfil
    Route::get('profile', fn() => Inertia::render('Profile'))->name('profile');

    // === COMERCIAL ===
    Route::prefix('diseno')->name('diseno.')->group(function () {
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

    // === CATEGORIAS ===
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

    // === WAREHOUSES ===
    Route::prefix('bodegas')->name('warehouses.')->group(function () {
        Route::get('/', [WarehouseController::class, 'index'])->name('index');
        Route::get('/data', [WarehouseController::class, 'getData'])->name('getData');
        Route::post('/', [WarehouseController::class, 'store'])->name('store');
        Route::get('/{warehouse}', [WarehouseController::class, 'show'])->name('show');
        Route::get('/{id}/warehouses', [ArticleController::class, 'getWarehouses'])->name('warehouses');
        Route::put('/{warehouse}', [WarehouseController::class, 'update'])->name('update');
        Route::delete('/{warehouse}', [WarehouseController::class, 'destroy'])->name('destroy');
    });

    // === COLORS ===
    Route::prefix('colores')->name('colors.')->group(function () {
        Route::get('/', [ColorController::class, 'index'])->name('index');
        Route::get('/data', [ColorController::class, 'getData'])->name('getData');
        Route::post('/', [ColorController::class, 'store'])->name('store');
        Route::get('/{color}', [ColorController::class, 'show'])->name('show');
        Route::put('/{color}', [ColorController::class, 'update'])->name('update');
        Route::delete('/{color}', [ColorController::class, 'destroy'])->name('destroy');
    });

    // === ARTICLES ===
    Route::prefix('articulos')->name('articulos.')->group(function () {
        Route::get('/descargar-plantilla', [ArticleController::class, 'downloadTemplate'])->name('descargar-plantilla');
        Route::get('/exportar-excel', [ArticleController::class, 'exportExcel'])->name('exportar-excel');
        Route::get('/', [ArticleController::class, 'index'])->name('index');
        Route::get('/data', [ArticleController::class, 'getData'])->name('data');
        Route::post('/carga-masiva', [ArticleController::class, 'bulkUpload'])->name('carga-masiva');
        Route::get('/{id}', [ArticleController::class, 'show'])->name('show');
        Route::post('/', [ArticleController::class, 'store'])->name('store');
        Route::put('/{id}', [ArticleController::class, 'update'])->name('update');
        Route::delete('/{id}', [ArticleController::class, 'destroy'])->name('destroy');
        Route::post('/{id}/toggle-status', [ArticleController::class, 'toggleStatus'])->name('toggle-status');
    });

    // === FABRIC ROLLS ===
    Route::prefix('rollos-tela')->name('fabric-rolls.')->group(function () {
        Route::get('/', [App\Http\Controllers\Inventory\FabricRollController::class, 'index'])->name('index');
        Route::get('/crear', [App\Http\Controllers\Inventory\FabricRollController::class, 'create'])->name('create');
        Route::get('/data', [App\Http\Controllers\Inventory\FabricRollController::class, 'getData'])->name('getData');
        Route::post('/', [App\Http\Controllers\Inventory\FabricRollController::class, 'store'])->name('store');
        Route::get('/{fabricRoll}', [App\Http\Controllers\Inventory\FabricRollController::class, 'show'])->name('show');
        Route::get('/{fabricRoll}/editar', [App\Http\Controllers\Inventory\FabricRollController::class, 'edit'])->name('edit');
        Route::put('/{fabricRoll}', [App\Http\Controllers\Inventory\FabricRollController::class, 'update'])->name('update');
        Route::delete('/{fabricRoll}', [App\Http\Controllers\Inventory\FabricRollController::class, 'destroy'])->name('destroy');
        Route::patch('/{fabricRoll}/update-output', [App\Http\Controllers\Inventory\FabricRollController::class, 'updateOutput'])->name('update-output');
    });

    // === GASTOS (AGRUPADO) ===
    Route::prefix('gastos')->name('gastos.')->group(function () {
        // === ORDENES DE COMPRA ===
        Route::prefix('ordenes-compra')->name('purchase-orders.')->group(function () {
            Route::get('/', [PurchaseOrderController::class, 'index'])->name('index');
            Route::get('/data', [PurchaseOrderController::class, 'getData'])->name('getData');
            Route::post('/', [PurchaseOrderController::class, 'store'])->name('store');
            Route::get('/{purchaseOrder}', [PurchaseOrderController::class, 'show'])->name('show');
            Route::put('/{purchaseOrder}', [PurchaseOrderController::class, 'update'])->name('update');
            Route::delete('/{purchaseOrder}', [PurchaseOrderController::class, 'destroy'])->name('destroy');
            Route::post('/{purchaseOrder}/enviar-correo-pdf', [PurchaseOrderController::class, 'enviarCorreoPdf']);
            Route::post('/{purchaseOrder}/convert-to-invoice', [PurchaseOrderController::class, 'convertToInvoice']);
        });

        // === FACTURAS DE COMPRA ===
        Route::prefix('facturas-compra')->name('purchase-invoices.')->group(function () {
            Route::get('/', [PurchaseInvoiceController::class, 'index'])->name('index');
            Route::get('/data', [PurchaseInvoiceController::class, 'getData'])->name('getData');
            Route::post('/', [PurchaseInvoiceController::class, 'store'])->name('store');
            Route::get('/{purchaseInvoice}', [PurchaseInvoiceController::class, 'show'])->name('show');
            Route::put('/{purchaseInvoice}', [PurchaseInvoiceController::class, 'update'])->name('update');
            Route::delete('/{purchaseInvoice}', [PurchaseInvoiceController::class, 'destroy'])->name('destroy');
        });
    });

    // === Catálogos para campos parametrizables ===
    Route::get('catalogos/sections', [SectionController::class, 'data']);
    Route::get('catalogos/groups', [GroupController::class, 'data']);
    Route::get('catalogos/brands', [BrandController::class, 'data']);
    Route::get('catalogos/worlds', [WorldController::class, 'data']);
    Route::get('catalogos/fabric-bases', [FabricBaseController::class, 'data']);
    Route::get('catalogos/yarn-bases', [YarnBaseController::class, 'data']);

    // === RETENCIONES ===
    Route::prefix('retenciones')->name('retenciones.')->group(function () {
        Route::get('/', [RetentionController::class, 'index'])->name('index');
        Route::get('/data', [RetentionController::class, 'data'])->name('data');
        Route::get('/{id}', [RetentionController::class, 'show'])->name('show');
        Route::post('/', [RetentionController::class, 'store'])->name('store');
        Route::put('/{id}', [RetentionController::class, 'update'])->name('update');
        Route::delete('/{id}', [RetentionController::class, 'destroy'])->name('destroy');
    });

    // === TAXES ===
    Route::prefix('impuestos')->name('impuestos.')->group(function () {
        Route::get('/', [TaxController::class, 'index'])->name('index');
        Route::get('/data', [TaxController::class, 'data'])->name('data');
        Route::get('/{id}', [TaxController::class, 'show'])->name('show');
        Route::post('/', [TaxController::class, 'store'])->name('store');
        Route::put('/{id}', [TaxController::class, 'update'])->name('update');
        Route::delete('/{id}', [TaxController::class, 'destroy'])->name('destroy');
        Route::post('/{id}/toggle-status', [TaxController::class, 'toggleStatus'])->name('toggle-status');
    });

    // === KARDEX TELAS ===
    Route::prefix('kardex-telas')->name('kardex-telas.')->group(function () {
        Route::get('/', [App\Http\Controllers\Inventory\FabricRollController::class, 'kardex'])->name('index');
        Route::prefix('recepcion')->name('reception.')->group(function () {
            Route::get('/', [App\Http\Controllers\Inventory\FabricRollController::class, 'reception'])->name('index');
            Route::get('/{invoice}', [App\Http\Controllers\Inventory\FabricRollController::class, 'receptionShow'])->name('show');
            Route::patch('/{invoice}/aprobar', [App\Http\Controllers\Inventory\FabricRollController::class, 'approveReception'])->name('approve');
            Route::patch('/{invoice}/rechazar', [App\Http\Controllers\Inventory\FabricRollController::class, 'rejectReception'])->name('reject');
        });
        Route::post('/rollos', [App\Http\Controllers\Inventory\FabricRollController::class, 'storeFromReception'])->name('rollos.store');
        Route::get('/rollos/item/{itemId}', [App\Http\Controllers\Inventory\FabricRollController::class, 'getByInvoiceItem'])->name('rollos.by-item');
    });

    // === INVENTARIO ===
    Route::get('inventario/articulos', [ArticleController::class, 'listView'])->name('listado');
    Route::get('inventario/ajuste-inventario', [ArticleController::class, 'adjustmentView'])->name('inventario.ajuste-inventario');
    Route::get('inventario/ajuste-inventario/crear', function () {
        return Inertia::render('Inventory/Articles/InventoryAdjustmentForm');
    })->name('inventario.ajuste-inventario.create');
    // Route::prefix('ajustes-inventario')->name('ajustes-inventario.')->group(function () {
    // Route::get('/data', [InventoryAdjustmentController::class, 'getData'])->name('data'); // para la tabla
    // Route::get('/', [InventoryAdjustmentController::class, 'index'])->name('index');      // listado simple o redirección
    // Route::get('/{id}', [InventoryAdjustmentController::class, 'show'])->name('show');   // ver un ajuste
    // Route::post('/', [InventoryAdjustmentController::class, 'store'])->name('store');    // guardar nuevo
    // Route::put('/{id}', [InventoryAdjustmentController::class, 'update'])->name('update');
    // Route::delete('/{id}', [InventoryAdjustmentController::class, 'destroy'])->name('destroy');
    //});

});

require __DIR__ . '/auth.php';
