<?php
/**
 * POST /api/cotacao/incendio/{protocolo}/imovel/{imovel_id}/desistir
 * Registra a desistência do cliente pra UM imóvel específico da remessa —
 * pode ser acionado pela imobiliária ou pela MPS (espelha
 * includes/acao/cotacao-desistir.php).
 */

$user = require_auth();
csrf_verify();

$protocolo = $params['protocolo'] ?? '';
$imovel_id = (int) ($params['imovel_id'] ?? 0);
if (!$protocolo || !$imovel_id) {
    json_response(['success' => false, 'message' => 'Parâmetros inválidos.'], 400);
}

$is_admin = ($user->role ?? '') === 'admin';

if (!$is_admin && empty($user->imob_admin) && (($user->perm_cotar ?? 'N') !== 'S' || ($user->perm_incendio ?? 'N') !== 'S')) {
    json_response(['success' => false, 'message' => 'Sem permissão para Seguro Incêndio.'], 403);
}

$cotacao = db_fetch_one('SELECT * FROM seguro_incendio WHERE protocolo = ?', ['s', $protocolo]);
if (!$cotacao) {
    json_response(['success' => false, 'message' => 'Cotação não encontrada.'], 404);
}

if (!$is_admin && (int) $cotacao['imobiliaria_id'] !== (int) ($user->imobiliaria_id ?? 0)) {
    json_response(['success' => false, 'message' => 'Cotação não encontrada.'], 404);
}
if (!$is_admin && empty($user->imob_admin) && (int) ($cotacao['usuario_id'] ?? 0) !== (int) $user->uid) {
    json_response(['success' => false, 'message' => 'Cotação não encontrada.'], 404);
}

$imovel = db_fetch_one(
    'SELECT * FROM seguro_incendio_imoveis WHERE id = ? AND seguro_incendio_id = ?',
    ['ii', $imovel_id, $cotacao['id']]
);
if (!$imovel) {
    json_response(['success' => false, 'message' => 'Imóvel não encontrado.'], 404);
}

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


try {
    if ($is_admin) {
        notificar_imobiliaria(
            (int) $cotacao['imobiliaria_id'],
            'status_alterado',
            'Imóvel encerrado por desistência',
            "O imóvel {$imovel['numero_imovel']} da cotação {$protocolo} foi encerrado por desistência do cliente.",
            (int) $cotacao['id'],
            null,
            (int) ($cotacao['usuario_id'] ?? 0)
        );
    } else {
        notificar_todos_admins(
            'acao_imob',
            'Cliente desistiu do imóvel',
            "O imóvel {$imovel['numero_imovel']} da cotação {$protocolo} foi encerrado por desistência do cliente.",
            (int) $cotacao['id']
        );
    }
} catch (Throwable $e) {
    error_log('[incendio-imovel-desistir] Erro notificação: ' . $e->getMessage());
}

json_response([
    'success' => true,
    'message' => 'Imóvel encerrado por desistência do cliente.',
    'status'  => STATUS_DESISTENCIA,
]);
