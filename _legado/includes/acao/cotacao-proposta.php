<?php
/**
 * POST /api/cotacao/{protocolo}/proposta
 * MPS envia a proposta (PDF) para a cotação. Usa a tabela nativa cotacoes_anexos
 * (já suporta múltiplas linhas — cada nova proposta é uma nova linha, sem apagar a anterior).
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
$tbl_anexos  = $resolvido['tabela_anexos'];
$cotacao     = $resolvido['row'];

// Fiança exige status "Pré-aprovado" (biometria já concluída/dispensada, ver cotacao-biometria.php);
// os demais tipos (sem esse passo intermediário) seguem exigindo "Em Analise", como sempre.
$prontoParaDecisao = $tbl_cotacao === 'seguro_fianca'
    ? $cotacao['status'] === 'Pré-aprovado'
    : $cotacao['status'] === 'Em Analise';

// Permite enviar proposta quando pronto pra decisão, OU já está aprovado mas
// a proposta anterior foi recusada pelo cliente (envia nova proposta no mesmo status "Aprovado").
$anexoAnterior = db_fetch_one(
    "SELECT * FROM {$tbl_anexos} WHERE protocolo = ? ORDER BY data_envio DESC LIMIT 1",
    ['s', $protocolo]
);
$podeReenviar = $cotacao['status'] === 'Aprovado' && $anexoAnterior && $anexoAnterior['status_aprovacao'] === 'Recusada';

if (!($prontoParaDecisao || $podeReenviar)) {
    json_response([
        'success' => false,
        'message' => 'Status atual não permite envio de proposta. Conclua ou dispense a biometria primeiro.',
    ], 422);
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
    $arquivo_url = media_upload($_FILES['arquivo'], 'propostas/' . $resolvido['tipo']);
} catch (Throwable $e) {
    json_response(['success' => false, 'message' => 'Erro ao enviar arquivo: ' . $e->getMessage()], 500);
}

db_execute(
    "INSERT INTO {$tbl_anexos} (protocolo, nome_arquivo, caminho_arquivo, tipo_arquivo, data_envio, usuario_envio, status_aprovacao)
     VALUES (?, ?, ?, 'proposta', NOW(), ?, 'Aguardando')",
    ['ssss', $protocolo, $_FILES['arquivo']['name'], $arquivo_url, $admin->nome ?? 'MPS']
);

$status_anterior = $cotacao['status'];
$status_novo      = 'Aprovado';

db_execute(
    "UPDATE {$tbl_cotacao} SET status = ?, data_status_aprovado = NOW(), data_atualizacao = NOW() WHERE protocolo = ?",
    ['ss', $status_novo, $protocolo]
);

historico_registrar(
    (int) $cotacao['id'], $protocolo, 'ENVIO_COTACAO',
    $status_anterior, $status_novo, (int) $admin->uid, 'admin', $admin->nome ?? 'MPS',
    trim(($podeReenviar ? 'Nova proposta enviada após recusa do cliente. ' : 'Proposta enviada. ') . $observacao)
);

try {
    notificar_imobiliaria(
        (int) $cotacao['imobiliaria_id'],
        'status_alterado',
        'Proposta enviada',
        "Proposta enviada para o protocolo {$protocolo}. Aguardando sua decisão.",
        (int) $cotacao['id'],
        null,
        (int) ($cotacao['usuario_id'] ?? 0)
    );
} catch (Throwable $e) {
    error_log('[proposta] Erro notificação: ' . $e->getMessage());
}

json_response([
    'success'     => true,
    'message'     => 'Proposta enviada com sucesso.',
    'status'      => $status_novo,
    'arquivo_url' => $arquivo_url,
]);
