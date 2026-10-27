<?php
/**
 * GET /api/chamado/{protocolo}/mensagens?depois_de={id}
 * Retorna mensagens novas (id > depois_de) e o status atual do chamado.
 * Usado para polling em tempo real, sem recarregar a página.
 */
$current_user = require_auth();
header('Content-Type: application/json; charset=utf-8');

$proto = $params['protocolo'] ?? '';
$id = chamado_id_do_protocolo($proto);
if ($id <= 0) { echo json_encode(['ok' => false, 'message' => 'Protocolo inválido.']); exit; }

$chamado = db_fetch_one(
    'SELECT id, status FROM chamados_suporte WHERE id = ? AND imobiliaria_id = ?',
    ['ii', $id, $current_user->imobiliaria_id]
);
if (!$chamado) { echo json_encode(['ok' => false, 'message' => 'Chamado não encontrado.']); exit; }

$depois_de = (int) ($_GET['depois_de'] ?? 0);

$respostas = db_fetch_all(
    'SELECT id, autor_tipo, autor_id, autor_nome, mensagem, anexo_url, anexo_nome, anexo_tipo, criado_em
       FROM chamados_respostas
      WHERE chamado_id = ? AND id > ?
      ORDER BY criado_em ASC',
    ['ii', $id, $depois_de]
);

$mensagens = array_map(function ($m) {
    return [
        'id'         => (int) $m['id'],
        'autor_tipo' => $m['autor_tipo'],
        'autor_id'   => $m['autor_id'],
        'autor_nome' => $m['autor_nome'],
        'mensagem'   => $m['mensagem'],
        'anexo_url'  => $m['anexo_url'],
        'anexo_nome' => $m['anexo_nome'],
        'anexo_tipo' => $m['anexo_tipo'],
        'criado_em'  => $m['criado_em'],
        'hora'       => date('H:i', strtotime($m['criado_em'])),
        'data'       => date('Y-m-d', strtotime($m['criado_em'])),
        'data_label' => date('d/m/Y', strtotime($m['criado_em'])),
    ];
}, $respostas);

echo json_encode([
    'ok'        => true,
    'status'    => $chamado['status'],
    'mensagens' => $mensagens,
]);
