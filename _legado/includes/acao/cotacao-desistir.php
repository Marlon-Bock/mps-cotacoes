<?php
/**
 * POST /api/cotacao/{protocolo}/desistir
 * Registra a desistência do cliente: a cotação é encerrada com o status
 * "Desistência" e não avança mais (ver cotacao_status_encerrado).
 *
 * Pode ser acionado pela imobiliária, na tela de detalhe da cotação, ou pela
 * MPS, quando o cliente avisa a desistência direto para a equipe. Diferente de
 * "Recusado" (decisão da análise) e de "Recusado pelo cliente" (proposta
 * enviada e recusada) — aqui quem abandona é o cliente, em qualquer etapa.
 */

$user = require_auth();
csrf_verify();

$protocolo = $params['protocolo'] ?? '';
if (!$protocolo) {
    json_response(['success' => false, 'message' => 'Protocolo inválido.'], 400);
}

$is_admin = ($user->role ?? '') === 'admin';

$resolvido = cotacao_resolver($protocolo);
if (!$resolvido) {
    json_response(['success' => false, 'message' => 'Cotação não encontrada.'], 404);
}

// Usuário de imobiliária só desiste das próprias cotações (admin da
// imobiliária pode de qualquer atendente; operador comum só a dele).
if (!cotacao_pode_acessar($resolvido, $user)) {
    json_response(['success' => false, 'message' => 'Cotação não encontrada.'], 404);
}

$tbl_cotacao     = $resolvido['tabela'];
$cotacao         = $resolvido['row'];
$status_anterior = (string) ($cotacao['status'] ?? '');

if (cotacao_status_encerrado($status_anterior)) {
    json_response(['success' => false, 'message' => 'Esta cotação já está encerrada.'], 422);
}

// Fiança/Residencial da API: depois de escolhida a proposta de uma seguradora (emissão
// na fila, proposta ou apólice) não dá mais pra desistir por aqui.
if (in_array($tbl_cotacao, ['seguro_fianca', 'seguro_residencial'], true) && ($cotacao['origem'] ?? '') === 'api') {
    require_once BASE_PATH . '/src/helpers/api.php';
    $escolhida = api_seguradora_escolhida((int) $cotacao['id']);
    if ($escolhida !== null) {
        json_response(['success' => false, 'message' => 'O cliente já escolheu a proposta da ' . api_seguradora_nome($escolhida) . ' — não dá para encerrar por desistência.'], 422);
    }
}

$ok = db_execute(
    "UPDATE {$tbl_cotacao} SET status = ?, data_atualizacao = NOW() WHERE protocolo = ? LIMIT 1",
    ['ss', STATUS_DESISTENCIA, $protocolo]
);
if (!$ok) {
    json_response(['success' => false, 'message' => 'Erro ao registrar a desistência.'], 500);
}

$autor_tipo = $is_admin ? 'admin' : 'imobiliaria';
$autor_nome = $user->nome ?? ($is_admin ? 'MPS' : 'Imobiliária');

historico_registrar(
    (int) $cotacao['id'], $protocolo, 'ALTERACAO_STATUS',
    $status_anterior, STATUS_DESISTENCIA, (int) $user->uid, $autor_tipo, $autor_nome,
    'Cliente desistiu da cotação.'
);


try {
    if ($is_admin) {
        notificar_imobiliaria(
            (int) $cotacao['imobiliaria_id'],
            'status_alterado',
            'Cotação encerrada por desistência',
            "A cotação {$protocolo} foi encerrada por desistência do cliente.",
            (int) $cotacao['id'],
            null,
            (int) ($cotacao['usuario_id'] ?? 0)
        );
    } else {
        notificar_todos_admins(
            'acao_imob',
            'Cliente desistiu da cotação',
            "A cotação {$protocolo} foi encerrada por desistência do cliente.",
            (int) $cotacao['id']
        );

        require_once BASE_PATH . '/src/helpers/zapi.php';
        zapi_notificar_atendente(
            isset($cotacao['atendente_id']) ? (int) $cotacao['atendente_id'] : null,
            "🚪 *Cliente desistiu da cotação — " . zapi_imobiliaria_nome((int) $cotacao['imobiliaria_id']) . "*\n\n"
            . "📄 Protocolo: `{$protocolo}`",
            'desistencia',
            $protocolo
        );
    }
} catch (Throwable $e) {
    error_log('[desistir] Erro notificação: ' . $e->getMessage());
}

json_response([
    'success' => true,
    'message' => 'Cotação encerrada por desistência do cliente.',
    'status'  => STATUS_DESISTENCIA,
]);
