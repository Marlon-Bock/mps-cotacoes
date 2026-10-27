<?php
/**
 * POST /api/cotacao/{protocolo}/iniciar-analise
 * MPS inicia a análise (ficha completa pronta). Atribui o atendente responsável,
 * igual ao sistema legado (admin/includes/acao/status.php, caso 'Em Analise').
 */

$admin = require_admin();
csrf_verify();

$protocolo = $params['protocolo'] ?? '';
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
} catch (Throwable $e) {
    error_log('[iniciar-analise] Erro notificação: ' . $e->getMessage());
}

json_response(['success' => true, 'message' => 'Análise iniciada.', 'status' => $status_novo]);
