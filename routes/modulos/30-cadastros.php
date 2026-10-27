<?php

/*
 * Cadastros: imobiliárias, usuários (MPS e da imobiliária), perfil,
 * notificações, log de WhatsApp e log de erros do sistema.
 */

use App\Http\Controllers\Cadastros\ImobiliariasController;
use App\Http\Controllers\Cadastros\NotificacoesController;
use App\Http\Controllers\Cadastros\PerfilController;
use App\Http\Controllers\Cadastros\SistemaErrosController;
use App\Http\Controllers\Cadastros\UsuariosController;
use App\Http\Controllers\Cadastros\UsuariosMpsController;
use Illuminate\Support\Facades\Route;

// ─── Imobiliárias (MPS) ──────────────────────────────────────────────────────
Route::get('/admin/imobiliarias', [ImobiliariasController::class, 'index']);
Route::match(['get', 'post'], '/admin/imobiliarias/criar', [ImobiliariasController::class, 'criar']);
Route::match(['get', 'post'], '/admin/imobiliarias/{slug}/editar', [ImobiliariasController::class, 'editar']);
Route::get('/admin/imobiliarias/{slug}/usuarios', [ImobiliariasController::class, 'usuarios']);
Route::match(['get', 'post'], '/admin/imobiliarias/{slug}/usuarios/criar', [ImobiliariasController::class, 'usuarioCriar']);
Route::match(['get', 'post'], '/admin/imobiliarias/{slug}/usuarios/{usuario_slug}/editar', [ImobiliariasController::class, 'usuarioEditar']);
Route::get('/api/admin/imobiliarias/buscar-institucional', [ImobiliariasController::class, 'buscarInstitucional']);
Route::post('/api/admin/imobiliarias/testar-samiloca', [ImobiliariasController::class, 'testarSamiloca']);
Route::get('/api/admin/imobiliarias/listar', [ImobiliariasController::class, 'listar']);
Route::post('/api/admin/imobiliarias/usuario-toggle-ativo', [ImobiliariasController::class, 'usuarioToggleAtivo']);

// ─── Usuários MPS ────────────────────────────────────────────────────────────
Route::get('/admin/usuarios', [UsuariosMpsController::class, 'index']);
Route::match(['get', 'post'], '/admin/usuarios/criar', [UsuariosMpsController::class, 'criar']);
Route::match(['get', 'post'], '/admin/usuarios/{slug}/editar', [UsuariosMpsController::class, 'editar']);
Route::get('/api/admin/usuarios/listar', [UsuariosMpsController::class, 'listar']);
Route::post('/api/admin/usuarios/excluir', [UsuariosMpsController::class, 'excluir']);

// ─── Usuários da imobiliária (admin da imobiliária) ─────────────────────────
Route::get('/usuarios', [UsuariosController::class, 'index']);
Route::get('/api/usuarios/listar', [UsuariosController::class, 'listar']);
Route::post('/api/usuarios/excluir', [UsuariosController::class, 'excluir']);
Route::match(['get', 'post'], '/usuarios/criar', [UsuariosController::class, 'criar']);
Route::match(['get', 'post'], '/usuarios/{slug}/editar', [UsuariosController::class, 'editar']);

// ─── Perfil (mesma página pros dois perfis) ─────────────────────────────────
Route::match(['get', 'post'], '/perfil', [PerfilController::class, 'index']);
Route::match(['get', 'post'], '/admin/perfil', [PerfilController::class, 'index']);
Route::post('/api/perfil', [PerfilController::class, 'atualizar']);
Route::post('/api/perfil/avatar', [PerfilController::class, 'avatar']);

// ─── Notificações (central + sininho em toda página) ────────────────────────
Route::get('/notificacoes', [NotificacoesController::class, 'imobiliaria']);
Route::get('/admin/notificacoes', [NotificacoesController::class, 'admin']);
Route::post('/api/notificacoes/lidas', [NotificacoesController::class, 'lidas']);
Route::get('/api/notificacoes/resumo', [NotificacoesController::class, 'resumo']);
Route::get('/api/notificacoes/listar', [NotificacoesController::class, 'listar']);
Route::get('/api/admin/notificacoes/listar', [NotificacoesController::class, 'adminListar']);

// ─── Logs (WhatsApp e erros do sistema) ──────────────────────────────────────
Route::get('/admin/log', [SistemaErrosController::class, 'log']);
Route::get('/admin/sistema-erros', [SistemaErrosController::class, 'index']);
Route::post('/api/admin/diagnostico-permissoes', [SistemaErrosController::class, 'diagnosticoPermissoes']);
Route::post('/api/admin/gerar-erro-teste', [SistemaErrosController::class, 'gerarErroTeste']);
Route::get('/api/admin/sistema-erros/listar', [SistemaErrosController::class, 'listar']);
Route::post('/api/admin/sistema-erros/{id}/resolver', [SistemaErrosController::class, 'resolver']);
