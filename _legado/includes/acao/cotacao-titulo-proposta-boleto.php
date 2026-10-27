<?php
/**
 * POST /api/cotacao/titulo/{protocolo}/proposta-boleto
 * Específico do Título de Capitalização — substitui o fluxo genérico de
 * aprovar/recusar por um passo único: a MPS envia a proposta e, junto,
 * o boleto (se a forma de pagamento escolhida pelo cliente for "Boleto")
 * ou um link de pagamento (se for "Cartão") — o que já libera a cotação pra
 * imobiliária confirmar o pagamento.
 * Não mexe em nada do fluxo genérico (cotacao-aprovar.php/cotacao-recusar.php/
 * cotacao-proposta.php) usado por fiança/auto/condominial.
 */

$admin = require_admin();
csrf_verify();

$protocolo = $params['protocolo'] ?? '';
if (!$protocolo) {
    json_response(['success' => false, 'message' => 'Protocolo inválido.'], 400);
}

$cotacao = db_fetch_one('SELECT * FROM titulo_capitalizacao WHERE protocolo = ?', ['s', $protocolo]);
if (!$cotacao) {
    json_response(['success' => false, 'message' => 'Cotação não encontrada.'], 404);
}

if ($cotacao['status'] !== 'Em Analise') {
    json_response(['success' => false, 'message' => 'Status atual não permite enviar proposta e boleto.'], 422);
}

$ehCartao = $cotacao['forma_pagamento'] === 'Cartão';

if ($m = upload_erro_limite()) {
    json_response(['success' => false, 'message' => $m], 413);
}

if (empty($_FILES['proposta']) || ($_FILES['proposta']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
    json_response(['success' => false, 'message' => 'Arquivo de Proposta é obrigatório.'], 422);
}
if ($_FILES['proposta']['error'] !== UPLOAD_ERR_OK) {
    $msg = in_array($_FILES['proposta']['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
        ? 'O arquivo de Proposta excede o limite do servidor (' . upload_limite_label() . '). Envie um arquivo menor.'
        : 'Falha no upload do arquivo de Proposta. Tente novamente.';
    json_response(['success' => false, 'message' => $msg], 422);
}

$linkPagamento = null;
if ($ehCartao) {
    $linkPagamento = trim((string) ($_POST['link_pagamento'] ?? ''));
    if ($linkPagamento === '' || !filter_var($linkPagamento, FILTER_VALIDATE_URL)) {
        json_response(['success' => false, 'message' => 'Informe um link de pagamento válido.'], 422);
    }
} else {
    if (empty($_FILES['boleto']) || ($_FILES['boleto']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        json_response(['success' => false, 'message' => 'Arquivo de Boleto é obrigatório.'], 422);
    }
    if ($_FILES['boleto']['error'] !== UPLOAD_ERR_OK) {
        $msg = in_array($_FILES['boleto']['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
            ? 'O arquivo de Boleto excede o limite do servidor (' . upload_limite_label() . '). Envie um arquivo menor.'
            : 'Falha no upload do arquivo de Boleto. Tente novamente.';
        json_response(['success' => false, 'message' => $msg], 422);
    }
}

try {
    $url_proposta = media_upload($_FILES['proposta'], 'titulo/proposta');
    $url_boleto   = $ehCartao ? null : media_upload($_FILES['boleto'], 'titulo/boleto');
} catch (Throwable $e) {
    json_response(['success' => false, 'message' => 'Erro ao enviar arquivo: ' . $e->getMessage()], 500);
}

db_execute(
    "INSERT INTO cotacoes_anexos_titulo (protocolo, nome_arquivo, caminho_arquivo, tipo_arquivo, data_envio, usuario_envio, status_aprovacao)
     VALUES (?, ?, ?, 'proposta', NOW(), ?, 'Enviada')",
    ['ssss', $protocolo, $_FILES['proposta']['name'], $url_proposta, $admin->nome ?? 'MPS']
);

if ($ehCartao) {
    db_execute(
        "INSERT INTO cotacoes_anexos_titulo (protocolo, nome_arquivo, caminho_arquivo, tipo_arquivo, data_envio, usuario_envio, status_aprovacao)
         VALUES (?, 'Link de pagamento (cartão)', ?, 'link_pagamento_cartao', NOW(), ?, 'Enviada')",
        ['sss', $protocolo, $linkPagamento, $admin->nome ?? 'MPS']
    );
} else {
    db_execute(
        "INSERT INTO cotacoes_anexos_titulo (protocolo, nome_arquivo, caminho_arquivo, tipo_arquivo, data_envio, usuario_envio, status_aprovacao)
         VALUES (?, ?, ?, 'boleto', NOW(), ?, 'Enviada')",
        ['ssss', $protocolo, $_FILES['boleto']['name'], $url_boleto, $admin->nome ?? 'MPS']
    );
}

$status_anterior = $cotacao['status'];
$status_novo      = 'Aguardando Pagamento';

db_execute(
    "UPDATE titulo_capitalizacao SET status = ?, data_atualizacao = NOW() WHERE protocolo = ?",
    ['ss', $status_novo, $protocolo]
);

historico_registrar(
    (int) $cotacao['id'], $protocolo, 'ALTERACAO_STATUS',
    $status_anterior, $status_novo, (int) $admin->uid, 'admin', $admin->nome ?? 'MPS',
    $ehCartao ? 'Proposta e link de pagamento enviados.' : 'Proposta e boleto enviados.'
);

try {
    notificar_imobiliaria(
        (int) $cotacao['imobiliaria_id'],
        'status_alterado',
        $ehCartao ? 'Proposta e link de pagamento enviados' : 'Proposta e boleto enviados',
        $ehCartao
            ? "A proposta e o link de pagamento do protocolo {$protocolo} já estão disponíveis. Aguardando confirmação de pagamento."
            : "A proposta e o boleto do protocolo {$protocolo} já estão disponíveis. Aguardando confirmação de pagamento.",
        (int) $cotacao['id'],
        null,
        (int) ($cotacao['usuario_id'] ?? 0)
    );
} catch (Throwable $e) {
    error_log('[titulo-proposta-boleto] Erro notificação: ' . $e->getMessage());
}

json_response([
    'success' => true,
    'message' => $ehCartao ? 'Proposta e link de pagamento enviados com sucesso.' : 'Proposta e boleto enviados com sucesso.',
    'status'  => $status_novo,
]);
