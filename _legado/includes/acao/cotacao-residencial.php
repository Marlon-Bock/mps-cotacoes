<?php
/**
 * POST /api/cotacao/residencial
 * Nova cotação de Seguro Residencial (formulário pages/cotacao/residencial).
 *
 * Mesma lógica da Fiança: com alguma seguradora residencial ligada (ver
 * api_seguradoras_da_ficha), a cotação vai pra API ('Cotação API', origem
 * 'api') — as seguradoras cotam em segundo plano e a MPS só é avisada
 * quando a análise termina ("Fazer cotação"). Sem nenhuma ligada, vai
 * direto pra fila da MPS ('Aguardando análise', fluxo manual).
 */

$user = require_auth();
csrf_verify();

if (($user->role ?? '') !== 'imobiliaria') {
    json_response(['success' => false, 'message' => 'Acesso não permitido.'], 403);
}
if (empty($user->imob_admin) && (($user->perm_cotar ?? 'N') !== 'S' || ($user->perm_seguro_residencial ?? 'N') !== 'S')) {
    json_response(['success' => false, 'message' => 'Sem permissão para cotar Seguro Residencial.'], 403);
}

$imobiliaria_id = (int) ($user->imobiliaria_id ?? 0);
$usuario_id     = (int) ($user->uid ?? 0);
if (!$imobiliaria_id) {
    json_response(['success' => false, 'message' => 'Imobiliária não identificada.'], 400);
}

$submit_token = submit_token_ler();
if ($dup = submit_token_duplicado($submit_token)) {
    json_response(['success' => true, 'protocolo' => $dup, 'duplicado' => true]);
}

$str    = static fn(string $k, int $max = 255): string => mb_substr(trim((string) ($_POST[$k] ?? '')), 0, $max);
$bool   = static fn(string $k): int => !empty($_POST[$k]) && $_POST[$k] !== '0' ? 1 : 0;
$moeda  = static function (string $k): ?float {
    $v = preg_replace('/[^\d,]/', '', (string) ($_POST[$k] ?? ''));
    return $v !== '' ? round((float) str_replace(',', '.', $v), 2) : null;
};
$opcao  = static fn(string $k, array $validas, string $padrao = ''): string => array_key_exists((string) ($_POST[$k] ?? ''), $validas) ? (string) $_POST[$k] : $padrao;
$digitos = static fn(string $k): string => preg_replace('/\D/', '', (string) ($_POST[$k] ?? ''));

// ── Segurado ───────────────────────────────────────────────────────────────
$dados = [
    'nome_completo'                => $str('nome_completo', 150),
    'cpf_cnpj'                     => $digitos('cpf_cnpj'),
    'data_nascimento'              => (string) (date_from_br($str('data_nascimento')) ?? ''),
    'sexo'                         => $opcao('sexo', ['masculino' => 1, 'feminino' => 1]),
    'email'                        => $str('email', 150),
    'tel_celular'                  => $digitos('tel_celular'),
    'pessoa_politicamente_exposta' => $bool('pessoa_politicamente_exposta'),
    'segurado_proprietario'        => $bool('segurado_proprietario'),
    'endereco_segurado_igual'      => $bool('endereco_segurado_igual'),
    // Imóvel
    'cep'             => $digitos('cep'),
    'endereco'        => $str('endereco'),
    'numero'          => $str('numero', 20),
    'complemento'     => $str('complemento', 100),
    'bairro'          => $str('bairro', 100),
    'cidade'          => $str('cidade', 100),
    'uf'              => strtoupper($str('uf', 2)),
    'tipo_imovel'     => $opcao('tipo_imovel', RESIDENCIAL_TIPOS_IMOVEL),
    'tipo_construcao' => $opcao('tipo_construcao', RESIDENCIAL_CONSTRUCAO, 'alvenaria'),
    'uso_imovel'      => $opcao('uso_imovel', RESIDENCIAL_USO, 'habitual'),
    // Vigência
    'vigencia_inicio' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $str('vigencia_inicio')) ? $str('vigencia_inicio') : null,
    'vigencia_meses'  => 12,
    'observacoes'     => $str('observacoes', 2000) ?: null,
];
// Endereço do segurado: só quando ele não mora no imóvel segurado.
foreach (['cep', 'endereco', 'numero', 'complemento', 'bairro', 'cidade', 'uf'] as $c) {
    $v = $c === 'cep' ? $digitos('seg_cep') : ($c === 'uf' ? strtoupper($str('seg_uf', 2)) : $str('seg_' . $c));
    $dados['seg_' . $c] = $dados['endereco_segurado_igual'] ? null : ($v !== '' ? $v : null);
}
foreach (array_keys(RESIDENCIAL_COBERTURAS) as $c) {
    $v = $moeda($c);
    $dados[$c] = $v !== null && $v > 0 ? $v : null;
}

// ── Validação ──────────────────────────────────────────────────────────────
$erros = [];
if (!str_contains($dados['nome_completo'], ' ')) $erros[] = 'Informe o nome completo do segurado (nome e sobrenome).';
if (!validar_cpf($dados['cpf_cnpj']))            $erros[] = 'CPF do segurado inválido.';
if ($dados['data_nascimento'] === '')            $erros[] = 'Informe a data de nascimento.';
if ($dados['sexo'] === '')                       $erros[] = 'Selecione o sexo do segurado.';
if (!filter_var($dados['email'], FILTER_VALIDATE_EMAIL)) $erros[] = 'E-mail inválido.';
if (strlen($dados['tel_celular']) !== 11)        $erros[] = 'Informe o celular com DDD.';
if (strlen($dados['cep']) !== 8)                 $erros[] = 'CEP do imóvel inválido.';
foreach (['endereco' => 'o endereço', 'numero' => 'o número', 'bairro' => 'o bairro', 'cidade' => 'a cidade'] as $c => $r) {
    if ($dados[$c] === '') $erros[] = "Informe {$r} do imóvel.";
}
if (!preg_match('/^[A-Z]{2}$/', $dados['uf']))   $erros[] = 'UF do imóvel inválida.';
if ($dados['tipo_imovel'] === '')                $erros[] = 'Selecione o tipo do imóvel.';
if (!$dados['endereco_segurado_igual'] && (strlen((string) $dados['seg_cep']) !== 8 || !$dados['seg_endereco'] || !$dados['seg_cidade'] || !preg_match('/^[A-Z]{2}$/', (string) $dados['seg_uf']))) {
    $erros[] = 'Complete o endereço do segurado.';
}
if (($dados['cob_incendio'] ?? 0) <= 0)          $erros[] = 'Informe o valor da cobertura de incêndio.';
if ($erros) {
    json_response(['success' => false, 'message' => implode(' ', $erros)], 422);
}

// ── API das seguradoras ou fila da MPS ─────────────────────────────────────
require_once BASE_PATH . '/src/helpers/api.php';
$cotar_na_api = api_seguradoras_da_ficha($dados + ['produto' => 'residencial']) !== [];
$status       = $cotar_na_api ? API_STATUS_FICHA : 'Aguardando análise';

$protocolo    = gerar_protocolo('RES', 'seguro_residencial');
$atendente_id = funil_atribuir_atendente('residencial');

$cols = $dados + [
    'protocolo'              => $protocolo,
    'imobiliaria_id'         => $imobiliaria_id,
    'usuario_id'             => $usuario_id,
    'atendente_id'           => $atendente_id ?: null,
    'status'                 => $status,
    'origem'                 => $cotar_na_api ? 'api' : 'manual',
    'data_status_aguardando' => $cotar_na_api ? null : date('Y-m-d H:i:s'),
    'data_atualizacao'       => date('Y-m-d H:i:s'),
];
// Tudo como string (o MySQL converte) — mesma prática de cotacao-condominial.php.
$valores = array_map(fn($v) => $v === null ? null : (string) $v, array_values($cols));
$fichaId = db_execute(
    'INSERT INTO seguro_residencial (' . implode(', ', array_keys($cols)) . ') VALUES (' . implode(', ', array_fill(0, count($cols), '?')) . ')',
    [str_repeat('s', count($valores)), ...$valores]
);
if (!$fichaId) {
    json_response(['success' => false, 'message' => 'Erro ao salvar a cotação. Tente novamente.'], 500);
}

historico_registrar(
    (int) $fichaId, $protocolo, 'CRIACAO', null, $cotar_na_api ? 'Em análise' : 'Aguardando análise',
    $usuario_id, 'imobiliaria', $user->nome ?? 'Imobiliária', 'Cotação de Seguro Residencial criada.'
);

if ($cotar_na_api) {
    db_execute('UPDATE seguro_residencial SET api_enviado_em = NOW() WHERE id = ?', ['i', (int) $fichaId]);
    api_enfileirar_cotacoes((int) $fichaId);
}

submit_token_concluir($submit_token, $protocolo);

// Responde antes dos avisos (e-mail/WhatsApp são chamadas de rede síncronas).
ignore_user_abort(true);
http_response_code(200);
header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'success'       => true,
    'protocolo'     => $protocolo,
    'url'           => '/cotacao/residencial/' . rawurlencode($protocolo),
    'aviso_horario' => $cotar_na_api ? null : horario_atendimento_verificar('residencial'),
]);
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
} else {
    flush();
}

// Na API, a MPS só é avisada quando a análise termina (api_apos_resultado).
if ($cotar_na_api) {
    exit;
}

try {
    notificar_todos_admins(
        'nova_cotacao',
        'Nova cotação recebida',
        "Protocolo {$protocolo} — Seguro Residencial aguardando análise.",
        (int) $fichaId,
        cotacao_notif_url('residencial', $protocolo, 'admin')
    );
    notificar_cotacao_email('residencial', 'Seguro Residencial', $protocolo, $imobiliaria_id);
} catch (Throwable $e) {
    error_log('[cotacao-residencial] Erro ao criar notificações: ' . $e->getMessage());
}

require_once BASE_PATH . '/src/helpers/zapi.php';
zapi_notificar_atendente(
    $atendente_id ?: null,
    "🆕 *Nova cotação — Seguro Residencial*\n\n"
    . "👤 Segurado: *{$dados['nome_completo']}*\n"
    . "🏢 Imobiliária: " . zapi_imobiliaria_nome($imobiliaria_id) . "\n\n"
    . "📄 Protocolo: `{$protocolo}`",
    'nova_cotacao',
    $protocolo
);
