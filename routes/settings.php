<?php

use App\Http\Controllers\Settings\AvatarController;
use App\Http\Controllers\Settings\PasswordController;
use App\Http\Controllers\Settings\ProfileController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware('auth')->group(function () {
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

    // Avatar routes (legacy aliases to avoid breaking existing integrations)
    Route::post('settings/avatar', [AvatarController::class, 'upload'])->name('avatar.upload');
    Route::post('settings/avatar/crop', [AvatarController::class, 'crop'])->name('avatar.crop');
    Route::delete('settings/avatar', [AvatarController::class, 'destroy'])->name('avatar.destroy');

    Route::get('settings/password', [PasswordController::class, 'edit'])->name('password.edit');
    Route::put('settings/password', [PasswordController::class, 'update'])->name('password.update');

    Route::get('settings/appearance', function () {
        return Inertia::render('settings/Appearance');
    })->name('appearance');
});
