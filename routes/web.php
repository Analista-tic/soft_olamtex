<?php

use App\Http\Controllers\MasterData\ContactController;
use App\Http\Controllers\Expenses\PurchaseInvoiceController;
use App\Http\Controllers\Expenses\PurchaseInvoiceTelaController;
use App\Http\Controllers\Expenses\PurchaseOrderController;
use App\Http\Controllers\Expenses\PurchaseRequestController;
use App\Http\Controllers\Production\MachineController;
use App\Http\Controllers\Production\OperationController;
use App\Http\Controllers\Production\SectionController;
use App\Http\Controllers\Settings\AuditLogController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

use App\Http\Controllers\Production\ProductOperationListController;
use App\Http\Controllers\Production\ProductSheetController;
use App\Http\Controllers\Production\ProgrammerController;
use App\Http\Controllers\Production\ProductionOrderController;
use App\Http\Controllers\Production\BatchControlController;
use App\Http\Controllers\Production\TechnicalDataSheetAdditionalInfoController;
use App\Http\Controllers\Production\TechnicalDataSheetRollsController;
use App\Http\Controllers\Production\TechnicalDataSheetSuppliesController;
use App\Http\Controllers\Production\TechnicalDataSheetThreadsController;
use App\Http\Controllers\Production\TechnicalDataSheetController;
use App\Http\Controllers\Settings\UserController;
use App\Http\Controllers\Settings\RoleController;
use App\Http\Controllers\Settings\CompanySettingsController;
use App\Http\Controllers\MasterData\CategoryGroupController;
use App\Http\Controllers\MasterData\CategorySubGroupController;
use App\Http\Controllers\Inventory\WarehouseController;
use App\Http\Controllers\MasterData\ItemController;
use App\Http\Controllers\MasterData\AdditionalFieldController;
use App\Http\Controllers\Finance\RetentionController;
use App\Http\Controllers\Finance\TaxController;
use App\Http\Controllers\Finance\CreditDebitNoteController;
use App\Http\Controllers\Inventory\InventoryAdjustmentController;
use App\Http\Controllers\Inventory\InventoryMovementController;
use App\Http\Controllers\Inventory\ItemInventoryController;
use App\Http\Controllers\Inventory\WarehouseItemStockController;
use App\Http\Controllers\Inventory\InventoryRequestController;
use App\Http\Controllers\Inventory\WarehouseTransferController;
use App\Http\Controllers\MasterData\ParameterController;

// PÃ¡gina principal
Route::get('/', fn() => redirect('login'))->name('home');

// Dashboard
Route::get('dashboard', fn() => Inertia::render('Dashboard'))
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

// Rutas protegidas por autenticaciÃ³n y verificaciÃ³n
Route::middleware(['auth', 'verified'])->group(function () {
    
    // Usuario autenticado
    Route::get('user', function () {
        $user = Auth::user(); //CONSULTAR USUARIO LOGUEADO

        return response()->json([
            'user' => $user,
            'roles' => $user->roles->pluck('name'),
            'permissions' => $user->getAllPermissions()->pluck('name'),
        ]);
    })->name('user');

    // Perfil
    Route::get('profile', fn() => Inertia::render('Profile'))->name('profile');
    Route::get('settings/parameters', [TechnicalDataSheetController::class, 'settingsParameters'])
        ->name('settings.parameters');

    // MÃ³dulos en desarrollo
    Route::get('en-desarrollo/{modulo?}', function (?string $modulo = null) {
        $title = request('titulo');
        if (!$title && $modulo) {
            $title = ucwords(str_replace('-', ' ', $modulo));
        }

        return Inertia::render('UnderConstruction', [
            'title' => $title,
        ]);
    })->name('under-construction');

    // === COMERCIAL ===
    Route::prefix('produccion')->name('produccion.')->group(function () {
        Route::get('ficha-tecnica', [TechnicalDataSheetController::class, 'index'])
            ->name('ficha-tecnica.index');
        Route::get('ficha-tecnica/opciones', [TechnicalDataSheetController::class, 'options'])
            ->name('ficha-tecnica.options');
        Route::get('ficha-tecnica/{opId}/basica', [TechnicalDataSheetController::class, 'basicInfo'])
            ->name('ficha-tecnica.basicInfo');
        Route::get('ficha-tecnica/{opId}/listado-operacional', [TechnicalDataSheetController::class, 'operationalList'])
            ->name('ficha-tecnica.operational-list');
        Route::get('ficha-tecnica/listados-operacionales/opciones', [TechnicalDataSheetController::class, 'operationalListOptions'])
            ->name('ficha-tecnica.operational-list-options');
        Route::post('ficha-tecnica/{opId}/listado-operacional', [TechnicalDataSheetController::class, 'assignOperationalList'])
            ->name('ficha-tecnica.assign-operational-list');
        Route::get('ficha-tecnica/{opId}/procesos', [TechnicalDataSheetController::class, 'processesSummary'])
            ->name('ficha-tecnica.processes-summary');
        Route::post('ficha-tecnica/{opId}/procesos', [TechnicalDataSheetController::class, 'saveProcesses'])
            ->name('ficha-tecnica.processes.save');
        Route::get('ficha-tecnica/{opId}/costos', [TechnicalDataSheetController::class, 'costsSummary'])
            ->name('ficha-tecnica.costs-summary');
        Route::get('ficha-tecnica/{opId}/insumos', [TechnicalDataSheetSuppliesController::class, 'index'])
            ->name('ficha-tecnica.supplies.index');
        Route::get('ficha-tecnica/insumos/opciones', [TechnicalDataSheetSuppliesController::class, 'itemOptions'])
            ->name('ficha-tecnica.supplies.item-options');
        Route::get('insumos/items/{itemId}/last-purchase', [TechnicalDataSheetSuppliesController::class, 'lastPurchaseForItem'])
            ->middleware('permission:ver gastos|ver sabana productos|ver programador')
            ->name('ficha-tecnica.supplies.last-purchase');
        Route::get('insumos/items/{itemId}/meta', [TechnicalDataSheetSuppliesController::class, 'itemMeta'])
            ->middleware('permission:ver gastos|ver sabana productos|ver programador')
            ->name('ficha-tecnica.supplies.item-meta');
        Route::post('ficha-tecnica/{opId}/insumos', [TechnicalDataSheetSuppliesController::class, 'upsert'])
            ->name('ficha-tecnica.supplies.upsert');
        Route::put('ficha-tecnica/{opId}/insumos', [TechnicalDataSheetSuppliesController::class, 'saveAll'])
            ->name('ficha-tecnica.supplies.save-all');
        Route::delete('ficha-tecnica/{opId}/insumos/{supplyId}', [TechnicalDataSheetSuppliesController::class, 'destroy'])
            ->name('ficha-tecnica.supplies.destroy');
        Route::get('ficha-tecnica/{opId}/rollos', [TechnicalDataSheetRollsController::class, 'index'])
            ->name('ficha-tecnica.rolls.index');
        Route::put('ficha-tecnica/{opId}/rollos', [TechnicalDataSheetRollsController::class, 'saveAll'])
            ->name('ficha-tecnica.rolls.save-all');
        Route::post('ficha-tecnica/{opId}/rollos/params', [TechnicalDataSheetRollsController::class, 'saveParams'])
            ->name('ficha-tecnica.rolls.params');
        Route::post('ficha-tecnica/{opId}/rollos/lines', [TechnicalDataSheetRollsController::class, 'upsertLine'])
            ->name('ficha-tecnica.rolls.lines.upsert');
        Route::delete('ficha-tecnica/{opId}/rollos/lines/{lineId}', [TechnicalDataSheetRollsController::class, 'destroyLine'])
            ->name('ficha-tecnica.rolls.lines.destroy');
        Route::get('ficha-tecnica/{opId}/hilos', [TechnicalDataSheetThreadsController::class, 'index'])
            ->name('ficha-tecnica.threads.index');
        Route::put('ficha-tecnica/{opId}/hilos', [TechnicalDataSheetThreadsController::class, 'saveAll'])
            ->name('ficha-tecnica.threads.save-all');
        Route::post('ficha-tecnica/{opId}/hilos/params', [TechnicalDataSheetThreadsController::class, 'saveParams'])
            ->name('ficha-tecnica.threads.params');
        Route::post('ficha-tecnica/{opId}/hilos/lines', [TechnicalDataSheetThreadsController::class, 'upsertLine'])
            ->name('ficha-tecnica.threads.lines.upsert');
        Route::delete('ficha-tecnica/{opId}/hilos/lines/{lineId}', [TechnicalDataSheetThreadsController::class, 'destroyLine'])
            ->name('ficha-tecnica.threads.lines.destroy');
        Route::get('ficha-tecnica/{opId}/info-adicional', [TechnicalDataSheetAdditionalInfoController::class, 'show'])
            ->name('ficha-tecnica.additional-info.show');
        Route::post('ficha-tecnica/{opId}/info-adicional', [TechnicalDataSheetAdditionalInfoController::class, 'store'])
            ->name('ficha-tecnica.additional-info.store');

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
        Route::post('sabana-productos/{sabana}/convert', [ProductSheetController::class, 'convertToProduct'])
            ->middleware('permission:editar sabana productos')
            ->name('sabana-productos.convertToProduct');

        Route::get('product-sheets/parameters', [ProductSheetController::class, 'parameters'])
            ->middleware('permission:ver sabana productos')
            ->name('product-sheets.parameters');
        Route::delete('sabana-productos/{sabana}', [ProductSheetController::class, 'destroy'])
            ->middleware('permission:eliminar sabana productos')
            ->name('sabana-productos.destroy');

        Route::get('listados-operacionales', [ProductOperationListController::class, 'indexView'])
            ->middleware('permission:ver listados operacionales|ver sabana productos')
            ->name('listados-operacionales.index');

        Route::prefix('listados-operacionales')->name('listados-operacionales.')->group(function () {
            Route::get('/data', [ProductOperationListController::class, 'index'])
                ->middleware('permission:ver listados operacionales|ver sabana productos')
                ->name('data');
            Route::get('/options', [ProductOperationListController::class, 'options'])
                ->middleware('permission:ver listados operacionales|ver sabana productos')
                ->name('options');
            Route::post('/', [ProductOperationListController::class, 'store'])
                ->middleware('permission:crear listados operacionales|crear sabana productos')
                ->name('store');
            Route::put('/{listItem}', [ProductOperationListController::class, 'update'])
                ->middleware('permission:editar listados operacionales|editar sabana productos')
                ->name('update');
            Route::delete('/{listItem}', [ProductOperationListController::class, 'destroy'])
                ->middleware('permission:eliminar listados operacionales|eliminar sabana productos')
                ->name('destroy');
            Route::post('/copiar-desde-producto', [ProductOperationListController::class, 'copyFromProduct'])
                ->middleware('permission:crear listados operacionales|crear sabana productos')
                ->name('copy-from-product');
        });

        // Parametros
        Route::prefix('parametros')->name('parametros.')->group(function () {
            Route::get('/data', [ParameterController::class, 'data'])
                ->middleware('permission:ver listados operacionales|ver sabana productos')
                ->name('data');
        });

        // Secciones de operaciones
        Route::prefix('secciones')->name('secciones.')->group(function () {
            Route::get('/data', [SectionController::class, 'data'])->name('data');
            Route::post('/', [SectionController::class, 'store'])
                ->middleware('permission:crear listados operacionales|crear sabana productos')
                ->name('store');
        });

        // Operaciones
        Route::prefix('operaciones')->name('operaciones.')->group(function () {
            Route::get('/data', [OperationController::class, 'data'])->name('data');
            Route::post('/', [OperationController::class, 'store'])
                ->middleware('permission:crear listados operacionales|crear sabana productos')
                ->name('store');
        });

        // MÃ¡quinas
        Route::prefix('maquinas')->name('maquinas.')->group(function () {
            Route::get('/data', [MachineController::class, 'data'])->name('data');
            Route::post('/', [MachineController::class, 'store'])
                ->middleware('permission:crear listados operacionales|crear sabana productos')
                ->name('store');
        });
    });

    // === DISEÃ‘O ===
    Route::get('produccion/ordenes-produccion', [ProductionOrderController::class, 'index'])
        ->middleware('permission:ver sabana productos')
        ->name('production.orders.index');
    Route::get('produccion/ordenes-produccion/{programmer}/detalle', [ProductionOrderController::class, 'show'])
        ->middleware('permission:ver sabana productos')
        ->name('production.orders.show');

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
    Route::prefix('control-de-lotes')->name('control-de-lotes.')->group(function () {
        Route::get('/', [BatchControlController::class, 'index'])->name('index');
        Route::get('/data', [BatchControlController::class, 'getData'])->name('data');
        Route::patch('/{programmer}/proceso', [BatchControlController::class, 'updateProcess'])->name('update-process');
    });


    // ===CONFIGURACIÃ“N ===
    Route::get('configuracion', fn() => Inertia::render('Configuration/Index'))
        ->middleware('permission:ver usuarios')
        ->name('configuracion');
    Route::get('configuracion/empresa', [CompanySettingsController::class, 'edit'])
        ->middleware('permission:ver usuarios')
        ->name('configuracion.empresa');
    Route::get('configuracion/perfil', [CompanySettingsController::class, 'edit'])
        ->middleware('permission:ver usuarios')
        ->name('configuracion.perfil')
        ->defaults('section', 'perfil');
    Route::get('configuracion/apariencia', [CompanySettingsController::class, 'edit'])
        ->middleware('permission:ver usuarios')
        ->name('configuracion.apariencia')
        ->defaults('section', 'personalizacion');
    Route::patch('configuracion/empresa', [CompanySettingsController::class, 'update'])
        ->middleware('permission:ver usuarios')
        ->name('configuracion.empresa.update');

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

    // === AUDITORÍA ===
    Route::middleware(['auth', 'verified'])->group(function () {
        Route::get('auditoria', [AuditLogController::class, 'index'])
            ->name('auditoria.index')
            ->middleware('permission:ver auditoria');
        Route::get('auditoria/data', [AuditLogController::class, 'data'])
            ->name('auditoria.data')
            ->middleware('permission:ver auditoria');
    });

    // === NOTIFICACIONES ===
    Route::prefix('configuracion/notificaciones')->name('notificaciones.')->group(function () {
        Route::get('/', [App\Http\Controllers\Settings\NotificationsController::class, 'index'])
            ->name('index');
        Route::get('/data', [App\Http\Controllers\Settings\NotificationsController::class, 'data'])
            ->name('data');
        Route::get('/latest', [App\Http\Controllers\Settings\NotificationsController::class, 'latest'])
            ->name('latest');
        Route::get('/unread-count', [App\Http\Controllers\Settings\NotificationsController::class, 'unreadCount'])
            ->name('unread-count');
        Route::get('/types', [App\Http\Controllers\Settings\NotificationsController::class, 'types'])
            ->name('types');
        Route::post('/{id}/read', [App\Http\Controllers\Settings\NotificationsController::class, 'markAsRead'])
            ->name('mark-read');
        Route::post('/read-all', [App\Http\Controllers\Settings\NotificationsController::class, 'markAllAsRead'])
            ->name('mark-all-read');
    });

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
        Route::get('/{id}/warehouses', [ItemController::class, 'getWarehouses'])->name('warehouses');
        Route::put('/{warehouse}', [WarehouseController::class, 'update'])->name('update');
        Route::delete('/{warehouse}', [WarehouseController::class, 'destroy'])->name('destroy');
    });

    // === TRANSFERENCIAS DE BODEGA ===
    Route::prefix('transferencias-bodegas')->name('warehouse-transfers.')->group(function () {
        Route::get('/', [WarehouseTransferController::class, 'index'])->name('index');
        Route::get('/data', [WarehouseTransferController::class, 'getData'])->name('data');
        Route::get('/{transfer}', [WarehouseTransferController::class, 'show'])->name('show');
        Route::post('/', [WarehouseTransferController::class, 'store'])->name('store');
        Route::put('/{transfer}', [WarehouseTransferController::class, 'update'])->name('update');
        Route::delete('/{transfer}', [WarehouseTransferController::class, 'destroy'])->name('destroy');
    });

    // === ITEMS ===
    Route::prefix('item')->name('item.')->group(function () {
        Route::get('/descargar-plantilla', [ItemController::class, 'downloadTemplate'])->name('descargar-plantilla');
        Route::get('/exportar-excel', [ItemController::class, 'exportExcel'])->name('exportar-excel');
        Route::get('/', [ItemController::class, 'index'])->name('index');
        Route::get('/data', [ItemController::class, 'getData'])->name('data');
        Route::post('/carga-masiva', [ItemController::class, 'bulkUpload'])->name('carga-masiva');
        Route::get('/check-duplicate', [ItemController::class, 'checkDuplicate'])->name('check-duplicate');
        Route::get('/{id}', [ItemController::class, 'show'])->name('show');
        Route::post('/', [ItemController::class, 'store'])->name('store');
        Route::put('/{id}', [ItemController::class, 'update'])->name('update');
        Route::delete('/{id}', [ItemController::class, 'destroy'])->name('destroy');
        Route::post('/{id}/toggle-status', [ItemController::class, 'toggleStatus'])->name('toggle-status');
    });

    // === FABRIC ROLLS ===
    Route::prefix('rollos-tela')->name('fabric-rolls.')->group(function () {
        Route::get('/', [App\Http\Controllers\Textil\FabricRollController::class, 'index'])->name('index');
        Route::get('/crear', [App\Http\Controllers\Textil\FabricRollController::class, 'create'])->name('create');
        Route::get('/data', [App\Http\Controllers\Textil\FabricRollController::class, 'getData'])->name('getData');
        Route::post('/', [App\Http\Controllers\Textil\FabricRollController::class, 'store'])->name('store');
        Route::get('/{fabricRoll}', [App\Http\Controllers\Textil\FabricRollController::class, 'show'])->name('show');
        Route::get('/{fabricRoll}/editar', [App\Http\Controllers\Textil\FabricRollController::class, 'edit'])->name('edit');
        Route::put('/{fabricRoll}', [App\Http\Controllers\Textil\FabricRollController::class, 'update'])->name('update');
        Route::delete('/{fabricRoll}', [App\Http\Controllers\Textil\FabricRollController::class, 'destroy'])->name('destroy');
        Route::patch('/{fabricRoll}/update-output', [App\Http\Controllers\Textil\FabricRollController::class, 'updateOutput'])->name('update-output');
    });

    // === GASTOS (AGRUPADO) ===
    Route::prefix('gastos')->name('gastos.')->group(function () {
        // === SOLICITUD DE COMPRA ===
        Route::prefix('solicitudes-compra')->name('purchase-requests.')->group(function () {
            Route::get('/', [PurchaseRequestController::class, 'index'])->name('index');
            Route::get('/data', [PurchaseRequestController::class, 'getData'])->name('getData');
            Route::post('/', [PurchaseRequestController::class, 'store'])->name('store');
            Route::get('/{purchaseRequest}', [PurchaseRequestController::class, 'show'])->name('show');
            Route::put('/{purchaseRequest}', [PurchaseRequestController::class, 'update'])->name('update');
            Route::delete('/{purchaseRequest}', [PurchaseRequestController::class, 'destroy'])->name('destroy');
            
            // Acciones de transiciÃ³n de estados
            Route::post('/{purchaseRequest}/send-to-approval', [PurchaseRequestController::class, 'sendToApproval'])->name('send-to-approval');
            Route::post('/{purchaseRequest}/approve', [PurchaseRequestController::class, 'approve'])->name('approve');
            Route::post('/{purchaseRequest}/reject', [PurchaseRequestController::class, 'reject'])->name('reject');
            Route::post('/{purchaseRequest}/cancel', [PurchaseRequestController::class, 'cancel'])->name('cancel');
        });
        
        // === ORDENES DE COMPRA ===
        Route::prefix('ordenes-compra')->name('purchase-orders.')->group(function () {
            Route::get('/', [PurchaseOrderController::class, 'index'])->name('index');
            Route::get('/data', [PurchaseOrderController::class, 'getData'])->name('getData');
            Route::post('/', [PurchaseOrderController::class, 'store'])->name('store');
            Route::get('/{purchaseDocument}', [PurchaseOrderController::class, 'show'])->name('show');
            Route::put('/{purchaseDocument}', [PurchaseOrderController::class, 'update'])->name('update');
            Route::delete('/{purchaseDocument}', [PurchaseOrderController::class, 'destroy'])->name('destroy');
            Route::post('/{purchaseDocument}/enviar-correo-pdf', [PurchaseOrderController::class, 'sendOrderPdf']);
            Route::post('/{purchaseDocument}/convert-to-invoice', [PurchaseOrderController::class, 'convertToInvoice']);
            Route::patch('/{purchaseDocument}/approve', [PurchaseOrderController::class, 'approve']);
        });

        // === FACTURAS DE COMPRA ===
        Route::prefix('facturas-compra')->name('purchase-invoices.')->group(function () {
            Route::get('/', [PurchaseInvoiceController::class, 'index'])->name('index');
            Route::get('/data', [PurchaseInvoiceController::class, 'getData'])->name('getData');
            Route::get('/{purchaseDocument}/notas-credito-debito', [PurchaseInvoiceController::class, 'creditDebitNotes'])->name('credit-debit-notes');
            Route::get('/{purchaseDocument}/notes-summary', [PurchaseInvoiceController::class, 'notesSummary'])->name('notes-summary');
            Route::post('/', [PurchaseInvoiceController::class, 'store'])->name('store');
            Route::get('/{purchaseDocument}', [PurchaseInvoiceController::class, 'show'])->name('show');
            Route::put('/{purchaseDocument}', [PurchaseInvoiceController::class, 'update'])->name('update');
            Route::delete('/{purchaseDocument}', [PurchaseInvoiceController::class, 'destroy'])->name('destroy');
            Route::post('/{purchaseDocument}/justificar', [PurchaseInvoiceController::class, 'justify'])->name('justify');
            // RecepciÃ³n de facturas NO tela
            Route::patch('/{purchaseDocument}/recibir', [PurchaseInvoiceController::class, 'receive'])->name('receive');
            Route::patch('/{purchaseDocument}/rechazar', [PurchaseInvoiceController::class, 'reject'])->name('reject');
        });

        // === FACTURAS DE COMPRA TELAS ===
        Route::prefix('facturas-compra-tela')->name('purchase-invoices-fabric.')->group(function () {
            Route::get('/', [PurchaseInvoiceTelaController::class, 'indexFabric'])->name('index');
            Route::get('/data', [PurchaseInvoiceTelaController::class, 'getData'])->name('getData');
            Route::post('/', [PurchaseInvoiceTelaController::class, 'store'])->name('store');
            Route::get('/{purchaseInvoice}', [PurchaseInvoiceTelaController::class, 'show'])->name('show');
            Route::put('/{purchaseInvoice}', [PurchaseInvoiceTelaController::class, 'update'])->name('update');
            Route::delete('/{purchaseInvoice}', [PurchaseInvoiceTelaController::class, 'destroy'])->name('destroy');
        });
    });

    // === CatÃ¡logos para campos parametrizables ===
    Route::get('catalogos/sections', [SectionController::class, 'data']);
        // Rutas eliminadas: groups, brands, worlds, fabric-bases, yarn-bases (controllers no existen)

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

    // === NOTAS CRÉDITO / DÉBITO ===
    Route::prefix('notas-credito-debito')->group(function () {
        Route::get('/', [CreditDebitNoteController::class, 'index']);
        Route::get('/data', [CreditDebitNoteController::class, 'getData']);
        Route::post('/', [CreditDebitNoteController::class, 'store']);
        Route::get('/{id}', [CreditDebitNoteController::class, 'show']);
        Route::put('/{id}', [CreditDebitNoteController::class, 'update']);
        Route::delete('/{id}', [CreditDebitNoteController::class, 'destroy']);
    });

    // === KARDEX TELAS ===
    Route::prefix('kardex-telas')->name('kardex-telas.')->group(function () {
          Route::get('/', [App\Http\Controllers\Textil\FabricRollController::class, 'kardex'])->name('index');
          Route::get('/inventario-rollos', [App\Http\Controllers\Textil\FabricRollController::class, 'inventoryRolls'])->name('inventario-rollos');
          Route::get('/inventario-rollos/data', [App\Http\Controllers\Textil\FabricRollController::class, 'inventoryRollsData'])->name('inventario-rollos.data');
          Route::get('/descargue-orden', [App\Http\Controllers\Textil\FabricRollController::class, 'downloadOrders'])->name('descargue-orden');
          Route::get('/descargue-orden/descarga', [App\Http\Controllers\Textil\FabricRollController::class, 'downloadWindow'])->name('descargue-orden.descarga');
          Route::get('/exportar-rollos', [App\Http\Controllers\Textil\FabricRollController::class, 'exportRolls'])->name('exportar-rollos');
        Route::prefix('recepcion')->name('reception.')->group(function () {
            Route::get('/', [App\Http\Controllers\Textil\FabricRollController::class, 'reception'])->name('index');
            Route::get('/{invoice}', [App\Http\Controllers\Textil\FabricRollController::class, 'receptionShow'])->name('show');
            Route::patch('/{invoice}/aprobar', [App\Http\Controllers\Textil\FabricRollController::class, 'approveReception'])->name('approve');
            Route::patch('/{invoice}/rechazar', [App\Http\Controllers\Textil\FabricRollController::class, 'rejectReception'])->name('reject');
        });
        Route::post('/rollos', [App\Http\Controllers\Textil\FabricRollController::class, 'storeFromReception'])->name('rollos.store');
        Route::get('/rollos/item/{itemId}', [App\Http\Controllers\Textil\FabricRollController::class, 'getByInvoiceItem'])->name('rollos.by-item');
    });

    // === INVENTARIO ===
    Route::get('inventario/item', [ItemController::class, 'listView'])->name('listado');
    Route::get('inventario/item/formulario-avanzado', [ItemController::class, 'advancedForm'])
        ->name('inventario.item.formulario-avanzado');
    Route::get('inventario/ajuste-inventario', [ItemController::class, 'adjustmentView'])->name('inventario.ajuste-inventario');
    Route::get('inventario/ajuste-inventario/crear', function () {
        return Inertia::render('Inventory/Articles/InventoryAdjustmentForm');
    })->name('inventario.ajuste-inventario.create');

    // === SOLICITUDES INTERNAS ===
    Route::prefix('inventario/solicitudes-internas')->name('inventory-requests.')->group(function () {
        Route::get('/', [InventoryRequestController::class, 'index'])->name('index');
        Route::get('/data', [InventoryRequestController::class, 'getData'])->name('data');
        Route::get('/next-number', [InventoryRequestController::class, 'nextNumber'])->name('next-number');
        Route::get('/{inventoryRequest}', [InventoryRequestController::class, 'show'])->name('show');
        Route::post('/', [InventoryRequestController::class, 'store'])->name('store');
        Route::put('/{inventoryRequest}', [InventoryRequestController::class, 'update'])->name('update');
        Route::post('/{inventoryRequest}/deliver', [InventoryRequestController::class, 'deliver'])->name('deliver');
        Route::post('/{inventoryRequest}/reject', [InventoryRequestController::class, 'reject'])->name('reject');
    });

    // === INVENTARIO DE ITEMS ===
    Route::prefix('inventario-item')->name('inventario-item.')->group(function () {
        Route::get('/', [ItemInventoryController::class, 'index'])->name('index');        // opcional
        Route::get('/data', [ItemInventoryController::class, 'getData'])->name('data');
    });
    Route::get('/warehouse-items', [WarehouseItemStockController::class, 'index']);

    // === CAMPOS ADICIONALES ===
    Route::prefix('campos-adicionales')->name('inventory.additional-fields.')->group(function () {
        Route::get('/', [AdditionalFieldController::class, 'index'])->name('index');
        Route::get('/options', [AdditionalFieldController::class, 'options'])->name('options');
        Route::get('/data', [AdditionalFieldController::class, 'getData'])->name('data');
        Route::post('/', [AdditionalFieldController::class, 'store'])->name('store');
        Route::post('/reorder', [AdditionalFieldController::class, 'reorder'])->name('reorder');
        Route::get('/{additionalField}', [AdditionalFieldController::class, 'show'])->name('show');
        Route::put('/{additionalField}', [AdditionalFieldController::class, 'update'])->name('update');
        Route::delete('/{additionalField}', [AdditionalFieldController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('parametros')->name('parametros.')->group(function () {
        Route::get('/', [ParameterController::class, 'index'])->name('index');
        Route::get('/data', [ParameterController::class, 'data'])->name('data');
        Route::post('/', [ParameterController::class, 'store'])->name('store');
        Route::get('/{id}', [ParameterController::class, 'show'])->name('show');
        Route::put('/{id}', [ParameterController::class, 'update'])->name('update');
        Route::delete('/{id}', [ParameterController::class, 'destroy'])->name('destroy');
    });

    // === AJUSTES DE INVENTARIO ===
    Route::prefix('ajustes-inventario')->name('ajustes-inventario.')->group(function () {
        Route::get('/data', [InventoryAdjustmentController::class, 'getData'])->name('data');   // para la tabla
        Route::get('/', [InventoryAdjustmentController::class, 'index'])->name('index');        // opcional
        Route::get('/next-number', [InventoryAdjustmentController::class, 'nextNumber'])->name('next-number'); // siguiente consecutivo
        Route::get('/{id}', [InventoryAdjustmentController::class, 'show'])->name('show');      // ver un ajuste
        Route::post('/', [InventoryAdjustmentController::class, 'store'])->name('store');       // guardar nuevo
        Route::put('/{id}', [InventoryAdjustmentController::class, 'update'])->name('update');  // actualizar
        Route::delete('/{id}', [InventoryAdjustmentController::class, 'destroy'])->name('destroy');
    });

    // === MOVIMIENTOS DE INVENTARIO ===
    Route::prefix('movimientos-inventario')->name('movimientos-inventario.')->group(function () {
        Route::get('/', [InventoryMovementController::class, 'index'])->name('index');
        Route::get('/data', [InventoryMovementController::class, 'getData'])->name('data');
        Route::get('/item/{itemId}', [InventoryMovementController::class, 'getItemMovements'])->name('item-movements');
        Route::get('/weighted-price', [InventoryMovementController::class, 'getCurrentWeightedPrice'])->name('weighted-price');
        Route::post('/transfer', [InventoryMovementController::class, 'storeTransfer'])->name('transfer');
        Route::post('/', [InventoryMovementController::class, 'store'])->name('store');
        Route::get('/{id}', [InventoryMovementController::class, 'show'])->name('show');
        Route::delete('/{id}', [InventoryMovementController::class, 'destroy'])->name('destroy');
    });

});

require __DIR__ . '/auth.php';
require __DIR__ . '/settings.php';
