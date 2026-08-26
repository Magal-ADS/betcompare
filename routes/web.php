<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\CollectionHistoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\RefreshOddsController;
use App\Http\Controllers\UserManagementController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:6,1')->name('login.store');
});

Route::middleware('auth')->group(function (): void {
    Route::get('/', DashboardController::class)->name('dashboard');
    Route::post('/atualizar-odds', RefreshOddsController::class)->name('odds.refresh');
    Route::get('/historico-coletas', CollectionHistoryController::class)->name('collection-history');
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::middleware('super.admin')->group(function (): void {
        Route::get('/usuarios', [UserManagementController::class, 'index'])->name('users.index');
        Route::post('/usuarios', [UserManagementController::class, 'store'])->name('users.store');
        Route::put('/usuarios/{user}', [UserManagementController::class, 'update'])->name('users.update');
    });
});
