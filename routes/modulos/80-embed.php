<?php

use App\Http\Controllers\Embed\EmbedController;
use Illuminate\Support\Facades\Route;

// Embed de cotações (iframe no site da imobiliária). Os POSTs dos formulários
// (/api/embed/{embed_token}/cotacao/*) ficam nos módulos de cada produto.
Route::get('/embed', [EmbedController::class, 'index']);
Route::post('/api/embed/ativar', [EmbedController::class, 'ativar']);

Route::get('/embed/{embed_token}/fianca/residencial', [EmbedController::class, 'fiancaResidencial']);
Route::get('/embed/{embed_token}/fianca/comercial', [EmbedController::class, 'fiancaComercial']);
Route::get('/embed/{embed_token}/auto', [EmbedController::class, 'auto']);
Route::get('/embed/{embed_token}/condominial', [EmbedController::class, 'condominial']);
Route::get('/embed/{embed_token}/titulo', [EmbedController::class, 'titulo']);
Route::get('/embed/{embed_token}/incendio', [EmbedController::class, 'incendio']);
Route::get('/embed/{embed_token}/residencial', [EmbedController::class, 'residencial']);

// Busca do imóvel por código no ERP (clientes Sami) de dentro do embed — sem login.
Route::get('/api/embed/{embed_token}/imovel-buscar', [EmbedController::class, 'imovelBuscar'])->middleware('throttle:embed-busca');

// Situação da ficha enviada pelo embed (o formulário mostra o resultado na hora).
Route::get('/api/embed/{embed_token}/cotacao/fianca/{protocolo}/situacao', [EmbedController::class, 'situacaoFianca'])
    ->where('protocolo', '[A-Za-z0-9-]+')->middleware('throttle:embed-situacao');
