<?php

/*
 * Seguro Fiança + API das seguradoras.
 * Mesmos caminhos/métodos de _legado/public/index.php.
 * Rotas fora do CSRF do Laravel (já configurado): api/webhook/*, api/embed/*.
 */

use App\Http\Controllers\Fianca\AnaliseController;
use App\Http\Controllers\Fianca\DetalheController;
use App\Http\Controllers\Fianca\DocumentosController;
use App\Http\Controllers\Fianca\FiancaController;
use App\Http\Controllers\Fianca\SeguradorasController;
use Illuminate\Support\Facades\Route;

// Formulário e envio da ficha
Route::match(['get', 'post'], '/cotacao/fianca/residencial', [FiancaController::class, 'residencial']);
Route::match(['get', 'post'], '/cotacao/fianca/comercial', [FiancaController::class, 'comercial']);
Route::post('/api/cotacao/fianca', [FiancaController::class, 'enviar']);
Route::post('/api/embed/{embed_token}/cotacao/fianca', [FiancaController::class, 'enviar'])->middleware('throttle:embed-envio'); // ramo embed (sem login)
Route::get('/api/cotacao/imovel-buscar', [FiancaController::class, 'imovelBuscar']);

// API das seguradoras — callback da Pottencial Fiança PJ (público, com token na URL)
Route::post('/api/webhook/pottencial-pj/{token}', [SeguradorasController::class, 'pottencialPjCallback']);

// API das seguradoras — tela de seguradoras (MPS) e detalhe da imobiliária
Route::prefix('/api/cotacao/fianca/{protocolo}/seguradoras')->group(function () {
    Route::post('/atualizar', [SeguradorasController::class, 'atualizar']);
    Route::get('/fila', [SeguradorasController::class, 'fila']);
    Route::get('/situacao', [SeguradorasController::class, 'situacao']);
    Route::get('/biometria-link', [SeguradorasController::class, 'biometriaLink']);
    Route::post('/propostas', [SeguradorasController::class, 'propostas']);
    Route::post('/propostas/recusar', [SeguradorasController::class, 'propostasRecusar']);
    Route::post('/apolice/enviar', [SeguradorasController::class, 'apoliceEnviar']);
    Route::post('/proposta/enviar', [SeguradorasController::class, 'propostaEnviar']);
    Route::post('/corrigir', [SeguradorasController::class, 'corrigir']);
    Route::post('/{seguradora}/emitir', [SeguradorasController::class, 'emitir']);
    Route::post('/{seguradora}/reanalisar', [SeguradorasController::class, 'reanalisar']);
    Route::get('/{seguradora}/biometria', [SeguradorasController::class, 'biometria']);
    Route::get('/{seguradora}/documento/{tipo}', [SeguradorasController::class, 'documento']);
});

// Pré-análise (formulário parcial), recotar e PDF
Route::post('/api/cotacao/{protocolo}/pre-analise', [AnaliseController::class, 'preAnalise']);

// Pré-aprovação e documentos (biometria, orçamento, contrato, apólice) — os 3 canais
Route::post('/api/cotacao/{protocolo}/pre-aprovar', [DocumentosController::class, 'preAprovar']);
Route::post('/api/cotacao/{protocolo}/biometria', [DocumentosController::class, 'biometria']);
Route::post('/api/cotacao/{protocolo}/orcamento', [DocumentosController::class, 'orcamento']);
Route::post('/api/cotacao/{protocolo}/contrato', [DocumentosController::class, 'contrato']);
Route::post('/api/cotacao/{protocolo}/apolice', [DocumentosController::class, 'apolice']);
Route::post('/api/cotacao/{protocolo}/recotar', [FiancaController::class, 'recotar']);
Route::get('/api/cotacao/{protocolo}/pdf', [FiancaController::class, 'pdf']);

// Páginas de detalhe
Route::get('/cotacao/fianca/{protocolo}', [DetalheController::class, 'imobiliaria']);
Route::get('/admin/cotacao/fianca/{protocolo}', [DetalheController::class, 'admin']);
Route::get('/admin/cotacao/fianca/{protocolo}/seguradoras', [DetalheController::class, 'seguradoras']);
Route::get('/admin/cotacao/residencial/{protocolo}/seguradoras', [DetalheController::class, 'seguradoras']);
