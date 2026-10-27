<?php

/*
 * Seguro Incêndio (remessa com N imóveis, cada um com seu status) e
 * Seguro Residencial (cotado na API das seguradoras; sem seguradora ligada,
 * fluxo manual da MPS). Mesmos caminhos/métodos de _legado/public/index.php.
 */

use App\Http\Controllers\Incendio\IncendioController;
use App\Http\Controllers\Incendio\IncendioImovelController;
use App\Http\Controllers\Residencial\ResidencialController;
use Illuminate\Support\Facades\Route;

// Seguro Residencial
Route::get('/cotacao/residencial', [ResidencialController::class, 'nova']);
Route::post('/api/cotacao/residencial', [ResidencialController::class, 'enviar']);
Route::post('/api/embed/{embed_token}/cotacao/residencial', [ResidencialController::class, 'enviar'])->middleware('throttle:embed-envio'); // ramo embed (sem login)
Route::get('/cotacao/residencial/{protocolo}', [ResidencialController::class, 'detalhe']);
Route::get('/admin/cotacao/residencial/{protocolo}', [ResidencialController::class, 'adminDetalhe']);

// Seguro Incêndio
Route::match(['get', 'post'], '/cotacao/incendio', [IncendioController::class, 'nova']);
Route::post('/api/cotacao/incendio', [IncendioController::class, 'enviar']);
Route::post('/api/embed/{embed_token}/cotacao/incendio', [IncendioController::class, 'enviar'])->middleware('throttle:embed-envio'); // ramo embed (sem login)
Route::get('/api/cotacao/incendio/imovel-buscar', [IncendioController::class, 'imovelBuscar']);
Route::get('/cotacao/incendio/{protocolo}', [IncendioController::class, 'detalhe']);
Route::get('/admin/cotacao/incendio/{protocolo}', [IncendioController::class, 'adminDetalhe']);

// Ações por imóvel da remessa
Route::prefix('/api/cotacao/incendio/{protocolo}/imovel/{imovel_id}')->group(function () {
    Route::post('/iniciar-analise', [IncendioImovelController::class, 'iniciarAnalise']);
    Route::post('/proposta', [IncendioImovelController::class, 'proposta']);
    Route::post('/recusar-mps', [IncendioImovelController::class, 'recusarMps']);
    Route::post('/recusar-cliente', [IncendioImovelController::class, 'recusarCliente']);
    Route::post('/desistir', [IncendioImovelController::class, 'desistir']);
    Route::post('/aprovar', [IncendioImovelController::class, 'aprovar']);
    Route::post('/recusar', [IncendioImovelController::class, 'recusar']);
    Route::post('/cotacao', [IncendioImovelController::class, 'cotacao']);
    Route::post('/cotacao-aprovar', [IncendioImovelController::class, 'cotacaoAprovar']);
    Route::post('/cotacao-recusar', [IncendioImovelController::class, 'cotacaoRecusar']);
    Route::post('/cotacao-recusar-cliente', [IncendioImovelController::class, 'cotacaoRecusarCliente']);
    Route::post('/alteracao-solicitar', [IncendioImovelController::class, 'alteracaoSolicitar']);
    Route::post('/proposta-alteracao-solicitar', [IncendioImovelController::class, 'propostaAlteracaoSolicitar']);
    Route::post('/apolice', [IncendioImovelController::class, 'apolice']);
});
