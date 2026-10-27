<?php
/**
 * POST /api/cotacao/{protocolo}/pre-analise
 * MPS responde à pré-análise (status "Aguardando liberação"): aceitar ou recusar.
 * Strings de status idênticas ao sistema legado (mps-antigo/admin/includes/acao/status.php).
 */

$admin = require_admin();
csrf_verify();

$protocolo = $params['protocolo'] ?? '';
if (!$protocolo) {
    json_response(['success' => false, 'message' => 'Protocolo inválido.'], 400);
}

$resultado = sanitize((string) ($_POST['resultado'] ?? ''));

if (!in_array($resultado, ['aceita', 'recusada'], true)) {
    json_response(['success' => false, 'message' => 'Resultado inválido. Use "aceita" ou "recusada".'], 422);
}

$cotacao = db_fetch_one("SELECT * FROM seguro_fianca WHERE protocolo = ?", ['s', $protocolo]);
if (!$cotacao) {
    json_response(['success' => false, 'message' => 'Cotação não encontrada.'], 404);
}

if ($cotacao['status'] !== 'Aguardando liberação') {
    json_response(['success' => false, 'message' => 'Esta cotação não está em pré-análise.'], 422);
}

$status_anterior = $cotacao['status'];
$status_novo      = $resultado === 'aceita' ? 'Aguardando preenchimento' : 'Pré-análise recusada';

// Em ambos os casos o legado seta liberado=1 (aceita libera o formulário completo;
// recusada também libera para a imobiliária poder corrigir e reenviar a pré-análise).
db_execute(
    "UPDATE seguro_fianca
     SET status = ?,
         liberado = 1,
         data_pre_analise_aceita   = IF(? = 'Aguardando preenchimento' AND data_pre_analise_aceita   IS NULL, NOW(), data_pre_analise_aceita),
         data_pre_analise_recusada = IF(? = 'Pré-análise recusada'     AND data_pre_analise_recusada IS NULL, NOW(), data_pre_analise_recusada),
         atendente_id = IF(atendente_id IS NULL, ?, atendente_id),
         data_atualizacao = NOW()
     WHERE protocolo = ?",
    ['sssis', $status_novo, $status_novo, $status_novo, (int) $admin->uid, $protocolo]
);

historico_registrar(
    (int) $cotacao['id'], $protocolo, 'ALTERACAO_STATUS',
    $status_anterior, $status_novo, (int) $admin->uid, 'admin', $admin->nome ?? 'MPS'
);

try {
    $msg = $resultado === 'aceita'
        ? "Pré-análise aceita para o protocolo {$protocolo}. Complete o formulário."
        : "Pré-análise recusada para o protocolo {$protocolo}.";
    notificar_imobiliaria(
        (int) $cotacao['imobiliaria_id'],
        $resultado === 'aceita' ? 'pre_analise_enviada' : 'pre_analise_recusada',
        $resultado === 'aceita' ? 'Pré-análise aceita' : 'Pré-análise recusada',
        $msg,
        (int) $cotacao['id'],
        null,
        (int) ($cotacao['usuario_id'] ?? 0)
    );
} catch (Throwable $e) {
    error_log('[pre-analise] Erro notificação: ' . $e->getMessage());
}

json_response([
    'success' => true,
    'message' => $resultado === 'aceita' ? 'Pré-análise aceita com sucesso.' : 'Pré-análise recusada.',
    'status'  => $status_novo,
]);
