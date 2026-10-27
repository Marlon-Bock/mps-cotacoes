<?php

use App\Http\Controllers\Gestao\CotacoesController;
use App\Http\Controllers\Gestao\AuditoriaController;
use App\Http\Controllers\Gestao\GerenciadorController;
use App\Http\Controllers\Gestao\LeadsController;
use App\Http\Controllers\Gestao\PainelController;
use App\Http\Controllers\Gestao\PipelineController;
use Illuminate\Support\Facades\Route;

// Painel de exibição (TV da empresa) — login e conta próprios
Route::get('/painel/login', [PainelController::class, 'loginForm']);
Route::get('/painel', [PainelController::class, 'index']);
Route::get('/api/painel/dados', [PainelController::class, 'dados']);

// Cotações (listagem da imobiliária)
Route::get('/cotacoes', [CotacoesController::class, 'imobiliaria']);
Route::get('/api/cotacoes/listar', [CotacoesController::class, 'listar']);

// Todas as Cotações (MPS)
Route::get('/admin/cotacoes', [CotacoesController::class, 'admin']);
Route::get('/api/admin/cotacoes/listar', [CotacoesController::class, 'adminListar']);
Route::get('/api/admin/cotacoes/exportar', [CotacoesController::class, 'adminExportar']);

// Leads do site
Route::get('/admin/leads', [LeadsController::class, 'index']);
Route::get('/api/admin/leads/listar', [LeadsController::class, 'listar']);
Route::get('/admin/leads/{protocolo}', [LeadsController::class, 'detalhe']);

// Pipeline (kanban)
Route::get('/admin/pipeline', [PipelineController::class, 'index']);
Route::get('/api/admin/pipeline/listar', [PipelineController::class, 'listar']);

// Central de gestão (gerenciador)
Route::get('/admin/gerenciador', [GerenciadorController::class, 'index']);

// Auditoria (só o suporte da Sami — require_suporte_sami)
Route::get('/admin/auditoria', [AuditoriaController::class, 'index']);
Route::get('/api/admin/auditoria/listar', [AuditoriaController::class, 'listar']);
Route::get('/api/admin/gerenciador/stats', [GerenciadorController::class, 'stats']);
Route::get('/api/admin/gerenciador/tempos', [GerenciadorController::class, 'tempos']);
Route::get('/api/admin/gerenciador/timeline', [GerenciadorController::class, 'timeline']);
