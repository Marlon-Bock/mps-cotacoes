<?php

/**
 * Suporte: chamados (imobiliária e MPS), FAQ (+ fluxo do chat guiado e
 * atendimentos) e campanhas. Mesmos caminhos/métodos do _legado/public/index.php.
 */

use App\Http\Controllers\Suporte\AdminChamadosController;
use App\Http\Controllers\Suporte\CampanhasController;
use App\Http\Controllers\Suporte\ChamadosController;
use App\Http\Controllers\Suporte\FaqController;
use Illuminate\Support\Facades\Route;

// ── Chamados — imobiliária ──────────────────────────────────────────────────
Route::get('/chamados', [ChamadosController::class, 'index']);
Route::get('/api/chamados/listar', [ChamadosController::class, 'listar']);
Route::match(['get', 'post'], '/chamados/novo', [ChamadosController::class, 'novo']);
Route::get('/chamado/{protocolo}', [ChamadosController::class, 'detalhe']);
Route::get('/api/chamado/{protocolo}/mensagens', [ChamadosController::class, 'mensagens']);
Route::post('/api/chamado/{protocolo}/responder', [ChamadosController::class, 'responder']);
Route::post('/api/chamado/{protocolo}/mensagem-anexo', [ChamadosController::class, 'mensagemAnexo']);
Route::post('/api/chamado/{protocolo}/encerrar', [ChamadosController::class, 'encerrar']);

// ── Chamados — MPS ──────────────────────────────────────────────────────────
Route::get('/admin/chamados', [AdminChamadosController::class, 'index']);
Route::get('/api/admin/chamados/listar', [AdminChamadosController::class, 'listar']);
Route::get('/admin/chamado/{protocolo}', [AdminChamadosController::class, 'detalhe']);
Route::get('/api/admin/chamado/{protocolo}/mensagens', [AdminChamadosController::class, 'mensagens']);
Route::post('/api/admin/chamado/{protocolo}/responder', [AdminChamadosController::class, 'responder']);
Route::post('/api/admin/chamado/{protocolo}/mensagem-anexo', [AdminChamadosController::class, 'mensagemAnexo']);
Route::post('/api/admin/chamado/{protocolo}/encerrar', [AdminChamadosController::class, 'encerrar']);
Route::post('/api/admin/chamado/{protocolo}/atribuir', [AdminChamadosController::class, 'atribuir']);

// ── FAQ ─────────────────────────────────────────────────────────────────────
Route::get('/faq', [FaqController::class, 'index']);
Route::get('/admin/faq', [FaqController::class, 'admin']);
Route::get('/api/admin/faq/listar', [FaqController::class, 'adminListar']);
Route::post('/api/admin/faq/bloco-salvar', [FaqController::class, 'blocoSalvar']);
Route::post('/api/admin/faq/bloco-excluir', [FaqController::class, 'blocoExcluir']);
Route::post('/api/admin/faq/item-salvar', [FaqController::class, 'itemSalvar']);
Route::post('/api/admin/faq/item-excluir', [FaqController::class, 'itemExcluir']);
Route::get('/admin/faq/fluxo', [FaqController::class, 'fluxo']);
Route::post('/api/admin/faq-fluxo/salvar', [FaqController::class, 'fluxoSalvar']);
Route::get('/api/faq/fluxo', [FaqController::class, 'fluxoObter']);
Route::post('/api/faq/fluxo/sessao', [FaqController::class, 'fluxoSessao']);
Route::get('/admin/faq/atendimentos', [FaqController::class, 'atendimentos']);
Route::get('/api/admin/faq-atendimentos/listar', [FaqController::class, 'atendimentosListar']);

// ── Campanhas ───────────────────────────────────────────────────────────────
Route::get('/campanhas', [CampanhasController::class, 'index']);
Route::get('/api/campanhas/listar', [CampanhasController::class, 'listar']);
Route::get('/admin/campanhas', [CampanhasController::class, 'admin']);
Route::match(['get', 'post'], '/admin/campanhas/criar', [CampanhasController::class, 'criar']);
Route::match(['get', 'post'], '/admin/campanhas/{id}/editar', [CampanhasController::class, 'editar']);
Route::get('/api/admin/campanhas/listar', [CampanhasController::class, 'adminListar']);
Route::post('/api/admin/campanhas/excluir', [CampanhasController::class, 'excluir']);
