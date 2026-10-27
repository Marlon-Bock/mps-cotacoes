<?php
/**
 * POST /api/cotacao/fianca/{protocolo}/seguradoras/corrigir
 * "Corrigir dados": a seguradora recusou um dado da ficha (CEP não
 * encontrado, nome com palavra proibida, encargos acima do limite...). A
 * imobiliária corrige só os campos envolvidos e a cotação é reenviada às
 * seguradoras que recusaram — sem refazer a ficha.
 *
 * Body JSON: { campos: { cep: "...", endereco: "...", ... } }
 */
$user = require_auth();
header('Content-Type: application/json; charset=utf-8');
csrf_verify();

require_once BASE_PATH . '/src/helpers/api.php';
$ficha    = api_ficha_autorizada($user, (string) ($params['protocolo'] ?? ''));
$fiancaId = (int) $ficha['id'];

if ($ficha['status'] !== API_STATUS_FICHA || api_ficha_encerrada($ficha)) {
    json_response(['success' => false, 'message' => 'Essa cotação não pode mais ser corrigida.'], 409);
}
if (api_fila_ativa($fiancaId) !== null) {
    json_response(['success' => false, 'message' => 'A seguradora ainda está processando. Aguarde.'], 409);
}

$pendentes = api_correcoes_pendentes($ficha, api_cotacoes_da_ficha($fiancaId));
if ($pendentes === []) {
    json_response(['success' => false, 'message' => 'Não há dados para corrigir nessa cotação.'], 409);
}

// Só os campos que as seguradoras recusaram.
$permitidos = array_unique(array_merge(...array_column($pendentes, 'campos')));
$definicoes = api_campos_corrigiveis();
$body       = json_decode(file_get_contents('php://input'), true) ?: [];
$enviados   = is_array($body['campos'] ?? null) ? $body['campos'] : [];

$moeda = static function (string $v): ?float {
    $v = trim(str_replace(['R$', ' '], '', $v));
    if ($v === '') return 0.0;
    if (str_contains($v, ',')) $v = str_replace(['.', ','], ['', '.'], $v);
    return is_numeric($v) ? round((float) $v, 2) : null;
};

$novos = [];
foreach ($permitidos as $campo) {
    [$rotulo, , $tipo] = $definicoes[$campo];
    $valor = trim((string) ($enviados[$campo] ?? ''));
    $erro  = null;

    switch ($tipo) {
        case 'nome':
            $valor = preg_replace('/\s+/', ' ', $valor);
            if (count(explode(' ', $valor)) < 2) $erro = 'Informe o nome completo do pretendente (nome e sobrenome).';
            break;
        case 'cpf':
            $valor = preg_replace('/\D/', '', $valor);
            if (!validar_cpf($valor)) $erro = 'CPF do pretendente inválido.';
            break;
        case 'email':
            if (!filter_var($valor, FILTER_VALIDATE_EMAIL)) $erro = 'Informe um e-mail válido.';
            break;
        case 'cep':
            $valor = preg_replace('/\D/', '', $valor);
            if (strlen($valor) !== 8) $erro = 'Informe um CEP válido (8 dígitos).';
            break;
        case 'uf':
            $valor = strtoupper($valor);
            if (!preg_match('/^[A-Z]{2}$/', $valor)) $erro = 'Informe a UF com 2 letras.';
            break;
        case 'aluguel':
        case 'moeda':
            $valor = $moeda($valor);
            if ($valor === null || $valor < 0) $erro = "Valor inválido em {$rotulo}.";
            elseif ($tipo === 'aluguel' && $valor <= 0) $erro = 'Informe o valor do aluguel.';
            break;
        case 'opcional':
            break;
        default:
            if ($valor === '') $erro = "Informe {$rotulo}.";
    }
    if ($erro !== null) {
        json_response(['success' => false, 'message' => $erro, 'campo' => $campo], 422);
    }
    $novos[$campo] = $valor;
}

// Encargos: mesma regra da seguradora (até 2x o aluguel) — avisa na hora.
if (isset($novos['valor_aluguel'])) {
    $encargos = array_sum(array_intersect_key($novos, array_flip(['valor_condominio', 'valor_iptu', 'valor_agua', 'valor_energia', 'valor_gas'])));
    if ($encargos > 2 * $novos['valor_aluguel']) {
        json_response(['success' => false, 'message' => 'Os encargos passam de 2x o aluguel.', 'campo' => 'valor_condominio'], 422);
    }
}

// O que mudou de fato (pro histórico e pra não reenviar igual).
$exibir = static function (string $campo, $v) use ($definicoes): string {
    $tipo = $definicoes[$campo][2];
    if (in_array($tipo, ['moeda', 'aluguel'], true)) return 'R$ ' . number_format((float) $v, 2, ',', '.');
    if ($tipo === 'cep') return preg_replace('/(\d{5})(\d{3})/', '$1-$2', (string) $v);
    if ($tipo === 'cpf') return api_formatar_documento((string) $v);
    return (string) $v === '' ? '(vazio)' : (string) $v;
};
$mudancas = [];
foreach ($novos as $campo => $valor) {
    $antes = $ficha[$campo] ?? '';
    $igual = in_array($definicoes[$campo][2], ['moeda', 'aluguel'], true)
        ? abs((float) $antes - (float) $valor) < 0.005
        : (string) $antes === (string) $valor;
    if (!$igual) {
        $mudancas[] = $definicoes[$campo][0] . ': ' . $exibir($campo, $antes) . ' → ' . $exibir($campo, $valor);
    }
}
if ($mudancas === []) {
    json_response(['success' => false, 'message' => 'Nenhum dado foi alterado.'], 422);
}

$sets  = implode(', ', array_map(fn($c) => "`{$c}` = ?", array_keys($novos)));
$tipos = implode('', array_map(fn($v) => is_float($v) ? 'd' : 's', $novos)) . 'i';
db_execute("UPDATE seguro_fianca SET {$sets}, data_atualizacao = NOW() WHERE id = ?", [$tipos, ...array_values($novos), $fiancaId]);

api_historico($fiancaId, 'ALTERACAO_STATUS', 'Dados corrigidos', implode('; ', $mudancas) . '.', api_historico_ator($user));
api_recotar($fiancaId, array_keys($pendentes));

json_response(['success' => true, 'enfileirado' => true]);
