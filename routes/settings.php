<?php

use App\Http\Controllers\Settings\AuditLogController;
use App\Http\Controllers\Settings\AppearanceController;
use App\Http\Controllers\Settings\AvatarController;
use App\Http\Controllers\Settings\CompanySettingsController;
use App\Http\Controllers\StorageFileController;
use App\Http\Controllers\Settings\NotificationsController;
use App\Http\Controllers\Settings\PasswordController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\RoleController;
use App\Http\Controllers\Settings\UserController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('configuracion', fn () => Inertia::render('Configuration/Index'))
        ->name('configuracion');

    Route::get('configuracion/empresa', [CompanySettingsController::class, 'edit'])
        // ->middleware('permission:ver empresa')
        ->name('configuracion.empresa');

    Route::get('configuracion/perfil', [CompanySettingsController::class, 'edit'])
        // ->middleware('permission:ver perfil')
        ->name('configuracion.perfil')
        ->defaults('section', 'perfil');

    Route::get('configuracion/apariencia', [CompanySettingsController::class, 'edit'])
        // ->middleware('permission:ver personalizacion')
        ->name('configuracion.apariencia')
        ->defaults('section', 'personalizacion');

    Route::match(['post', 'patch'], 'configuracion/empresa', [CompanySettingsController::class, 'update'])
        ->middleware('permission:editar empresa')
        ->name('configuracion.empresa.update');

    Route::get('users', [UserController::class, 'getData'])
        ->middleware('permission:ver usuarios')
        ->name('users.getData');

    Route::get('usuarios', [UserController::class, 'index'])
        ->middleware('permission:ver usuarios')
        ->name('usuarios.index');

    Route::post('usuarios', [UserController::class, 'store'])
        ->middleware('permission:crear usuarios')
        ->name('usuarios.store');

    Route::put('usuarios/{usuario}', [UserController::class, 'update'])
        ->middleware('permission:editar usuarios')
        ->name('usuarios.update');

    Route::delete('usuarios/{usuario}', [UserController::class, 'destroy'])
        ->middleware('permission:eliminar usuarios')
        ->name('usuarios.destroy');

    Route::get('roles-data', [RoleController::class, 'getData'])
        ->middleware('permission:ver roles')
        ->name('roles.getData');

    Route::get('roles', [RoleController::class, 'index'])
        ->middleware('permission:ver roles')
        ->name('roles.index');

    Route::post('roles', [RoleController::class, 'store'])
        ->middleware('permission:crear roles')
        ->name('roles.store');

    Route::put('roles/{role}', [RoleController::class, 'update'])
        ->middleware('permission:editar roles')
        ->name('roles.update');

    Route::delete('roles/{role}', [RoleController::class, 'destroy'])
        ->middleware('permission:eliminar roles')
        ->name('roles.destroy');

    Route::get('auditoria', [AuditLogController::class, 'index'])
        ->middleware('permission:ver auditoria')
        ->name('auditoria.index');

    Route::get('auditoria/data', [AuditLogController::class, 'data'])
        ->middleware('permission:ver auditoria')
        ->name('auditoria.data');

    Route::prefix('configuracion/notificaciones')->name('notificaciones.')->group(function () {
        Route::get('/', [NotificationsController::class, 'index'])->name('index');
        Route::get('/data', [NotificationsController::class, 'data'])->name('data');
        Route::get('/latest', [NotificationsController::class, 'latest'])->name('latest');
        Route::get('/unread-count', [NotificationsController::class, 'unreadCount'])->name('unread-count');
        Route::get('/types', [NotificationsController::class, 'types'])->name('types');
        Route::post('/{id}/read', [NotificationsController::class, 'markAsRead'])->name('mark-read');
        Route::post('/read-all', [NotificationsController::class, 'markAllAsRead'])->name('mark-all-read');
        Route::patch('/{id}/archive', [NotificationsController::class, 'archive'])->name('archive');
        Route::delete('/{id}', [NotificationsController::class, 'destroy'])->name('destroy');
    });

    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Avatar routes (new canonical endpoints)
    Route::post('profile/avatar', [AvatarController::class, 'upload'])->name('profile.avatar.upload');
    Route::delete('profile/avatar', [AvatarController::class, 'destroy'])->name('profile.avatar.destroy');
    Route::get('profile/avatar-file/{path}', [AvatarController::class, 'show'])
        ->where('path', '.*')
        ->name('profile.avatar.show');

    // Ruta genérica para servir archivos del disco public sin depender del symlink
    Route::get('storage-file/{path}', [StorageFileController::class, 'show'])
        ->where('path', '.*')
        ->name('storage.file.show');

    // Avatar routes (legacy aliases to avoid breaking existing integrations)
    Route::post('settings/avatar', [AvatarController::class, 'upload'])->name('avatar.upload');
    Route::post('settings/avatar/crop', [AvatarController::class, 'crop'])->name('avatar.crop');
    Route::delete('settings/avatar', [AvatarController::class, 'destroy'])->name('avatar.destroy');

    Route::get('settings/password', [PasswordController::class, 'edit'])->name('password.edit');
    Route::put('settings/password', [PasswordController::class, 'update'])->name('password.update');

    Route::get('settings/appearance', function () {
        return Inertia::render('settings/Appearance');
    })->name('appearance');

    Route::patch('settings/appearance', [AppearanceController::class, 'update'])
        ->name('settings.appearance.update');
});
