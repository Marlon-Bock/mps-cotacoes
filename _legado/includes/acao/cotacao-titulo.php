<?php
/**
 * POST /api/cotacao/titulo
 * Recebe os dados do formulário de Título de Capitalização e salva no banco.
 */

global $params;
if (!empty($params['embed_token'])) {
    $user = embed_resolver($params['embed_token']);
    embed_csrf_verify($params['embed_token']);
} else {
    $user = require_auth();
    csrf_verify();

    if (($user->role ?? '') !== 'imobiliaria') {
        json_response(['success' => false, 'message' => 'Acesso não permitido.'], 403);
    }
    if (empty($user->imob_admin) && (($user->perm_cotar ?? 'N') !== 'S' || ($user->perm_titulo ?? 'N') !== 'S')) {
        json_response(['success' => false, 'message' => 'Sem permissão para cotar Título de Capitalização.'], 403);
    }
}

$imobiliaria_id = (int)($user->imobiliaria_id ?? 0);
$usuario_id     = (int)($user->uid ?? 0);

if (!$imobiliaria_id) {
    json_response(['success' => false, 'message' => 'Imobiliária não identificada.'], 400);
}

$submit_token = submit_token_ler();
if ($dup = submit_token_duplicado($submit_token)) {
    json_response(['success' => true, 'protocolo' => $dup, 'duplicado' => true]);
}

$body = $_POST;

function tc_str(array $body, string $key, int $max = 255): string {
    return mb_substr(trim((string)($body[$key] ?? '')), 0, $max);
}
function tc_int(array $body, string $key): int {
    return (int)($body[$key] ?? 0);
}
function tc_money(array $body, string $key): string {
    $v = trim((string)($body[$key] ?? ''));
    if ($v === '') return '';
    $v = preg_replace('/[^\d,.-]/', '', $v);
    if (str_contains($v, ',')) {
        $v = str_replace('.', '', $v);
        $v = str_replace(',', '.', $v);
    }
    return is_numeric($v) ? $v : '';
}

// ── Contrato ───────────────────────────────────────────────────────────────
$finalidade      = tc_str($body, 'finalidade');
$prazo_contrato  = tc_int($body, 'prazo_contrato');
$valor_aluguel   = tc_money($body, 'valor_aluguel');
$valor_titulo    = tc_money($body, 'valor_titulo');
$forma_pagamento = tc_str($body, 'forma_pagamento', 20);

// ── Imóvel ─────────────────────────────────────────────────────────────────
$imovel_cep         = tc_str($body, 'imovel_cep');
$imovel_logradouro  = tc_str($body, 'imovel_logradouro');
$imovel_numero      = tc_str($body, 'imovel_numero');
$imovel_complemento = tc_str($body, 'imovel_complemento');
$imovel_bairro      = tc_str($body, 'imovel_bairro');
$imovel_cidade      = tc_str($body, 'imovel_cidade');
$imovel_uf          = strtoupper(tc_str($body, 'imovel_uf', 2));

// ── Inquilino ──────────────────────────────────────────────────────────────
$inquilino_tipo          = tc_str($body, 'inquilino_tipo');
// PF
$inq_nome                = tc_str($body, 'inq_nome');
$inq_cpf                 = tc_str($body, 'inq_cpf');
$inq_data_nasc           = tc_str($body, 'inq_data_nasc');
$inq_estado_civil        = tc_str($body, 'inq_estado_civil');
$inq_rg                  = tc_str($body, 'inq_rg');
$inq_rg_expedicao        = tc_str($body, 'inq_rg_expedicao');
$inq_orgao_emissor       = tc_str($body, 'inq_orgao_emissor');
$inq_email               = tc_str($body, 'inq_email');
$inq_profissao           = tc_str($body, 'inq_profissao');
$inq_telefone1           = tc_str($body, 'inq_telefone1');
$inq_telefone2           = tc_str($body, 'inq_telefone2');
$inq_cep                 = tc_str($body, 'inq_cep');
$inq_logradouro          = tc_str($body, 'inq_logradouro');
$inq_numero              = tc_str($body, 'inq_numero');
$inq_complemento         = tc_str($body, 'inq_complemento');
$inq_bairro              = tc_str($body, 'inq_bairro');
$inq_cidade              = tc_str($body, 'inq_cidade');
$inq_uf                  = strtoupper(tc_str($body, 'inq_uf', 2));
// PJ
$inq_razao_social        = tc_str($body, 'inq_razao_social');
$inq_cnpj                = tc_str($body, 'inq_cnpj');
$inq_representante       = tc_str($body, 'inq_representante');
$inq_cpf_representante   = tc_str($body, 'inq_cpf_representante');
$inq_pj_email            = tc_str($body, 'inq_pj_email');
$inq_pj_telefone         = tc_str($body, 'inq_pj_telefone');
$pol_exposta             = tc_str($body, 'pol_exposta');
$pol_exposta_funcao      = tc_str($body, 'pol_exposta_funcao');

// ── Proprietário ───────────────────────────────────────────────────────────
$prop_nome         = tc_str($body, 'prop_nome');
$prop_cpf          = tc_str($body, 'prop_cpf');
$prop_data_nasc    = tc_str($body, 'prop_data_nasc');
$prop_estado_civil = tc_str($body, 'prop_estado_civil');
$prop_rg           = tc_str($body, 'prop_rg');
$prop_rg_expedicao = tc_str($body, 'prop_rg_expedicao');
$prop_orgao_emissor= tc_str($body, 'prop_orgao_emissor');
$prop_profissao    = tc_str($body, 'prop_profissao');
$prop_email        = tc_str($body, 'prop_email');
$prop_telefone1    = tc_str($body, 'prop_telefone1');
$prop_telefone2    = tc_str($body, 'prop_telefone2');
$prop_cnpj         = tc_str($body, 'prop_cnpj');
$prop_nome_socio   = tc_str($body, 'prop_nome_socio');

// ── Validação mínima ───────────────────────────────────────────────────────
if (!$finalidade) {
    json_response(['success' => false, 'message' => 'Informe a finalidade do imóvel.'], 422);
}
if (!$prazo_contrato) {
    json_response(['success' => false, 'message' => 'Informe o prazo do contrato.'], 422);
}
if (!$valor_aluguel) {
    json_response(['success' => false, 'message' => 'Informe o valor do aluguel mensal.'], 422);
}
if (!$valor_titulo) {
    json_response(['success' => false, 'message' => 'Informe o valor do título.'], 422);
}
if (!$imovel_logradouro) {
    json_response(['success' => false, 'message' => 'Informe o logradouro do imóvel.'], 422);
}
if (!$imovel_cep) {
    json_response(['success' => false, 'message' => 'Informe o CEP do imóvel.'], 422);
}
if (!in_array($forma_pagamento, ['Boleto', 'Cartão'], true)) {
    json_response(['success' => false, 'message' => 'Selecione a forma de pagamento.'], 422);
}

// ── Protocolo ──────────────────────────────────────────────────────────────
$protocolo    = 'TC' . date('Y') . '-' . str_pad((string)random_int(1, 99999), 5, '0', STR_PAD_LEFT);
$atendente_id = funil_atribuir_atendente('titulo');

// ── Inserção ───────────────────────────────────────────────────────────────
$sql = "INSERT INTO titulo_capitalizacao (
    protocolo, imobiliaria_id, usuario_id, status,
    finalidade, prazo_contrato, valor_aluguel, valor_titulo, forma_pagamento,
    imovel_cep, imovel_logradouro, imovel_numero, imovel_complemento,
    imovel_bairro, imovel_cidade, imovel_uf,
    inquilino_tipo,
    inq_nome, inq_cpf, inq_data_nasc, inq_estado_civil,
    inq_rg, inq_rg_expedicao, inq_orgao_emissor,
    inq_email, inq_profissao, inq_telefone1, inq_telefone2,
    inq_cep, inq_logradouro, inq_numero, inq_complemento,
    inq_bairro, inq_cidade, inq_uf,
    inq_razao_social, inq_cnpj, inq_representante, inq_cpf_representante,
    inq_pj_email, inq_pj_telefone, pol_exposta, pol_exposta_funcao,
    prop_nome, prop_cpf, prop_data_nasc, prop_estado_civil,
    prop_rg, prop_rg_expedicao, prop_orgao_emissor,
    prop_profissao, prop_email, prop_telefone1, prop_telefone2,
    prop_cnpj, prop_nome_socio,
    atendente_id,
    criado_em
) VALUES (
    ?,?,?,?,
    ?,?,?,?,?,
    ?,?,?,?,
    ?,?,?,
    ?,
    ?,?,?,?,
    ?,?,?,
    ?,?,?,?,
    ?,?,?,?,
    ?,?,?,
    ?,?,?,?,
    ?,?,?,?,
    ?,?,?,?,
    ?,?,?,
    ?,?,?,?,
    ?,?,
    ?,
    NOW()
)";

$valores = [
    $protocolo, $imobiliaria_id, $usuario_id, 'Aguardando análise',
    $finalidade, $prazo_contrato, $valor_aluguel, $valor_titulo ?: null, $forma_pagamento,
    $imovel_cep, $imovel_logradouro, $imovel_numero, $imovel_complemento ?: null,
    $imovel_bairro, $imovel_cidade, $imovel_uf,
    $inquilino_tipo ?: 'pf',
    $inq_nome ?: null, $inq_cpf ?: null, $inq_data_nasc ?: null, $inq_estado_civil ?: null,
    $inq_rg ?: null, $inq_rg_expedicao ?: null, $inq_orgao_emissor ?: null,
    $inq_email ?: null, $inq_profissao ?: null, $inq_telefone1 ?: null, $inq_telefone2 ?: null,
    $inq_cep ?: null, $inq_logradouro ?: null, $inq_numero ?: null, $inq_complemento ?: null,
    $inq_bairro ?: null, $inq_cidade ?: null, $inq_uf ?: null,
    $inq_razao_social ?: null, $inq_cnpj ?: null, $inq_representante ?: null, $inq_cpf_representante ?: null,
    $inq_pj_email ?: null, $inq_pj_telefone ?: null, $pol_exposta ?: null, $pol_exposta_funcao ?: null,
    $prop_nome, $prop_cpf, $prop_data_nasc ?: null, $prop_estado_civil ?: null,
    $prop_rg ?: null, $prop_rg_expedicao ?: null, $prop_orgao_emissor ?: null,
    $prop_profissao ?: null, $prop_email ?: null, $prop_telefone1 ?: null, $prop_telefone2 ?: null,
    $prop_cnpj ?: null, $prop_nome_socio ?: null,
    $atendente_id ?: null,
];
// Ver comentário equivalente em cotacao-auto.php sobre por que ligar tudo
// como string em vez de manter uma string de tipos em sincronia manual.
$ok = db_execute($sql, [str_repeat('s', count($valores)), ...$valores]);

if (!$ok) {
    json_response(['success' => false, 'message' => 'Erro ao salvar cotação. Tente novamente.'], 500);
}

historico_registrar(
    (int) $ok, $protocolo, 'CRIACAO', null, 'Aguardando análise',
    $usuario_id, 'imobiliaria', $user->nome ?? 'Imobiliária', 'Cotação de título de capitalização criada.'
);

try {
    notificar_todos_admins(
        'nova_cotacao',
        'Nova cotação recebida',
        "Protocolo {$protocolo} — Título de Capitalização aguardando análise.",
        (int) $ok,
        cotacao_notif_url('titulo', $protocolo, 'admin')
    );
    notificar_cotacao_email('titulo', 'Título de Capitalização', $protocolo, (int) $imobiliaria_id);
} catch (Throwable $e) {
    error_log('[cotacao-titulo] Erro ao criar notificações: ' . $e->getMessage());
}

require_once BASE_PATH . '/src/helpers/zapi.php';
$inquilino_msg = $inquilino_tipo === 'PJ' ? ($inq_razao_social ?: 'Empresa') : ($inq_nome ?: 'Inquilino');
zapi_notificar_atendente(
    $atendente_id ?: null,
    "🆕 *Nova cotação — Título de Capitalização*\n\n"
    . "👤 Inquilino: *{$inquilino_msg}*\n"
    . "🏢 " . zapi_imobiliaria_nome($imobiliaria_id) . "\n\n"
    . "📄 Protocolo: `{$protocolo}`",
    'nova_cotacao',
    $protocolo
);

// Rascunho finalizado — remove para não aparecer mais na listagem
$rascunho_protocolo = tc_str($body, 'rascunho', 30);
if ($rascunho_protocolo !== '') {
    try {
        db_execute(
            'DELETE FROM seguro_titulo_rascunho WHERE protocolo = ? AND imobiliaria_id = ?',
            ['si', $rascunho_protocolo, $imobiliaria_id]
        );
    } catch (Throwable $e) {
        error_log('[cotacao-titulo] Erro ao excluir rascunho: ' . $e->getMessage());
    }
}

submit_token_concluir($submit_token, $protocolo);

json_response([
    'success'       => true,
    'protocolo'     => $protocolo,
    'aviso_horario' => horario_atendimento_verificar('titulo_capitalizacao'),
]);
