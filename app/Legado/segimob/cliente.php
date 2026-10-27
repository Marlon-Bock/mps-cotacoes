<?php
/**
 * Cliente HTTP da Segimob: login (token em cache), chamadas autenticadas,
 * disjuntor e download de documentos.
 */

const SEGIMOB_TOKEN_CACHE = '/storage/cache/segimob_token.json';

/** Disjuntor: depois de uma falha, pausa as chamadas por alguns minutos (mesma ideia do zapi_circuito_aberto()). */
const SEGIMOB_DISJUNTOR_MINUTOS = 5;

function segimob_url_base(): string
{
    $url = rtrim((string) env('SEGIMOB_API_URL', 'https://parceiro.api.segimob.com'), '/');
    // Tolera colar o link da documentação (…/swagger/index.html) no .env.
    return preg_replace('#/swagger(/.*)?$#i', '', $url);
}

/**
 * Credenciais da Segimob no .env (e-mail, senha e código da corretora). Sem
 * elas, imobiliária com canal Segimob segue o fluxo manual (ver canais.php).
 */
function segimob_configurada(): bool
{
    return (string) env('SEGIMOB_EMAIL', '') !== ''
        && (string) env('SEGIMOB_SENHA', '') !== ''
        && (string) env('SEGIMOB_CODIGO_CORRETORA', '') !== '';
}

/**
 * A Segimob ficou FORA DO AR (falha de conexão ou HTTP 5xx) no último envio,
 * há menos de SEGIMOB_DISJUNTOR_MINUTOS? Erro de validação de uma ficha
 * (HTTP 4xx) não conta — não é motivo pra pausar as outras.
 */
function segimob_disjuntor_aberto(): bool
{
    try {
        $ultima = db_fetch_one(
            'SELECT erro, enviado_em FROM segimob_fichas WHERE enviado_em IS NOT NULL ORDER BY enviado_em DESC LIMIT 1'
        );
        $foraDoAr = $ultima && $ultima['erro'] !== null
            && (str_starts_with($ultima['erro'], 'Falha ao conectar') || preg_match('/Segimob HTTP 5\d\d/', $ultima['erro']));
        if (!$foraDoAr) {
            return false;
        }
        return (time() - strtotime($ultima['enviado_em'])) / 60 < SEGIMOB_DISJUNTOR_MINUTOS;
    } catch (Throwable $e) {
        return false;
    }
}

/** Token de acesso (cacheado até perto de vencer); faz login se precisar. */
function segimob_autenticar(): array
{
    $cache = BASE_PATH . SEGIMOB_TOKEN_CACHE;

    if (is_file($cache)) {
        $dados = json_decode((string) file_get_contents($cache), true);
        // Margem de 1 minuto pra não usar um token na borda do vencimento.
        if (!empty($dados['token']) && !empty($dados['expiracao']) && strtotime($dados['expiracao']) - time() > 60) {
            return ['ok' => true, 'token' => $dados['token']];
        }
    }

    $email = (string) env('SEGIMOB_EMAIL', '');
    $senha = (string) env('SEGIMOB_SENHA', '');
    if ($email === '' || $senha === '') {
        return ['ok' => false, 'erro' => 'Segimob não configurada (.env).'];
    }

    $r = segimob_http('POST', segimob_url_base() . '/Seguranca/login?' . http_build_query(['email' => $email, 'senha' => $senha]), [], '', 8);
    if (!$r['ok']) {
        return ['ok' => false, 'erro' => 'Login na Segimob: ' . $r['erro']];
    }

    $data = json_decode($r['corpo'], true);
    if (empty($data['token'])) {
        return ['ok' => false, 'erro' => 'Resposta de login da Segimob sem token.'];
    }

    @file_put_contents($cache, json_encode(['token' => $data['token'], 'expiracao' => $data['expiracao'] ?? null]));
    @chmod($cache, 0666);   // site (www-data) e cron (marlon) renovam o mesmo token

    return ['ok' => true, 'token' => $data['token']];
}

/**
 * Estipulante (imobiliária) das próximas chamadas — definido por
 * segimob_usar_ficha() no início de cada operação.
 */
function segimob_estipulante(?int $codigo = null, bool $definir = false): ?int
{
    static $atual = null;
    if ($definir) {
        $atual = $codigo;
    }
    return $atual;
}

/**
 * Código da imobiliária da ficha na Segimob (estipulante) — vem do cadastro
 * dela no banco (imobiliarias.segimob_estipulante). Toda imobiliária é
 * criada na Segimob e tem o código ali; se estiver vazio, tenta achar pelo
 * CNPJ e grava. null = sem código (a ficha não é enviada).
 */
function segimob_estipulante_da_ficha(int $fiancaId): ?int
{
    $imob = db_fetch_one(
        'SELECT i.id, i.cnpj, i.segimob_estipulante FROM seguro_fianca f JOIN imobiliarias i ON i.id = f.imobiliaria_id WHERE f.id = ?',
        ['i', $fiancaId]
    );
    if (!$imob) {
        return null;
    }
    if (!empty($imob['segimob_estipulante'])) {
        return (int) $imob['segimob_estipulante'];
    }

    $cnpj = preg_replace('/\D/', '', (string) $imob['cnpj']);
    if (strlen($cnpj) !== 14) {
        return null;
    }
    $r = segimob_request('GET', '/dominio/imobiliaria?' . http_build_query(['cnpj' => $cnpj, 'tipoProduto' => 'FIANCA']));
    $codigo = $r['ok'] ? (int) ($r['data'][0]['id'] ?? 0) : 0;
    if ($codigo > 0) {
        db_execute('UPDATE imobiliarias SET segimob_estipulante = ? WHERE id = ?', ['ii', $codigo, (int) $imob['id']]);
        return $codigo;
    }
    return null;
}

/**
 * Prepara as chamadas pra uma ficha: o estipulante usado no ENVIO dela
 * (segimob_fichas.estipulante — o seguro só é achado com o mesmo); antes do
 * envio, o da imobiliária dela (banco).
 */
function segimob_usar_ficha(int $fiancaId): void
{
    $gravado = db_fetch_one('SELECT estipulante FROM segimob_fichas WHERE seguro_fianca_id = ?', ['i', $fiancaId]);
    segimob_estipulante((int) ($gravado['estipulante'] ?? 0) ?: segimob_estipulante_da_ficha($fiancaId), true);
}

/** Cabeçalhos das chamadas autenticadas. */
function segimob_cabecalhos(string $token): array
{
    $estipulante = segimob_estipulante();
    return [
        'Authorization: Bearer ' . $token,
        'codigoCorretora: ' . (string) env('SEGIMOB_CODIGO_CORRETORA', ''),
        'codigoEstipulante: ' . (string) ($estipulante ?? ''),
    ];
}

/**
 * Chamada HTTP crua. Libera a conexão do banco durante a espera
 * (db_fechar_temporariamente) — a Segimob pode levar segundos.
 *
 * @return array{ok:bool, http:int, corpo:string, content_type:string, erro?:string}
 */
function segimob_http(string $metodo, string $url, array $cabecalhos, ?string $corpo, int $timeout): array
{
    db_fechar_temporariamente();

    $ch = curl_init($url);
    $opcoes = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $cabecalhos,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT        => $timeout,
    ];
    if ($metodo === 'POST') {
        $opcoes[CURLOPT_POST]       = true;
        $opcoes[CURLOPT_POSTFIELDS] = $corpo ?? '';
    } elseif ($metodo !== 'GET') {
        $opcoes[CURLOPT_CUSTOMREQUEST] = $metodo;
        if ($corpo !== null) {
            $opcoes[CURLOPT_POSTFIELDS] = $corpo;
        }
    }
    curl_setopt_array($ch, $opcoes);

    $resposta = curl_exec($ch);
    $http     = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $tipo     = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $erroCurl = curl_error($ch);
    curl_close($ch);

    if ($resposta === false) {
        return ['ok' => false, 'http' => 0, 'corpo' => '', 'content_type' => '', 'erro' => 'Falha ao conectar na Segimob' . ($erroCurl ? " ({$erroCurl})" : '.')];
    }
    if ($http >= 400) {
        $json = json_decode((string) $resposta, true);
        $msg  = is_array($json) ? (string) ($json['message'] ?? $json['Message'] ?? $json['erro'] ?? '') : '';
        // Erro de negócio vem com outro JSON dentro da mensagem:
        // {"message": "{\"ErrorCode\":1,\"Message\":\"...\"}"}
        if (str_starts_with($msg, '{') && is_array($interno = json_decode($msg, true))) {
            $msg = (string) ($interno['Message'] ?? $interno['message'] ?? $msg);
        }
        // Validação (ASP.NET): {"errors": {"Campo": ["mensagem", ...]}} → "Campo: mensagem; ..."
        if ($msg === '' && is_array($json['errors'] ?? null)) {
            $partes = [];
            foreach ($json['errors'] as $campo => $erros) {
                $partes[] = (is_string($campo) ? "{$campo}: " : '') . implode(' ', (array) $erros);
            }
            $msg = implode('; ', $partes);
        }
        return ['ok' => false, 'http' => $http, 'corpo' => (string) $resposta, 'content_type' => $tipo,
                'erro' => "Segimob HTTP {$http}" . ($msg !== '' ? ": {$msg}" : ': ' . substr((string) $resposta, 0, 300))];
    }

    return ['ok' => true, 'http' => $http, 'corpo' => (string) $resposta, 'content_type' => $tipo];
}

/**
 * Chamada autenticada à API (JSON). Token vencido antes do previsto (401):
 * refaz o login uma vez e repete.
 *
 * @return array{ok:bool, data?:mixed, erro?:string}
 */
function segimob_request(string $metodo, string $caminho, ?array $corpo = null, bool $jaReautenticou = false, int $timeout = 8): array
{
    if (segimob_disjuntor_aberto()) {
        return ['ok' => false, 'erro' => 'Segimob com falha recente — pausado por ' . SEGIMOB_DISJUNTOR_MINUTOS . ' min (disjuntor).'];
    }

    $auth = segimob_autenticar();
    if (!$auth['ok']) {
        return $auth;
    }

    $r = segimob_http(
        $metodo,
        segimob_url_base() . $caminho,
        [...segimob_cabecalhos($auth['token']), 'Content-Type: application/json'],
        $corpo !== null ? json_encode($corpo) : null,
        $timeout
    );

    if ($r['http'] === 401 && !$jaReautenticou) {
        @unlink(BASE_PATH . SEGIMOB_TOKEN_CACHE);
        return segimob_request($metodo, $caminho, $corpo, true, $timeout);
    }
    if (!$r['ok']) {
        return ['ok' => false, 'erro' => $r['erro']];
    }

    return ['ok' => true, 'data' => json_decode($r['corpo'], true)];
}
