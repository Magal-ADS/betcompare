<?php

use App\Http\Controllers\CollectionHistoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\RefreshOddsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['internal.access', 'throttle:10,1'])->group(function (): void {
    Route::get('/', DashboardController::class)->name('dashboard');
    Route::post('/atualizar-odds', RefreshOddsController::class)->name('odds.refresh');
    Route::get('/historico-coletas', CollectionHistoryController::class)->name('collection-history');
});
