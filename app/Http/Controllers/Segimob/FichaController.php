<?php

namespace App\Http\Controllers\Segimob;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * Ações da ficha de Fiança cotada pela Segimob (seguro_fianca.origem =
 * 'segimob') — eram includes/acao/fianca-segimob-*.php do sistema segimob/.
 * Rotas /api/cotacao/fianca/{protocolo}/segimob/* (routes/modulos/73-segimob.php);
 * a lógica fica em app/Legado/segimob/.
 *
 * A Segimob só faz a análise: as seguradoras respondem e a ficha termina
 * sozinha em 'Recusado' ou 'Pré-aprovado' (depois segue o fluxo de
 * documentos do manual — Fianca\DocumentosController). Aqui: reconsultar,
 * a situação pro polling das telas e "Corrigir dados".
 */
class FichaController extends Controller
{
    /**
     * POST …/segimob/atualizar
     * Reconsulta o resultado de todas as seguradoras na Segimob (botão
     * "Atualizar" das seguradoras — só a MPS; a imobiliária acompanha a
     * pré-análise, que o segimob:acompanhamento atualiza).
     */
    public function atualizar(string $protocolo)
    {
        $user  = require_admin();
        $ficha = segimob_ficha_autorizada($user, $protocolo);

        $envio = segimob_ficha((int) $ficha['id']);
        // Ficha que nunca chegou na Segimob (falhou no envio): "Atualizar" reenvia.
        $jaEnviada = !empty($envio['codigo_seguro']);
        $r = $jaEnviada ? segimob_atualizar_status((int) $ficha['id']) : segimob_cotar_ficha((int) $ficha['id']);
        if (!$r['ok']) {
            error_log('[segimob/atualizar] ' . ($r['erro'] ?? ''));
            // 424, não 502: o Cloudflare troca respostas 502 pela página de erro dele.
            json_response(['success' => false, 'message' => $jaEnviada
                ? 'Erro ao consultar as seguradoras. Tente novamente.'
                : 'As seguradoras não aceitaram a ficha: ' . segimob_erro_amigavel((string) $r['erro'])], 424);
        }

        json_response(['success' => true, 'cotacoes' => $r['cotacoes']]);
    }

    /**
     * GET …/segimob/situacao
     * Consulta leve (só banco) pro polling das telas durante a análise: se
     * ainda espera a Segimob e a assinatura (status da ficha + etapa). Mudou
     * → a tela recarrega (pré-aprovada, recusada ou corrigir dados).
     */
    public function situacao(string $protocolo)
    {
        $user     = require_auth();
        $ficha    = segimob_ficha_autorizada($user, $protocolo);
        $cotacoes = segimob_cotacoes_da_ficha((int) $ficha['id']);
        $envio    = segimob_ficha((int) $ficha['id']);

        json_response([
            'success'    => true,
            'aguardando' => segimob_tela_aguardando($ficha, $envio, $cotacoes),
            'assinatura' => $ficha['status'] . '|' . segimob_etapa($envio, $cotacoes),
        ]);
    }

    /**
     * POST …/segimob/corrigir
     * "Corrigir dados": grava os campos corrigidos (só os apontados em
     * segimob_correcoes) e reenvia a ficha pras seguradoras.
     * Body JSON: { campos: { "titular.email": "...", "imovel.numero": "...", ... } }
     */
    public function corrigir(Request $request, string $protocolo)
    {
        $user     = require_auth();
        $ficha    = segimob_ficha_autorizada($user, $protocolo);
        $fiancaId = (int) $ficha['id'];

        if ($ficha['status'] !== SEGIMOB_STATUS_FICHA) {
            json_response(['success' => false, 'message' => 'Essa cotação não está mais com as seguradoras.'], 409);
        }

        $campos = $request->json('campos');
        $r = segimob_aplicar_correcao($fiancaId, is_array($campos) ? $campos : []);
        if (!$r['ok']) {
            json_response(['success' => false, 'message' => $r['erro']], 422);
        }

        segimob_historico($fiancaId, 'ALTERACAO_STATUS', 'Dados corrigidos', $r['mudancas'] ? implode(' · ', $r['mudancas']) : null, segimob_historico_ator($user));
        // Reenvia já com os dados corrigidos (o histórico registra o envio antes
        // das respostas das seguradoras, que chegam na própria consulta).
        segimob_historico($fiancaId, 'ENVIO_COTACAO', 'Enviada para análise', 'Reenviada após correção dos dados.', segimob_historico_ator($user));
        $envio = segimob_cotar_ficha($fiancaId);
        if (!$envio['ok']) {
            $dados = segimob_erro_de_dados($envio['erro'] ?? null);
            segimob_historico($fiancaId, $dados ? 'RECUSA_COTACAO' : 'ALTERACAO_STATUS', $dados ? 'Corrigir dados' : 'Erro no envio para as seguradoras', segimob_erro_amigavel((string) ($envio['erro'] ?? '')), null, null, !$dados);
            // Dado ainda não aceito → mostra o que falta; fora do ar → os dados já
            // ficaram salvos, é só usar "Atualizar" depois.
            json_response(['success' => false, 'message' => $dados
                ? 'Ainda falta corrigir: ' . segimob_erro_amigavel((string) $envio['erro'])
                : 'Dados salvos, mas as seguradoras não responderam agora. O envio é refeito automaticamente em alguns minutos.'], $dados ? 422 : 424);
        }

        json_response(['success' => true]);
    }
}
