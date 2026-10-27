<?php

namespace App\Http\Controllers\Cotacao;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * Ações genéricas de cotação (fluxo manual MPS ↔ imobiliária), válidas pra
 * todos os produtos via cotacao_resolver($protocolo).
 * Era includes/acao/cotacao-{aprovar,recusar,iniciar-analise,proposta,
 * recusar-mps,recusar-cliente,desistir}.php.
 */
class AcoesController extends Controller
{
    /**
     * POST /api/cotacao/{protocolo}/aprovar
     * Imobiliária aceita, em nome do cliente, a proposta enviada pela MPS.
     */
    public function aprovar(string $protocolo)
    {
        $user = require_perm('perm_aceitar');
        if (!$protocolo) json_response(['success' => false, 'message' => 'Protocolo inválido.'], 400);

        $resolvido = cotacao_resolver($protocolo);
        if (!$resolvido) json_response(['success' => false, 'message' => 'Cotação não encontrada.'], 404);
        if ($resolvido['tipo'] === 'fianca') {
            json_response(['success' => false, 'message' => 'No Seguro Fiança a decisão é pelos orçamentos.'], 422);
        }

        $tbl_anexos  = $resolvido['tabela_anexos'];
        $tbl_cotacao = $resolvido['tabela'];

        if (!cotacao_pode_acessar($resolvido, $user)) {
            json_response(['success' => false, 'message' => 'Cotação não encontrada.'], 404);
        }
        $cotacao = $resolvido['row'];

        if ($cotacao['status'] !== 'Aprovado') {
            json_response(['success' => false, 'message' => 'Esta cotação não está aguardando decisão do cliente.'], 422);
        }

        $anexo = db_fetch_one(
            "SELECT id FROM {$tbl_anexos} WHERE protocolo = ? AND status_aprovacao = 'Aguardando' ORDER BY data_envio DESC LIMIT 1",
            ['s', $protocolo]
        );
        if (!$anexo) json_response(['success' => false, 'message' => 'Nenhuma proposta aguardando decisão.'], 422);

        $ok1 = db_execute("UPDATE {$tbl_anexos} SET status_aprovacao = 'Aprovada' WHERE id = ?", ['i', (int) $anexo['id']]);
        $ok2 = db_execute(
            "UPDATE {$tbl_cotacao} SET status = 'Concluída', data_status_concluido = NOW(), data_atualizacao = NOW() WHERE protocolo = ? LIMIT 1",
            ['s', $protocolo]
        );

        if (!$ok1 || !$ok2) json_response(['success' => false, 'message' => 'Erro ao aprovar cotação.'], 500);

        historico_registrar(
            (int) $cotacao['id'], $protocolo, 'APROVACAO_COTACAO',
            'Aprovado', 'Concluída', (int) $user->uid, 'imobiliaria', $user->nome ?? 'Imobiliária',
            'Cliente aceitou a proposta.'
        );

        try {
            notificar_todos_admins(
                'cotacao_finalizada',
                'Cotação concluída',
                "Cotação {$protocolo} aceita pelo cliente. Concluída!",
                (int) $cotacao['id']
            );
        } catch (\Throwable $e) {
            error_log('[aprovar] Erro notificação: ' . $e->getMessage());
        }

        zapi_notificar_atendente(
            isset($cotacao['atendente_id']) ? (int) $cotacao['atendente_id'] : null,
            "✅ *Proposta aceita pelo cliente — " . zapi_imobiliaria_nome((int) $resolvido['imobiliaria_id']) . "*\n\n"
            . "📄 Protocolo: `{$protocolo}`",
            'proposta_aceita',
            $protocolo
        );

        json_response(['success' => true, 'message' => 'Cotação aprovada com sucesso!']);
    }

    /**
     * POST /api/cotacao/{protocolo}/recusar
     * Imobiliária recusa, em nome do cliente, a proposta enviada pela MPS.
     * Cotação volta para "Em análise" para a MPS poder enviar uma nova proposta.
     */
    public function recusar(string $protocolo)
    {
        $user = require_auth();
        if (!$protocolo) json_response(['success' => false, 'message' => 'Protocolo inválido.'], 400);

        $resolvido = cotacao_resolver($protocolo);
        if (!$resolvido) json_response(['success' => false, 'message' => 'Cotação não encontrada.'], 404);
        if ($resolvido['tipo'] === 'fianca') {
            json_response(['success' => false, 'message' => 'No Seguro Fiança a decisão é pelos orçamentos.'], 422);
        }

        $tbl_anexos  = $resolvido['tabela_anexos'];
        $tbl_cotacao = $resolvido['tabela'];

        if (!cotacao_pode_acessar($resolvido, $user)) {
            json_response(['success' => false, 'message' => 'Cotação não encontrada.'], 404);
        }
        $cotacao = $resolvido['row'];

        if ($cotacao['status'] !== 'Aprovado') {
            json_response(['success' => false, 'message' => 'Esta cotação não está aguardando decisão do cliente.'], 422);
        }

        $anexo = db_fetch_one(
            "SELECT id FROM {$tbl_anexos} WHERE protocolo = ? AND status_aprovacao = 'Aguardando' ORDER BY data_envio DESC LIMIT 1",
            ['s', $protocolo]
        );
        if (!$anexo) json_response(['success' => false, 'message' => 'Nenhuma proposta aguardando decisão.'], 422);

        // O status da cotação permanece "Aprovado" (igual ao legado) — só o anexo muda para
        // "Recusada", o que libera a MPS para enviar uma nova proposta sem perder o histórico.
        $ok1 = db_execute("UPDATE {$tbl_anexos} SET status_aprovacao = 'Recusada' WHERE id = ?", ['i', (int) $anexo['id']]);
        if (!$ok1) json_response(['success' => false, 'message' => 'Erro ao recusar cotação.'], 500);

        // Sem checar o retorno: affected_rows vem 0 quando data_atualizacao já é o
        // NOW() deste segundo (outra ação acabou de mexer na cotação) — não é falha.
        db_execute(
            "UPDATE {$tbl_cotacao} SET data_atualizacao = NOW() WHERE protocolo = ? LIMIT 1",
            ['s', $protocolo]
        );

        historico_registrar(
            (int) $cotacao['id'], $protocolo, 'RECUSA_COTACAO',
            'Aprovado', 'Aprovado', (int) $user->uid, 'imobiliaria', $user->nome ?? 'Imobiliária',
            'Cliente recusou a proposta.'
        );

        try {
            notificar_todos_admins(
                'acao_imob',
                'Proposta recusada pelo cliente',
                "Cotação {$protocolo} recusada pelo cliente.",
                (int) $cotacao['id']
            );
        } catch (\Throwable $e) {
            error_log('[recusar] Erro notificação: ' . $e->getMessage());
        }

        zapi_notificar_atendente(
            isset($cotacao['atendente_id']) ? (int) $cotacao['atendente_id'] : null,
            "❌ *Proposta recusada pelo cliente — " . zapi_imobiliaria_nome((int) $resolvido['imobiliaria_id']) . "*\n\n"
            . "📄 Protocolo: `{$protocolo}`",
            'proposta_recusada',
            $protocolo
        );

        json_response(['success' => true, 'message' => 'Cotação recusada. A MPS será notificada e poderá enviar uma nova proposta.']);
    }

    /**
     * POST /api/cotacao/{protocolo}/iniciar-analise
     * MPS inicia a análise (ficha completa pronta). Atribui o atendente responsável,
     * igual ao sistema legado (admin/includes/acao/status.php, caso 'Em Analise').
     */
    public function iniciarAnalise(string $protocolo)
    {
        $admin = require_admin();

        if (!$protocolo) {
            json_response(['success' => false, 'message' => 'Protocolo inválido.'], 400);
        }

        $resolvido = cotacao_resolver($protocolo);
        if (!$resolvido) {
            json_response(['success' => false, 'message' => 'Cotação não encontrada.'], 404);
        }
        $tbl_cotacao = $resolvido['tabela'];
        $cotacao     = $resolvido['row'];

        // "formulario_completo" só existe (e só importa) no fluxo de fiança — os
        // demais tipos são enviados sempre completos, sem etapa de rascunho na
        // própria tabela principal.
        $fichaCompleta = $resolvido['tipo'] !== 'fianca' || (int) ($cotacao['formulario_completo'] ?? 1) === 1;

        $statusPermitidos = ['Aguardando análise', 'Aguardando preenchimento'];
        if (!in_array($cotacao['status'], $statusPermitidos, true) || !$fichaCompleta) {
            json_response(['success' => false, 'message' => 'Esta cotação não está com a ficha completa pronta para análise.'], 422);
        }

        $status_anterior = $cotacao['status'];
        $status_novo     = 'Em Analise';

        db_execute(
            "UPDATE {$tbl_cotacao}
             SET status = ?, atendente_id = ?, data_status_em_analise = NOW(), data_atualizacao = NOW()
             WHERE protocolo = ?",
            ['sis', $status_novo, (int) $admin->uid, $protocolo]
        );

        historico_registrar(
            (int) $cotacao['id'], $protocolo, 'ALTERACAO_STATUS',
            $status_anterior, $status_novo, (int) $admin->uid, 'admin', $admin->nome ?? 'MPS',
            'Alteração de status via tela de detalhes.'
        );

        try {
            notificar_imobiliaria(
                (int) $cotacao['imobiliaria_id'],
                'status_alterado',
                'Cotação em análise',
                "Sua cotação {$protocolo} entrou em análise pela equipe MPS.",
                (int) $cotacao['id'],
                null,
                (int) ($cotacao['usuario_id'] ?? 0)
            );
        } catch (\Throwable $e) {
            error_log('[iniciar-analise] Erro notificação: ' . $e->getMessage());
        }

        json_response(['success' => true, 'message' => 'Análise iniciada.', 'status' => $status_novo]);
    }

    /**
     * POST /api/cotacao/{protocolo}/proposta
     * MPS envia a proposta (PDF) para a cotação. Usa a tabela de anexos do produto
     * (já suporta múltiplas linhas — cada nova proposta é uma nova linha, sem apagar a anterior).
     */
    public function proposta(Request $request, string $protocolo)
    {
        $admin = require_admin();

        if (!$protocolo) {
            json_response(['success' => false, 'message' => 'Protocolo inválido.'], 400);
        }

        $resolvido = cotacao_resolver($protocolo);
        if (!$resolvido) {
            json_response(['success' => false, 'message' => 'Cotação não encontrada.'], 404);
        }
        if ($resolvido['tipo'] === 'fianca') {
            json_response(['success' => false, 'message' => 'No Seguro Fiança a MPS envia orçamentos.'], 422);
        }
        $tbl_cotacao = $resolvido['tabela'];
        $tbl_anexos  = $resolvido['tabela_anexos'];
        $cotacao     = $resolvido['row'];

        $prontoParaDecisao = $cotacao['status'] === 'Em Analise';

        // Permite enviar proposta quando pronto pra decisão, OU já está aprovado mas
        // a proposta anterior foi recusada pelo cliente (envia nova proposta no mesmo status "Aprovado").
        $anexoAnterior = db_fetch_one(
            "SELECT * FROM {$tbl_anexos} WHERE protocolo = ? ORDER BY data_envio DESC LIMIT 1",
            ['s', $protocolo]
        );
        $podeReenviar = $cotacao['status'] === 'Aprovado' && $anexoAnterior && $anexoAnterior['status_aprovacao'] === 'Recusada';

        if (!($prontoParaDecisao || $podeReenviar)) {
            json_response([
                'success' => false,
                'message' => 'Status atual não permite envio de proposta.',
            ], 422);
        }

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

        $observacao = sanitize((string) $request->input('observacao', ''));

        try {
            $arquivo_url = media_upload($_FILES['arquivo'], 'propostas/' . $resolvido['tipo']);
        } catch (\Illuminate\Http\Exceptions\HttpResponseException $e) {
            throw $e;
        } catch (\Throwable $e) {
            json_response(['success' => false, 'message' => 'Erro ao enviar arquivo: ' . $e->getMessage()], 500);
        }

        db_execute(
            "INSERT INTO {$tbl_anexos} (protocolo, nome_arquivo, caminho_arquivo, tipo_arquivo, data_envio, usuario_envio, status_aprovacao)
             VALUES (?, ?, ?, 'proposta', NOW(), ?, 'Aguardando')",
            ['ssss', $protocolo, $_FILES['arquivo']['name'], $arquivo_url, $admin->nome ?? 'MPS']
        );

        $status_anterior = $cotacao['status'];
        $status_novo     = 'Aprovado';

        db_execute(
            "UPDATE {$tbl_cotacao} SET status = ?, data_status_aprovado = NOW(), data_atualizacao = NOW() WHERE protocolo = ?",
            ['ss', $status_novo, $protocolo]
        );

        historico_registrar(
            (int) $cotacao['id'], $protocolo, 'ENVIO_COTACAO',
            $status_anterior, $status_novo, (int) $admin->uid, 'admin', $admin->nome ?? 'MPS',
            trim(($podeReenviar ? 'Nova proposta enviada após recusa do cliente. ' : 'Proposta enviada. ') . $observacao)
        );

        try {
            notificar_imobiliaria(
                (int) $cotacao['imobiliaria_id'],
                'status_alterado',
                'Proposta enviada',
                "Proposta enviada para o protocolo {$protocolo}. Aguardando sua decisão.",
                (int) $cotacao['id'],
                null,
                (int) ($cotacao['usuario_id'] ?? 0)
            );
        } catch (\Throwable $e) {
            error_log('[proposta] Erro notificação: ' . $e->getMessage());
        }

        json_response([
            'success'     => true,
            'message'     => 'Proposta enviada com sucesso.',
            'status'      => $status_novo,
            'arquivo_url' => $arquivo_url,
        ]);
    }

    /**
     * POST /api/cotacao/{protocolo}/recusar-mps
     * MPS recusa uma cotação em análise (após avaliação completa).
     */
    public function recusarMps(string $protocolo)
    {
        $admin = require_admin();

        if (!$protocolo) {
            json_response(['success' => false, 'message' => 'Protocolo inválido.'], 400);
        }

        $resolvido = cotacao_resolver($protocolo);
        if (!$resolvido) {
            json_response(['success' => false, 'message' => 'Cotação não encontrada.'], 404);
        }
        $tbl_cotacao = $resolvido['tabela'];
        $cotacao     = $resolvido['row'];

        $prontoParaRecusar = $cotacao['status'] === 'Em Analise'
            || ($tbl_cotacao === 'seguro_fianca' && $cotacao['status'] === 'Pré-aprovado');

        if (!$prontoParaRecusar) {
            json_response(['success' => false, 'message' => 'Esta cotação ainda não está pronta para ser recusada.'], 422);
        }

        $status_anterior = $cotacao['status'];
        $status_novo     = 'Recusado';

        db_execute(
            "UPDATE {$tbl_cotacao}
             SET status = ?, atendente_id = IF(atendente_id IS NULL, ?, atendente_id),
                 data_status_recusado = IF(data_status_recusado IS NULL, NOW(), data_status_recusado),
                 data_atualizacao = NOW()
             WHERE protocolo = ?",
            ['sis', $status_novo, (int) $admin->uid, $protocolo]
        );

        historico_registrar(
            (int) $cotacao['id'], $protocolo, 'ALTERACAO_STATUS',
            $status_anterior, $status_novo, (int) $admin->uid, 'admin', $admin->nome ?? 'MPS', null
        );

        try {
            notificar_imobiliaria(
                (int) $cotacao['imobiliaria_id'],
                'status_alterado',
                'Cotação recusada',
                "Sua cotação {$protocolo} foi recusada pela MPS.",
                (int) $cotacao['id'],
                null,
                (int) ($cotacao['usuario_id'] ?? 0)
            );
        } catch (\Throwable $e) {
            error_log('[recusar-mps] Erro notificação: ' . $e->getMessage());
        }

        json_response(['success' => true, 'message' => 'Cotação recusada.', 'status' => $status_novo]);
    }

    /**
     * POST /api/cotacao/{protocolo}/recusar-cliente
     * MPS encerra a cotação depois que o cliente recusou a(s) proposta(s) enviada(s).
     * A cotação fica com status "Recusado pelo cliente" (diferente de "Recusado",
     * que é a recusa da própria MPS na análise).
     */
    public function recusarCliente(string $protocolo)
    {
        $admin = require_admin();

        if (!$protocolo) {
            json_response(['success' => false, 'message' => 'Protocolo inválido.'], 400);
        }

        $resolvido = cotacao_resolver($protocolo);
        if (!$resolvido) {
            json_response(['success' => false, 'message' => 'Cotação não encontrada.'], 404);
        }
        $tbl_cotacao = $resolvido['tabela'];
        $tbl_anexos  = $resolvido['tabela_anexos'];
        $cotacao     = $resolvido['row'];

        if ($cotacao['status'] !== 'Aprovado') {
            json_response(['success' => false, 'message' => 'Esta cotação não está aguardando decisão do cliente.'], 422);
        }

        // Só encerra se a última proposta enviada foi mesmo recusada pelo cliente.
        $ultimo_anexo = db_fetch_one(
            "SELECT status_aprovacao FROM {$tbl_anexos} WHERE protocolo = ? AND tipo_arquivo = 'proposta' ORDER BY data_envio DESC, id DESC LIMIT 1",
            ['s', $protocolo]
        );
        if (!$ultimo_anexo || ($ultimo_anexo['status_aprovacao'] ?? '') !== 'Recusada') {
            json_response(['success' => false, 'message' => 'Nenhuma proposta recusada pelo cliente.'], 422);
        }

        $status_anterior = $cotacao['status'];
        $status_novo     = STATUS_RECUSADO_CLIENTE;

        db_execute(
            "UPDATE {$tbl_cotacao}
             SET status = ?, atendente_id = IF(atendente_id IS NULL, ?, atendente_id),
                 data_status_recusado = IF(data_status_recusado IS NULL, NOW(), data_status_recusado),
                 data_atualizacao = NOW()
             WHERE protocolo = ?",
            ['sis', $status_novo, (int) $admin->uid, $protocolo]
        );

        historico_registrar(
            (int) $cotacao['id'], $protocolo, 'ALTERACAO_STATUS',
            $status_anterior, $status_novo, (int) $admin->uid, 'admin', $admin->nome ?? 'MPS',
            'Cliente recusou as propostas enviadas.'
        );

        try {
            notificar_imobiliaria(
                (int) $cotacao['imobiliaria_id'],
                'status_alterado',
                'Cotação recusada pelo cliente',
                "A cotação {$protocolo} foi encerrada como recusada pelo cliente.",
                (int) $cotacao['id'],
                null,
                (int) ($cotacao['usuario_id'] ?? 0)
            );
        } catch (\Throwable $e) {
            error_log('[recusar-cliente] Erro notificação: ' . $e->getMessage());
        }

        json_response(['success' => true, 'message' => 'Cotação marcada como recusada pelo cliente.', 'status' => $status_novo]);
    }

    /**
     * POST /api/cotacao/{protocolo}/desistir
     * Registra a desistência do cliente: a cotação é encerrada com o status
     * "Desistência" e não avança mais (ver cotacao_status_encerrado).
     *
     * Pode ser acionado pela imobiliária, na tela de detalhe da cotação, ou pela
     * MPS, quando o cliente avisa a desistência direto para a equipe. Diferente de
     * "Recusado" (decisão da análise) e de "Recusado pelo cliente" (proposta
     * enviada e recusada) — aqui quem abandona é o cliente, em qualquer etapa.
     */
    public function desistir(string $protocolo)
    {
        $user = require_auth();

        if (!$protocolo) {
            json_response(['success' => false, 'message' => 'Protocolo inválido.'], 400);
        }

        $is_admin = ($user->role ?? '') === 'admin';

        $resolvido = cotacao_resolver($protocolo);
        if (!$resolvido) {
            json_response(['success' => false, 'message' => 'Cotação não encontrada.'], 404);
        }

        // Usuário de imobiliária só desiste das próprias cotações (admin da
        // imobiliária pode de qualquer atendente; operador comum só a dele).
        if (!cotacao_pode_acessar($resolvido, $user)) {
            json_response(['success' => false, 'message' => 'Cotação não encontrada.'], 404);
        }

        $tbl_cotacao     = $resolvido['tabela'];
        $cotacao         = $resolvido['row'];
        $status_anterior = (string) ($cotacao['status'] ?? '');

        if (cotacao_status_encerrado($status_anterior)) {
            json_response(['success' => false, 'message' => 'Esta cotação já está encerrada.'], 422);
        }

        // Residencial da API: depois de escolhida a proposta de uma seguradora (emissão
        // na fila, proposta ou apólice) não dá mais pra desistir por aqui.
        if ($tbl_cotacao === 'seguro_residencial' && ($cotacao['origem'] ?? '') === 'api') {
            $escolhida = api_seguradora_escolhida((int) $cotacao['id']);
            if ($escolhida !== null) {
                json_response(['success' => false, 'message' => 'O cliente já escolheu a proposta da ' . api_seguradora_nome($escolhida) . ' — não dá para encerrar por desistência.'], 422);
            }
        }

        $ok = db_execute(
            "UPDATE {$tbl_cotacao} SET status = ?, data_atualizacao = NOW() WHERE protocolo = ? LIMIT 1",
            ['ss', STATUS_DESISTENCIA, $protocolo]
        );
        if (!$ok) {
            json_response(['success' => false, 'message' => 'Erro ao registrar a desistência.'], 500);
        }

        $autor_tipo = $is_admin ? 'admin' : 'imobiliaria';
        $autor_nome = $user->nome ?? ($is_admin ? 'MPS' : 'Imobiliária');

        historico_registrar(
            (int) $cotacao['id'], $protocolo, 'ALTERACAO_STATUS',
            $status_anterior, STATUS_DESISTENCIA, (int) $user->uid, $autor_tipo, $autor_nome,
            'Cliente desistiu da cotação.'
        );

        try {
            if ($is_admin) {
                notificar_imobiliaria(
                    (int) $cotacao['imobiliaria_id'],
                    'status_alterado',
                    'Cotação encerrada por desistência',
                    "A cotação {$protocolo} foi encerrada por desistência do cliente.",
                    (int) $cotacao['id'],
                    null,
                    (int) ($cotacao['usuario_id'] ?? 0)
                );
            } else {
                notificar_todos_admins(
                    'acao_imob',
                    'Cliente desistiu da cotação',
                    "A cotação {$protocolo} foi encerrada por desistência do cliente.",
                    (int) $cotacao['id']
                );

                zapi_notificar_atendente(
                    isset($cotacao['atendente_id']) ? (int) $cotacao['atendente_id'] : null,
                    "🚪 *Cliente desistiu da cotação — " . zapi_imobiliaria_nome((int) $cotacao['imobiliaria_id']) . "*\n\n"
                    . "📄 Protocolo: `{$protocolo}`",
                    'desistencia',
                    $protocolo
                );
            }
        } catch (\Throwable $e) {
            error_log('[desistir] Erro notificação: ' . $e->getMessage());
        }

        json_response([
            'success' => true,
            'message' => 'Cotação encerrada por desistência do cliente.',
            'status'  => STATUS_DESISTENCIA,
        ]);
    }
}
