<?php

namespace App\Http\Controllers\Cotacao;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * Chat/histórico da cotação (off-canvas aberto em toda página — partial
 * chat-offcanvas + public/assets/js/cotacao-chat.js). Vale pra todos os
 * produtos via cotacao_resolver($protocolo).
 * Era includes/acao/cotacao-mensagem(-anexo).php, cotacao-mensagens-listar.php,
 * cotacao-historico-listar.php e cotacao-unread-lote.php.
 */
class ChatController extends Controller
{
    /**
     * POST /api/cotacao/{protocolo}/mensagem
     * Envia uma mensagem no chat da cotação (MPS ou imobiliária dona da cotação).
     * Bloqueado quando a cotação está em status terminal (Recusado/Concluída).
     */
    public function mensagem(Request $request, string $protocolo)
    {
        $user = require_auth();

        if (!$protocolo) json_response(['success' => false, 'message' => 'Protocolo inválido.'], 400);

        $resolvido = cotacao_resolver($protocolo);
        if (!$resolvido) json_response(['success' => false, 'message' => 'Cotação não encontrada.'], 404);

        $autor_tipo = $user->role === 'admin' ? 'admin' : 'imobiliaria';
        if (!cotacao_pode_acessar($resolvido, $user)) {
            json_response(['success' => false, 'message' => 'Cotação não encontrada.'], 404);
        }

        if (cotacao_status_encerrado($resolvido['status'])) {
            json_response(['success' => false, 'message' => 'Esta cotação está encerrada, não é mais possível enviar mensagens.'], 422);
        }

        // Corpo JSON cru (era php://input), independente do Content-Type.
        $body     = json_decode($request->getContent(), true);
        $body     = is_array($body) ? $body : [];
        $mensagem = mb_substr(trim((string) ($body['mensagem'] ?? '')), 0, 2000);
        if (!$mensagem) json_response(['success' => false, 'message' => 'Mensagem vazia.'], 422);

        $autor_nome = $user->nome ?? ($autor_tipo === 'admin' ? 'MPS' : 'Imobiliária');

        $id = db_execute(
            "INSERT INTO cotacao_mensagens (protocolo, autor_tipo, autor_id, autor_nome, mensagem, criado_em) VALUES (?, ?, ?, ?, ?, NOW())",
            ['ssiss', $protocolo, $autor_tipo, (int) $user->uid, $autor_nome, $mensagem]
        );

        // Mensagem no chat também conta como atividade da cotação — sem isso, a
        // coluna "Última atualização" das listagens ficava presa na última troca
        // de status, mesmo com conversa nova rolando depois.
        db_execute(
            "UPDATE {$resolvido['tabela']} SET data_atualizacao = NOW() WHERE id = ?",
            ['i', $resolvido['id']]
        );

        // Quem envia já leu tudo até a própria mensagem — evita a mensagem enviada
        // aparecer como "não lida" pra si mesmo em outra aba/dispositivo.
        self::marcarLido($protocolo, (int) $user->uid, $autor_tipo, (int) $id);

        // Notifica o outro lado da conversa (aparece no sininho de notificações).
        $resumo_msg = mb_substr($mensagem, 0, 120) . (mb_strlen($mensagem) > 120 ? '…' : '');
        if ($autor_tipo === 'admin') {
            notificar_imobiliaria(
                $resolvido['imobiliaria_id'],
                'chat_mensagem',
                "Nova mensagem: {$protocolo}",
                "{$autor_nome}: {$resumo_msg}",
                $resolvido['id'],
                cotacao_notif_url($resolvido['tipo'], $protocolo, 'imob'),
                (int) ($resolvido['usuario_id'] ?? 0)
            );
        } else {
            notificar_todos_admins(
                'chat_mensagem',
                "Nova mensagem: {$protocolo}",
                "{$autor_nome}: {$resumo_msg}",
                $resolvido['id'],
                cotacao_notif_url($resolvido['tipo'], $protocolo, 'admin')
            );

            // WhatsApp só para o atendente responsável por essa cotação (não todos os admins).
            $atendente_id = isset($resolvido['row']['atendente_id']) ? (int) $resolvido['row']['atendente_id'] : null;
            $saudacao     = $atendente_id ? explode(' ', zapi_atendente_nome($atendente_id))[0] : '';
            zapi_notificar_atendente(
                $atendente_id,
                ($saudacao ? "*{$saudacao}*\n\n" : '')
                . "💬 *Nova mensagem no chat — " . zapi_imobiliaria_nome($resolvido['imobiliaria_id']) . "*\n\n"
                . "👤 {$autor_nome} enviou: \"{$resumo_msg}\"\n\n"
                . "📄 Protocolo: `{$protocolo}`",
                'chat_mensagem',
                $protocolo
            );
        }

        json_response([
            'success' => true,
            'mensagem' => [
                'id'         => (int) $id,
                'autor_tipo' => $autor_tipo,
                'autor_nome' => $autor_nome,
                'mensagem'   => $mensagem,
                'criado_em'  => date('Y-m-d H:i:s'),
                'hora'       => date('H:i'),
                'data_label' => date('d/m/Y'),
            ],
        ]);
    }

    /**
     * POST /api/cotacao/{protocolo}/mensagem-anexo
     * Envia um documento (PDF/imagem) no chat da cotação, como uma mensagem com
     * anexo. Mesmas regras de acesso e bloqueio de mensagem().
     */
    public function mensagemAnexo(Request $request, string $protocolo)
    {
        $user = require_auth();

        if ($m = upload_erro_limite()) json_response(['success' => false, 'message' => $m], 413);

        if (!$protocolo) json_response(['success' => false, 'message' => 'Protocolo inválido.'], 400);

        $resolvido = cotacao_resolver($protocolo);
        if (!$resolvido) json_response(['success' => false, 'message' => 'Cotação não encontrada.'], 404);

        $autor_tipo = $user->role === 'admin' ? 'admin' : 'imobiliaria';
        if (!cotacao_pode_acessar($resolvido, $user)) {
            json_response(['success' => false, 'message' => 'Cotação não encontrada.'], 404);
        }

        if (cotacao_status_encerrado($resolvido['status'])) {
            json_response(['success' => false, 'message' => 'Esta cotação está encerrada, não é mais possível enviar mensagens.'], 422);
        }

        if (empty($_FILES['arquivo'])) {
            json_response(['success' => false, 'message' => 'Selecione um arquivo para enviar.'], 422);
        }
        if ($_FILES['arquivo']['error'] !== UPLOAD_ERR_OK) {
            $erroUpload = in_array($_FILES['arquivo']['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
                ? 'Arquivo excede o tamanho máximo permitido pelo servidor.'
                : 'Erro no envio do arquivo. Tente novamente.';
            json_response(['success' => false, 'message' => $erroUpload], 422);
        }

        $legenda = mb_substr(trim((string) $request->input('mensagem', '')), 0, 2000);

        $tipos_permitidos = [
            'application/pdf' => 10 * 1024 * 1024,
            'image/jpeg' => 10 * 1024 * 1024,
            'image/png' => 10 * 1024 * 1024,
            'image/webp' => 10 * 1024 * 1024,
            'application/msword' => 10 * 1024 * 1024,
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 10 * 1024 * 1024,
        ];

        try {
            $anexo_url = media_upload($_FILES['arquivo'], 'chat-cotacao/' . $protocolo, $tipos_permitidos);
        } catch (\Illuminate\Http\Exceptions\HttpResponseException $e) {
            throw $e;
        } catch (\Throwable $e) {
            json_response(['success' => false, 'message' => 'Erro ao enviar arquivo: ' . $e->getMessage()], 500);
        }

        $anexo_nome = $_FILES['arquivo']['name'];
        $anexo_tipo = strtolower(pathinfo($anexo_nome, PATHINFO_EXTENSION));
        $autor_nome = $user->nome ?? ($autor_tipo === 'admin' ? 'MPS' : 'Imobiliária');

        $id = db_execute(
            "INSERT INTO cotacao_mensagens (protocolo, autor_tipo, autor_id, autor_nome, mensagem, anexo_url, anexo_nome, anexo_tipo, criado_em)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())",
            ['ssisssss', $protocolo, $autor_tipo, (int) $user->uid, $autor_nome, $legenda, $anexo_url, $anexo_nome, $anexo_tipo]
        );

        // Mensagem/anexo no chat também conta como atividade da cotação — ver
        // mesmo comentário em mensagem().
        db_execute(
            "UPDATE {$resolvido['tabela']} SET data_atualizacao = NOW() WHERE id = ?",
            ['i', $resolvido['id']]
        );

        // Quem envia já leu tudo até a própria mensagem — mesmo racional de mensagem().
        self::marcarLido($protocolo, (int) $user->uid, $autor_tipo, (int) $id);

        $resumo_msg = 'Enviou um documento: ' . $anexo_nome;
        if ($autor_tipo === 'admin') {
            notificar_imobiliaria(
                $resolvido['imobiliaria_id'],
                'chat_mensagem',
                "Nova mensagem: {$protocolo}",
                "{$autor_nome}: {$resumo_msg}",
                $resolvido['id'],
                null,
                (int) ($resolvido['usuario_id'] ?? 0)
            );
        } else {
            notificar_todos_admins(
                'chat_mensagem',
                "Nova mensagem: {$protocolo}",
                "{$autor_nome}: {$resumo_msg}",
                $resolvido['id']
            );
        }

        json_response([
            'success' => true,
            'mensagem' => [
                'id'         => (int) $id,
                'autor_tipo' => $autor_tipo,
                'autor_nome' => $autor_nome,
                'mensagem'   => $legenda,
                'anexo_url'  => $anexo_url,
                'anexo_nome' => $anexo_nome,
                'anexo_tipo' => $anexo_tipo,
                'criado_em'  => date('Y-m-d H:i:s'),
                'hora'       => date('H:i'),
                'data_label' => date('d/m/Y'),
            ],
        ]);
    }

    /**
     * GET /api/cotacao/{protocolo}/mensagens?depois_de={id}
     * Retorna mensagens novas do chat da cotação (id > depois_de), para polling
     * (cotacao-chat.js, a cada 60s por chat aberto).
     * O antigo respondia com echo json_encode() puro (sem JSON_UNESCAPED_UNICODE
     * e sempre 200) — response()->json() padrão gera o mesmo.
     */
    public function mensagens(Request $request, string $protocolo)
    {
        $user = require_auth();

        if (!$protocolo) return response()->json(['ok' => false, 'message' => 'Protocolo inválido.']);

        $resolvido = cotacao_resolver($protocolo);
        if (!$resolvido) return response()->json(['ok' => false, 'message' => 'Cotação não encontrada.']);

        $autor_tipo = $user->role === 'admin' ? 'admin' : 'imobiliaria';
        if (!cotacao_pode_acessar($resolvido, $user)) {
            return response()->json(['ok' => false, 'message' => 'Cotação não encontrada.']);
        }

        $depois_de = (int) $request->query('depois_de', 0);

        $mensagens = db_fetch_all(
            'SELECT id, autor_tipo, autor_nome, mensagem, anexo_url, anexo_nome, anexo_tipo, criado_em
               FROM cotacao_mensagens
              WHERE protocolo = ? AND id > ?
              ORDER BY criado_em ASC',
            ['si', $protocolo, $depois_de]
        );

        $mensagens = array_map(function ($m) {
            return [
                'id'         => (int) $m['id'],
                'autor_tipo' => $m['autor_tipo'],
                'autor_nome' => $m['autor_nome'],
                'mensagem'   => $m['mensagem'],
                'anexo_url'  => $m['anexo_url'],
                'anexo_nome' => $m['anexo_nome'],
                'anexo_tipo' => $m['anexo_tipo'],
                'criado_em'  => $m['criado_em'],
                'hora'       => date('H:i', strtotime($m['criado_em'])),
                'data_label' => date('d/m/Y', strtotime($m['criado_em'])),
            ];
        }, $mensagens);

        // Marca como lido até a última mensagem já existente (mesmo sem mensagem
        // nova nesse poll) — sempre grava, pra manter `atualizado_em` em dia mesmo
        // em cotações sem nenhuma mensagem ainda (só eventos de status).
        self::marcarLido($protocolo, (int) $user->uid, $autor_tipo, self::ultimaMensagemId($protocolo));

        return response()->json([
            'ok'        => true,
            'encerrado' => cotacao_status_encerrado($resolvido['status']),
            'mensagens' => $mensagens,
        ]);
    }

    /**
     * GET /api/cotacao/{protocolo}/historico
     * Retorna o feed unificado (histórico de status + mensagens do chat) de uma
     * cotação e marca as mensagens como lidas para o usuário atual — é o que a
     * abertura do off-canvas de chat usa pra carregar tudo de uma vez.
     */
    public function historico(string $protocolo)
    {
        $user = require_auth();

        if (!$protocolo) json_response(['success' => false, 'message' => 'Protocolo inválido.'], 400);

        $resolvido = cotacao_resolver($protocolo);
        if (!$resolvido) json_response(['success' => false, 'message' => 'Cotação não encontrada.'], 404);

        $autor_tipo = $user->role === 'admin' ? 'admin' : 'imobiliaria';
        if (!cotacao_pode_acessar($resolvido, $user)) {
            json_response(['success' => false, 'message' => 'Cotação não encontrada.'], 404);
        }

        $feed = cotacao_montar_feed($protocolo, $autor_tipo === 'admin');

        // Conta quantos itens (mensagens + eventos) ainda não tinham sido vistos
        // ANTES de marcar como lido agora — é o que alimenta o divisor
        // "X não lidas" no front, inserido antes dos últimos N itens do feed.
        $nao_lidas = cotacao_chat_unread($protocolo, $autor_tipo, (int) $user->uid);

        // Sempre grava (mesmo sem mensagens ainda) — é o que atualiza `atualizado_em`,
        // usado como corte pra considerar os eventos de status como lidos também.
        self::marcarLido($protocolo, (int) $user->uid, $autor_tipo, self::ultimaMensagemId($protocolo));

        json_response([
            'success'   => true,
            'protocolo' => $protocolo,
            'tipo'      => $resolvido['tipo'],
            'status'    => $resolvido['status'],
            'encerrado' => cotacao_status_encerrado($resolvido['status']),
            'nao_lidas' => $nao_lidas,
            'feed'      => $feed,
        ]);
    }

    /**
     * GET /api/cotacao/unread-lote?protocolos=A,B,C
     * Retorna a contagem de não lidas (mensagens + eventos de status) de vários
     * protocolos de uma vez — usado pelo JS pra "revalidar" os badges de chat já
     * renderizados numa página (grid ou detalhe) sempre que ela volta a ficar
     * visível, garantindo que ler o chat em um lugar também limpe o badge em
     * qualquer outro lugar que mostre a mesma cotação, sem precisar recarregar.
     */
    public function unreadLote(Request $request)
    {
        $user = require_auth();

        $protocolos = array_filter(array_map('trim', explode(',', (string) $request->query('protocolos', ''))));
        $protocolos = array_slice(array_values(array_unique($protocolos)), 0, 100);

        $papel = ($user->role ?? '') === 'admin' ? 'admin' : 'imobiliaria';
        $uid   = (int) ($user->uid ?? 0);

        // Imobiliária só pode consultar as próprias cotações (operador comum) ou as
        // da imobiliária inteira (admin da imobiliária) — admin MPS vê qualquer uma.
        if ($papel === 'imobiliaria') {
            $protocolos = array_values(array_filter($protocolos, function ($p) use ($user) {
                $r = cotacao_resolver($p);
                return $r && cotacao_pode_acessar($r, $user);
            }));
        }

        $mapa = $protocolos ? cotacao_unread_map($protocolos, $papel, $uid) : [];

        json_response(['success' => true, 'unread' => $mapa]);
    }

    /** Maior id de mensagem do chat do protocolo (0 se não tem nenhuma). */
    private static function ultimaMensagemId(string $protocolo): int
    {
        $ultima = db_fetch_one(
            'SELECT MAX(id) AS max_id FROM cotacao_mensagens WHERE protocolo = ?',
            ['s', $protocolo]
        );
        return (int) ($ultima['max_id'] ?? 0);
    }

    /** Marca o chat como lido até $ultimaId pro usuário (só avança, nunca volta). */
    private static function marcarLido(string $protocolo, int $uid, string $papel, int $ultimaId): void
    {
        db_execute(
            "INSERT INTO cotacao_mensagens_lidas (protocolo, usuario_id, papel, ultima_id_lida)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE ultima_id_lida = GREATEST(ultima_id_lida, VALUES(ultima_id_lida)), atualizado_em = NOW()",
            ['sisi', $protocolo, $uid, $papel, $ultimaId]
        );
    }
}
