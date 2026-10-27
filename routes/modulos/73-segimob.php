<?php

use App\Http\Controllers\Segimob\EstipulanteController;
use App\Http\Controllers\Segimob\FichaController;
use Illuminate\Support\Facades\Route;

/*
 * Segimob (agregador do Seguro Fiança) — canal de cotação 'segimob' da
 * imobiliária (ver app/Legado/canais.php). Lógica em app/Legado/segimob/.
 */

// Cadastro da imobiliária: código dela na Segimob (estipulante).
Route::get('/api/admin/imobiliarias/estipulante', [EstipulanteController::class, 'listar']);

// Análise da ficha de Fiança cotada pela Segimob (seguro_fianca.origem = 'segimob').
Route::prefix('/api/cotacao/fianca/{protocolo}/segimob')
    ->where(['protocolo' => '[A-Za-z0-9-]+'])
    ->controller(FichaController::class)
    ->group(function () {
        Route::post('/atualizar', 'atualizar');
        Route::get('/situacao', 'situacao');
        Route::post('/corrigir', 'corrigir');
    });
