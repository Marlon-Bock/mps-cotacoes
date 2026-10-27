<?php
/**
 * POST /api/cotacao/condominial
 * Recebe os dados do formulário de Seguro Condomínio e salva no banco.
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
    if (empty($user->imob_admin) && (($user->perm_cotar ?? 'N') !== 'S' || ($user->perm_condo ?? 'N') !== 'S')) {
        json_response(['success' => false, 'message' => 'Sem permissão para cotar Seguro Condomínio.'], 403);
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

function cond_str(array $body, string $key, int $max = 255): string {
    return mb_substr(trim((string)($body[$key] ?? '')), 0, $max);
}

function cond_int(array $body, string $key): ?int {
    $v = trim((string)($body[$key] ?? ''));
    return $v !== '' ? (int)$v : null;
}

function cond_currency(array $body, string $key): ?string {
    $v = trim((string)($body[$key] ?? ''));
    if ($v === '') return null;
    // Remove R$, pontos de milhar, substitui vírgula por ponto
    $v = preg_replace('/[^\d,]/', '', $v);
    $v = str_replace(',', '.', $v);
    return is_numeric($v) ? $v : null;
}

// ── Condomínio ─────────────────────────────────────────────────────────────
$tipo_condominio      = cond_str($body, 'tipo_condominio');
$imovel_nome          = cond_str($body, 'imovel_nome');
$imovel_cnpj          = cond_str($body, 'imovel_cnpj');
$imovel_endereco      = cond_str($body, 'imovel_endereco');
$imovel_numero        = cond_str($body, 'imovel_numero');
$imovel_complemento   = cond_str($body, 'imovel_complemento');
$imovel_bairro        = cond_str($body, 'imovel_bairro');
$imovel_cep           = cond_str($body, 'imovel_cep');
$imovel_cidade        = cond_str($body, 'imovel_cidade');
$imovel_uf            = strtoupper(cond_str($body, 'imovel_uf', 2));
$imovel_ano_construcao = cond_int($body, 'imovel_ano_construcao');
$imovel_blocos        = cond_int($body, 'imovel_blocos');
$imovel_andares       = cond_int($body, 'imovel_andares');
$imovel_elevadores    = cond_int($body, 'imovel_elevadores');
$imovel_area_m2       = cond_int($body, 'imovel_area_m2');

// ── Características ────────────────────────────────────────────────────────
$tem_alarme              = cond_str($body, 'tem_alarme');
$tem_cameras             = cond_str($body, 'tem_cameras');
$tem_central_telefonica  = cond_str($body, 'tem_central_telefonica');
$tem_funcionarios        = cond_str($body, 'tem_funcionarios');
$qtd_funcionarios        = cond_int($body, 'qtd_funcionarios');
$tem_seguro_atual        = cond_str($body, 'tem_seguro_atual');
$seguro_atual_seguradora = cond_str($body, 'seguro_atual_seguradora');
$seguro_atual_classe_bonus = cond_str($body, 'seguro_atual_classe_bonus');

// ── Coberturas ─────────────────────────────────────────────────────────────
$cob_incendio                 = cond_currency($body, 'cob_incendio');
$cob_danos_eletricos          = cond_currency($body, 'cob_danos_eletricos');
$cob_vazamento                = cond_currency($body, 'cob_vazamento');
$cob_rc_sindico               = cond_currency($body, 'cob_rc_sindico');
$cob_roubo_cond               = cond_currency($body, 'cob_roubo_cond');
$cob_rc_condominio            = cond_currency($body, 'cob_rc_condominio');
$cob_perda_aluguel            = cond_currency($body, 'cob_perda_aluguel');
$cob_rc_danos_morais          = cond_currency($body, 'cob_rc_danos_morais');
$cob_roubo_condominios        = cond_currency($body, 'cob_roubo_condominios');
$cob_rc_empregador            = cond_currency($body, 'cob_rc_empregador');
$cob_incendio_condominios     = cond_currency($body, 'cob_incendio_condominios');
$cob_rc_portoes               = cond_currency($body, 'cob_rc_portoes');
$cob_equipamentos_eletronicos = cond_currency($body, 'cob_equipamentos_eletronicos');
$cob_vendaval                 = cond_currency($body, 'cob_vendaval');
$cob_impacto_veiculos         = cond_currency($body, 'cob_impacto_veiculos');
$cob_tumulto_greve            = cond_currency($body, 'cob_tumulto_greve');
$cob_quebra_vidros            = cond_currency($body, 'cob_quebra_vidros');
$cob_despesas_fixas           = cond_currency($body, 'cob_despesas_fixas');
$cob_rc_garagista_compreensiva = cond_currency($body, 'cob_rc_garagista_compreensiva');
$cob_lucros_cessantes         = cond_currency($body, 'cob_lucros_cessantes');
$cob_rc_garagista_exclusiva   = cond_currency($body, 'cob_rc_garagista_exclusiva');
$cob_desmoronamento           = cond_currency($body, 'cob_desmoronamento');
$cob_vida_grupo               = cond_currency($body, 'cob_vida_grupo');
$cob_alagamento               = cond_currency($body, 'cob_alagamento');
$cob_chuveiros                = cond_currency($body, 'cob_chuveiros');
$cob_placas_solares           = cond_currency($body, 'cob_placas_solares');

// ── Síndico ────────────────────────────────────────────────────────────────
$sindico_nome     = cond_str($body, 'sindico_nome');
$sindico_telefone = cond_str($body, 'sindico_telefone');
$sindico_email    = cond_str($body, 'sindico_email');

// ── Validação mínima ───────────────────────────────────────────────────────
if (!$tipo_condominio || !$imovel_nome || !$imovel_cnpj || !$imovel_cep || !$imovel_endereco
    || !$imovel_numero || !$imovel_bairro || !$imovel_cidade || !$imovel_uf
    || !$sindico_nome || !$sindico_telefone || !$sindico_email) {
    json_response(['success' => false, 'message' => 'Preencha os campos obrigatórios.'], 422);
}

// ── Protocolo ──────────────────────────────────────────────────────────────
$protocolo    = 'COND' . date('Y') . '-' . str_pad((string)random_int(1, 99999), 5, '0', STR_PAD_LEFT);
$atendente_id = funil_atribuir_atendente('condominial');

// ── Inserção ───────────────────────────────────────────────────────────────
$sql = "INSERT INTO seguro_condominial (
    protocolo, imobiliaria_id, usuario_id, status,
    tipo_condominio, imovel_nome, imovel_cnpj,
    imovel_endereco, imovel_numero, imovel_complemento, imovel_bairro,
    imovel_cep, imovel_cidade, imovel_uf,
    imovel_ano_construcao, imovel_blocos, imovel_andares, imovel_elevadores, imovel_area_m2,
    tem_alarme, tem_cameras, tem_central_telefonica,
    tem_funcionarios, qtd_funcionarios,
    tem_seguro_atual, seguro_atual_seguradora, seguro_atual_classe_bonus,
    cob_incendio, cob_danos_eletricos, cob_vazamento, cob_rc_sindico,
    cob_roubo_cond, cob_rc_condominio, cob_perda_aluguel, cob_rc_danos_morais,
    cob_roubo_condominios, cob_rc_empregador, cob_incendio_condominios, cob_rc_portoes,
    cob_equipamentos_eletronicos, cob_vendaval, cob_impacto_veiculos, cob_tumulto_greve,
    cob_quebra_vidros, cob_despesas_fixas, cob_rc_garagista_compreensiva, cob_lucros_cessantes,
    cob_rc_garagista_exclusiva, cob_desmoronamento, cob_vida_grupo, cob_alagamento,
    cob_chuveiros, cob_placas_solares,
    sindico_nome, sindico_telefone, sindico_email,
    atendente_id,
    criado_em
) VALUES (
    ?,?,?,?,
    ?,?,?,
    ?,?,?,?,
    ?,?,?,
    ?,?,?,?,?,
    ?,?,?,
    ?,?,
    ?,?,?,
    ?,?,?,?,
    ?,?,?,?,
    ?,?,?,?,
    ?,?,?,?,
    ?,?,?,?,
    ?,?,?,?,
    ?,?,
    ?,?,?,
    ?,
    NOW()
)";

$valores = [
    $protocolo, $imobiliaria_id, $usuario_id, 'Aguardando análise',
    $tipo_condominio, $imovel_nome, $imovel_cnpj ?: null,
    $imovel_endereco, $imovel_numero ?: null, $imovel_complemento ?: null, $imovel_bairro ?: null,
    $imovel_cep, $imovel_cidade ?: null, $imovel_uf ?: null,
    $imovel_ano_construcao, $imovel_blocos, $imovel_andares, $imovel_elevadores, $imovel_area_m2,
    $tem_alarme ?: null, $tem_cameras ?: null, $tem_central_telefonica ?: null,
    $tem_funcionarios ?: null, $qtd_funcionarios,
    $tem_seguro_atual ?: null, $seguro_atual_seguradora ?: null, $seguro_atual_classe_bonus ?: null,
    $cob_incendio, $cob_danos_eletricos, $cob_vazamento, $cob_rc_sindico,
    $cob_roubo_cond, $cob_rc_condominio, $cob_perda_aluguel, $cob_rc_danos_morais,
    $cob_roubo_condominios, $cob_rc_empregador, $cob_incendio_condominios, $cob_rc_portoes,
    $cob_equipamentos_eletronicos, $cob_vendaval, $cob_impacto_veiculos, $cob_tumulto_greve,
    $cob_quebra_vidros, $cob_despesas_fixas, $cob_rc_garagista_compreensiva, $cob_lucros_cessantes,
    $cob_rc_garagista_exclusiva, $cob_desmoronamento, $cob_vida_grupo, $cob_alagamento,
    $cob_chuveiros, $cob_placas_solares,
    $sindico_nome ?: null, $sindico_telefone ?: null, $sindico_email ?: null,
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
    $usuario_id, 'imobiliaria', $user->nome ?? 'Imobiliária', 'Cotação de seguro condominial criada.'
);

try {
    notificar_todos_admins(
        'nova_cotacao',
        'Nova cotação recebida',
        "Protocolo {$protocolo} — Seguro Condominial aguardando análise.",
        (int) $ok,
        cotacao_notif_url('condominial', $protocolo, 'admin')
    );
    notificar_cotacao_email('condominial', 'Seguro Condominial', $protocolo, (int) $imobiliaria_id);
} catch (Throwable $e) {
    error_log('[cotacao-condominial] Erro ao criar notificações: ' . $e->getMessage());
}

require_once BASE_PATH . '/src/helpers/zapi.php';
zapi_notificar_atendente(
    $atendente_id ?: null,
    "🆕 *Nova cotação — Seguro Condominial*\n\n"
    . "🏢 Condomínio: *{$imovel_nome}*\n"
    . "🏢 Imobiliária: " . zapi_imobiliaria_nome($imobiliaria_id) . "\n\n"
    . "📄 Protocolo: `{$protocolo}`",
    'nova_cotacao',
    $protocolo
);

// Rascunho finalizado — remove para não aparecer mais na listagem
$rascunho_protocolo = cond_str($body, 'rascunho', 30);
if ($rascunho_protocolo !== '') {
    try {
        db_execute(
            'DELETE FROM seguro_condominial_rascunho WHERE protocolo = ? AND imobiliaria_id = ?',
            ['si', $rascunho_protocolo, $imobiliaria_id]
        );
    } catch (Throwable $e) {
        error_log('[cotacao-condominial] Erro ao excluir rascunho: ' . $e->getMessage());
    }
}

submit_token_concluir($submit_token, $protocolo);

json_response([
    'success'       => true,
    'protocolo'     => $protocolo,
    'aviso_horario' => horario_atendimento_verificar('condominial'),
]);
