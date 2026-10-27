<?php

namespace App\Http\Controllers\Fianca;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * Documentos do Seguro Fiança depois da pré-aprovação (ver
 * app/Legado/fianca_documentos.php): pré-aprovar, biometria, orçamento,
 * contrato de locação e apólice. Mesmo fluxo do cotacoes/ — vale para os
 * três canais (manual, API e Segimob): API/Segimob chegam aqui já
 * pré-aprovadas pelo resultado da análise das seguradoras.
 */
class DocumentosController extends Controller
{
    /**
     * POST /api/cotacao/{protocolo}/pre-aprovar
     * MPS pré-aprova a ficha de fiança em análise (canal manual). Libera os
     * documentos.
     */
    public function preAprovar(string $protocolo)
    {
        $admin     = require_admin();
        $resolvido = $protocolo !== '' ? cotacao_resolver($protocolo) : null;
        if (!$resolvido || $resolvido['tipo'] !== 'fianca') {
            json_response(['success' => false, 'message' => 'Cotação não encontrada.'], 404);
        }
        $cotacao = $resolvido['row'];

        if (!in_array($cotacao['status'], ['Em Analise', 'Em Análise'], true)) {
            json_response(['success' => false, 'message' => 'Só uma cotação em análise pode ser pré-aprovada.'], 422);
        }

        fianca_docs_mudar_status($cotacao, 'Pré-aprovado', $admin, 'Cotação pré-aprovada. Documentos liberados.');
        fianca_docs_notificar(
            $cotacao, $admin, 'Cotação pré-aprovada',
            "A cotação {$protocolo} foi pré-aprovada. Acompanhe a biometria e os orçamentos."
        );

        json_response(['success' => true, 'message' => 'Cotação pré-aprovada.', 'status' => 'Pré-aprovado']);
    }

    /**
     * POST /api/cotacao/{protocolo}/biometria
     * Links de biometria — um por seguradora (FIANCA_SEGURADORAS).
     *  - adicionar (MPS, seguradora + link) — link "pendente"; depois de adicionado não muda mais
     *  - concluir  (MPS ou imobiliária, id) — o cliente concluiu a biometria
     */
    public function biometria(Request $request, string $protocolo)
    {
        [$user, $ehAdmin, $cotacao, $acao, $autor] = $this->contexto($request, $protocolo);

        if (!in_array($acao, ['adicionar', 'concluir'], true)) {
            json_response(['success' => false, 'message' => 'Ação inválida.'], 422);
        }
        if ($acao === 'adicionar' && !$ehAdmin) {
            json_response(['success' => false, 'message' => 'Ação não permitida para o seu usuário.'], 403);
        }

        if ($acao === 'adicionar') {
            $seguradora = fianca_seguradora_ou_erro();
            $nome       = FIANCA_SEGURADORAS[$seguradora]['nome'];
            $link       = trim((string) $request->post('link', ''));
            if (!filter_var($link, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $link)) {
                json_response(['success' => false, 'message' => 'Informe um link válido (https://...).'], 422);
            }
            $existe = db_fetch_one(
                'SELECT id FROM seguro_fianca_biometrias WHERE protocolo = ? AND seguradora = ? LIMIT 1',
                ['ss', $protocolo, $seguradora]
            );
            if ($existe) {
                json_response(['success' => false, 'message' => "A {$nome} já tem link de biometria."], 422);
            }
            db_execute(
                'INSERT INTO seguro_fianca_biometrias (seguro_fianca_id, protocolo, seguradora, link, criado_por) VALUES (?, ?, ?, ?, ?)',
                ['issss', (int) $cotacao['id'], $protocolo, $seguradora, $link, $autor]
            );
            // Mesmo texto do sistema antigo: o chat da cotação mostra o link com botão de copiar.
            fianca_docs_registrar($cotacao, $user, "Link de biometria enviado: {$link}");
            fianca_docs_notificar($cotacao, $user, 'Biometria', "Link de biometria da {$nome} na cotação {$protocolo}. Copie e envie ao cliente.");
            json_response(['success' => true, 'message' => 'Link de biometria adicionado.']);
        }

        $id  = (int) $request->post('id', 0);
        $bio = db_fetch_one('SELECT * FROM seguro_fianca_biometrias WHERE id = ? AND protocolo = ?', ['is', $id, $protocolo]);
        if (!$bio) {
            json_response(['success' => false, 'message' => 'Link de biometria não encontrado.'], 404);
        }
        if ($bio['situacao'] === 'concluida') {
            json_response(['success' => false, 'message' => 'Esta biometria já está concluída.'], 422);
        }
        db_execute(
            "UPDATE seguro_fianca_biometrias SET situacao = 'concluida', concluida_em = NOW(), concluida_por = ? WHERE id = ?",
            ['si', $autor, $id]
        );
        $nome = fianca_doc_nome($bio);
        fianca_docs_registrar($cotacao, $user, "Biometria concluída: {$nome}.");
        if ($ehAdmin) {
            fianca_docs_notificar($cotacao, $user, 'Biometria concluída', "A MPS marcou a biometria da {$nome} como concluída na cotação {$protocolo}.");
        } else {
            fianca_docs_notificar($cotacao, $user, 'Biometria concluída', "A imobiliária concluiu a biometria da {$nome} na cotação {$protocolo}. Anexe o orçamento.");
            zapi_notificar_atendente(
                isset($cotacao['atendente_id']) ? (int) $cotacao['atendente_id'] : null,
                "✅ *Biometria concluída pela imobiliária*\n\n"
                . 'Pretendente: *' . ($cotacao['nome_completo'] ?? 'Pretendente') . "*\n\n"
                . "📄 Protocolo: `{$protocolo}`",
                'biometria_concluida',
                $protocolo
            );
        }
        json_response(['success' => true, 'message' => 'Biometria concluída.']);
    }

    /**
     * POST /api/cotacao/{protocolo}/orcamento
     * Orçamentos (PDF) — cotacoes_anexos com tipo_arquivo 'proposta'.
     *  - enviar   (MPS, seguradora + arquivo) — um por seguradora (troca o que ainda
     *             aguarda decisão); liberado depois da biometria da seguradora, se
     *             ela pediu; o primeiro leva a cotação para "Aprovado"
     *  - remover  (MPS, id) — só orçamento ainda aguardando decisão
     *  - escolher (imobiliária com permissão de aceitar, id) — os demais são
     *             recusados e a cotação vai para "Aguardando apólice"
     *  - recusar  (imobiliária) — cliente recusou todos; a MPS envia outros ou
     *             encerra como recusada pelo cliente
     */
    public function orcamento(Request $request, string $protocolo)
    {
        [$user, $ehAdmin, $cotacao, $acao, $autor] = $this->contexto($request, $protocolo);

        if (!in_array($acao, ['enviar', 'remover', 'escolher', 'recusar'], true)) {
            json_response(['success' => false, 'message' => 'Ação inválida.'], 422);
        }
        if (in_array($acao, ['enviar', 'remover'], true) !== $ehAdmin) {
            json_response(['success' => false, 'message' => 'Ação não permitida para o seu usuário.'], 403);
        }

        $aguardando = db_fetch_all(
            "SELECT * FROM cotacoes_anexos WHERE protocolo = ? AND tipo_arquivo = 'proposta' AND status_aprovacao = 'Aguardando' ORDER BY id",
            ['s', $protocolo]
        );

        if ($acao === 'enviar') {
            if (!in_array($cotacao['status'], ['Pré-aprovado', 'Aprovado'], true)) {
                json_response(['success' => false, 'message' => 'O cliente já escolheu um orçamento.'], 422);
            }
            $seguradora = fianca_seguradora_ou_erro();
            $nome       = FIANCA_SEGURADORAS[$seguradora]['nome'];
            $biometria  = db_fetch_one(
                'SELECT situacao FROM seguro_fianca_biometrias WHERE protocolo = ? AND seguradora = ? ORDER BY id DESC LIMIT 1',
                ['ss', $protocolo, $seguradora]
            );
            if (!fianca_orcamento_liberado($biometria)) {
                json_response(['success' => false, 'message' => "O orçamento da {$nome} é liberado quando a biometria for concluída."], 422);
            }
            $arquivo = fianca_docs_upload_pdf('orcamentos/fianca');
            // Trocar: o orçamento da seguradora que ainda aguarda decisão sai.
            db_execute(
                "DELETE FROM cotacoes_anexos WHERE protocolo = ? AND tipo_arquivo = 'proposta' AND seguradora = ? AND status_aprovacao = 'Aguardando'",
                ['ss', $protocolo, $seguradora]
            );
            fianca_docs_anexar($protocolo, 'proposta', ['nome' => $nome] + $arquivo, $autor, 'Aguardando', $seguradora);

            if ($cotacao['status'] === 'Pré-aprovado') {
                fianca_docs_mudar_status($cotacao, 'Aprovado', $user, "Orçamento enviado: {$nome}.", 'ENVIO_COTACAO');
            } else {
                fianca_docs_registrar($cotacao, $user, "Orçamento enviado: {$nome}.");
            }
            fianca_docs_notificar($cotacao, $user, 'Orçamento enviado', "Orçamento da {$nome} na cotação {$protocolo}. Aguardando a escolha do cliente.");
            json_response(['success' => true, 'message' => 'Orçamento enviado.']);
        }

        if ($acao === 'recusar') {
            if (!$aguardando) {
                json_response(['success' => false, 'message' => 'Nenhum orçamento aguardando decisão.'], 422);
            }
            db_execute(
                "UPDATE cotacoes_anexos SET status_aprovacao = 'Recusada' WHERE protocolo = ? AND tipo_arquivo = 'proposta' AND status_aprovacao = 'Aguardando'",
                ['s', $protocolo]
            );
            fianca_docs_registrar($cotacao, $user, 'Cliente recusou os orçamentos.', 'RECUSA_COTACAO');
            fianca_docs_notificar($cotacao, $user, 'Orçamentos recusados pelo cliente', "O cliente recusou os orçamentos da cotação {$protocolo}.");
            zapi_notificar_atendente(
                isset($cotacao['atendente_id']) ? (int) $cotacao['atendente_id'] : null,
                '❌ *Orçamentos recusados pelo cliente — ' . zapi_imobiliaria_nome((int) $cotacao['imobiliaria_id']) . "*\n\n"
                . "📄 Protocolo: `{$protocolo}`",
                'proposta_recusada',
                $protocolo
            );
            json_response(['success' => true, 'message' => 'Orçamentos recusados. A MPS será avisada.']);
        }

        $id        = (int) $request->post('id', 0);
        $orcamento = current(array_filter($aguardando, fn($a) => (int) $a['id'] === $id));
        if (!$orcamento) {
            json_response(['success' => false, 'message' => 'Orçamento não encontrado ou já decidido.'], 404);
        }

        if ($acao === 'remover') {
            db_execute('DELETE FROM cotacoes_anexos WHERE id = ?', ['i', $id]);
            $obs = 'Orçamento removido: ' . fianca_doc_nome($orcamento) . '.';
            // Sem nenhum orçamento enviado, a cotação volta a ser só pré-aprovada.
            $restantes = (int) (db_fetch_one(
                "SELECT COUNT(*) AS n FROM cotacoes_anexos WHERE protocolo = ? AND tipo_arquivo = 'proposta'",
                ['s', $protocolo]
            )['n'] ?? 0);
            if ($restantes === 0 && $cotacao['status'] === 'Aprovado') {
                fianca_docs_mudar_status($cotacao, 'Pré-aprovado', $user, $obs);
            } else {
                fianca_docs_registrar($cotacao, $user, $obs);
            }
            json_response(['success' => true, 'message' => 'Orçamento removido.']);
        }

        // escolher — mesma permissão do "Aceitar proposta" dos outros produtos.
        if (empty($user->imob_admin) && ($user->perm_aceitar ?? 'N') !== 'S') {
            json_response(['success' => false, 'message' => 'Seu usuário não tem permissão para aceitar orçamentos.'], 403);
        }
        db_execute(
            "UPDATE cotacoes_anexos SET status_aprovacao = IF(id = ?, 'Aprovada', 'Recusada')
              WHERE protocolo = ? AND tipo_arquivo = 'proposta' AND status_aprovacao = 'Aguardando'",
            ['is', $id, $protocolo]
        );
        fianca_docs_mudar_status($cotacao, 'Aguardando apólice', $user, 'Cliente escolheu o orçamento ' . fianca_doc_nome($orcamento) . '.', 'APROVACAO_COTACAO');
        fianca_docs_notificar($cotacao, $user, 'Orçamento escolhido', "O cliente escolheu um orçamento na cotação {$protocolo}. Anexe a apólice.");
        zapi_notificar_atendente(
            isset($cotacao['atendente_id']) ? (int) $cotacao['atendente_id'] : null,
            '✅ *Orçamento escolhido pelo cliente — ' . zapi_imobiliaria_nome((int) $cotacao['imobiliaria_id']) . "*\n\n"
            . "📄 Protocolo: `{$protocolo}`",
            'proposta_aceita',
            $protocolo
        );
        json_response(['success' => true, 'message' => 'Orçamento escolhido.']);
    }

    /**
     * POST /api/cotacao/{protocolo}/contrato
     * Imobiliária anexa o contrato de locação (PDF), depois de escolher o
     * orçamento. Pode trocar enquanto a cotação não conclui — vale o último.
     * Com a apólice já anexada, conclui a cotação (ver fianca_docs_avancar).
     */
    public function contrato(string $protocolo)
    {
        $user = require_auth();
        if (($user->role ?? '') !== 'imobiliaria') {
            json_response(['success' => false, 'message' => 'O contrato de locação é enviado pela imobiliária.'], 403);
        }
        $cotacao = fianca_docs_cotacao_ou_erro($protocolo, $user);
        if (!in_array($cotacao['status'], ['Aguardando apólice', 'Aguardando contrato'], true)) {
            json_response(['success' => false, 'message' => 'O contrato de locação é anexado depois de escolher o orçamento.'], 422);
        }
        $arquivo = fianca_docs_upload_pdf('contratos/fianca');

        fianca_docs_anexar($protocolo, 'contrato', $arquivo, $user->nome ?? 'Imobiliária', 'Enviado');
        fianca_docs_registrar($cotacao, $user, 'Contrato de locação anexado.');
        fianca_docs_notificar($cotacao, $user, 'Contrato de locação anexado', "A imobiliária anexou o contrato de locação da cotação {$protocolo}.");
        fianca_docs_avancar($protocolo, $user);

        json_response(['success' => true, 'message' => 'Contrato de locação anexado.']);
    }

    /**
     * POST /api/cotacao/{protocolo}/apolice
     * MPS anexa a apólice (PDF), depois que o cliente escolheu o orçamento.
     * Pode trocar enquanto a cotação não conclui — vale a última. Com o
     * contrato de locação já anexado, conclui a cotação.
     */
    public function apolice(string $protocolo)
    {
        $admin   = require_admin();
        $cotacao = fianca_docs_cotacao_ou_erro($protocolo, $admin);
        if (!in_array($cotacao['status'], ['Aguardando apólice', 'Aguardando contrato'], true)) {
            json_response(['success' => false, 'message' => 'A apólice só pode ser anexada depois que o cliente escolher o orçamento.'], 422);
        }
        $arquivo = fianca_docs_upload_pdf('apolices/fianca');

        fianca_docs_anexar($protocolo, 'apolice', $arquivo, $admin->nome ?? 'MPS', 'Emitida');
        fianca_docs_registrar($cotacao, $admin, 'Apólice anexada.');
        fianca_docs_notificar($cotacao, $admin, 'Apólice anexada', "A apólice da cotação {$protocolo} foi anexada.");
        fianca_docs_avancar($protocolo, $admin);

        json_response(['success' => true, 'message' => 'Apólice anexada.']);
    }

    /** Usuário (MPS ou imobiliária), cotação aberta para documentos, ação e autor. */
    private function contexto(Request $request, string $protocolo): array
    {
        $user    = require_auth();
        $ehAdmin = ($user->role ?? '') === 'admin';
        if (!$ehAdmin && ($user->role ?? '') !== 'imobiliaria') {
            json_response(['success' => false, 'message' => 'Acesso não permitido.'], 403);
        }
        $cotacao = fianca_docs_cotacao_ou_erro($protocolo, $user);
        $autor   = $user->nome ?? ($ehAdmin ? 'MPS' : 'Imobiliária');

        return [$user, $ehAdmin, $cotacao, (string) $request->post('acao', ''), $autor];
    }
}
