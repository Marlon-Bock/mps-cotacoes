<?php

use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DashboardController::class, 'imobiliaria']);
Route::get('/admin', [DashboardController::class, 'admin']);
Route::get('/api/admin/popup-cotacoes-em-analise', [DashboardController::class, 'popupEmAnalise']);
Route::post('/api/novidade-incendio/visto', [DashboardController::class, 'novidadeIncendioVisto']);
