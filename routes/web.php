<?php

use App\Http\Controllers\Production\TechnicalDataSheetController;
<<<<<<< HEAD
=======
use App\Http\Controllers\Settings\AuditLogController;
use App\Http\Controllers\Settings\CompanySettingsController;
use App\Http\Controllers\Settings\RoleController;
use App\Http\Controllers\Settings\UserController;
>>>>>>> 2432eba56061a1274b6ace45a1d2c13bcb709181
use App\Models\Settings\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
<<<<<<< HEAD

Route::get('/', fn () => redirect('login'))->name('home');
=======
>>>>>>> 2432eba56061a1274b6ace45a1d2c13bcb709181

Route::get('dashboard', fn () => Inertia::render('Dashboard'))
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('user', function () {
        $user = Auth::user();
<<<<<<< HEAD

        if (! $user instanceof User) {
=======
        if (!$user instanceof User) {
>>>>>>> 2432eba56061a1274b6ace45a1d2c13bcb709181
            abort(401);
        }

        $user->load('roles.permissions', 'permissions');

        return response()->json([
            'user' => $user,
            'roles' => $user->roles->pluck('name'),
            'permissions' => $user->getAllPermissions()->pluck('name'),
        ]);
    })->name('user');

    Route::get('profile', fn () => Inertia::render('Profile'))->name('profile');

    Route::get('settings/parameters', [TechnicalDataSheetController::class, 'settingsParameters'])
        ->name('settings.parameters');

    Route::get('en-desarrollo/{modulo?}', function (?string $modulo = null) {
        $title = request('titulo');

        if (! $title && $modulo) {
            $title = ucwords(str_replace('-', ' ', $modulo));
        }

        return Inertia::render('UnderConstruction', [
            'title' => $title,
        ]);
    })->name('under-construction');

<<<<<<< HEAD
    require __DIR__.'/master-data.php';
    require __DIR__.'/production.php';
    require __DIR__.'/inventory.php';
    require __DIR__.'/expenses.php';
    require __DIR__.'/finance.php';
    require __DIR__.'/income.php';
    require __DIR__.'/textil.php';
=======
    // ===================================================================
    // MÓDULOS DEL SISTEMA (cada archivo contiene sus rutas aisladas)
    // ===================================================================
    require __DIR__ . '/production.php';
    require __DIR__ . '/expenses.php';
    require __DIR__ . '/income.php';
    require __DIR__ . '/finance.php';
    require __DIR__ . '/inventory.php';
    require __DIR__ . '/master-data.php';
    require __DIR__ . '/textil.php';

    // ===================================================================
    // CONFIGURACIÓN Y ADMINISTRACIÓN
    // ===================================================================

    // === CONFIGURACIÓN ===
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
    Route::get('auditoria', [AuditLogController::class, 'index'])
        ->name('auditoria.index')
        ->middleware('permission:ver auditoria');
    Route::get('auditoria/data', [AuditLogController::class, 'data'])
        ->name('auditoria.data')
        ->middleware('permission:ver auditoria');

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
        Route::patch('/{id}/archive', [App\Http\Controllers\Settings\NotificationsController::class, 'archive'])
            ->name('archive');
        Route::delete('/{id}', [App\Http\Controllers\Settings\NotificationsController::class, 'destroy'])
            ->name('destroy');
    });
>>>>>>> 2432eba56061a1274b6ace45a1d2c13bcb709181
});

require __DIR__.'/auth.php';
require __DIR__.'/settings.php';
