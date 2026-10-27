<?php
/**
 * POST /api/cotacao/auto
 * Recebe os dados do formulário de Seguro Auto e salva no banco.
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
    if (empty($user->imob_admin) && (($user->perm_cotar ?? 'N') !== 'S' || ($user->perm_auto ?? 'N') !== 'S')) {
        json_response(['success' => false, 'message' => 'Sem permissão para cotar Seguro Auto.'], 403);
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

function auto_str(array $body, string $key, int $max = 255): string {
    return mb_substr(trim((string)($body[$key] ?? '')), 0, $max);
}
function auto_int(array $body, string $key): int {
    return (int)($body[$key] ?? 0);
}

// ── Veículo ────────────────────────────────────────────────────────────────
$tipo_veiculo          = auto_str($body, 'tipo_veiculo');
$marca_id              = auto_int($body, 'marca_id');
$modelo_id             = auto_int($body, 'modelo_id');
$ano_id                = auto_str($body, 'ano_id');
$marca_nome            = auto_str($body, 'marca_nome');
$modelo_nome           = auto_str($body, 'modelo_nome');
$ano_nome              = auto_str($body, 'ano_nome');
$placa                 = strtoupper(auto_str($body, 'placa'));
$chassi                = auto_str($body, 'chassi');
$combustivel           = auto_str($body, 'combustivel');
$kit_gas               = auto_str($body, 'kit_gas');
$utilizado_trabalho    = auto_str($body, 'utilizado_trabalho');
$utilizado_ir_trabalho = auto_str($body, 'utilizado_ir_trabalho');
$km_distancia_trabalho = auto_str($body, 'km_distancia_trabalho');
$tipo_seguro           = auto_str($body, 'tipo_seguro');
$seguro_novo           = $tipo_seguro === 'seguro_novo'   ? '1' : '0';
$renovacao_cia         = $tipo_seguro === 'renovacao_cia' ? '1' : '0';

// ── Segurado ───────────────────────────────────────────────────────────────
$segurado_nome         = auto_str($body, 'segurado_nome');
$segurado_cpf          = auto_str($body, 'segurado_cpf');
$segurado_data_nasc    = auto_str($body, 'segurado_data_nasc');
$segurado_estado_civil = auto_str($body, 'segurado_estado_civil');
$segurado_cep          = auto_str($body, 'segurado_cep');
$segurado_endereco     = auto_str($body, 'segurado_endereco');
$segurado_numero       = auto_str($body, 'segurado_numero');
$segurado_complemento  = auto_str($body, 'segurado_complemento');
$segurado_bairro       = auto_str($body, 'segurado_bairro');
$segurado_cidade       = auto_str($body, 'segurado_cidade');
$segurado_uf           = strtoupper(auto_str($body, 'segurado_uf', 2));

// ── Garagem ────────────────────────────────────────────────────────────────
$garagem_residencia    = auto_str($body, 'garagem_residencia') === '1' ? '1' : '0';
$portao_residencia     = auto_str($body, 'portao_residencia')  === '1' ? '1' : '0';
$garagem_trabalho      = auto_str($body, 'garagem_trabalho')   === '1' ? '1' : '0';
$portao_trabalho       = auto_str($body, 'portao_trabalho')    === '1' ? '1' : '0';
$garagem_faculdade     = auto_str($body, 'garagem_faculdade')  === '1' ? '1' : '0';
$portao_faculdade      = auto_str($body, 'portao_faculdade')   === '1' ? '1' : '0';

// ── Condutor ───────────────────────────────────────────────────────────────
$condutor_nome             = auto_str($body, 'condutor_nome');
$condutor_cpf              = auto_str($body, 'condutor_cpf');
$condutor_data_nasc        = auto_str($body, 'condutor_data_nasc');
$condutor_estado_civil     = auto_str($body, 'condutor_estado_civil');
$condutor_relacao_segurado = auto_str($body, 'condutor_relacao_segurado');
$condutor_menor_25         = auto_str($body, 'condutor_menor_25');
$condutor_menor_25_sexo    = auto_str($body, 'condutor_menor_25_sexo');

// ── Validação mínima ───────────────────────────────────────────────────────
if (!$tipo_veiculo || !$tipo_seguro || !$placa || !$segurado_nome || !$segurado_cpf) {
    json_response(['success' => false, 'message' => 'Preencha os campos obrigatórios.'], 422);
}

// O formulário manda DD/MM/AAAA (data-mask="date"), mas as colunas são DATE —
// gravado cru, o MySQL (STRICT_TRANS_TABLES) recusava e todo envio com data
// de nascimento preenchida caía em "Erro interno".
foreach (['segurado_data_nasc' => 'do segurado', 'condutor_data_nasc' => 'do condutor'] as $campo_data => $rotulo_data) {
    if ($$campo_data === '') continue;
    $convertida = date_from_br($$campo_data);
    if ($convertida === null) {
        json_response(['success' => false, 'message' => "Data de nascimento {$rotulo_data} inválida (use DD/MM/AAAA)."], 422);
    }
    $$campo_data = $convertida;
}

// Coluna INT: só os dígitos (o campo tem máscara numérica, mas não confia no cliente).
$km_distancia_trabalho = preg_replace('/\D/', '', $km_distancia_trabalho);

// ── Protocolo ──────────────────────────────────────────────────────────────
$protocolo    = 'AUTO' . date('Y') . '-' . str_pad((string)random_int(1, 99999), 5, '0', STR_PAD_LEFT);
$atendente_id = funil_atribuir_atendente('auto');

// ── Inserção ───────────────────────────────────────────────────────────────
$sql = "INSERT INTO seguro_auto (
    protocolo, imobiliaria_id, usuario_id, status, atendente_id,
    tipo_veiculo, marca_id, modelo_id, ano_id, marca_nome, modelo_nome, ano_nome,
    placa, chassi, combustivel, kit_gas,
    utilizado_trabalho, utilizado_ir_trabalho, km_distancia_trabalho,
    seguro_novo, renovacao_cia,
    segurado_nome, segurado_cpf, segurado_data_nasc, segurado_estado_civil,
    segurado_cep, segurado_endereco, segurado_numero, segurado_complemento,
    segurado_bairro, segurado_cidade, segurado_uf,
    garagem_residencia, portao_residencia,
    garagem_trabalho, portao_trabalho,
    garagem_faculdade, portao_faculdade,
    condutor_nome, condutor_cpf, condutor_data_nasc, condutor_estado_civil,
    condutor_relacao_segurado, condutor_menor_25, condutor_menor_25_sexo,
    data_cadastro, data_atualizacao, data_status_aguardando
) VALUES (
    ?,?,?,?,?,
    ?,?,?,?,?,?,?,
    ?,?,?,?,
    ?,?,?,
    ?,?,
    ?,?,?,?,
    ?,?,?,?,
    ?,?,?,
    ?,?,
    ?,?,
    ?,?,
    ?,?,?,?,
    ?,?,?,
    NOW(), NOW(), NOW()
)";

$valores = [
    $protocolo, $imobiliaria_id, $usuario_id, 'Aguardando análise', $atendente_id ?: null,
    $tipo_veiculo, $marca_id ?: null, $modelo_id ?: null, $ano_id ?: null, $marca_nome, $modelo_nome, $ano_nome,
    $placa, $chassi ?: null, $combustivel, $kit_gas,
    $utilizado_trabalho, $utilizado_ir_trabalho, $km_distancia_trabalho ?: null,
    $seguro_novo, $renovacao_cia,
    $segurado_nome, $segurado_cpf, $segurado_data_nasc ?: null, $segurado_estado_civil,
    $segurado_cep, $segurado_endereco, $segurado_numero, $segurado_complemento ?: null,
    $segurado_bairro, $segurado_cidade, $segurado_uf,
    $garagem_residencia, $portao_residencia,
    $garagem_trabalho, $portao_trabalho,
    $garagem_faculdade, $portao_faculdade,
    $condutor_nome ?: null, $condutor_cpf ?: null, $condutor_data_nasc ?: null, $condutor_estado_civil ?: null,
    $condutor_relacao_segurado ?: null, $condutor_menor_25 ?: null, $condutor_menor_25_sexo ?: null,
];
// Tudo ligado como string — mysqli/MySQL convertem certinho pras colunas
// numéricas, e evita ter que manter uma string de tipos "iissi..." em sincronia
// manualmente com as ~45 colunas acima (era exatamente esse descompasso que
// fazia todo envio de Seguro Auto quebrar com ArgumentCountError).
$ok = db_execute($sql, [str_repeat('s', count($valores)), ...$valores]);

if (!$ok) {
    json_response(['success' => false, 'message' => 'Erro ao salvar cotação. Tente novamente.'], 500);
}

historico_registrar(
    (int) $ok, $protocolo, 'CRIACAO', null, 'Aguardando análise',
    $usuario_id, 'imobiliaria', $user->nome ?? 'Imobiliária', 'Cotação de seguro auto criada.'
);

try {
    notificar_todos_admins(
        'nova_cotacao',
        'Nova cotação recebida',
        "Protocolo {$protocolo} — Seguro Auto aguardando análise.",
        (int) $ok,
        cotacao_notif_url('auto', $protocolo, 'admin')
    );
} catch (Throwable $e) {
    error_log('[cotacao-auto] Erro ao criar notificações: ' . $e->getMessage());
}

require_once BASE_PATH . '/src/helpers/zapi.php';
zapi_notificar_atendente(
    $atendente_id ?: null,
    "🆕 *Nova cotação — Seguro Auto*\n\n"
    . "👤 Segurado: *{$segurado_nome}*\n"
    . "🏢 " . zapi_imobiliaria_nome($imobiliaria_id) . "\n\n"
    . "📄 Protocolo: `{$protocolo}`",
    'nova_cotacao',
    $protocolo
);

// E-mail para os usuários MPS que recebem avisos de Auto.
notificar_cotacao_email('auto', 'Seguro Auto', $protocolo, (int) $imobiliaria_id);

// Rascunho finalizado — remove para não aparecer mais na listagem
$rascunho_protocolo = auto_str($body, 'rascunho', 30);
if ($rascunho_protocolo !== '') {
    try {
        db_execute(
            'DELETE FROM seguro_auto_rascunho WHERE protocolo = ? AND imobiliaria_id = ?',
            ['si', $rascunho_protocolo, $imobiliaria_id]
        );
    } catch (Throwable $e) {
        error_log('[cotacao-auto] Erro ao excluir rascunho: ' . $e->getMessage());
    }
}

submit_token_concluir($submit_token, $protocolo);

json_response([
    'success'       => true,
    'protocolo'     => $protocolo,
    'aviso_horario' => horario_atendimento_verificar('auto'),
]);
