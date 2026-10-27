<?php

namespace App\Http\Controllers\Incendio;

use App\Http\Controllers\Controller;

/**
 * Ações por imóvel do Seguro Incêndio — todas
 * POST /api/cotacao/incendio/{protocolo}/imovel/{imovel_id}/<acao>.
 * Era includes/acao/cotacao-incendio-imovel-<acao>.php (um método por arquivo).
 *
 * Cada imóvel da remessa é um risco segurado independente (locador/locatário/
 * vigência/valor próprios), então o ciclo de vida é por imóvel, não pelo
 * protocolo inteiro. Toda ação termina em seguro_incendio_recalcular_status(),
 * que deriva o status da remessa a partir dos imóveis.
 *
 * Fluxo novo (fluxo = 'novo'): Em Analise → [cotacao] Aguardando aprovação →
 * [cotacao-aprovar] Cotação aprovada → [proposta] Aguardando apólice →
 * [apolice] Concluída. Fluxo antigo: Em Analise → [proposta] Aprovado →
 * [aprovar] Concluída.
 */
class IncendioImovelController extends Controller
{
    /**
     * .../iniciar-analise — MPS inicia a análise de UM imóvel específico da
     * remessa (espelha includes/acao/cotacao-iniciar-analise.php, a versão
     * genérica usada pelos demais produtos).
     */
    public function iniciarAnalise(string $protocolo, string $imovel_id)
    {
        $admin = require_admin();
        $imovel_id = self::parametros($protocolo, $imovel_id);
        $cotacao = self::remessa($protocolo);
        $imovel  = self::imovel($imovel_id, $cotacao);

        if ($imovel['status'] !== 'Aguardando análise') {
            json_response(['success' => false, 'message' => 'Este imóvel não está aguardando análise.'], 422);
        }

        $status_anterior = $imovel['status'];
        $status_novo     = 'Em Analise';

        db_execute(
            'UPDATE seguro_incendio_imoveis
             SET status = ?, data_status_em_analise = NOW(), data_atualizacao = NOW()
             WHERE id = ?',
            ['si', $status_novo, $imovel_id]
        );

        if (!$cotacao['atendente_id']) {
            db_execute('UPDATE seguro_incendio SET atendente_id = ? WHERE id = ? AND atendente_id IS NULL', ['ii', (int) $admin->uid, $cotacao['id']]);
        }

        seguro_incendio_recalcular_status((int) $cotacao['id']);

        historico_registrar(
            (int) $cotacao['id'], $protocolo, 'ALTERACAO_STATUS',
            $status_anterior, $status_novo, (int) $admin->uid, 'admin', $admin->nome ?? 'MPS',
            "Imóvel {$imovel['numero_imovel']}: análise iniciada."
        );

        self::notificarImobiliaria($cotacao, 'Cotação em análise',
            "O imóvel {$imovel['numero_imovel']} da cotação {$protocolo} entrou em análise pela equipe MPS.",
            'incendio-imovel-iniciar-analise');

        json_response(['success' => true, 'message' => 'Análise iniciada.', 'status' => $status_novo]);
    }

    /**
     * .../proposta — MPS envia a proposta (PDF) de UM imóvel específico — cada
     * imóvel vira sua própria apólice, então cada um recebe sua própria proposta
     * (espelha includes/acao/cotacao-proposta.php, a versão genérica).
     */
    public function proposta(string $protocolo, string $imovel_id)
    {
        $admin = require_admin();
        $imovel_id = self::parametros($protocolo, $imovel_id);
        $cotacao = self::remessa($protocolo);
        $imovel  = self::imovel($imovel_id, $cotacao);

        $fluxoNovo = ($imovel['fluxo'] ?? 'antigo') === 'novo';

        if ($fluxoNovo) {
            if ($imovel['status'] !== 'Cotação aprovada') {
                json_response(['success' => false, 'message' => 'A proposta só pode ser enviada depois da aprovação da cotação.'], 422);
            }
            $statusNovoProposta = 'Aguardando apólice';
            $podeReenviar       = false;
        } else {
            $prontoParaDecisao = $imovel['status'] === 'Em Analise';
            $anexoAnterior = db_fetch_one(
                'SELECT * FROM cotacoes_anexos_incendio WHERE seguro_incendio_imovel_id = ? ORDER BY data_envio DESC LIMIT 1',
                ['i', $imovel_id]
            );
            $podeReenviar = ($imovel['status'] === 'Aprovado' && $anexoAnterior && $anexoAnterior['status_aprovacao'] === 'Recusada')
                || $imovel['status'] === 'Alteração solicitada';

            if (!($prontoParaDecisao || $podeReenviar)) {
                json_response(['success' => false, 'message' => 'Status atual do imóvel não permite envio de proposta.'], 422);
            }
            $statusNovoProposta = 'Aprovado';
        }

        self::validarUpload();

        $observacao = sanitize((string) ($_POST['observacao'] ?? ''));

        $arquivo_url = self::upload('propostas/incendio');

        db_execute(
            "INSERT INTO cotacoes_anexos_incendio (protocolo, seguro_incendio_imovel_id, nome_arquivo, caminho_arquivo, tipo_arquivo, data_envio, usuario_envio, status_aprovacao)
             VALUES (?, ?, ?, ?, 'proposta', NOW(), ?, 'Aguardando')",
            ['sisss', $protocolo, $imovel_id, $_FILES['arquivo']['name'], $arquivo_url, $admin->nome ?? 'MPS']
        );

        $status_anterior = $imovel['status'];
        $status_novo     = $statusNovoProposta;
        $eraAlteracaoSolicitada = $status_anterior === 'Alteração solicitada';

        db_execute(
            'UPDATE seguro_incendio_imoveis SET status = ?, data_status_aprovado = IF(? = \'Aprovado\', NOW(), data_status_aprovado), data_atualizacao = NOW() WHERE id = ?',
            ['ssi', $status_novo, $status_novo, $imovel_id]
        );

        seguro_incendio_recalcular_status((int) $cotacao['id']);

        historico_registrar(
            (int) $cotacao['id'], $protocolo, 'ENVIO_COTACAO',
            $status_anterior, $status_novo, (int) $admin->uid, 'admin', $admin->nome ?? 'MPS',
            trim("Imóvel {$imovel['numero_imovel']}: " . (
                $eraAlteracaoSolicitada ? 'nova proposta enviada após pedido de alteração. '
                : ($podeReenviar ? 'nova proposta enviada após recusa do cliente. ' : 'proposta enviada. ')
            ) . $observacao)
        );

        self::notificarImobiliaria($cotacao, 'Proposta enviada',
            "Proposta enviada para o imóvel {$imovel['numero_imovel']} do protocolo {$protocolo}. Aguardando sua decisão.",
            'incendio-imovel-proposta');

        json_response([
            'success'     => true,
            'message'     => 'Proposta enviada com sucesso.',
            'status'      => $status_novo,
            'arquivo_url' => $arquivo_url,
        ]);
    }

    /**
     * .../recusar-mps — MPS recusa UM imóvel específico em análise (espelha
     * includes/acao/cotacao-recusar-mps.php, a versão genérica).
     */
    public function recusarMps(string $protocolo, string $imovel_id)
    {
        $admin = require_admin();
        $imovel_id = self::parametros($protocolo, $imovel_id);
        $cotacao = self::remessa($protocolo);
        $imovel  = self::imovel($imovel_id, $cotacao);

        if ($imovel['status'] !== 'Em Analise') {
            json_response(['success' => false, 'message' => 'Este imóvel ainda não está pronto para ser recusado.'], 422);
        }

        $status_anterior = $imovel['status'];
        $status_novo     = 'Recusado';

        db_execute(
            'UPDATE seguro_incendio_imoveis
             SET status = ?, data_status_recusado = IF(data_status_recusado IS NULL, NOW(), data_status_recusado),
                 data_atualizacao = NOW()
             WHERE id = ?',
            ['si', $status_novo, $imovel_id]
        );

        seguro_incendio_recalcular_status((int) $cotacao['id']);

        historico_registrar(
            (int) $cotacao['id'], $protocolo, 'ALTERACAO_STATUS',
            $status_anterior, $status_novo, (int) $admin->uid, 'admin', $admin->nome ?? 'MPS',
            "Imóvel {$imovel['numero_imovel']}: recusado pela MPS."
        );

        self::notificarImobiliaria($cotacao, 'Imóvel recusado',
            "O imóvel {$imovel['numero_imovel']} da cotação {$protocolo} foi recusado pela MPS.",
            'incendio-imovel-recusar-mps');

        json_response(['success' => true, 'message' => 'Imóvel recusado.', 'status' => $status_novo]);
    }

    /**
     * .../recusar-cliente — MPS encerra UM imóvel depois que o cliente recusou
     * a proposta enviada pra ele (espelha includes/acao/cotacao-recusar-cliente.php).
     */
    public function recusarCliente(string $protocolo, string $imovel_id)
    {
        $admin = require_admin();
        $imovel_id = self::parametros($protocolo, $imovel_id);
        $cotacao = self::remessa($protocolo);
        $imovel  = self::imovel($imovel_id, $cotacao);

        if ($imovel['status'] !== 'Aprovado') {
            json_response(['success' => false, 'message' => 'Este imóvel não está aguardando decisão do cliente.'], 422);
        }

        $ultimo_anexo = db_fetch_one(
            'SELECT status_aprovacao FROM cotacoes_anexos_incendio WHERE seguro_incendio_imovel_id = ? ORDER BY data_envio DESC LIMIT 1',
            ['i', $imovel_id]
        );
        if (!$ultimo_anexo || ($ultimo_anexo['status_aprovacao'] ?? '') !== 'Recusada') {
            json_response(['success' => false, 'message' => 'Nenhuma proposta recusada pelo cliente para este imóvel.'], 422);
        }

        $status_anterior = $imovel['status'];
        $status_novo     = STATUS_RECUSADO_CLIENTE;

        db_execute(
            'UPDATE seguro_incendio_imoveis
             SET status = ?, data_status_recusado = IF(data_status_recusado IS NULL, NOW(), data_status_recusado),
                 data_atualizacao = NOW()
             WHERE id = ?',
            ['si', $status_novo, $imovel_id]
        );

        seguro_incendio_recalcular_status((int) $cotacao['id']);

        historico_registrar(
            (int) $cotacao['id'], $protocolo, 'ALTERACAO_STATUS',
            $status_anterior, $status_novo, (int) $admin->uid, 'admin', $admin->nome ?? 'MPS',
            "Imóvel {$imovel['numero_imovel']}: cliente recusou a proposta enviada."
        );

        self::notificarImobiliaria($cotacao, 'Imóvel recusado pelo cliente',
            "O imóvel {$imovel['numero_imovel']} da cotação {$protocolo} foi encerrado como recusado pelo cliente.",
            'incendio-imovel-recusar-cliente');

        json_response(['success' => true, 'message' => 'Imóvel marcado como recusado pelo cliente.', 'status' => $status_novo]);
    }

    /**
     * .../desistir — registra a desistência do cliente pra UM imóvel específico
     * da remessa — pode ser acionado pela imobiliária ou pela MPS (espelha
     * includes/acao/cotacao-desistir.php).
     */
    public function desistir(string $protocolo, string $imovel_id)
    {
        $user = require_auth();
        $imovel_id = self::parametros($protocolo, $imovel_id);

        $is_admin = ($user->role ?? '') === 'admin';

        if (!$is_admin && empty($user->imob_admin) && (($user->perm_cotar ?? 'N') !== 'S' || ($user->perm_incendio ?? 'N') !== 'S')) {
            json_response(['success' => false, 'message' => 'Sem permissão para Seguro Incêndio.'], 403);
        }

        $cotacao = self::remessa($protocolo);

        if (!$is_admin && (int) $cotacao['imobiliaria_id'] !== (int) ($user->imobiliaria_id ?? 0)) {
            json_response(['success' => false, 'message' => 'Cotação não encontrada.'], 404);
        }
        if (!$is_admin && empty($user->imob_admin) && (int) ($cotacao['usuario_id'] ?? 0) !== (int) $user->uid) {
            json_response(['success' => false, 'message' => 'Cotação não encontrada.'], 404);
        }

        $imovel = self::imovel($imovel_id, $cotacao);

        $status_anterior = (string) $imovel['status'];
        if (cotacao_status_encerrado($status_anterior)) {
            json_response(['success' => false, 'message' => 'Este imóvel já está encerrado.'], 422);
        }

        db_execute(
            'UPDATE seguro_incendio_imoveis SET status = ?, data_atualizacao = NOW() WHERE id = ?',
            ['si', STATUS_DESISTENCIA, $imovel_id]
        );

        seguro_incendio_recalcular_status((int) $cotacao['id']);

        $autor_tipo = $is_admin ? 'admin' : 'imobiliaria';
        $autor_nome = $user->nome ?? ($is_admin ? 'MPS' : 'Imobiliária');

        historico_registrar(
            (int) $cotacao['id'], $protocolo, 'ALTERACAO_STATUS',
            $status_anterior, STATUS_DESISTENCIA, (int) $user->uid, $autor_tipo, $autor_nome,
            "Imóvel {$imovel['numero_imovel']}: cliente desistiu."
        );

        $texto = "O imóvel {$imovel['numero_imovel']} da cotação {$protocolo} foi encerrado por desistência do cliente.";
        if ($is_admin) {
            self::notificarImobiliaria($cotacao, 'Imóvel encerrado por desistência', $texto, 'incendio-imovel-desistir');
        } else {
            self::notificarAdmins($cotacao, 'acao_imob', 'Cliente desistiu do imóvel', $texto, 'incendio-imovel-desistir');
        }

        json_response([
            'success' => true,
            'message' => 'Imóvel encerrado por desistência do cliente.',
            'status'  => STATUS_DESISTENCIA,
        ]);
    }

    /**
     * .../aprovar — imobiliária aceita, em nome do cliente, a proposta enviada
     * pela MPS pra UM imóvel específico (espelha includes/acao/cotacao-aprovar.php).
     */
    public function aprovar(string $protocolo, string $imovel_id)
    {
        $user = require_perm('perm_aceitar');
        $imovel_id = self::parametros($protocolo, $imovel_id);
        $cotacao = self::remessa($protocolo, $user);
        $imovel  = self::imovel($imovel_id, $cotacao);

        if ($imovel['status'] !== 'Aprovado') {
            json_response(['success' => false, 'message' => 'Este imóvel não está aguardando decisão do cliente.'], 422);
        }

        $anexo = db_fetch_one(
            "SELECT id FROM cotacoes_anexos_incendio WHERE seguro_incendio_imovel_id = ? AND status_aprovacao = 'Aguardando' ORDER BY data_envio DESC LIMIT 1",
            ['i', $imovel_id]
        );
        if (!$anexo) {
            json_response(['success' => false, 'message' => 'Nenhuma proposta aguardando decisão para este imóvel.'], 422);
        }

        $ok1 = db_execute("UPDATE cotacoes_anexos_incendio SET status_aprovacao = 'Aprovada' WHERE id = ?", ['i', (int) $anexo['id']]);
        $ok2 = db_execute(
            "UPDATE seguro_incendio_imoveis SET status = 'Concluída', data_status_concluido = NOW(), data_atualizacao = NOW() WHERE id = ?",
            ['i', $imovel_id]
        );
        if (!$ok1 || !$ok2) {
            json_response(['success' => false, 'message' => 'Erro ao aprovar imóvel.'], 500);
        }

        seguro_incendio_recalcular_status((int) $cotacao['id']);

        historico_registrar(
            (int) $cotacao['id'], $protocolo, 'APROVACAO_COTACAO',
            'Aprovado', 'Concluída', (int) $user->uid, 'imobiliaria', $user->nome ?? 'Imobiliária',
            "Imóvel {$imovel['numero_imovel']}: cliente aceitou a proposta."
        );

        self::notificarAdmins($cotacao, 'cotacao_finalizada', 'Imóvel concluído',
            "O imóvel {$imovel['numero_imovel']} da cotação {$protocolo} foi aceito pelo cliente. Concluído!",
            'incendio-imovel-aprovar');

        zapi_notificar_atendente(
            isset($cotacao['atendente_id']) ? (int) $cotacao['atendente_id'] : null,
            "✅ *Proposta aceita pelo cliente — " . zapi_imobiliaria_nome((int) $cotacao['imobiliaria_id']) . "*\n\n"
            . "📄 Protocolo: `{$protocolo}` — Imóvel {$imovel['numero_imovel']}",
            'proposta_aceita',
            $protocolo
        );

        json_response(['success' => true, 'message' => 'Imóvel aprovado com sucesso!', 'status' => 'Concluída']);
    }

    /**
     * .../recusar — imobiliária recusa, em nome do cliente, a proposta enviada
     * pela MPS pra UM imóvel específico. O imóvel continua "Aprovado" (a MPS
     * pode enviar nova proposta) — espelha includes/acao/cotacao-recusar.php.
     */
    public function recusar(string $protocolo, string $imovel_id)
    {
        $user = require_perm('perm_aceitar');
        $imovel_id = self::parametros($protocolo, $imovel_id);
        $cotacao = self::remessa($protocolo, $user);
        $imovel  = self::imovel($imovel_id, $cotacao);

        if ($imovel['status'] !== 'Aprovado') {
            json_response(['success' => false, 'message' => 'Este imóvel não está aguardando decisão do cliente.'], 422);
        }

        $anexo = db_fetch_one(
            "SELECT id FROM cotacoes_anexos_incendio WHERE seguro_incendio_imovel_id = ? AND status_aprovacao = 'Aguardando' ORDER BY data_envio DESC LIMIT 1",
            ['i', $imovel_id]
        );
        if (!$anexo) {
            json_response(['success' => false, 'message' => 'Nenhuma proposta aguardando decisão para este imóvel.'], 422);
        }

        // O status do imóvel permanece "Aprovado" (igual ao fluxo genérico) — só o
        // anexo muda pra "Recusada", liberando a MPS pra enviar nova proposta.
        $ok1 = db_execute("UPDATE cotacoes_anexos_incendio SET status_aprovacao = 'Recusada' WHERE id = ?", ['i', (int) $anexo['id']]);
        if (!$ok1) {
            json_response(['success' => false, 'message' => 'Erro ao recusar proposta.'], 500);
        }
        // Sem checar o retorno: affected_rows vem 0 quando data_atualizacao já é o
        // NOW() deste segundo (outra ação acabou de mexer no imóvel) — não é falha.
        db_execute('UPDATE seguro_incendio_imoveis SET data_atualizacao = NOW() WHERE id = ?', ['i', $imovel_id]);

        historico_registrar(
            (int) $cotacao['id'], $protocolo, 'RECUSA_COTACAO',
            'Aprovado', 'Aprovado', (int) $user->uid, 'imobiliaria', $user->nome ?? 'Imobiliária',
            "Imóvel {$imovel['numero_imovel']}: cliente recusou a proposta."
        );

        self::notificarAdmins($cotacao, 'acao_imob', 'Proposta recusada pelo cliente',
            "O imóvel {$imovel['numero_imovel']} da cotação {$protocolo} teve a proposta recusada pelo cliente.",
            'incendio-imovel-recusar');

        zapi_notificar_atendente(
            isset($cotacao['atendente_id']) ? (int) $cotacao['atendente_id'] : null,
            "❌ *Proposta recusada pelo cliente — " . zapi_imobiliaria_nome((int) $cotacao['imobiliaria_id']) . "*\n\n"
            . "📄 Protocolo: `{$protocolo}` — Imóvel {$imovel['numero_imovel']}",
            'proposta_recusada',
            $protocolo
        );

        json_response(['success' => true, 'message' => 'Proposta recusada. A MPS será notificada e poderá enviar uma nova proposta.']);
    }

    /**
     * .../cotacao — fluxo novo: MPS anexa o PDF da cotação de UM imóvel e ele
     * passa a aguardar a aprovação da imobiliária/cliente.
     */
    public function cotacao(string $protocolo, string $imovel_id)
    {
        $admin = require_admin();
        $imovel_id = self::parametros($protocolo, $imovel_id);
        $cotacao = self::remessa($protocolo);
        $imovel  = self::imovel($imovel_id, $cotacao);

        if (($imovel['fluxo'] ?? 'antigo') !== 'novo') {
            json_response(['success' => false, 'message' => 'Esta cotação está no fluxo anterior. Use o envio de proposta.'], 422);
        }
        $ultimaCotacao = db_fetch_one(
            "SELECT status_aprovacao FROM cotacoes_anexos_incendio WHERE seguro_incendio_imovel_id = ? AND tipo_arquivo = 'cotacao' ORDER BY data_envio DESC LIMIT 1",
            ['i', $imovel_id]
        );
        // Reenvio depois de recusa (status continua "Aguardando aprovação", anexo
        // "Recusada") ou depois de pedido de alteração (status vira "Aguardando
        // nova cotação", ver alteracaoSolicitar()).
        $podeReenviarCotacao = ($imovel['status'] === 'Aguardando aprovação' && ($ultimaCotacao['status_aprovacao'] ?? '') === 'Recusada')
            || $imovel['status'] === 'Aguardando nova cotação';

        if ($imovel['status'] !== 'Em Analise' && !$podeReenviarCotacao) {
            json_response(['success' => false, 'message' => 'O imóvel precisa estar em análise para enviar a cotação.'], 422);
        }

        self::validarUpload();

        $arquivo_url = self::upload('cotacoes/incendio');

        db_execute(
            "INSERT INTO cotacoes_anexos_incendio (protocolo, seguro_incendio_imovel_id, nome_arquivo, caminho_arquivo, tipo_arquivo, data_envio, usuario_envio, status_aprovacao)
             VALUES (?, ?, ?, ?, 'cotacao', NOW(), ?, 'Aguardando')",
            ['sisss', $protocolo, $imovel_id, $_FILES['arquivo']['name'], $arquivo_url, $admin->nome ?? 'MPS']
        );

        $status_anterior = $imovel['status'];
        $status_novo     = 'Aguardando aprovação';

        db_execute(
            'UPDATE seguro_incendio_imoveis SET status = ?, data_status_cotacao_anexada = NOW(), data_atualizacao = NOW() WHERE id = ?',
            ['si', $status_novo, $imovel_id]
        );

        seguro_incendio_recalcular_status((int) $cotacao['id']);

        historico_registrar(
            (int) $cotacao['id'], $protocolo, 'ENVIO_COTACAO',
            $status_anterior, $status_novo, (int) $admin->uid, 'admin', $admin->nome ?? 'MPS',
            "Imóvel {$imovel['numero_imovel']}: PDF da cotação anexado."
        );

        self::notificarImobiliaria($cotacao, 'Cotação disponível',
            "A cotação do imóvel {$imovel['numero_imovel']} do protocolo {$protocolo} está pronta. Aguardando sua aprovação.",
            'incendio-imovel-cotacao');

        json_response([
            'success'     => true,
            'message'     => 'Cotação enviada. Aguardando aprovação da imobiliária.',
            'status'      => $status_novo,
            'arquivo_url' => $arquivo_url,
        ]);
    }

    /**
     * .../cotacao-aprovar — imobiliária aceita, em nome do cliente, a cotação do
     * imóvel (fluxo novo). Libera a MPS pra anexar o PDF da proposta.
     */
    public function cotacaoAprovar(string $protocolo, string $imovel_id)
    {
        $user = require_perm('perm_aceitar');
        $imovel_id = self::parametros($protocolo, $imovel_id);
        $cotacao = self::remessa($protocolo, $user);
        $imovel  = self::imovel($imovel_id, $cotacao);

        if (($imovel['fluxo'] ?? 'antigo') !== 'novo' || $imovel['status'] !== 'Aguardando aprovação') {
            json_response(['success' => false, 'message' => 'Este imóvel não está aguardando aprovação da cotação.'], 422);
        }

        $anexo = self::cotacaoAguardando($imovel_id);

        $ok1 = db_execute("UPDATE cotacoes_anexos_incendio SET status_aprovacao = 'Aprovada' WHERE id = ?", ['i', (int) $anexo['id']]);
        $ok2 = db_execute(
            "UPDATE seguro_incendio_imoveis SET status = 'Cotação aprovada', data_status_cotacao_aprovada = NOW(), data_atualizacao = NOW() WHERE id = ?",
            ['i', $imovel_id]
        );
        if (!$ok1 || !$ok2) {
            json_response(['success' => false, 'message' => 'Erro ao aprovar a cotação.'], 500);
        }

        seguro_incendio_recalcular_status((int) $cotacao['id']);

        historico_registrar(
            (int) $cotacao['id'], $protocolo, 'APROVACAO_COTACAO',
            'Aguardando aprovação', 'Cotação aprovada', (int) $user->uid, 'imobiliaria', $user->nome ?? 'Imobiliária',
            "Imóvel {$imovel['numero_imovel']}: cliente aprovou a cotação."
        );

        self::notificarAdmins($cotacao, 'acao_imob', 'Cotação aprovada',
            "O imóvel {$imovel['numero_imovel']} da cotação {$protocolo} foi aprovado pelo cliente. Já pode enviar a proposta.",
            'incendio-imovel-cotacao-aprovar');

        json_response(['success' => true, 'message' => 'Cotação aprovada!', 'status' => 'Cotação aprovada']);
    }

    /**
     * .../cotacao-recusar — imobiliária recusa, em nome do cliente, a cotação do
     * imóvel (fluxo novo). O status do imóvel permanece "Aguardando aprovação" —
     * a MPS pode enviar uma nova cotação ou encerrar o imóvel (cotacaoRecusarCliente).
     */
    public function cotacaoRecusar(string $protocolo, string $imovel_id)
    {
        $user = require_perm('perm_aceitar');
        $imovel_id = self::parametros($protocolo, $imovel_id);
        $cotacao = self::remessa($protocolo, $user);
        $imovel  = self::imovel($imovel_id, $cotacao);

        if (($imovel['fluxo'] ?? 'antigo') !== 'novo' || $imovel['status'] !== 'Aguardando aprovação') {
            json_response(['success' => false, 'message' => 'Este imóvel não está aguardando aprovação da cotação.'], 422);
        }

        $anexo = self::cotacaoAguardando($imovel_id);

        if (!db_execute("UPDATE cotacoes_anexos_incendio SET status_aprovacao = 'Recusada' WHERE id = ?", ['i', (int) $anexo['id']])) {
            json_response(['success' => false, 'message' => 'Erro ao recusar a cotação.'], 500);
        }

        historico_registrar(
            (int) $cotacao['id'], $protocolo, 'RECUSA_COTACAO',
            'Aguardando aprovação', 'Aguardando aprovação', (int) $user->uid, 'imobiliaria', $user->nome ?? 'Imobiliária',
            "Imóvel {$imovel['numero_imovel']}: cliente recusou a cotação."
        );

        self::notificarAdmins($cotacao, 'acao_imob', 'Cotação recusada pelo cliente',
            "O imóvel {$imovel['numero_imovel']} da cotação {$protocolo} teve a cotação recusada pelo cliente.",
            'incendio-imovel-cotacao-recusar');

        json_response(['success' => true, 'message' => 'Cotação recusada. A MPS será notificada.']);
    }

    /**
     * .../cotacao-recusar-cliente — MPS encerra UM imóvel (fluxo novo) depois
     * que o cliente recusou a cotação.
     */
    public function cotacaoRecusarCliente(string $protocolo, string $imovel_id)
    {
        $admin = require_admin();
        $imovel_id = self::parametros($protocolo, $imovel_id);
        $cotacao = self::remessa($protocolo);
        $imovel  = self::imovel($imovel_id, $cotacao);

        if (($imovel['fluxo'] ?? 'antigo') !== 'novo' || $imovel['status'] !== 'Aguardando aprovação') {
            json_response(['success' => false, 'message' => 'Este imóvel não está aguardando decisão sobre a cotação.'], 422);
        }

        $ultimo_anexo = db_fetch_one(
            "SELECT status_aprovacao FROM cotacoes_anexos_incendio WHERE seguro_incendio_imovel_id = ? AND tipo_arquivo = 'cotacao' ORDER BY data_envio DESC LIMIT 1",
            ['i', $imovel_id]
        );
        if (!$ultimo_anexo || ($ultimo_anexo['status_aprovacao'] ?? '') !== 'Recusada') {
            json_response(['success' => false, 'message' => 'Nenhuma cotação recusada pelo cliente para este imóvel.'], 422);
        }

        $status_anterior = $imovel['status'];
        $status_novo     = STATUS_RECUSADO_CLIENTE;

        db_execute(
            'UPDATE seguro_incendio_imoveis
             SET status = ?, data_status_recusado = IF(data_status_recusado IS NULL, NOW(), data_status_recusado),
                 data_atualizacao = NOW()
             WHERE id = ?',
            ['si', $status_novo, $imovel_id]
        );

        seguro_incendio_recalcular_status((int) $cotacao['id']);

        historico_registrar(
            (int) $cotacao['id'], $protocolo, 'ALTERACAO_STATUS',
            $status_anterior, $status_novo, (int) $admin->uid, 'admin', $admin->nome ?? 'MPS',
            "Imóvel {$imovel['numero_imovel']}: cliente recusou a cotação."
        );

        self::notificarImobiliaria($cotacao, 'Imóvel recusado pelo cliente',
            "O imóvel {$imovel['numero_imovel']} da cotação {$protocolo} foi encerrado como recusado pelo cliente.",
            'incendio-imovel-cotacao-recusar-cliente');

        json_response(['success' => true, 'message' => 'Imóvel marcado como recusado pelo cliente.', 'status' => $status_novo]);
    }

    /**
     * .../alteracao-solicitar — fluxo novo: a imobiliária pede pra MPS ajustar
     * valores da cotação enviada (antes de aprovar ou recusar de vez). O imóvel
     * sai de "Aguardando aprovação" pra "Aguardando nova cotação" — a MPS vê o
     * pedido no histórico e pode enviar uma nova cotação (cotacao() já aceita
     * esse status como origem).
     */
    public function alteracaoSolicitar(string $protocolo, string $imovel_id)
    {
        $user = require_auth();
        $imovel_id = self::parametros($protocolo, $imovel_id);

        $mensagem = trim(sanitize((string) ($_POST['mensagem'] ?? '')));
        if ($mensagem === '') {
            json_response(['success' => false, 'message' => 'Descreva o que precisa ser alterado na cotação.'], 422);
        }

        $cotacao = self::remessa($protocolo, $user);
        $imovel  = self::imovel($imovel_id, $cotacao);

        if (($imovel['fluxo'] ?? 'antigo') !== 'novo' || $imovel['status'] !== 'Aguardando aprovação') {
            json_response(['success' => false, 'message' => 'Este imóvel não está aguardando aprovação da cotação.'], 422);
        }

        $anexo = self::cotacaoAguardando($imovel_id);

        if (!db_execute("UPDATE cotacoes_anexos_incendio SET status_aprovacao = 'Alteração solicitada' WHERE id = ?", ['i', (int) $anexo['id']])) {
            json_response(['success' => false, 'message' => 'Erro ao registrar o pedido de alteração.'], 500);
        }

        $status_anterior = $imovel['status'];
        $status_novo     = 'Aguardando nova cotação';

        db_execute(
            'UPDATE seguro_incendio_imoveis SET status = ?, data_atualizacao = NOW() WHERE id = ?',
            ['si', $status_novo, $imovel_id]
        );

        seguro_incendio_recalcular_status((int) $cotacao['id']);

        historico_registrar(
            (int) $cotacao['id'], $protocolo, 'ALTERACAO_SOLICITADA',
            $status_anterior, $status_novo, (int) $user->uid, 'imobiliaria', $user->nome ?? 'Imobiliária',
            "Imóvel {$imovel['numero_imovel']}: solicitou alteração na cotação — \"{$mensagem}\""
        );

        self::postarNoChat($protocolo, $user, "Pedido de alteração na cotação do imóvel {$imovel['numero_imovel']}: {$mensagem}",
            'incendio-imovel-alteracao-solicitar');

        self::notificarAdmins($cotacao, 'acao_imob', 'Alteração solicitada na cotação',
            "O imóvel {$imovel['numero_imovel']} da cotação {$protocolo} recebeu um pedido de alteração do cliente.",
            'incendio-imovel-alteracao-solicitar');

        json_response(['success' => true, 'message' => 'Pedido de alteração enviado. A MPS vai enviar uma nova cotação.', 'status' => $status_novo]);
    }

    /**
     * .../proposta-alteracao-solicitar — fluxo antigo (status "Aprovado" =
     * proposta enviada, aguardando decisão): a imobiliária pede pra MPS ajustar
     * valores da proposta antes de aceitar ou recusar de vez. O imóvel vai pra
     * status próprio "Alteração solicitada" — a MPS vê o pedido no histórico e
     * reenvia (proposta() já aceita esse status como origem pra reenvio, igual
     * já fazia com o anexo "Recusada").
     */
    public function propostaAlteracaoSolicitar(string $protocolo, string $imovel_id)
    {
        $user = require_auth();
        $imovel_id = self::parametros($protocolo, $imovel_id);

        $mensagem = trim(sanitize((string) ($_POST['mensagem'] ?? '')));
        if ($mensagem === '') {
            json_response(['success' => false, 'message' => 'Descreva o que precisa ser alterado na proposta.'], 422);
        }

        $cotacao = self::remessa($protocolo, $user);
        $imovel  = self::imovel($imovel_id, $cotacao);

        if ($imovel['status'] !== 'Aprovado') {
            json_response(['success' => false, 'message' => 'Este imóvel não está aguardando decisão sobre a proposta.'], 422);
        }

        $anexo = db_fetch_one(
            "SELECT id FROM cotacoes_anexos_incendio WHERE seguro_incendio_imovel_id = ? AND status_aprovacao = 'Aguardando' ORDER BY data_envio DESC LIMIT 1",
            ['i', $imovel_id]
        );
        if (!$anexo) {
            json_response(['success' => false, 'message' => 'Nenhuma proposta aguardando decisão para este imóvel.'], 422);
        }

        if (!db_execute("UPDATE cotacoes_anexos_incendio SET status_aprovacao = 'Alteração solicitada' WHERE id = ?", ['i', (int) $anexo['id']])) {
            json_response(['success' => false, 'message' => 'Erro ao registrar o pedido de alteração.'], 500);
        }

        $status_anterior = $imovel['status'];
        $status_novo     = 'Alteração solicitada';

        db_execute('UPDATE seguro_incendio_imoveis SET status = ?, data_atualizacao = NOW() WHERE id = ?', ['si', $status_novo, $imovel_id]);

        seguro_incendio_recalcular_status((int) $cotacao['id']);

        historico_registrar(
            (int) $cotacao['id'], $protocolo, 'ALTERACAO_SOLICITADA',
            $status_anterior, $status_novo, (int) $user->uid, 'imobiliaria', $user->nome ?? 'Imobiliária',
            "Imóvel {$imovel['numero_imovel']}: solicitou alteração na proposta — \"{$mensagem}\""
        );

        self::postarNoChat($protocolo, $user, "Pedido de alteração na proposta do imóvel {$imovel['numero_imovel']}: {$mensagem}",
            'incendio-imovel-proposta-alteracao-solicitar');

        self::notificarAdmins($cotacao, 'acao_imob', 'Alteração solicitada na proposta',
            "O imóvel {$imovel['numero_imovel']} da cotação {$protocolo} recebeu um pedido de alteração do cliente.",
            'incendio-imovel-proposta-alteracao-solicitar');

        json_response(['success' => true, 'message' => 'Pedido de alteração enviado. A MPS vai enviar uma nova proposta.', 'status' => $status_novo]);
    }

    /**
     * .../apolice — fluxo novo: MPS anexa o PDF da apólice de UM imóvel.
     * Anexar a apólice conclui o imóvel direto, sem etapa de confirmação.
     */
    public function apolice(string $protocolo, string $imovel_id)
    {
        $admin = require_admin();
        $imovel_id = self::parametros($protocolo, $imovel_id);
        $cotacao = self::remessa($protocolo);
        $imovel  = self::imovel($imovel_id, $cotacao);

        if (($imovel['fluxo'] ?? 'antigo') !== 'novo' || $imovel['status'] !== 'Aguardando apólice') {
            json_response(['success' => false, 'message' => 'Este imóvel não está aguardando a apólice.'], 422);
        }

        self::validarUpload();

        $arquivo_url = self::upload('apolices/incendio');

        db_execute(
            "INSERT INTO cotacoes_anexos_incendio (protocolo, seguro_incendio_imovel_id, nome_arquivo, caminho_arquivo, tipo_arquivo, data_envio, usuario_envio, status_aprovacao)
             VALUES (?, ?, ?, ?, 'apolice', NOW(), ?, 'Emitida')",
            ['sisss', $protocolo, $imovel_id, $_FILES['arquivo']['name'], $arquivo_url, $admin->nome ?? 'MPS']
        );

        $status_anterior = $imovel['status'];
        $status_novo     = 'Concluída';

        db_execute(
            "UPDATE seguro_incendio_imoveis SET status = ?, data_status_apolice_anexada = NOW(), data_status_concluido = NOW(), data_atualizacao = NOW() WHERE id = ?",
            ['si', $status_novo, $imovel_id]
        );

        seguro_incendio_recalcular_status((int) $cotacao['id']);

        historico_registrar(
            (int) $cotacao['id'], $protocolo, 'ENVIO_COTACAO',
            $status_anterior, $status_novo, (int) $admin->uid, 'admin', $admin->nome ?? 'MPS',
            "Imóvel {$imovel['numero_imovel']}: PDF da apólice anexado. Processo concluído."
        );

        self::notificarImobiliaria($cotacao, 'Apólice emitida',
            "A apólice do imóvel {$imovel['numero_imovel']} do protocolo {$protocolo} foi anexada. Processo concluído.",
            'incendio-imovel-apolice');

        json_response([
            'success'     => true,
            'message'     => 'Apólice anexada. Processo concluído.',
            'status'      => $status_novo,
            'arquivo_url' => $arquivo_url,
        ]);
    }

    // ── Trechos repetidos em todos os arquivos antigos ──────────────────────

    /** Confere os parâmetros da rota; devolve o id do imóvel (int). */
    private static function parametros(string $protocolo, string $imovel_id): int
    {
        $id = (int) $imovel_id;
        if (!$protocolo || !$id) {
            json_response(['success' => false, 'message' => 'Parâmetros inválidos.'], 400);
        }
        return $id;
    }

    /**
     * Remessa pelo protocolo. Com $imob (ações da imobiliária): só a da própria
     * imobiliária e, pro operador comum, só a que ele mesmo criou.
     */
    private static function remessa(string $protocolo, ?object $imob = null): array
    {
        $cotacao = db_fetch_one('SELECT * FROM seguro_incendio WHERE protocolo = ?', ['s', $protocolo]);
        if (!$cotacao || ($imob && (int) $cotacao['imobiliaria_id'] !== (int) ($imob->imobiliaria_id ?? 0))) {
            json_response(['success' => false, 'message' => 'Cotação não encontrada.'], 404);
        }
        if ($imob && empty($imob->imob_admin) && (int) ($cotacao['usuario_id'] ?? 0) !== (int) $imob->uid) {
            json_response(['success' => false, 'message' => 'Cotação não encontrada.'], 404);
        }
        return $cotacao;
    }

    /** Imóvel da remessa. */
    private static function imovel(int $imovel_id, array $cotacao): array
    {
        $imovel = db_fetch_one(
            'SELECT * FROM seguro_incendio_imoveis WHERE id = ? AND seguro_incendio_id = ?',
            ['ii', $imovel_id, $cotacao['id']]
        );
        if (!$imovel) {
            json_response(['success' => false, 'message' => 'Imóvel não encontrado.'], 404);
        }
        return $imovel;
    }

    /** Última cotação (PDF, fluxo novo) aguardando a decisão da imobiliária. */
    private static function cotacaoAguardando(int $imovel_id): array
    {
        $anexo = db_fetch_one(
            "SELECT id FROM cotacoes_anexos_incendio WHERE seguro_incendio_imovel_id = ? AND tipo_arquivo = 'cotacao' AND status_aprovacao = 'Aguardando' ORDER BY data_envio DESC LIMIT 1",
            ['i', $imovel_id]
        );
        if (!$anexo) {
            json_response(['success' => false, 'message' => 'Nenhuma cotação aguardando aprovação para este imóvel.'], 422);
        }
        return $anexo;
    }

    /** Validação do PDF em $_FILES['arquivo'] (limite do servidor, ausente, erro). */
    private static function validarUpload(): void
    {
        if ($m = upload_erro_limite()) {
            json_response(['success' => false, 'message' => $m], 413);
        }
        if (empty($_FILES['arquivo']) || ($_FILES['arquivo']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            json_response(['success' => false, 'message' => 'Arquivo PDF é obrigatório.'], 422);
        }
        if ($_FILES['arquivo']['error'] !== UPLOAD_ERR_OK) {
            $msg = in_array($_FILES['arquivo']['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
                ? 'O arquivo excede o limite do servidor (' . upload_limite_label() . '). Envie um arquivo menor.'
                : 'Falha no upload do arquivo. Tente novamente.';
            json_response(['success' => false, 'message' => $msg], 422);
        }
    }

    /** media_upload() do PDF; falha vira JSON 500 com a mensagem. */
    private static function upload(string $pasta): string
    {
        try {
            return media_upload($_FILES['arquivo'], $pasta);
        } catch (\Throwable $e) {
            json_response(['success' => false, 'message' => 'Erro ao enviar arquivo: ' . $e->getMessage()], 500);
        }
    }

    /** Notificação 'status_alterado' pra imobiliária (o dono da remessa). */
    private static function notificarImobiliaria(array $cotacao, string $titulo, string $texto, string $tag): void
    {
        try {
            notificar_imobiliaria(
                (int) $cotacao['imobiliaria_id'],
                'status_alterado',
                $titulo,
                $texto,
                (int) $cotacao['id'],
                null,
                (int) ($cotacao['usuario_id'] ?? 0)
            );
        } catch (\Throwable $e) {
            error_log("[{$tag}] Erro notificação: " . $e->getMessage());
        }
    }

    /** Notificação pra todos da MPS. */
    private static function notificarAdmins(array $cotacao, string $tipo, string $titulo, string $texto, string $tag): void
    {
        try {
            notificar_todos_admins($tipo, $titulo, $texto, (int) $cotacao['id']);
        } catch (\Throwable $e) {
            error_log("[{$tag}] Erro notificação: " . $e->getMessage());
        }
    }

    /**
     * Pedido de alteração também entra no chat da cotação — é a MPS quem
     * acompanha o chat no dia a dia, não só o histórico de status.
     */
    private static function postarNoChat(string $protocolo, object $user, string $mensagem, string $tag): void
    {
        try {
            $chat_msg_id = db_execute(
                "INSERT INTO cotacao_mensagens (protocolo, autor_tipo, autor_id, autor_nome, mensagem, criado_em) VALUES (?, ?, ?, ?, ?, NOW())",
                ['ssiss', $protocolo, 'imobiliaria', (int) $user->uid, $user->nome ?? 'Imobiliária', $mensagem]
            );
            db_execute(
                "INSERT INTO cotacao_mensagens_lidas (protocolo, usuario_id, papel, ultima_id_lida)
                 VALUES (?, ?, 'imobiliaria', ?)
                 ON DUPLICATE KEY UPDATE ultima_id_lida = GREATEST(ultima_id_lida, VALUES(ultima_id_lida)), atualizado_em = NOW()",
                ['sii', $protocolo, (int) $user->uid, $chat_msg_id]
            );
        } catch (\Throwable $e) {
            error_log("[{$tag}] Erro ao postar no chat: " . $e->getMessage());
        }
    }
}
