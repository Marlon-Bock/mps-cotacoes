<?php

namespace App\Http\Controllers\Cotacao;

use App\Http\Controllers\Controller;

/**
 * GET+POST /cotacao/{protocolo} — link genérico de cotação: redireciona pra
 * tela de detalhe do produto pelo prefixo do protocolo. Era pages/cotacao.php.
 * A rota é a última a casar (routes/modulos/90-acoes-chat.php) — as rotas
 * específicas (/cotacao/auto, /cotacao/rascunhos...) vêm antes.
 */
class RedirecionarController extends Controller
{
    public function cotacao(string $protocolo)
    {
        $current_user = require_auth();
        if (($current_user->role ?? '') === 'admin') return redirect(url('/admin/cotacoes'));

        if (!$protocolo) return redirect(url('/cotacoes'));

        if (str_starts_with($protocolo, 'AUTO')) return redirect(url('/cotacao/auto/'        . urlencode($protocolo)));
        if (str_starts_with($protocolo, 'TC'))   return redirect(url('/cotacao/titulo/'      . urlencode($protocolo)));
        if (str_starts_with($protocolo, 'COND')) return redirect(url('/cotacao/condominial/' . urlencode($protocolo)));
        if (str_starts_with($protocolo, 'INC'))  return redirect(url('/cotacao/incendio/'    . urlencode($protocolo)));
        if (str_starts_with($protocolo, 'RES'))  return redirect(url('/cotacao/residencial/' . urlencode($protocolo)));
        return redirect(url('/cotacao/fianca/' . urlencode($protocolo)));
    }
}
