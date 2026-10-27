<?php

/*
 * Produtos: Seguro Auto, Título de Capitalização, Seguro Condominial e Rascunhos.
 * As rotas /api/embed/... caem no mesmo método (ramo embed pelo {embed_token});
 * ficam fora do CSRF do Laravel (api/embed/*) — o CSRF do embed é o stateless.
 */

use App\Http\Controllers\Produtos\AutoController;
use App\Http\Controllers\Produtos\CondominialController;
use App\Http\Controllers\Produtos\RascunhosController;
use App\Http\Controllers\Produtos\TituloController;
use Illuminate\Support\Facades\Route;

// ── Seguro Auto ──────────────────────────────────────────────────────────────
Route::match(['get', 'post'], '/cotacao/auto', [AutoController::class, 'index']);
Route::post('/api/cotacao/auto', [AutoController::class, 'salvar']);
Route::post('/api/embed/{embed_token}/cotacao/auto', [AutoController::class, 'salvar'])->middleware('throttle:embed-envio');
Route::get('/api/fipe', [AutoController::class, 'fipe']);
Route::get('/cotacao/auto/{protocolo}', [AutoController::class, 'detalhe']);
Route::get('/admin/cotacao/auto/{protocolo}', [AutoController::class, 'adminDetalhe']);

// ── Rascunhos ────────────────────────────────────────────────────────────────
Route::get('/cotacao/rascunhos', [RascunhosController::class, 'index']);
Route::post('/api/cotacao/rascunho/salvar', [RascunhosController::class, 'salvar']);
Route::post('/api/cotacao/rascunho/excluir', [RascunhosController::class, 'excluir']);

// ── Título de Capitalização ──────────────────────────────────────────────────
Route::match(['get', 'post'], '/cotacao/titulo', [TituloController::class, 'index']);
Route::post('/api/cotacao/titulo', [TituloController::class, 'salvar']);
Route::post('/api/embed/{embed_token}/cotacao/titulo', [TituloController::class, 'salvar'])->middleware('throttle:embed-envio');
Route::post('/api/cotacao/titulo/{protocolo}/proposta-boleto', [TituloController::class, 'propostaBoleto']);
Route::post('/api/cotacao/titulo/{protocolo}/pagar', [TituloController::class, 'pagar']);
Route::post('/api/cotacao/titulo/{protocolo}/certificado', [TituloController::class, 'certificado']);
Route::get('/api/cotacao/{protocolo}/titulo-pdf', [TituloController::class, 'pdf']);
Route::get('/cotacao/titulo/{protocolo}', [TituloController::class, 'detalhe']);
Route::get('/admin/cotacao/titulo/{protocolo}', [TituloController::class, 'adminDetalhe']);

// ── Seguro Condominial ───────────────────────────────────────────────────────
Route::match(['get', 'post'], '/cotacao/condominial', [CondominialController::class, 'index']);
Route::post('/api/cotacao/condominial', [CondominialController::class, 'salvar']);
Route::post('/api/embed/{embed_token}/cotacao/condominial', [CondominialController::class, 'salvar'])->middleware('throttle:embed-envio');
Route::get('/api/cotacao/{protocolo}/condominial-pdf', [CondominialController::class, 'pdf']);
Route::get('/cotacao/condominial/{protocolo}', [CondominialController::class, 'detalhe']);
Route::get('/admin/cotacao/condominial/{protocolo}', [CondominialController::class, 'adminDetalhe']);
