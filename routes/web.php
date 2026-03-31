<?php

use App\Http\Controllers\Production\TechnicalDataSheetController;
use App\Models\Settings\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => redirect('login'))->name('home');

Route::get('dashboard', fn () => Inertia::render('Dashboard'))
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('user', function () {
        $user = Auth::user();

        if (! $user instanceof User) {
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

    require __DIR__.'/master-data.php';
    require __DIR__.'/production.php';
    require __DIR__.'/inventory.php';
    require __DIR__.'/expenses.php';
    require __DIR__.'/finance.php';
    require __DIR__.'/income.php';
    require __DIR__.'/textil.php';
});

require __DIR__.'/auth.php';
require __DIR__.'/settings.php';
