<?php
/**
 * POST /api/cotacao/{protocolo}/mensagem
 * Envia uma mensagem no chat da cotação (MPS ou imobiliária dona da cotação).
 * Bloqueado quando a cotação está em status terminal (Recusado/Concluída).
 */
$user = require_auth();
header('Content-Type: application/json; charset=utf-8');
csrf_verify();

$protocolo = $params['protocolo'] ?? '';
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

$body     = json_decode(file_get_contents('php://input'), true) ?? [];
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
db_execute(
    "INSERT INTO cotacao_mensagens_lidas (protocolo, usuario_id, papel, ultima_id_lida)
     VALUES (?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE ultima_id_lida = GREATEST(ultima_id_lida, VALUES(ultima_id_lida)), atualizado_em = NOW()",
    ['sisi', $protocolo, (int) $user->uid, $autor_tipo, $id]
);

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
    require_once BASE_PATH . '/src/helpers/zapi.php';
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
