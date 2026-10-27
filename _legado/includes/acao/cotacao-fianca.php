<?php
/**
 * POST /api/cotacao/fianca
 * Recebe FormData do formulário de Seguro Fiança (residencial ou comercial).
 * Insere em seguro_fianca, salva pretendentes extras e cria notificações.
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
    if (empty($user->imob_admin) && (($user->perm_cotar ?? 'N') !== 'S' || ($user->perm_fianca ?? 'N') !== 'S')) {
        json_response(['success' => false, 'message' => 'Sem permissão para cotar Seguro Fiança.'], 403);
    }
}

$imobiliaria_id = (int) ($user->imobiliaria_id ?? 0);
$usuario_id     = (int) ($user->uid ?? 0);

if (!$imobiliaria_id) {
    json_response(['success' => false, 'message' => 'Imobiliária não identificada.'], 400);
}

// Idempotência: reenvio do mesmo formulário devolve o protocolo já criado.
$submit_token = submit_token_ler();
if ($dup = submit_token_duplicado($submit_token)) {
    json_response(['success' => true, 'protocolo' => $dup, 'duplicado' => true]);
}

// ── Helpers de leitura de POST ────────────────────────────────────────────
$str  = fn(string $k) => sanitize((string) ($_POST[$k] ?? ''));
$int  = fn(string $k) => (int) ($_POST[$k] ?? 0);
$bool = fn(string $k) => isset($_POST[$k]) && $_POST[$k] !== '' && $_POST[$k] !== '0' ? 1 : 0;

$envio_parcial = $int('envio_parcial') === 1;
$tipo_ficha    = $str('tipo_ficha') === 'comercial' ? 'comercial' : 'residencial';
// Ficha completa (não a pré-análise) é cotada na API das seguradoras — ver
// src/helpers/api.php. A pré-análise (botão hoje desativado no formulário)
// segue o fluxo manual da MPS.
$cotar_na_api = !$envio_parcial;

// ── Campos obrigatórios ────────────────────────────────────────────────────
$cpf_cnpj             = preg_replace('/\D/', '', $str('cpf_cnpj'));
// Nenhuma seguradora ligada cota esse pretendente (ex: CNPJ com a Pottencial
// PJ desligada): a ficha vai direto pra fila da MPS, sem passar pela API.
if ($cotar_na_api) {
    require_once BASE_PATH . '/src/helpers/api.php';
    $cotar_na_api = api_seguradoras_da_ficha(['cpf_cnpj' => $cpf_cnpj, 'tipo_ficha' => $tipo_ficha]) !== [];
}
$nome_completo        = $str('nome_completo');
$vigencia_contrato    = $str('vigencia_contrato') ?: null;
$cep_imovel           = preg_replace('/\D/', '', $str('cep'));
$endereco             = $str('endereco');
$numero               = $str('numero');
$complemento          = $str('complemento');
$bairro               = $str('bairro');
$cidade               = $str('cidade');
$uf                   = strtoupper(substr($str('uf'), 0, 2));

$to_decimal = function (string $k) use ($str): ?float {
    $v = $str($k);
    return $v !== '' ? (float) $v : null;
};

$valor_aluguel    = $to_decimal('valor_aluguel');
$valor_agua       = $to_decimal('valor_agua');
$valor_condominio = $to_decimal('valor_condominio');
$valor_gas        = $to_decimal('valor_gas');
$valor_iptu       = $to_decimal('valor_iptu');
$valor_energia    = $to_decimal('valor_energia');

$errors = [];
if (empty($cpf_cnpj))          $errors[] = 'CPF/CNPJ é obrigatório.';
if (empty($nome_completo))     $errors[] = 'Nome ou razão social é obrigatório.';
if (empty($vigencia_contrato)) $errors[] = 'Vigência do contrato é obrigatória.';
if (empty($cep_imovel))        $errors[] = 'CEP do imóvel é obrigatório.';
if (empty($endereco))          $errors[] = 'Endereço é obrigatório.';
if (empty($numero))            $errors[] = 'Número é obrigatório.';
if (empty($bairro))            $errors[] = 'Bairro é obrigatório.';
if (empty($cidade))            $errors[] = 'Cidade é obrigatória.';
if (empty($uf))                $errors[] = 'UF é obrigatória.';

// As seguradoras exigem e-mail do titular pra cotar.
if ($cotar_na_api && empty($str('email'))) {
    $errors[] = 'E-mail é obrigatório.';
}

if ($valor_aluguel === null) $errors[] = 'Informe o valor do Aluguel.';

// Cada valor de locação precisa vir preenchido ou explicitamente marcado
// como "não informar" (campo "_skip" = 1) — não pode ficar em branco sem
// uma escolha explícita do usuário.
$valores_locacao = [
    'valor_agua'       => ['Água',       $valor_agua],
    'valor_condominio' => ['Condomínio', $valor_condominio],
    'valor_gas'        => ['Gás',        $valor_gas],
    'valor_iptu'       => ['IPTU',       $valor_iptu],
    'valor_energia'    => ['Luz',        $valor_energia],
];
foreach ($valores_locacao as $campo => [$rotulo, $valor]) {
    if ($valor === null && $bool("{$campo}_skip") !== 1) {
        $errors[] = "Informe o valor de {$rotulo} ou marque \"Não informar\".";
    }
}

// Regra da Pottencial: a soma dos encargos não pode passar de 2x o aluguel —
// a API recusa com HTTP 400. Barra aqui, no formulário, em vez de gastar a
// chamada e devolver o erro só na tela de seguradoras.
if ($cotar_na_api && $valor_aluguel !== null) {
    $soma_encargos = array_sum(array_map(fn($v) => (float) ($v[1] ?? 0), $valores_locacao));
    if ($soma_encargos > 2 * $valor_aluguel) {
        $errors[] = 'A soma dos encargos (água, condomínio, gás, IPTU e luz) não pode passar de 2 vezes o valor do aluguel.';
    }
}

// Valida CPF / CNPJ
if (!empty($cpf_cnpj)) {
    $len = strlen($cpf_cnpj);
    if ($len === 11 && !validar_cpf($cpf_cnpj)) {
        $errors[] = 'CPF inválido.';
    } elseif ($len === 14 && !validar_cnpj($cpf_cnpj)) {
        $errors[] = 'CNPJ inválido.';
    } elseif (!in_array($len, [11, 14], true)) {
        $errors[] = 'CPF deve ter 11 dígitos ou CNPJ 14 dígitos.';
    }
}

// ── Pretendentes adicionais (quantidade livre, máximo 7) ──────────────────
$pretendentes_extras = [];
$pretendentes_post   = $_POST['pretendentes_extra'] ?? [];
if (is_array($pretendentes_post)) {
    if (count($pretendentes_post) > 7) {
        $errors[] = 'Máximo de 7 pretendentes adicionais.';
    }

    $cpfs_vistos = [];
    if (!empty($cpf_cnpj)) $cpfs_vistos[$cpf_cnpj] = true;

    $n = 2;
    foreach ($pretendentes_post as $p) {
        if (!is_array($p)) continue;
        $pnome = sanitize((string) ($p['nome_completo'] ?? ''));
        $pcpf  = preg_replace('/\D/', '', sanitize((string) ($p['cpf_cnpj'] ?? '')));
        $numero_atual = $n;

        if ($pnome === '' && $pcpf === '') continue;
        if ($pnome === '' || $pcpf === '') {
            $errors[] = "Informe nome completo e CPF/CNPJ do {$numero_atual}º pretendente adicional.";
        }

        if ($pcpf !== '') {
            if (isset($cpfs_vistos[$pcpf])) {
                $errors[] = "CPF/CNPJ do {$numero_atual}º pretendente adicional já foi informado para outro pretendente nesta cotação.";
            }
            $cpfs_vistos[$pcpf] = true;
        }

        $pretendentes_extras[] = [
            'numero_pretendente'  => $n++,
            'nome_completo'       => $pnome,
            'cpf_cnpj'            => $pcpf,
            'tel_celular'         => sanitize((string) ($p['tel_celular'] ?? '')),
            'data_nascimento'     => date_from_br(sanitize((string) ($p['data_nascimento'] ?? ''))),
            'email'               => sanitize((string) ($p['email'] ?? '')),
            'vinculo_empregaticio'=> sanitize((string) ($p['vinculo_empregaticio'] ?? '')),
            'salario_bruto'       => (string) ($p['salario_bruto'] ?? '') !== '' ? (float) $p['salario_bruto'] : null,
        ];
    }
}

if ($errors) {
    json_response(['success' => false, 'message' => implode(' ', $errors)], 422);
}

// ── Campos opcionais ────────────────────────────────────────────────────────
$tel_celular          = $str('tel_celular');
$data_nascimento_br   = $str('data_nascimento');
$email                = filter_var($str('email'), FILTER_SANITIZE_EMAIL);
$vinculo_empregaticio = $str('vinculo_empregaticio');

$data_nascimento = date_from_br($data_nascimento_br);
if ($data_nascimento_br && !$data_nascimento) {
    json_response(['success' => false, 'message' => 'Data de nascimento inválida (use DD/MM/AAAA).'], 422);
}
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_response(['success' => false, 'message' => 'E-mail inválido.'], 422);
}

$salario_raw    = $str('salario_bruto');
$salario_bruto  = $salario_raw !== '' ? (float) $salario_raw : null;
$estado_civil   = $str('estado_civil');
$num_dep_raw    = $_POST['num_dependentes'] ?? '';
$num_dependentes = $num_dep_raw !== '' ? (int) $num_dep_raw : null;
$reside_imovel   = $bool('reside_imovel');
$tempo_residencia = $str('tempo_residencia');
$tel_residencial  = $str('tel_residencial');
$cep_atual        = preg_replace('/\D/', '', $str('cep_atual'));

// Campos específicos por tipo
$sexo             = $tipo_ficha === 'residencial' ? $str('sexo')             : null;
$nacionalidade    = $tipo_ficha === 'residencial' ? $str('nacionalidade')    : null;
$franquia         = $tipo_ficha === 'comercial'   ? $str('franquia')         : null;
$cnae             = $tipo_ficha === 'comercial'   ? $str('cnae')             : null;
$opcao_tributaria = $tipo_ficha === 'comercial'   ? $str('opcao_tributaria') : null;
$ramo_atividade   = $tipo_ficha === 'comercial'   ? $str('ramo_atividade')   : null;
$cnpj_empresa     = $tipo_ficha === 'comercial'   ? preg_replace('/\D/', '', $str('cnpj_empresa')) : null;

$profissao              = mb_substr($str('profissao'), 0, 40);

// Too Seguros ligada + ficha residencial de CPF: ela exige nascimento, renda,
// vínculo e profissão do pretendente (e nascimento dos adicionais) — mesmos
// campos que o formulário mostra nesse caso (ver _form.php, $campos_too).
if (!$envio_parcial && $tipo_ficha === 'residencial' && strlen($cpf_cnpj) === 11) {
    require_once BASE_PATH . '/src/helpers/api.php';
    if (in_array('too', array_keys(api_seguradoras()), true)) {
        $faltando = array_keys(array_filter([
            'data de nascimento'   => !$data_nascimento,
            'renda mensal'         => (float) $salario_bruto <= 0,
            'vínculo empregatício' => $vinculo_empregaticio === '',
            'profissão'            => $profissao === '',
        ]));
        if ($faltando) {
            json_response(['success' => false, 'message' => 'Informe ' . implode(', ', $faltando) . ' do pretendente.'], 422);
        }
        foreach ($pretendentes_extras as $i => $p) {
            if (empty($p['data_nascimento'])) {
                json_response(['success' => false, 'message' => 'Informe a data de nascimento do ' . ($i + 1) . 'º pretendente adicional.'], 422);
            }
        }
    }
}

$indice_reajuste        = $str('indice_reajuste');
$proprietario_imovel    = $str('proprietario_imovel');
$cpf_cnpj_proprietario  = preg_replace('/\D/', '', $str('cpf_cnpj_proprietario'));

// ── Coberturas ────────────────────────────────────────────────────────────
$tipo_plano       = 'Completo';
$danos_imovel     = $bool('danos_imovel');
$pintura_externa  = $bool('pintura_externa');
$multa_contratual = $bool('multa_contratual');
$pintura_interna  = $bool('pintura_interna');
$cobertura_moveis = $bool('cobertura_moveis');

// ── Detecta se é envio completo de uma pré-análise existente ─────────────
$cotacao_protocolo_existente = trim($_POST['cotacao'] ?? '');
$is_completando_pre          = !$envio_parcial && $cotacao_protocolo_existente !== '';

$pretendente_adicional = !empty($pretendentes_extras) ? 'S' : 'N';

if ($is_completando_pre) {
    // ── UPDATE: completa cotação com pré-análise existente ────────────────
    $cotacao_pre = db_fetch_one(
        "SELECT id, protocolo, status, imobiliaria_id FROM seguro_fianca WHERE protocolo = ? AND imobiliaria_id = ?",
        ['si', $cotacao_protocolo_existente, $imobiliaria_id]
    );
    if (!$cotacao_pre || !in_array($cotacao_pre['status'], ['Pré-análise aceita', 'Pre-analise aceita', 'Pré-análise recusada', 'Pre-analise recusada', 'Aguardando preenchimento'], true)) {
        json_response(['success' => false, 'message' => 'Cotação inválida ou não autorizada.'], 422);
    }

    $protocolo  = $cotacao_pre['protocolo'];
    $fianca_id  = (int) $cotacao_pre['id'];
    $status_ant = $cotacao_pre['status'];

    try {
        db_execute(
            "UPDATE seguro_fianca SET
                tipo_ficha = ?, tipo_plano = ?, cpf_cnpj = ?, nome_completo = ?, tel_celular = ?,
                data_nascimento = ?, email = ?, vinculo_empregaticio = ?, salario_bruto = ?,
                estado_civil = ?, num_dependentes = ?, reside_imovel = ?, tempo_residencia = ?,
                tel_residencial = ?, cep_atual = ?, sexo = ?, nacionalidade = ?,
                franquia = ?, cnae = ?, opcao_tributaria = ?, ramo_atividade = ?, cnpj_empresa = ?,
                vigencia_contrato = ?, cep = ?, endereco = ?, numero = ?, complemento = ?,
                bairro = ?, cidade = ?, uf = ?, indice_reajuste = ?,
                proprietario_imovel = ?, cpf_cnpj_proprietario = ?,
                valor_aluguel = ?, valor_agua = ?, valor_condominio = ?, valor_gas = ?,
                valor_iptu = ?, valor_energia = ?,
                danos_imovel = ?, pintura_externa = ?, multa_contratual = ?, pintura_interna = ?,
                cobertura_moveis = ?, pretendenteAdicional = ?,
                status = 'Aguardando análise', formulario_completo = 1, liberado = 1,
                data_status_aguardando = NOW(), data_atualizacao = NOW()
             WHERE id = ?",
            ['ssssssssdsiisssssssssssssssssssssddddddiiiiisi',
             $tipo_ficha, $tipo_plano, $cpf_cnpj, $nome_completo, $tel_celular,
             $data_nascimento, $email, $vinculo_empregaticio, $salario_bruto,
             $estado_civil, $num_dependentes, $reside_imovel, $tempo_residencia,
             $tel_residencial, $cep_atual, $sexo, $nacionalidade,
             $franquia, $cnae, $opcao_tributaria, $ramo_atividade, $cnpj_empresa,
             $vigencia_contrato, $cep_imovel ?: null, $endereco ?: null, $numero ?: null, $complemento ?: null,
             $bairro ?: null, $cidade ?: null, $uf ?: null, $indice_reajuste ?: null,
             $proprietario_imovel ?: null, $cpf_cnpj_proprietario ?: null,
             $valor_aluguel, $valor_agua, $valor_condominio, $valor_gas,
             $valor_iptu, $valor_energia,
             $danos_imovel, $pintura_externa, $multa_contratual, $pintura_interna,
             $cobertura_moveis, $pretendente_adicional,
             $fianca_id]
        );
    } catch (Throwable $e) {
        error_log('[cotacao-fianca] Erro ao atualizar: ' . $e->getMessage());
        json_response(['success' => false, 'message' => 'Erro ao salvar a cotação. Tente novamente.'], 500);
    }

    historico_registrar(
        $fianca_id, $protocolo, 'ALTERACAO_STATUS',
        $status_ant, 'Aguardando análise',
        $usuario_id, 'imobiliaria', $user->nome ?? 'Imobiliária',
        'Ficha completa enviada após pré-análise aceita.'
    );

} else {
    // ── INSERT: nova cotação (pré-análise ou envio direto) ────────────────
    $protocolo   = gerar_protocolo('MPS', 'seguro_fianca');
    $atendente_id = funil_atribuir_atendente('fianca');
    // Ficha cotada na API fica FORA da fila da MPS ('Cotação API') até ser
    // recusada nas seguradoras ou a imobiliária pedir outras seguradoras
    // (ver src/helpers/api/encaminhamento.php).
    $status = $envio_parcial ? 'Aguardando liberação' : ($cotar_na_api ? 'Cotação API' : 'Aguardando análise');

    $cols = [
        'tipo_ficha'           => ['s', $tipo_ficha],
        'origem'               => ['s', $cotar_na_api ? 'api' : 'manual'],
        'tipo_plano'           => ['s', $tipo_plano],
        'cpf_cnpj'             => ['s', $cpf_cnpj],
        'nome_completo'        => ['s', $nome_completo],
        'tel_celular'          => ['s', $tel_celular],
        'data_nascimento'      => ['s', $data_nascimento],
        'email'                => ['s', $email],
        'vinculo_empregaticio' => ['s', $vinculo_empregaticio],
        'profissao'            => ['s', $profissao ?: null],
        'salario_bruto'        => ['d', $salario_bruto],
        'estado_civil'         => ['s', $estado_civil],
        'num_dependentes'      => ['i', $num_dependentes],
        'reside_imovel'        => ['i', $reside_imovel],
        'tempo_residencia'     => ['s', $tempo_residencia],
        'tel_residencial'      => ['s', $tel_residencial],
        'cep_atual'            => ['s', $cep_atual],
        'sexo'                 => ['s', $sexo],
        'nacionalidade'        => ['s', $nacionalidade],
        'franquia'             => ['s', $franquia],
        'cnae'                 => ['s', $cnae],
        'opcao_tributaria'     => ['s', $opcao_tributaria],
        'ramo_atividade'       => ['s', $ramo_atividade],
        'cnpj_empresa'         => ['s', $cnpj_empresa],
        'vigencia_contrato'    => ['s', $vigencia_contrato],
        'cep'                  => ['s', $cep_imovel ?: null],
        'endereco'             => ['s', $endereco ?: null],
        'numero'               => ['s', $numero ?: null],
        'complemento'          => ['s', $complemento ?: null],
        'bairro'               => ['s', $bairro ?: null],
        'cidade'               => ['s', $cidade ?: null],
        'uf'                   => ['s', $uf ?: null],
        'indice_reajuste'      => ['s', $indice_reajuste ?: null],
        'proprietario_imovel'  => ['s', $proprietario_imovel ?: null],
        'cpf_cnpj_proprietario'=> ['s', $cpf_cnpj_proprietario ?: null],
        'valor_aluguel'        => ['d', $valor_aluguel],
        'valor_agua'           => ['d', $valor_agua],
        'valor_condominio'     => ['d', $valor_condominio],
        'valor_gas'            => ['d', $valor_gas],
        'valor_iptu'           => ['d', $valor_iptu],
        'valor_energia'        => ['d', $valor_energia],
        'danos_imovel'         => ['i', $danos_imovel],
        'pintura_externa'      => ['i', $pintura_externa],
        'multa_contratual'     => ['i', $multa_contratual],
        'pintura_interna'      => ['i', $pintura_interna],
        'cobertura_moveis'     => ['i', $cobertura_moveis],
        'protocolo'            => ['s', $protocolo],
        'status'               => ['s', $status],
        'imobiliaria_id'       => ['i', $imobiliaria_id],
        'usuario_id'           => ['i', $usuario_id],
        'atendente_id'         => ['i', $atendente_id ?: null],
        'formulario_completo'  => ['i', $envio_parcial ? 0 : 1],
        'liberado'             => ['i', $envio_parcial ? 0 : 1],
        'pretendenteAdicional' => ['s', $pretendente_adicional],
        'data_pre_analise'     => ['s', $envio_parcial ? date('Y-m-d H:i:s') : null],
        // 'Aguardando análise' só começa quando a ficha entra na fila da MPS.
        'data_status_aguardando' => ['s', $status === 'Aguardando análise' ? date('Y-m-d H:i:s') : null],
    ];

    $col_names    = implode(', ', array_keys($cols));
    $placeholders = implode(', ', array_fill(0, count($cols), '?'));
    $types        = implode('', array_column($cols, 0));
    $values       = array_column($cols, 1);

    try {
        $fianca_id = db_execute(
            "INSERT INTO seguro_fianca ({$col_names}, data_cadastro, data_atualizacao)
             VALUES ({$placeholders}, NOW(), NOW())",
            [$types, ...$values]
        );
        if (!$fianca_id) throw new RuntimeException('INSERT não retornou ID.');
    } catch (Throwable $e) {
        error_log('[cotacao-fianca] Erro ao inserir: ' . $e->getMessage());
        json_response(['success' => false, 'message' => 'Erro ao salvar a cotação. Tente novamente.'], 500);
    }

    historico_registrar(
        (int) $fianca_id, $protocolo, 'CRIACAO', null, $status,
        $usuario_id, 'imobiliaria', $user->nome ?? 'Imobiliária',
        $envio_parcial ? 'Pré-análise enviada.' : 'Ficha completa enviada.'
    );
}

// ── Pretendentes adicionais ───────────────────────────────────────────────
foreach ($pretendentes_extras as $p) {
    try {
        db_execute(
            "INSERT INTO pretendentes
                (seguro_fianca_id, numero_pretendente, nome_completo, cpf_cnpj,
                 tel_celular, data_nascimento, email, vinculo_empregaticio, salario_bruto)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
            ['iissssssd',
             $fianca_id, $p['numero_pretendente'], $p['nome_completo'], $p['cpf_cnpj'],
             $p['tel_celular'], $p['data_nascimento'], $p['email'],
             $p['vinculo_empregaticio'], $p['salario_bruto']]
        );
    } catch (Throwable $e) {
        error_log('[cotacao-fianca] Erro ao inserir pretendente extra: ' . $e->getMessage());
    }
}

// ── Cotação na API das seguradoras ────────────────────────────────────────
// A chamada à seguradora é LENTA (23-60s) — aqui só enfileira (um trabalho
// por seguradora, em paralelo) e marca o envio (a tela de seguradoras já
// mostra "Carregando proposta"); quem cota é o worker cron/api-fila.php,
// lendo a ficha do banco. Só na ficha NOVA:
// completar uma pré-análise (UPDATE) segue o fluxo manual da MPS.
if ($cotar_na_api && !$is_completando_pre) {
    require_once BASE_PATH . '/src/helpers/api.php';
    db_execute('UPDATE seguro_fianca SET api_enviado_em = NOW() WHERE id = ?', ['i', (int) $fianca_id]);
    api_enfileirar_cotacoes((int) $fianca_id);
    // A imobiliária não vê em quais seguradoras — só que foi pra análise.
    api_historico(
        (int) $fianca_id, 'ENVIO_COTACAO', 'Enviada para análise', null,
        ['id' => $usuario_id, 'tipo' => 'imobiliaria', 'nome' => $user->nome ?? 'Imobiliária']
    );
}

// Rascunho finalizado — remove para não aparecer mais na listagem
$rascunho_protocolo_post = trim($_POST['rascunho'] ?? '');
if ($rascunho_protocolo_post !== '') {
    try {
        db_execute(
            'DELETE FROM seguro_fianca_rascunho WHERE protocolo = ? AND imobiliaria_id = ?',
            ['si', $rascunho_protocolo_post, $imobiliaria_id]
        );
    } catch (Throwable $e) {
        error_log('[cotacao-fianca] Erro ao excluir rascunho: ' . $e->getMessage());
    }
}

submit_token_concluir($submit_token, $protocolo);

// Responde o navegador AGORA — o que vem depois (notificações por e-mail/
// WhatsApp) é chamada de rede e não deve travar o formulário. Com
// fastcgi_finish_request a conexão HTTP fecha aqui e o script termina em
// segundo plano. A cotação direta em si já foi pra fila (worker CLI).
http_response_code(200);
header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'success'   => true,
    'protocolo' => $protocolo,
    'message'   => $envio_parcial
        ? 'Pré-análise enviada com sucesso!'
        : 'Cotação enviada com sucesso!',
    'aviso_horario' => horario_atendimento_verificar('fianca'),
], JSON_UNESCAPED_UNICODE);

// Libera o lock do arquivo de sessão ANTES do trabalho em segundo plano —
// senão a próxima requisição do usuário (a tela de seguradoras) fica
// travada esperando a sessão até as notificações terminarem.
if (session_status() === PHP_SESSION_ACTIVE) session_write_close();

if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
} else {
    ignore_user_abort(true);
    flush();
}

// Notificações (e-mail via SMTP e WhatsApp) só depois de responder — são
// chamadas de rede síncronas e seguravam o formulário na tela.
// Ficha na API ainda não é trabalho da MPS ('Cotação API') — os avisos saem
// quando ela for encaminhada (api_avisar_mps_externo).
if (($status ?? '') === 'Cotação API') {
    exit;
}

// ── Notificação para a equipe MPS ─────────────────────────────────────────
try {
    notificar_todos_admins(
        'nova_cotacao',
        'Nova cotação recebida',
        "Protocolo {$protocolo} — Fiança " . ($tipo_ficha === 'comercial' ? 'Comercial' : 'Residencial') . ' aguardando análise.',
        (int) $fianca_id,
        cotacao_notif_url('fianca', $protocolo, 'admin')
    );
    notificar_cotacao_email(
        'fianca',
        'Seguro Fiança ' . ($tipo_ficha === 'comercial' ? 'Comercial' : 'Residencial'),
        $protocolo,
        (int) $imobiliaria_id
    );
} catch (Throwable $e) {
    // Notificações são não-críticas; não interrompem o fluxo
    error_log('[cotacao-fianca] Erro ao criar notificações: ' . $e->getMessage());
}

// ── WhatsApp só para o atendente responsável (Z-API) ──────────────────────
if (!$is_completando_pre) {
    require_once BASE_PATH . '/src/helpers/zapi.php';
    zapi_notificar_atendente(
        $atendente_id ?: null,
        "🆕 *Nova cotação — Fiança " . ($tipo_ficha === 'comercial' ? 'Comercial' : 'Residencial') . "*\n\n"
        . "👤 Pretendente: *{$nome_completo}*\n"
        . "🏢 " . zapi_imobiliaria_nome($imobiliaria_id) . "\n\n"
        . "📄 Protocolo: `{$protocolo}`",
        'nova_cotacao',
        $protocolo
    );
}
