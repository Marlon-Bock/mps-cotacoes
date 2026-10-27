<?php
/**
 * POST /api/cotacao/incendio
 * Recebe os dados do formulário de Seguro Incêndio e salva no banco. Uma
 * remessa (1 protocolo) pode ter N imóveis — vem em $_POST['imoveis'][i][...],
 * todos com o mesmo peso (não tem "imóvel principal" vs "imóvel extra").
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
    if (empty($user->imob_admin) && (($user->perm_cotar ?? 'N') !== 'S' || ($user->perm_incendio ?? 'N') !== 'S')) {
        json_response(['success' => false, 'message' => 'Sem permissão para cotar Seguro Incêndio.'], 403);
    }
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

function inc_str(array $body, string $key, int $max = 200): string
{
    return mb_substr(trim((string) ($body[$key] ?? '')), 0, $max);
}

function inc_currency(array $body, string $key): ?string
{
    $v = trim((string) ($body[$key] ?? ''));
    if ($v === '') return null;
    $v = preg_replace('/[^\d,]/', '', $v);
    $v = str_replace(',', '.', (string) $v);
    return is_numeric($v) ? $v : null;
}

function inc_int(array $body, string $key): ?int
{
    $v = trim((string) ($body[$key] ?? ''));
    return $v !== '' ? (int) $v : null;
}

// ── Imóveis da remessa ────────────────────────────────────────────────────
$imoveisPost = $_POST['imoveis'] ?? [];
if (!is_array($imoveisPost) || $imoveisPost === []) {
    json_response(['success' => false, 'message' => 'Informe pelo menos um imóvel.'], 422);
}
if (count($imoveisPost) > 20) {
    json_response(['success' => false, 'message' => 'Máximo de 20 imóveis por remessa.'], 422);
}

$imoveis = [];
$errors  = [];
$numero  = 1;
foreach ($imoveisPost as $item) {
    if (!is_array($item)) continue;

    $locador   = inc_str($item, 'locador', 150);
    $locatario = inc_str($item, 'locatario', 150);
    $endereco  = inc_str($item, 'endereco');
    $cep       = preg_replace('/\D/', '', (string) ($item['cep'] ?? ''));

    if ($locador === '' || $locatario === '' || $endereco === '' || $cep === '') {
        $errors[] = "Imóvel {$numero}: preencha locador, locatário, endereço e CEP.";
        $numero++;
        continue;
    }

    $imoveis[] = [
        'numero_imovel'      => $numero,
        'locador'            => $locador,
        'cpf_cnpj_locador'   => inc_str($item, 'cpf_cnpj_locador', 20),
        'locatario'          => $locatario,
        'cpf_cnpj_locatario' => inc_str($item, 'cpf_cnpj_locatario', 20),
        'tipo_imovel'        => inc_str($item, 'tipo_imovel', 60),
        'atividade_imovel'   => inc_str($item, 'atividade_imovel', 150),
        'endereco'           => $endereco,
        'numero'             => inc_str($item, 'numero', 20),
        'complemento'        => inc_str($item, 'complemento', 100),
        'bairro'             => inc_str($item, 'bairro', 100),
        'cidade'             => inc_str($item, 'cidade', 100),
        'uf'                 => strtoupper(inc_str($item, 'uf', 2)),
        'cep'                => $cep,
        'metragem'           => inc_currency($item, 'metragem'),
        'valor_aluguel'      => inc_currency($item, 'valor_aluguel'),
        'valor_incendio'               => inc_currency($item, 'valor_incendio'),
        'valor_danos_eletricos'        => inc_currency($item, 'valor_danos_eletricos'),
        'valor_impacto_veiculos'       => inc_currency($item, 'valor_impacto_veiculos'),
        'valor_vendaval'               => inc_currency($item, 'valor_vendaval'),
        'valor_perda_aluguel'          => inc_currency($item, 'valor_perda_aluguel'),
        'valor_incendio_conteudo'      => inc_currency($item, 'valor_incendio_conteudo'),
        'valor_responsabilidade_civil' => inc_currency($item, 'valor_responsabilidade_civil'),
        'parcelas'           => inc_int($item, 'parcelas'),
        'codigo_imovel_sami' => inc_str($item, 'codigo_imovel_sami', 30),
    ];
    $numero++;
}

if ($errors) {
    json_response(['success' => false, 'message' => implode(' ', $errors)], 422);
}
if (!$imoveis) {
    json_response(['success' => false, 'message' => 'Nenhum imóvel válido informado.'], 422);
}

// ── Bloqueia imóveis duplicados na mesma remessa (mesmo código Sami, ou
// mesmo conjunto locador/locatário/endereço) — reforço do que o JS já
// impede na tela, pra não depender só da validação do navegador.
$codigosVistos = [];
$assinaturasVistas = [];
foreach ($imoveis as $im) {
    if ($im['codigo_imovel_sami'] !== '') {
        $cod = $im['codigo_imovel_sami'];
        if (isset($codigosVistos[$cod])) {
            json_response(['success' => false, 'message' => "Os imóveis {$codigosVistos[$cod]} e {$im['numero_imovel']} usam o mesmo código ({$cod})."], 422);
        }
        $codigosVistos[$cod] = $im['numero_imovel'];
    }

    $assinatura = mb_strtolower(implode('|', [
        $im['locador'], $im['cpf_cnpj_locador'], $im['locatario'], $im['cpf_cnpj_locatario'],
        $im['endereco'], $im['numero'], $im['complemento'], $im['bairro'], $im['cidade'], $im['uf'], $im['cep'],
    ]));
    if (isset($assinaturasVistas[$assinatura])) {
        json_response(['success' => false, 'message' => "Os imóveis {$assinaturasVistas[$assinatura]} e {$im['numero_imovel']} estão com os mesmos dados (endereço, locador e locatário iguais)."], 422);
    }
    $assinaturasVistas[$assinatura] = $im['numero_imovel'];
}

// ── Protocolo ──────────────────────────────────────────────────────────────
$protocolo    = 'INC' . date('Y') . '-' . str_pad((string) random_int(1, 99999), 5, '0', STR_PAD_LEFT);
$atendente_id = funil_atribuir_atendente('incendio');

$incendio_id = db_execute(
    'INSERT INTO seguro_incendio (protocolo, imobiliaria_id, usuario_id, atendente_id, status, data_cadastro)
     VALUES (?, ?, ?, ?, ?, NOW())',
    ['siiis', $protocolo, $imobiliaria_id, $usuario_id, $atendente_id ?: null, 'Aguardando análise']
);

if (!$incendio_id) {
    json_response(['success' => false, 'message' => 'Erro ao salvar cotação. Tente novamente.'], 500);
}

// Se QUALQUER imóvel falhar ao salvar, a remessa inteira é desfeita — uma
// cotação "fantasma" com o cabeçalho criado mas 0 imóveis (ou faltando um)
// é pior do que simplesmente recusar o envio, porque fica invisível até
// alguém notar no admin e o cliente nem sabe que precisa reenviar.
$erro_insercao = null;
foreach ($imoveis as $imovel) {
    try {
        db_execute(
            'INSERT INTO seguro_incendio_imoveis
                (seguro_incendio_id, numero_imovel, locador, cpf_cnpj_locador, locatario, cpf_cnpj_locatario,
                 tipo_imovel, atividade_imovel, endereco, numero, complemento, bairro, cidade, uf, cep,
                 metragem, valor_aluguel, valor_incendio, valor_danos_eletricos, valor_impacto_veiculos,
                 valor_vendaval, valor_perda_aluguel, valor_incendio_conteudo, valor_responsabilidade_civil,
                 parcelas, codigo_imovel_sami)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            ['iisssssssssssssdddddddddis',
             (int) $incendio_id,
             $imovel['numero_imovel'],
             $imovel['locador'],
             $imovel['cpf_cnpj_locador'] ?: null,
             $imovel['locatario'],
             $imovel['cpf_cnpj_locatario'] ?: null,
             $imovel['tipo_imovel'] ?: null,
             $imovel['atividade_imovel'] ?: null,
             $imovel['endereco'],
             $imovel['numero'] ?: null,
             $imovel['complemento'] ?: null,
             $imovel['bairro'] ?: null,
             $imovel['cidade'] ?: null,
             $imovel['uf'] ?: null,
             $imovel['cep'],
             $imovel['metragem'],
             $imovel['valor_aluguel'],
             $imovel['valor_incendio'],
             $imovel['valor_danos_eletricos'],
             $imovel['valor_impacto_veiculos'],
             $imovel['valor_vendaval'],
             $imovel['valor_perda_aluguel'],
             $imovel['valor_incendio_conteudo'],
             $imovel['valor_responsabilidade_civil'],
             $imovel['parcelas'],
             $imovel['codigo_imovel_sami'] ?: null]
        );
    } catch (Throwable $e) {
        error_log('[cotacao-incendio] Erro ao inserir imóvel: ' . $e->getMessage());
        $erro_insercao = $e;
        break;
    }
}

if ($erro_insercao) {
    try {
        db_execute('DELETE FROM seguro_incendio_imoveis WHERE seguro_incendio_id = ?', ['i', (int) $incendio_id]);
        db_execute('DELETE FROM seguro_incendio WHERE id = ?', ['i', (int) $incendio_id]);
    } catch (Throwable $e) {
        error_log('[cotacao-incendio] Erro ao desfazer cotação após falha: ' . $e->getMessage());
    }
    json_response(['success' => false, 'message' => 'Erro ao salvar os imóveis. Tente novamente ou avise a MPS.'], 500);
}

historico_registrar(
    (int) $incendio_id, $protocolo, 'CRIACAO', null, 'Aguardando análise',
    $usuario_id, 'imobiliaria', $user->nome ?? 'Imobiliária',
    'Cotação de seguro incêndio criada — ' . count($imoveis) . ' imóvel(is).'
);

try {
    notificar_todos_admins(
        'nova_cotacao',
        'Nova cotação recebida',
        "Protocolo {$protocolo} — Seguro Incêndio (" . count($imoveis) . ' imóvel(is)) aguardando análise.',
        (int) $incendio_id,
        cotacao_notif_url('incendio', $protocolo, 'admin')
    );
    notificar_cotacao_email('incendio', 'Seguro Incêndio', $protocolo, $imobiliaria_id);
} catch (Throwable $e) {
    error_log('[cotacao-incendio] Erro ao criar notificações: ' . $e->getMessage());
}

require_once BASE_PATH . '/src/helpers/zapi.php';
zapi_notificar_atendente(
    $atendente_id ?: null,
    "🆕 *Nova cotação — Seguro Incêndio*\n\n"
    . '🏠 ' . count($imoveis) . " imóvel(is)\n"
    . "🏢 " . zapi_imobiliaria_nome($imobiliaria_id) . "\n\n"
    . "📄 Protocolo: `{$protocolo}`",
    'nova_cotacao',
    $protocolo
);

submit_token_concluir($submit_token, $protocolo);

json_response([
    'success'       => true,
    'protocolo'     => $protocolo,
    'aviso_horario' => horario_atendimento_verificar('incendio'),
]);
