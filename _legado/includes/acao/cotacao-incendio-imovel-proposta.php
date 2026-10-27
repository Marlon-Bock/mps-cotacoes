<?php
/**
 * POST /api/cotacao/incendio/{protocolo}/imovel/{imovel_id}/proposta
 * MPS envia a proposta (PDF) de UM imóvel específico — cada imóvel vira
 * sua própria apólice, então cada um recebe sua própria proposta (espelha
 * includes/acao/cotacao-proposta.php, a versão genérica).
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

$fluxoNovo = ($imovel['fluxo'] ?? 'antigo') === 'novo';

if ($fluxoNovo) {
    if ($imovel['status'] !== 'Cotação aprovada') {
        json_response(['success' => false, 'message' => 'A proposta só pode ser enviada depois da aprovação da cotação.'], 422);
    }
    $statusNovoProposta = 'Aguardando apólice';
    $podeReenviar       = false;
} else {
    $prontoParaDecisao = $imovel['status'] === 'Em Analise';
    $anexoAnterior = db_fetch_one(
        'SELECT * FROM cotacoes_anexos_incendio WHERE seguro_incendio_imovel_id = ? ORDER BY data_envio DESC LIMIT 1',
        ['i', $imovel_id]
    );
    $podeReenviar = ($imovel['status'] === 'Aprovado' && $anexoAnterior && $anexoAnterior['status_aprovacao'] === 'Recusada')
        || $imovel['status'] === 'Alteração solicitada';

    if (!($prontoParaDecisao || $podeReenviar)) {
        json_response(['success' => false, 'message' => 'Status atual do imóvel não permite envio de proposta.'], 422);
    }
    $statusNovoProposta = 'Aprovado';
}

if ($m = upload_erro_limite()) {
    json_response(['success' => false, 'message' => $m], 413);
}
if (empty($_FILES['arquivo']) || ($_FILES['arquivo']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
    json_response(['success' => false, 'message' => 'Arquivo PDF é obrigatório.'], 422);
}
if ($_FILES['arquivo']['error'] !== UPLOAD_ERR_OK) {
    $msg = in_array($_FILES['arquivo']['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
        ? 'O arquivo excede o limite do servidor (' . upload_limite_label() . '). Envie um arquivo menor.'
        : 'Falha no upload do arquivo. Tente novamente.';
    json_response(['success' => false, 'message' => $msg], 422);
}

$observacao = sanitize((string) ($_POST['observacao'] ?? ''));

try {
    $arquivo_url = media_upload($_FILES['arquivo'], 'propostas/incendio');
} catch (Throwable $e) {
    json_response(['success' => false, 'message' => 'Erro ao enviar arquivo: ' . $e->getMessage()], 500);
}

db_execute(
    "INSERT INTO cotacoes_anexos_incendio (protocolo, seguro_incendio_imovel_id, nome_arquivo, caminho_arquivo, tipo_arquivo, data_envio, usuario_envio, status_aprovacao)
     VALUES (?, ?, ?, ?, 'proposta', NOW(), ?, 'Aguardando')",
    ['sisss', $protocolo, $imovel_id, $_FILES['arquivo']['name'], $arquivo_url, $admin->nome ?? 'MPS']
);

$status_anterior = $imovel['status'];
$status_novo     = $statusNovoProposta;
$eraAlteracaoSolicitada = $status_anterior === 'Alteração solicitada';

db_execute(
    'UPDATE seguro_incendio_imoveis SET status = ?, data_status_aprovado = IF(? = \'Aprovado\', NOW(), data_status_aprovado), data_atualizacao = NOW() WHERE id = ?',
    ['ssi', $status_novo, $status_novo, $imovel_id]
);

seguro_incendio_recalcular_status((int) $cotacao['id']);

historico_registrar(
    (int) $cotacao['id'], $protocolo, 'ENVIO_COTACAO',
    $status_anterior, $status_novo, (int) $admin->uid, 'admin', $admin->nome ?? 'MPS',
    trim("Imóvel {$imovel['numero_imovel']}: " . (
        $eraAlteracaoSolicitada ? 'nova proposta enviada após pedido de alteração. '
        : ($podeReenviar ? 'nova proposta enviada após recusa do cliente. ' : 'proposta enviada. ')
    ) . $observacao)
);

try {
    notificar_imobiliaria(
        (int) $cotacao['imobiliaria_id'],
        'status_alterado',
        'Proposta enviada',
        "Proposta enviada para o imóvel {$imovel['numero_imovel']} do protocolo {$protocolo}. Aguardando sua decisão.",
        (int) $cotacao['id'],
        null,
        (int) ($cotacao['usuario_id'] ?? 0)
    );
} catch (Throwable $e) {
    error_log('[incendio-imovel-proposta] Erro notificação: ' . $e->getMessage());
}

json_response([
    'success'     => true,
    'message'     => 'Proposta enviada com sucesso.',
    'status'      => $status_novo,
    'arquivo_url' => $arquivo_url,
]);
