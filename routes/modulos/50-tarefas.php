<?php

use App\Http\Controllers\Tarefas\AnexosController;
use App\Http\Controllers\Tarefas\ConfigController;
use App\Http\Controllers\Tarefas\SubitensController;
use App\Http\Controllers\Tarefas\TarefasController;
use Illuminate\Support\Facades\Route;

// Gerenciador de Tarefas da MPS (pages/admin/tarefas.php + includes/acao/admin-tarefas-*.php).
Route::get('/admin/tarefas', [TarefasController::class, 'index']);
Route::get('/api/admin/tarefas/listar', [TarefasController::class, 'listar']);
Route::get('/api/admin/tarefas/obter', [TarefasController::class, 'obter']);
Route::post('/api/admin/tarefas/salvar', [TarefasController::class, 'salvar']);
Route::post('/api/admin/tarefas/status', [TarefasController::class, 'status']);
Route::post('/api/admin/tarefas/responsaveis', [TarefasController::class, 'responsaveis']);
Route::post('/api/admin/tarefas/prazo', [TarefasController::class, 'prazo']);
Route::post('/api/admin/tarefas/anexo', [AnexosController::class, 'enviar']);
Route::get('/api/admin/tarefas/anexos', [AnexosController::class, 'listar']);
Route::post('/api/admin/tarefas/anexo/excluir', [AnexosController::class, 'excluir']);
Route::get('/api/admin/tarefas/detalhe', [TarefasController::class, 'detalhe']);
Route::post('/api/admin/tarefas/comentar', [TarefasController::class, 'comentar']);
Route::post('/api/admin/tarefas/criar-rapido', [TarefasController::class, 'criarRapido']);
Route::post('/api/admin/tarefas/campo', [TarefasController::class, 'campo']);
Route::post('/api/admin/tarefas/excluir', [TarefasController::class, 'excluir']);
Route::post('/api/admin/tarefas/mover', [TarefasController::class, 'mover']);
Route::post('/api/admin/tarefas/arquivar', [TarefasController::class, 'arquivar']);
Route::get('/api/admin/tarefas/subitens/listar', [SubitensController::class, 'listar']);
Route::post('/api/admin/tarefas/subitens/salvar', [SubitensController::class, 'salvar']);
Route::post('/api/admin/tarefas/subitens/excluir', [SubitensController::class, 'excluir']);
Route::get('/api/admin/tarefas/dashboard', [TarefasController::class, 'dashboard']);
Route::get('/api/admin/tarefas/config', [ConfigController::class, 'listar']);
Route::post('/api/admin/tarefas/config/salvar', [ConfigController::class, 'salvar']);
Route::post('/api/admin/tarefas/config/excluir', [ConfigController::class, 'excluir']);
Route::post('/api/admin/tarefas/usuario-setores', [ConfigController::class, 'usuarioSetores']);
