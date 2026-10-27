<?php

use App\Http\Controllers\Auth\DoisFatoresController;
use App\Http\Controllers\Cadastros\DoisFatoresContaController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\SistemaController;
use Illuminate\Support\Facades\Route;

// Acesso e sessão
Route::get('/login', [AuthController::class, 'loginForm']);
Route::post('/api/auth/login', [AuthController::class, 'login']);
// Dois fatores (segunda etapa do login — Auth\DoisFatoresController).
Route::post('/api/auth/2fa/email', [DoisFatoresController::class, 'configurarEmail']);
Route::post('/api/auth/2fa/totp', [DoisFatoresController::class, 'configurarTotp']);
Route::post('/api/auth/2fa/reenviar', [DoisFatoresController::class, 'reenviar'])->middleware('throttle:10,1');
Route::post('/api/auth/2fa/verificar', [DoisFatoresController::class, 'verificar'])->middleware('throttle:20,1');
Route::post('/api/auth/2fa/cancelar', [DoisFatoresController::class, 'cancelar']);
// Redefinir os dois fatores: a própria conta (Meu Perfil) ou o admin da MPS.
Route::post('/api/perfil/2fa/redefinir', [DoisFatoresContaController::class, 'redefinirPropria']);
Route::post('/api/admin/2fa/redefinir', [DoisFatoresContaController::class, 'redefinir']);
Route::post('/logout', [AuthController::class, 'logout']);
Route::get('/sessao-expirada', [AuthController::class, 'sessaoExpirada']);
Route::post('/api/renovar-sessao', [AuthController::class, 'renovarSessao']);
Route::get('/esqueceu-senha', [AuthController::class, 'esqueceuForm']);
Route::post('/api/auth/esqueceu-senha', [AuthController::class, 'esqueceu']);
Route::get('/recuperar-senha/{token}', [AuthController::class, 'recuperarForm']);
Route::post('/api/auth/recuperar-senha', [AuthController::class, 'recuperar']);

// Sistema
Route::match(['get', 'post'], '/verificacao', [SistemaController::class, 'verificacao']);
Route::get('/403', [SistemaController::class, 'erro403']);
Route::get('/404', [SistemaController::class, 'erro404']);
Route::get('/manifest.webmanifest', [SistemaController::class, 'manifest']);
Route::get('/politica-de-privacidade', [SistemaController::class, 'politica']);
Route::get('/termos-de-servico', [SistemaController::class, 'termos']);
