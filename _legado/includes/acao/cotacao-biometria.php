<?php
/**
 * POST /api/cotacao/{protocolo}/biometria
 * Gerencia biometria via biometria_url/biometria_status — igual ao legado
 * (admin/includes/acao/acao-salvar-biometria.php). Para fiança, enviar_link/
 * dispensar já adiantam o status da cotação pra "Pré-aprovado" (a decisão de
 * aprovar/recusar só libera depois que a biometria for concluída ou dispensada,
 * via biometria_status). Para os demais tipos o status não muda.
 * Ações: enviar_link | dispensar | concluir.
 */

$protocolo = $params['protocolo'] ?? '';
if (!$protocolo) {
    json_response(['success' => false, 'message' => 'Protocolo inválido.'], 400);
}

// require_admin() nunca "lança" em caso de papel errado — ela redireciona e
// dá exit (retorno `never`), então um try/catch em volta dela nunca cai no
// catch. Isso deixava o ramo da imobiliária (usado por "concluir" e agora
// "marcar_lido") inatingível: toda chamada de um usuário não-admin virava um
// redirect pro /403 antes de chegar na checagem de papel abaixo.
$auth_user = require_auth();
csrf_verify();
$papel     = $auth_user->role ?? '';
if (!in_array($papel, ['admin', 'imobiliaria'], true)) {
    json_response(['success' => false, 'message' => 'Acesso não permitido.'], 403);
}
$admin = $papel === 'admin' ? $auth_user : null;
$user  = $papel === 'imobiliaria' ? $auth_user : null;

$acao = sanitize((string) ($_POST['acao'] ?? ''));
$link = sanitize((string) ($_POST['link'] ?? ''));

if (!in_array($acao, ['enviar_link', 'dispensar', 'concluir', 'marcar_lido'], true)) {
    json_response(['success' => false, 'message' => 'Ação inválida.'], 422);
}

if (in_array($acao, ['enviar_link', 'dispensar'], true) && !$admin) {
    json_response(['success' => false, 'message' => 'Somente a equipe MPS pode realizar esta ação.'], 403);
}

if ($acao === 'enviar_link' && empty($link)) {
    json_response(['success' => false, 'message' => 'O link é obrigatório para envio de biometria.'], 422);
}

$resolvido = cotacao_resolver($protocolo);
if (!$resolvido) {
    json_response(['success' => false, 'message' => 'Cotação não encontrada.'], 404);
}
if (!$resolvido['suporta_biometria']) {
    json_response(['success' => false, 'message' => 'Este tipo de cotação não usa biometria.'], 422);
}
$tbl_cotacao = $resolvido['tabela'];
$cotacao     = $resolvido['row'];
$ehFianca    = $tbl_cotacao === 'seguro_fianca';

if ($user && !cotacao_pode_acessar($resolvido, $user)) {
    json_response(['success' => false, 'message' => 'Acesso não permitido.'], 403);
}

if ($cotacao['status'] !== 'Em Analise' && !($ehFianca && $cotacao['status'] === 'Pré-aprovado')) {
    json_response(['success' => false, 'message' => 'Biometria só pode ser gerenciada enquanto a cotação está "Em Analise".'], 422);
}

// "Marcar como lido" é só um sinal visual pro grid (tira o destaque vermelho
// do botão de copiar link) — não é um evento de negócio, então não gera
// histórico nem notificação, ao contrário das demais ações desta rota.
if ($acao === 'marcar_lido') {
    db_execute(
        "UPDATE {$tbl_cotacao} SET biometria_link_lido_em = NOW() WHERE protocolo = ? AND biometria_link_lido_em IS NULL",
        ['s', $protocolo]
    );
    json_response(['success' => true]);
}

$obs             = null;
$status_anterior = $cotacao['status'];
$status_novo     = $status_anterior;

switch ($acao) {
    case 'enviar_link':
        if ($ehFianca) $status_novo = 'Pré-aprovado';
        db_execute(
            "UPDATE {$tbl_cotacao} SET biometria_url = ?, biometria_status = 'pendente', biometria_link_lido_em = NULL, status = ?, data_atualizacao = NOW() WHERE protocolo = ?",
            ['sss', $link, $status_novo, $protocolo]
        );
        $obs = "Link de biometria enviado: {$link}";
        break;

    case 'dispensar':
        if ($ehFianca) $status_novo = 'Pré-aprovado';
        db_execute(
            "UPDATE {$tbl_cotacao} SET biometria_status = 'sem_biometria', status = ?, data_atualizacao = NOW() WHERE protocolo = ?",
            ['ss', $status_novo, $protocolo]
        );
        $obs = 'Biometria dispensada pela MPS.';
        break;

    case 'concluir':
        db_execute(
            "UPDATE {$tbl_cotacao} SET biometria_status = 'concluida', data_atualizacao = NOW() WHERE protocolo = ?",
            ['s', $protocolo]
        );
        $obs = 'Biometria marcada como concluída.';
        break;
}

if ($admin) {
    [$uid, $uname] = [(int) $admin->uid, $admin->nome ?? 'MPS'];
} else {
    [$uid, $uname] = [(int) $user->uid, $user->nome ?? 'Imobiliária'];
}

// tipo_evento é ENUM no banco nativo: usamos ALTERACAO_STATUS (não há valor "BIOMETRIA" no enum).
historico_registrar((int) $cotacao['id'], $protocolo, 'ALTERACAO_STATUS', $status_anterior, $status_novo, $uid, $admin ? 'admin' : 'imobiliaria', $uname, $obs);

try {
    if ($admin) {
        notificar_imobiliaria(
            (int) $cotacao['imobiliaria_id'],
            'biometria_url',
            'Biometria',
            $obs,
            (int) $cotacao['id'],
            null,
            (int) ($cotacao['usuario_id'] ?? 0)
        );
    } elseif ($acao === 'concluir') {
        // Biometria concluída pela própria imobiliária — avisa só o atendente.
        require_once BASE_PATH . '/src/helpers/zapi.php';
        $nome_pretendente = $cotacao['nome_completo'] ?? $cotacao['segurado_nome'] ?? 'Pretendente';
        zapi_notificar_atendente(
            isset($cotacao['atendente_id']) ? (int) $cotacao['atendente_id'] : null,
            "✅ *Biometria concluída pela imobiliária*\n\n"
            . "Pretendente: *{$nome_pretendente}*\n"
            . "Já pode seguir com a análise.\n\n"
            . "📄 Protocolo: `{$protocolo}`",
            'biometria_concluida',
            $protocolo
        );
    }
} catch (Throwable $e) {
    error_log('[biometria] Erro notificação: ' . $e->getMessage());
}

json_response(['success' => true, 'message' => $obs, 'status' => $status_novo]);
