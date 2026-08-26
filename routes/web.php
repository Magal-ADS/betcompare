<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\RefreshOddsController;
use Illuminate\Support\Facades\Route;

Route::get('/', DashboardController::class)->name('dashboard');
Route::post('/atualizar-odds', RefreshOddsController::class)->name('odds.refresh');
