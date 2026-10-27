<?php

/*
 * Visitas, Rodízio, Horários de atendimento e Comissões (área MPS).
 * Mesmos caminhos/métodos de _legado/public/index.php.
 */

use App\Http\Controllers\Visitas\ComissoesController;
use App\Http\Controllers\Visitas\HorariosController;
use App\Http\Controllers\Visitas\RodizioController;
use App\Http\Controllers\Visitas\VisitasController;
use Illuminate\Support\Facades\Route;

// Rodízio de atendentes
Route::match(['get', 'post'], '/admin/rodizio', [RodizioController::class, 'pagina']);
Route::get('/api/admin/rodizio/listar', [RodizioController::class, 'listar']);
Route::post('/api/admin/rodizio/adicionar', [RodizioController::class, 'adicionar']);
Route::post('/api/admin/rodizio/remover', [RodizioController::class, 'remover']);
Route::post('/api/admin/rodizio/reordenar', [RodizioController::class, 'reordenar']);
Route::post('/api/admin/rodizio/status', [RodizioController::class, 'status']);

// Visitas — páginas
Route::get('/admin/visitas', [VisitasController::class, 'historico']);            // Histórico
Route::get('/admin/visitas/relatorio', [VisitasController::class, 'relatorio']);  // aceita ?ag_id=N
Route::get('/admin/visitas/agendar', [VisitasController::class, 'agendar']);

// Horário de atendimento e bloqueios
Route::get('/admin/horarios', [HorariosController::class, 'pagina']);
Route::post('/api/admin/horario-atendimento/salvar', [HorariosController::class, 'horarioSalvar']);
Route::post('/api/admin/bloqueio-horario/salvar', [HorariosController::class, 'bloqueioSalvar']);
Route::post('/api/admin/bloqueio-horario/excluir', [HorariosController::class, 'bloqueioExcluir']);

// Histórico de visitas
Route::get('/api/admin/visitas-historico/listar', [VisitasController::class, 'historicoListar']);

// Relatório de visitas
Route::get('/api/admin/visitas-relatorio/listar', [VisitasController::class, 'relatorioListar']);
Route::post('/api/admin/visitas-relatorio/salvar', [VisitasController::class, 'relatorioSalvar']);
Route::post('/api/admin/visitas-relatorio/excluir', [VisitasController::class, 'relatorioExcluir']);

// Agendar visita
Route::get('/api/admin/visitas-agendar/listar', [VisitasController::class, 'agendarListar']);
Route::post('/api/admin/visitas-agendar/salvar', [VisitasController::class, 'agendarSalvar']);
Route::post('/api/admin/visitas-agendar/excluir', [VisitasController::class, 'agendarExcluir']);

// Compartilhados entre Relatório, Histórico e Agendar (imobiliária + comissão + região)
Route::get('/api/admin/visitas/imobiliaria-buscar', [VisitasController::class, 'imobiliariaBuscar']);
Route::match(['get', 'post'], '/api/admin/visitas/comissao', [VisitasController::class, 'comissao']);
Route::get('/api/admin/visitas/regiao-listar', [VisitasController::class, 'regiaoListar']);
Route::post('/api/admin/visitas/regiao-criar', [VisitasController::class, 'regiaoCriar']);
Route::post('/api/admin/visitas/regiao-excluir', [VisitasController::class, 'regiaoExcluir']);

// Google Agenda (OAuth)
Route::get('/admin/visitas/google/conectar', [VisitasController::class, 'googleConectar']);
Route::get('/admin/visitas/google/callback', [VisitasController::class, 'googleCallback']);
Route::post('/api/admin/visitas/google-desconectar', [VisitasController::class, 'googleDesconectar']);

// Comissões
Route::match(['get', 'post'], '/admin/comissoes', [ComissoesController::class, 'pagina']);
