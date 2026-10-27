<?php

/**
 * Ações genéricas de cotação + chat/histórico da cotação (todos os produtos,
 * via cotacao_resolver). Mesmos caminhos/métodos do _legado/public/index.php.
 *
 * Este arquivo carrega por ÚLTIMO (ordem alfabética de routes/modulos/) de
 * propósito: o catch-all GET+POST /cotacao/{protocolo} só pode casar depois
 * das rotas específicas dos produtos (/cotacao/auto, /cotacao/fianca/{protocolo},
 * /cotacao/rascunhos...). O where() impede que ele case caminhos com "/".
 */

use App\Http\Controllers\Cotacao\AcoesController;
use App\Http\Controllers\Cotacao\ChatController;
use App\Http\Controllers\Cotacao\RedirecionarController;
use Illuminate\Support\Facades\Route;

// ── Chat / histórico ────────────────────────────────────────────────────────
Route::get('/api/cotacao/unread-lote', [ChatController::class, 'unreadLote']);
Route::post('/api/cotacao/{protocolo}/mensagem', [ChatController::class, 'mensagem']);
Route::post('/api/cotacao/{protocolo}/mensagem-anexo', [ChatController::class, 'mensagemAnexo']);
Route::get('/api/cotacao/{protocolo}/mensagens', [ChatController::class, 'mensagens']);
Route::get('/api/cotacao/{protocolo}/historico', [ChatController::class, 'historico']);

// ── Ações genéricas (fluxo manual MPS ↔ imobiliária) ───────────────────────
Route::post('/api/cotacao/{protocolo}/aprovar', [AcoesController::class, 'aprovar']);
Route::post('/api/cotacao/{protocolo}/recusar', [AcoesController::class, 'recusar']);
Route::post('/api/cotacao/{protocolo}/iniciar-analise', [AcoesController::class, 'iniciarAnalise']);
Route::post('/api/cotacao/{protocolo}/proposta', [AcoesController::class, 'proposta']);
Route::post('/api/cotacao/{protocolo}/recusar-mps', [AcoesController::class, 'recusarMps']);
Route::post('/api/cotacao/{protocolo}/recusar-cliente', [AcoesController::class, 'recusarCliente']);
Route::post('/api/cotacao/{protocolo}/desistir', [AcoesController::class, 'desistir']);

// ── Link genérico /cotacao/{protocolo} → detalhe do produto (sempre por último) ──
Route::match(['get', 'post'], '/cotacao/{protocolo}', [RedirecionarController::class, 'cotacao'])
    ->where('protocolo', '[A-Za-z0-9-]+');
