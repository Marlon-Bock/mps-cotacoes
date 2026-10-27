<?php
/**
 * POST /api/cotacao/{protocolo}/recotar
 * Cria uma NOVA cotação de Fiança a partir de uma cotação recusada,
 * reaproveitando os dados do imóvel/contrato/coberturas e trocando só os
 * dados do pretendente (nome, CPF, telefone, e-mail) — usado quando o
 * inquilino anterior foi recusado e a imobiliária quer tentar com outra
 * pessoa, sem reconstruir a ficha inteira do zero.
 *
 * Residencial e Comercial: no Comercial o "pretendente" é a empresa
 * (nome_completo = razão social, cpf_cnpj = CNPJ) — os mesmos 2 campos,
 * só que aceitando CNPJ. Campos de perfil da empresa (CNAE, franquia,
 * opção tributária, sócios, CNPJ do Segimob) não são copiados nem pedidos
 * aqui, igual não copiamos dados pessoais (profissão, salário etc.) no
 * Residencial — ficam em branco pra MPS completar na análise.
 *
 * dev-cotacoes: a nova cotação segue o mesmo caminho de uma ficha nova — vai
 * pra API das seguradoras ('Cotação API', fora da fila da MPS; a cotação em
 * si roda no worker cron/api-fila.php) em vez de direto pra MPS.
 */
$user = require_auth();
header('Content-Type: application/json; charset=utf-8');
csrf_verify();

if (($user->role ?? '') !== 'imobiliaria') {
    json_response(['success' => false, 'message' => 'Acesso não permitido.'], 403);
}
if (empty($user->imob_admin) && (($user->perm_cotar ?? 'N') !== 'S' || ($user->perm_fianca ?? 'N') !== 'S')) {
    json_response(['success' => false, 'message' => 'Sem permissão para cotar Seguro Fiança.'], 403);
}

$protocolo = $params['protocolo'] ?? '';
if (!$protocolo) json_response(['success' => false, 'message' => 'Protocolo inválido.'], 400);

$imobiliaria_id = (int) ($user->imobiliaria_id ?? 0);
$usuario_id     = (int) ($user->uid ?? 0);

$cotacao = db_fetch_one('SELECT * FROM seguro_fianca WHERE protocolo = ? AND imobiliaria_id = ?', ['si', $protocolo, $imobiliaria_id]);
if (!$cotacao) {
    json_response(['success' => false, 'message' => 'Cotação não encontrada.'], 404);
}
if (empty($user->imob_admin) && (int) ($cotacao['usuario_id'] ?? 0) !== $usuario_id) {
    json_response(['success' => false, 'message' => 'Cotação não encontrada.'], 404);
}
if (!cotacao_status_recusado($cotacao['status'])) {
    json_response(['success' => false, 'message' => 'Só é possível cotar com outro pretendente em uma cotação recusada.'], 422);
}

$tipo_ficha = ($cotacao['tipo_ficha'] ?? 'residencial') === 'comercial' ? 'comercial' : 'residencial';

$str   = fn(string $k) => sanitize((string) ($_POST[$k] ?? ''));
$nome_completo = $str('nome_completo');
$cpf_cnpj      = preg_replace('/\D/', '', $str('cpf_cnpj'));
$tel_celular   = $str('tel_celular');
$email         = filter_var($str('email'), FILTER_SANITIZE_EMAIL);
// Dados que as seguradoras analisam do novo pretendente (mesmos do formulário).
$data_nascimento      = date_from_br($str('data_nascimento'));
$salario_bruto        = (float) str_replace(',', '.', $str('salario_bruto'));
$vinculo_empregaticio = $str('vinculo_empregaticio');
$profissao            = mb_substr($str('profissao'), 0, 40);
require_once BASE_PATH . '/src/helpers/api.php';
$profissao_obrigatoria = $tipo_ficha === 'residencial' && in_array('too', array_keys(api_seguradoras()), true);

$errors = [];
if ($nome_completo === '') $errors[] = 'Informe o nome/razão social do novo pretendente.';
if ($cpf_cnpj === '') {
    $errors[] = 'Informe o CPF/CNPJ do novo pretendente.';
} else {
    $len = strlen($cpf_cnpj);
    if ($len === 11 && !validar_cpf($cpf_cnpj)) {
        $errors[] = 'CPF inválido.';
    } elseif ($len === 14 && !validar_cnpj($cpf_cnpj)) {
        $errors[] = 'CNPJ inválido.';
    } elseif (!in_array($len, [11, 14], true)) {
        $errors[] = 'CPF deve ter 11 dígitos ou CNPJ 14 dígitos.';
    }
}
if ($tel_celular === '')   $errors[] = 'Informe o telefone do novo pretendente.';
if (!$data_nascimento || $data_nascimento > date('Y-m-d')) $errors[] = 'Informe a data de nascimento do novo pretendente (DD/MM/AAAA).';
if ($salario_bruto <= 0)   $errors[] = 'Informe a renda mensal do novo pretendente.';
if (!in_array($vinculo_empregaticio, fianca_vinculos_empregaticios(), true)) $errors[] = 'Selecione o vínculo empregatício do novo pretendente.';
if ($profissao_obrigatoria && $profissao === '') $errors[] = 'Informe a profissão do novo pretendente.';
if ($email === '')         $errors[] = 'Informe o e-mail do novo pretendente.';
elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'E-mail inválido.';
if ($cpf_cnpj !== '' && $cpf_cnpj === preg_replace('/\D/', '', (string) $cotacao['cpf_cnpj'])) {
    $errors[] = 'O novo pretendente precisa ter um CPF/CNPJ diferente do pretendente recusado.';
}
require_once BASE_PATH . '/src/helpers/api.php';
if (!$errors && api_seguradoras_da_ficha(['cpf_cnpj' => $cpf_cnpj, 'tipo_ficha' => $tipo_ficha]) === []) {
    $errors[] = strlen($cpf_cnpj) === 14
        ? 'A cotação automática ainda não atende pretendente CNPJ. Envie uma nova cotação pelo formulário — a MPS analisa.'
        : 'Nenhuma seguradora atende esse pretendente. Envie uma nova cotação pelo formulário.';
}
if ($errors) {
    json_response(['success' => false, 'message' => implode(' ', $errors)], 422);
}

$protocolo_novo = gerar_protocolo('MPS', 'seguro_fianca');
$atendente_id   = funil_atribuir_atendente('fianca');

$cols = [
    'tipo_ficha'            => ['s', $tipo_ficha],
    'tipo_plano'            => ['s', $cotacao['tipo_plano']],
    'cpf_cnpj'              => ['s', $cpf_cnpj],
    'nome_completo'         => ['s', $nome_completo],
    'tel_celular'           => ['s', $tel_celular],
    'email'                 => ['s', $email],
    'data_nascimento'       => ['s', $data_nascimento],
    'salario_bruto'         => ['d', $salario_bruto],
    'vinculo_empregaticio'  => ['s', $vinculo_empregaticio],
    'profissao'             => ['s', $profissao !== '' ? $profissao : null],
    'vigencia_contrato'     => ['s', $cotacao['vigencia_contrato']],
    'codigo_imovel'         => ['s', $cotacao['codigo_imovel']],
    'cep'                   => ['s', $cotacao['cep']],
    'endereco'              => ['s', $cotacao['endereco']],
    'numero'                => ['s', $cotacao['numero']],
    'complemento'           => ['s', $cotacao['complemento']],
    'ramo_atividade'        => ['s', $tipo_ficha === 'comercial' ? ($cotacao['ramo_atividade'] ?? null) : null],
    'bairro'                => ['s', $cotacao['bairro']],
    'cidade'                => ['s', $cotacao['cidade']],
    'uf'                    => ['s', $cotacao['uf']],
    'indice_reajuste'       => ['s', $cotacao['indice_reajuste']],
    'proprietario_imovel'   => ['s', $cotacao['proprietario_imovel']],
    'cpf_cnpj_proprietario' => ['s', $cotacao['cpf_cnpj_proprietario']],
    'valor_aluguel'         => ['d', $cotacao['valor_aluguel']],
    'valor_agua'            => ['d', $cotacao['valor_agua']],
    'valor_condominio'      => ['d', $cotacao['valor_condominio']],
    'valor_gas'             => ['d', $cotacao['valor_gas']],
    'valor_iptu'            => ['d', $cotacao['valor_iptu']],
    'valor_energia'         => ['d', $cotacao['valor_energia']],
    'danos_imovel'          => ['i', (int) $cotacao['danos_imovel']],
    'pintura_externa'       => ['i', (int) $cotacao['pintura_externa']],
    'multa_contratual'      => ['i', (int) $cotacao['multa_contratual']],
    'pintura_interna'       => ['i', (int) $cotacao['pintura_interna']],
    'cobertura_moveis'      => ['i', (int) $cotacao['cobertura_moveis']],
    'protocolo'             => ['s', $protocolo_novo],
    // Pela API das seguradoras: fora da fila da MPS (igual ficha nova).
    'status'                => ['s', 'Cotação API'],
    'origem'                => ['s', 'api'],
    'imobiliaria_id'        => ['i', $imobiliaria_id],
    'usuario_id'            => ['i', $usuario_id],
    'atendente_id'          => ['i', $atendente_id ?: null],
    'formulario_completo'   => ['i', 1],
    'liberado'              => ['i', 1],
    'data_status_aguardando'=> ['s', null],
];

$col_names    = implode(', ', array_keys($cols));
$placeholders = implode(', ', array_fill(0, count($cols), '?'));
$types        = implode('', array_column($cols, 0));
$values       = array_column($cols, 1);

try {
    $novo_id = db_execute(
        "INSERT INTO seguro_fianca ({$col_names}, data_cadastro, data_atualizacao) VALUES ({$placeholders}, NOW(), NOW())",
        [$types, ...$values]
    );
    if (!$novo_id) throw new RuntimeException('INSERT não retornou ID.');
} catch (Throwable $e) {
    error_log('[cotacao-fianca-recotar] Erro ao inserir: ' . $e->getMessage());
    json_response(['success' => false, 'message' => 'Erro ao criar a nova cotação. Tente novamente.'], 500);
}

historico_registrar(
    (int) $novo_id, $protocolo_novo, 'CRIACAO', null, 'Cotação API',
    $usuario_id, 'imobiliaria', $user->nome ?? 'Imobiliária',
    "Cotação criada a partir da {$protocolo} (recusada), com outro pretendente."
);
historico_registrar(
    (int) $cotacao['id'], $protocolo, 'ALTERACAO_STATUS', $cotacao['status'], $cotacao['status'],
    $usuario_id, 'imobiliaria', $user->nome ?? 'Imobiliária',
    "Nova cotação aberta para o mesmo imóvel com outro pretendente: {$protocolo_novo}."
);

$produto_label = 'Fiança ' . ($tipo_ficha === 'comercial' ? 'Comercial' : 'Residencial');

// Enfileira a cotação nas seguradoras (o worker cota em segundo plano) e vai
// pro detalhe, que acompanha a pré-análise — sem avisar a MPS (ela é avisada
// quando a análise termina: "Fazer cotação").
require_once BASE_PATH . '/src/helpers/api.php';
db_execute('UPDATE seguro_fianca SET api_enviado_em = NOW() WHERE id = ?', ['i', (int) $novo_id]);
api_enfileirar_cotacoes((int) $novo_id);
api_historico(
    (int) $novo_id, 'ENVIO_COTACAO', 'Enviada para análise', null,
    ['id' => $usuario_id, 'tipo' => 'imobiliaria', 'nome' => $user->nome ?? 'Imobiliária']
);
json_response([
    'success'    => true,
    'protocolo'  => $protocolo_novo,
    'em_analise' => true,
    'message'    => 'Nova cotação enviada para análise!',
]);

try {
    notificar_todos_admins(
        'nova_cotacao',
        'Nova cotação recebida',
        "Protocolo {$protocolo_novo} — {$produto_label} aguardando análise (novo pretendente após recusa de {$protocolo}).",
        (int) $novo_id,
        cotacao_notif_url('fianca', $protocolo_novo, 'admin')
    );
    notificar_cotacao_email('fianca', "Seguro {$produto_label}", $protocolo_novo, $imobiliaria_id);
} catch (Throwable $e) {
    error_log('[cotacao-fianca-recotar] Erro ao criar notificações: ' . $e->getMessage());
}

require_once BASE_PATH . '/src/helpers/zapi.php';
zapi_notificar_atendente(
    $atendente_id ?: null,
    "🆕 *Nova cotação — {$produto_label}*\n\n"
    . "👤 Pretendente: *{$nome_completo}*\n"
    . "🏢 " . zapi_imobiliaria_nome($imobiliaria_id) . "\n\n"
    . "📄 Protocolo: `{$protocolo_novo}`\n"
    . "↩️ Novo pretendente após recusa de {$protocolo}.",
    'nova_cotacao',
    $protocolo_novo
);

json_response([
    'success'   => true,
    'protocolo' => $protocolo_novo,
    'message'   => 'Nova cotação criada com sucesso!',
]);
