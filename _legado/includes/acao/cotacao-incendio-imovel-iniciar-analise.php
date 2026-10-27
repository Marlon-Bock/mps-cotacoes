<?php
/**
 * POST /api/cotacao/incendio/{protocolo}/imovel/{imovel_id}/iniciar-analise
 * MPS inicia a análise de UM imóvel específico da remessa — cada imóvel é
 * um risco segurado independente (locador/locatário/vigência/valor
 * próprios), então o ciclo de vida é por imóvel, não pelo protocolo
 * inteiro (ver includes/acao/cotacao-iniciar-analise.php, a versão
 * genérica usada pelos demais produtos, que este arquivo espelha).
 */

$admin = require_admin();
csrf_verify();

$protocolo = $params['protocolo'] ?? '';
$imovel_id = (int) ($params['imovel_id'] ?? 0);
if (!$protocolo || !$imovel_id) {
    json_response(['success' => false, 'message' => 'Parâmetros inválidos.'], 400);
}

$cotacao = db_fetch_one('SELECT * FROM seguro_incendio WHERE protocolo = ?', ['s', $protocolo]);
if (!$cotacao) {
    json_response(['success' => false, 'message' => 'Cotação não encontrada.'], 404);
}

$imovel = db_fetch_one(
    'SELECT * FROM seguro_incendio_imoveis WHERE id = ? AND seguro_incendio_id = ?',
    ['ii', $imovel_id, $cotacao['id']]
);
if (!$imovel) {
    json_response(['success' => false, 'message' => 'Imóvel não encontrado.'], 404);
}

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

try {
    notificar_imobiliaria(
        (int) $cotacao['imobiliaria_id'],
        'status_alterado',
        'Cotação em análise',
        "O imóvel {$imovel['numero_imovel']} da cotação {$protocolo} entrou em análise pela equipe MPS.",
        (int) $cotacao['id'],
        null,
        (int) ($cotacao['usuario_id'] ?? 0)
    );
} catch (Throwable $e) {
    error_log('[incendio-imovel-iniciar-analise] Erro notificação: ' . $e->getMessage());
}

json_response(['success' => true, 'message' => 'Análise iniciada.', 'status' => $status_novo]);
