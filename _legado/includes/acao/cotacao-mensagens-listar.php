<?php
/**
 * GET /api/cotacao/{protocolo}/mensagens?depois_de={id}
 * Retorna mensagens novas do chat da cotação (id > depois_de), para polling.
 */
$user = require_auth();
header('Content-Type: application/json; charset=utf-8');

// Polling (cotacao-chat.js, a cada 60s por chat aberto) — não escreve mais
// nada na sessão depois daqui. Fecha logo cedo pra liberar o lock do arquivo
// de sessão (mesma sessão em todas as abas/requisições do navegador).
session_write_close();

$protocolo = $params['protocolo'] ?? '';
if (!$protocolo) { echo json_encode(['ok' => false, 'message' => 'Protocolo inválido.']); exit; }

$resolvido = cotacao_resolver($protocolo);
if (!$resolvido) { echo json_encode(['ok' => false, 'message' => 'Cotação não encontrada.']); exit; }

$autor_tipo = $user->role === 'admin' ? 'admin' : 'imobiliaria';
if (!cotacao_pode_acessar($resolvido, $user)) {
    echo json_encode(['ok' => false, 'message' => 'Cotação não encontrada.']);
    exit;
}

$depois_de = (int) ($_GET['depois_de'] ?? 0);

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

echo json_encode([
    'ok'        => true,
    'encerrado' => cotacao_status_encerrado($resolvido['status']),
    'mensagens' => $mensagens,
]);
