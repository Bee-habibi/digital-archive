<?php

use App\Http\Controllers\Auth\TwoFactorController as AuthTwoFactorController;
use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\ArchiveCategoryController;
use App\Http\Controllers\ArchiveController;
use App\Http\Controllers\ArchiveTypeController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VerificationController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('dashboard'))->name('home');

Route::middleware('auth')->group(function () {

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Verifikasi dua langkah (MFA/TOTP) - gerbang kedua setelah password
    Route::get('two-factor', [AuthTwoFactorController::class, 'challenge'])->name('two-factor.challenge');
    Route::post('two-factor', [AuthTwoFactorController::class, 'verify'])->name('two-factor.verify');
    Route::get('two-factor/setup', [AuthTwoFactorController::class, 'setup'])->name('two-factor.setup');
    Route::post('two-factor/setup', [AuthTwoFactorController::class, 'confirmSetup'])->name('two-factor.confirm');
    Route::get('two-factor/pengaturan', [AuthTwoFactorController::class, 'profile'])->name('two-factor.profile');
    Route::post('two-factor/kode-pemulihan', [AuthTwoFactorController::class, 'regenerateRecovery'])->name('two-factor.recovery.regenerate');
    Route::post('two-factor/nonaktifkan', [AuthTwoFactorController::class, 'disable'])->name('two-factor.disable');
    Route::post('two-factor/reset/{user}', [AuthTwoFactorController::class, 'adminReset'])->middleware('role:super_admin')->name('two-factor.admin-reset');

    // Profile - bawaan Breeze, tetap dipertahankan
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Arsip - semua role login boleh akses, isinya dibatasi di controller/policy
    Route::resource('archives', ArchiveController::class);
    Route::get('archives/files/{file}/download', [ArchiveController::class, 'downloadFile'])->name('archives.files.download');

    // Super User & Super Admin
    Route::middleware('role:super_user,super_admin')->group(function () {
        Route::get('verifikasi', [VerificationController::class, 'index'])->name('verification.index');
        Route::post('verifikasi/{archive}', [VerificationController::class, 'process'])->name('verification.process');
        Route::get('activity-log', [ActivityLogController::class, 'index'])->name('logs.index');
    });

    // Khusus Super Admin
    Route::middleware('role:super_admin')->group(function () {
        Route::resource('units', UnitController::class)->except(['create', 'edit', 'show', 'destroy']);
        Route::resource('archive-categories', ArchiveCategoryController::class)->only(['index', 'store']);
        Route::post('archive-types', [ArchiveTypeController::class, 'store'])->name('archive-types.store');

        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::post('users', [UserController::class, 'store'])->name('users.store');
        Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::post('users/{user}/reset-password', [UserController::class, 'resetPassword'])->name('users.reset-password');
        Route::post('users/{user}/deactivate', [UserController::class, 'deactivate'])->name('users.deactivate');
        Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy');

        Route::get('activity-log/download/{format}', [ActivityLogController::class, 'download'])->name('logs.download');
    });
});

require __DIR__.'/auth.php';