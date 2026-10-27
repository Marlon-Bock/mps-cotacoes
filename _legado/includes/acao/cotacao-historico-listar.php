<?php
/**
 * GET /api/cotacao/{protocolo}/historico
 * Retorna o feed unificado (histórico de status + mensagens do chat) de uma
 * cotação e marca as mensagens como lidas para o usuário atual — é o que a
 * abertura do off-canvas de chat usa pra carregar tudo de uma vez.
 */
$user = require_auth();
header('Content-Type: application/json; charset=utf-8');

$protocolo = $params['protocolo'] ?? '';
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
$ultima = db_fetch_one(
    'SELECT MAX(id) AS max_id FROM cotacao_mensagens WHERE protocolo = ?',
    ['s', $protocolo]
);
db_execute(
    "INSERT INTO cotacao_mensagens_lidas (protocolo, usuario_id, papel, ultima_id_lida)
     VALUES (?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE ultima_id_lida = GREATEST(ultima_id_lida, VALUES(ultima_id_lida)), atualizado_em = NOW()",
    ['sisi', $protocolo, (int) $user->uid, $autor_tipo, (int) ($ultima['max_id'] ?? 0)]
);

json_response([
    'success'   => true,
    'protocolo' => $protocolo,
    'tipo'      => $resolvido['tipo'],
    'status'    => $resolvido['status'],
    'encerrado' => cotacao_status_encerrado($resolvido['status']),
    'nao_lidas' => $nao_lidas,
    'feed'      => $feed,
]);
