<?php
/**
 * POST /api/cotacao/incendio/{protocolo}/imovel/{imovel_id}/aprovar
 * Imobiliária aceita, em nome do cliente, a proposta enviada pela MPS pra
 * UM imóvel específico (espelha includes/acao/cotacao-aprovar.php).
 */

$user = require_perm('perm_aceitar');
csrf_verify();

$protocolo = $params['protocolo'] ?? '';
$imovel_id = (int) ($params['imovel_id'] ?? 0);
if (!$protocolo || !$imovel_id) {
    json_response(['success' => false, 'message' => 'Parâmetros inválidos.'], 400);
}

$cotacao = db_fetch_one('SELECT * FROM seguro_incendio WHERE protocolo = ?', ['s', $protocolo]);
if (!$cotacao || (int) $cotacao['imobiliaria_id'] !== (int) ($user->imobiliaria_id ?? 0)) {
    json_response(['success' => false, 'message' => 'Cotação não encontrada.'], 404);
}
if (empty($user->imob_admin) && (int) ($cotacao['usuario_id'] ?? 0) !== (int) $user->uid) {
    json_response(['success' => false, 'message' => 'Cotação não encontrada.'], 404);
}

$imovel = db_fetch_one(
    'SELECT * FROM seguro_incendio_imoveis WHERE id = ? AND seguro_incendio_id = ?',
    ['ii', $imovel_id, $cotacao['id']]
);
if (!$imovel) {
    json_response(['success' => false, 'message' => 'Imóvel não encontrado.'], 404);
}

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

try {
    notificar_todos_admins(
        'cotacao_finalizada',
        'Imóvel concluído',
        "O imóvel {$imovel['numero_imovel']} da cotação {$protocolo} foi aceito pelo cliente. Concluído!",
        (int) $cotacao['id']
    );
} catch (Throwable $e) {
    error_log('[incendio-imovel-aprovar] Erro notificação: ' . $e->getMessage());
}

require_once BASE_PATH . '/src/helpers/zapi.php';
zapi_notificar_atendente(
    isset($cotacao['atendente_id']) ? (int) $cotacao['atendente_id'] : null,
    "✅ *Proposta aceita pelo cliente — " . zapi_imobiliaria_nome((int) $cotacao['imobiliaria_id']) . "*\n\n"
    . "📄 Protocolo: `{$protocolo}` — Imóvel {$imovel['numero_imovel']}",
    'proposta_aceita',
    $protocolo
);

json_response(['success' => true, 'message' => 'Imóvel aprovado com sucesso!', 'status' => 'Concluída']);
