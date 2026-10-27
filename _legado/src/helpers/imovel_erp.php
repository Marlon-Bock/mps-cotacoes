<?php
/**
 * Busca de imóvel por código no ERP do cliente Sami (endereço + valores +
 * proprietário), para preencher a ficha de cotação de Seguro Fiança.
 *
 * Reproduz a integração do sistema anterior (acao-buscar-imovel-erp.php), que
 * trata dois fluxos conforme o campo `sistema` da imobiliária:
 *   - SAMILOCA        → API REST widesys (via site_samiloca), único endpoint
 *                       que devolve os proprietários completos;
 *   - ERP/ERP++/etc.  → conexão direta ao banco do cliente (consultaDadosConexao
 *                       na biblioteca compartilhada) + SQL de imóvel/proprietário.
 *
 * Usado apenas quando a imobiliária é cliente Sami (eh_cliente_sami = 1) e
 * possui sigla_erp cadastrada.
 */

/** Caminho da biblioteca ERP compartilhada (pode ser sobrescrito via .env). */
function imovel_erp_lib_path(): string
{
    return (string) env('ERP_FUNCOES_PATH', '/u01/home/funcoes80/www/Integracao/erp/funcoes.php');
}

/**
 * Ponto de entrada: decide o fluxo pelo sistema da imobiliária.
 *
 * @return array{ok:bool, erro?:string, imovel?:array}
 */
function imovel_erp_buscar(string $sigla, string $codigo, string $sistema = '', string $siteSamiloca = '', string $samilocaApiUser = '', string $samilocaApiPass = ''): array
{
    $sigla  = trim($sigla);
    $codigo = trim($codigo);

    if ($sigla === '' || $codigo === '') {
        return ['ok' => false, 'erro' => 'Informe o código do imóvel.'];
    }

    // Banco/API de terceiro (cada cliente tem o seu) — com mysqli_report(STRICT)
    // ligado pelo db(), qualquer falha lá (conexão caída, tabela/coluna que não
    // existe naquela versão do ERP) vira exceção e derrubava a busca com
    // "Erro interno". A busca é só um atalho de preenchimento: falha = mensagem.
    try {
        if (strtoupper(trim($sistema)) === 'SAMILOCA') {
            return imovel_erp_buscar_samiloca(rtrim(trim($siteSamiloca), '/'), $codigo, $samilocaApiUser, $samilocaApiPass);
        }

        return imovel_erp_buscar_banco($sigla, $codigo);
    } catch (Throwable $e) {
        error_log('[imovel_erp] Falha na busca (sigla ' . $sigla . ', código ' . $codigo . '): ' . $e->getMessage());
        return ['ok' => false, 'erro' => 'Não foi possível consultar o imóvel no sistema da imobiliária. Preencha os dados manualmente.'];
    }
}

/* ─────────────────────────────────────────────────────────────────────────
 * Fluxo SAMILOCA — API REST widesys
 *
 * A URL base varia conforme quem fez o site do cliente:
 *   - site próprio (feito pelo Samiloca)  → https://cliente.com.br
 *   - site hospedado pela Sami            → https://cliente.samiloca.com.br
 *                                           https://cliente.app.samiloca.com.br
 * Não há como derivar isso do cadastro institucional (o campo SITE está vazio
 * na maioria dos clientes SAMILOCA e o `subdominio` nunca é preenchido), por
 * isso a URL fica em imobiliarias.site_samiloca, informada/detectada no
 * cadastro da imobiliária.
 * ──────────────────────────────────────────────────────────────────────── */

/**
 * Normaliza a base da API: garante o esquema https, remove a barra final e
 * corta um caminho /api/... que o admin tenha colado junto.
 */
function samiloca_url_normalizar(string $url): string
{
    $url = trim($url);
    if ($url === '') return '';

    if (!preg_match('~^https?://~i', $url)) {
        $url = 'https://' . ltrim($url, '/');
    }
    $url = preg_replace('~/api/index\.php.*$~i', '', $url);

    return rtrim((string) $url, '/');
}

/**
 * Bloqueia URLs que apontem para dentro da infra (defesa anti-SSRF, já que a
 * URL vem de um formulário e a chamada sai do servidor).
 */
function samiloca_url_publica(string $url): bool
{
    $host = parse_url($url, PHP_URL_HOST);
    if (!$host) return false;

    $ip = filter_var($host, FILTER_VALIDATE_IP) ? $host : gethostbyname($host);
    if ($ip === $host && !filter_var($host, FILTER_VALIDATE_IP)) {
        return false;   // não resolveu
    }

    return (bool) filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
}

/**
 * Chamada ao endpoint widesys/produto de uma base SAMILOCA.
 * Valida que a resposta é realmente o JSON do widesys — sem isso uma URL
 * errada cai no redirect coringa do samiloca.com.br, volta HTTP 200 com HTML
 * e o erro apareceria como "imóvel não encontrado".
 *
 * @return array{ok:bool, data?:array, erro?:string}
 */
function samiloca_api_produto(string $base, array $filtros, int $timeout = 15, string $apiUser = '', string $apiPass = ''): array
{
    $url = $base . '/api/index.php/v1/widesys/produto?' . http_build_query($filtros);

    // Cada cliente SAMILOCA tem seu próprio usuário/senha de API (confirmado
    // com dados reais — não é um usuário compartilhado). Quando a imobiliária
    // não tiver credencial própria cadastrada, cai no par global do .env.
    $userpwd = ($apiUser !== '' || $apiPass !== '')
        ? "{$apiUser}:{$apiPass}"
        : (string) env('SAMILOCA_API_USER', 'website') . ':' . (string) env('SAMILOCA_API_PASS', '');

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERPWD        => $userpwd,
        CURLOPT_HTTPAUTH       => CURLAUTH_BASIC,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_FOLLOWLOCATION => true,
    ]);
    $resp     = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($resp === false) {
        return ['ok' => false, 'erro' => 'Não foi possível conectar em ' . $base . ($curlErr ? " ({$curlErr})" : '.')];
    }
    if ($httpCode === 401 || $httpCode === 403) {
        return ['ok' => false, 'erro' => 'A API do SAMILOCA recusou as credenciais (HTTP ' . $httpCode . ').'];
    }
    if ($httpCode !== 200) {
        return ['ok' => false, 'erro' => $base . ' respondeu HTTP ' . $httpCode . '.'];
    }

    $d = json_decode((string) $resp, true);
    if (!is_array($d) || !array_key_exists('data', $d)) {
        return ['ok' => false, 'erro' => $base . ' não responde a API do SAMILOCA (a resposta não é o JSON do widesys).'];
    }

    return ['ok' => true, 'data' => is_array($d['data']) ? $d['data'] : []];
}

/**
 * Testa se uma URL é uma base válida da API SAMILOCA.
 *
 * @return array{ok:bool, url:string, erro?:string}
 */
function samiloca_api_testar(string $url, int $timeout = 15, string $apiUser = '', string $apiPass = ''): array
{
    $base = samiloca_url_normalizar($url);
    if ($base === '') {
        return ['ok' => false, 'url' => '', 'erro' => 'Informe a URL do site.'];
    }
    if (!samiloca_url_publica($base)) {
        return ['ok' => false, 'url' => $base, 'erro' => 'Endereço inválido ou não público.'];
    }

    $r = samiloca_api_produto($base, ['list[limit]' => 1, 'page[offset]' => 0], $timeout, $apiUser, $apiPass);

    return $r['ok']
        ? ['ok' => true, 'url' => $base]
        : ['ok' => false, 'url' => $base, 'erro' => $r['erro']];
}

/**
 * Apelidos prováveis do cliente no subdomínio (ex.: "SL - Bom Fim Imóveis
 * Ltda (0472)" → bomfimimoveis, bom, bomfim). Usados só para tentar adivinhar
 * a URL no botão "Detectar" do cadastro.
 *
 * @return string[]
 */
function samiloca_slugs_candidatos(string $nome, string $site = ''): array
{
    $slugs = [];

    // Domínio próprio já dá o apelido mais confiável: bomfimimoveis.com.br → bomfimimoveis
    $host = parse_url(samiloca_url_normalizar($site), PHP_URL_HOST);
    if ($host) {
        $partes = explode('.', preg_replace('/^www\./i', '', $host));
        if (!empty($partes[0])) $slugs[] = $partes[0];
    }

    // Nome institucional: descarta prefixos de carteira ("SL - ", "FL - ") e o código entre parênteses.
    $limpo = preg_replace('/\(.*?\)/', ' ', $nome);
    $limpo = preg_replace('/\b[A-Z]{2}\s*-\s*/', ' ', (string) $limpo);

    // slugify() já resolve acentos e pontuação; aqui só interessam as palavras.
    $palavras = array_values(array_filter(explode('-', slugify((string) $limpo))));
    if ($palavras) {
        $slugs[] = implode('', array_slice($palavras, 0, 4));
        $slugs[] = $palavras[0];
        if (count($palavras) > 1) $slugs[] = $palavras[0] . $palavras[1];
    }

    return array_values(array_unique(array_filter($slugs, static fn ($s) => strlen($s) >= 3)));
}

/**
 * URLs candidatas da API, na ordem de tentativa: o site próprio do cliente e
 * depois os dois padrões de subdomínio hospedado pela Sami.
 *
 * @return string[]
 */
function samiloca_url_candidatas(string $nome, string $site = ''): array
{
    $cands = [];

    $proprio = samiloca_url_normalizar($site);
    if ($proprio !== '') $cands[] = $proprio;

    foreach (samiloca_slugs_candidatos($nome, $site) as $slug) {
        $cands[] = 'https://' . $slug . '.samiloca.com.br';
        $cands[] = 'https://' . $slug . '.app.samiloca.com.br';
    }

    return array_slice(array_values(array_unique($cands)), 0, 7);
}

function imovel_erp_buscar_samiloca(string $siteSamiloca, string $codigo, string $apiUser = '', string $apiPass = ''): array
{
    $base = samiloca_url_normalizar($siteSamiloca);
    if ($base === '') {
        return ['ok' => false, 'erro' => 'URL do site SAMILOCA não configurada para esta imobiliária. Cadastre-a na tela da imobiliária.'];
    }

    // ATENÇÃO: o filtro é `filter[produtos.referencia]`. A chave `filter[referencia]`
    // (usada antes) é IGNORADA pela API: com list[limit]=1 a busca devolvia sempre o
    // primeiro imóvel da lista, ou seja, qualquer código trazia o imóvel errado.
    $consulta = static function (string $filtro, bool $soPublicados) use ($base, $apiUser, $apiPass): array {
        $query = ['filter[produtos.referencia]' => $filtro, 'list[limit]' => 5, 'page[offset]' => 0];
        if ($soPublicados) {
            $query = ['filter[produtos.published]' => 1] + $query;
        }
        return samiloca_api_produto($base, $query, 15, $apiUser, $apiPass);
    };

    // Confere a referência devolvida: se a API voltar a ignorar o filtro, é melhor
    // dizer "não encontrado" do que preencher a cotação com outro imóvel.
    $conferir = static function (array $resposta, string $codigo): ?array {
        foreach ($resposta['data'] ?? [] as $item) {
            $attr = $item['attributes'] ?? [];
            if (strcasecmp(trim((string) ($attr['referencia'] ?? '')), $codigo) === 0) {
                return $attr;
            }
        }
        return null;
    };

    // Tenta o código exato e depois só a parte numérica; em cada um, primeiro entre
    // os publicados e depois na base toda (imóvel já alugado costuma sair do site).
    $codigos = [$codigo];
    $numerico = preg_replace('/\D/', '', $codigo);
    if ($numerico !== '' && $numerico !== $codigo) {
        $codigos[] = $numerico;
    }

    $attr = null;
    $r    = ['ok' => true, 'data' => []];

    foreach ($codigos as $tentativa) {
        foreach ([true, false] as $soPublicados) {
            $r = $consulta($tentativa, $soPublicados);
            if (!$r['ok']) break 2;

            $attr = $conferir($r, $tentativa);
            if ($attr) break 2;
        }
    }

    // Falha de comunicação/URL tem mensagem própria: não é "imóvel não encontrado".
    if (!$r['ok']) {
        return ['ok' => false, 'erro' => $r['erro']];
    }
    if (empty($attr)) {
        return ['ok' => false, 'erro' => 'Nenhum imóvel encontrado na API SAMILOCA para o código ' . $codigo . '.'];
    }

    $nomeEstado = (string) ($attr['nome_estado'] ?? '');
    $uf = strlen($nomeEstado) === 2 ? strtoupper($nomeEstado) : (imovel_erp_uf_por_estado($nomeEstado) ?? '');

    $cep = preg_replace('/\D/', '', (string) ($attr['cep'] ?? ''));
    if (strlen($cep) === 8) {
        $cep = substr($cep, 0, 5) . '-' . substr($cep, 5);
    }

    // Proprietário: a API devolve um array; pega o principal (idefault = 1) ou o primeiro.
    $proprietario = $cpfProprietario = '';
    $proprietarios = $attr['proprietarios'] ?? [];
    if (!empty($proprietarios)) {
        $principal = null;
        foreach ($proprietarios as $p) {
            if (($p['idefault'] ?? 0) == 1) { $principal = $p; break; }
        }
        if (!$principal) { $principal = $proprietarios[0]; }
        $proprietario    = (string) ($principal['nome']     ?? '');
        $cpfProprietario = (string) ($principal['cpf_cnpj'] ?? '');
    }

    return ['ok' => true, 'imovel' => [
        'codigo'           => (string) ($attr['referencia'] ?? $attr['id'] ?? $codigo),
        'logradouro'       => trim((string) ($attr['logradouro'] ?? $attr['endereco'] ?? '')),
        'numero'           => (string) ($attr['endereco_num'] ?? ''),
        'complemento'      => (string) ($attr['complemento'] ?? ''),
        'cep'              => $cep,
        'bairro'           => (string) ($attr['nome_bairro'] ?? ''),
        'cidade'           => (string) ($attr['nome_cidade'] ?? ''),
        'uf'               => $uf,
        'valor_aluguel'    => (float) ($attr['valor_locacao'] ?? 0),
        'valor_condominio' => (float) ($attr['valor_condominio'] ?? 0),
        'valor_iptu'       => (float) ($attr['valor_iptu'] ?? 0),
        'proprietario'     => $proprietario,
        'cpf_proprietario' => $cpfProprietario,
        // Tipo confirmado contra payload real (imóvel AP3205 da Bom fim):
        // attr['tipo'] é só o código da finalidade (ex: "U"), o nome legível
        // é attr['nome_tipo'] ("Apartamento") — mesma info do bloco aninhado
        // attr['tipo_imovel']['title'].
        // Locatário: essa API de "produto" não devolve inquilino/contrato
        // (confirmado contra o payload completo) — fica vazio, o usuário
        // preenche na mão. Não é erro, só não existe nesse endpoint.
        'locatario'        => '',
        'cpf_locatario'    => '',
        'tipo_imovel'      => (string) ($attr['nome_tipo'] ?? ''),
        'metragem'         => (float) ($attr['area_util'] ?? 0) ?: null,
    ]];
}

/* ─────────────────────────────────────────────────────────────────────────
 * Fluxo ERP / ERP++ / IMOBIFORCE — conexão direta ao banco do cliente
 * ──────────────────────────────────────────────────────────────────────── */
function imovel_erp_buscar_banco(string $sigla, string $codigo): array
{
    $lib = imovel_erp_lib_path();
    if (!is_file($lib)) {
        error_log('[imovel_erp] Biblioteca ERP não encontrada: ' . $lib);
        return ['ok' => false, 'erro' => 'Integração de imóveis indisponível no momento.'];
    }
    require_once $lib;

    if (!function_exists('consultaDadosConexao')) {
        error_log('[imovel_erp] Função consultaDadosConexao não existe na biblioteca ERP.');
        return ['ok' => false, 'erro' => 'Integração de imóveis indisponível no momento.'];
    }

    $conn = consultaDadosConexao($sigla);
    if (is_array($conn)) { // array => erro de conexão
        error_log('[imovel_erp] Conexão falhou (sigla ' . $sigla . '): ' . ($conn['ERRO'] ?? '—'));
        return ['ok' => false, 'erro' => 'Não foi possível acessar o sistema da imobiliária.'];
    }

    mysqli_set_charset($conn, 'utf8mb4');

    // Imóvel: endereço + valores (valores em centavos → dividir por 100).
    $sql = "
        SELECT
            i.CodImovel_CadImov                                     AS codigo,
            CONCAT(i.LogImovel_CadImov, ' ', i.EndImovel_CadImov)   AS logradouro,
            i.NumImovel_CadImov                                     AS numero,
            i.ComplEndImovel_CadImov                                AS complemento,
            CONCAT(
                SUBSTR(i.CEPEndImovel_CadImov, 1, 5), '-',
                SUBSTR(i.CEPEndImovel_CadImov, 6, 3)
            )                                                       AS cep,
            b.Nome_Bairros                                          AS bairro,
            c.Nome_Cid                                              AS cidade,
            c.Uf_Cid                                                AS uf,
            -- Imóvel alugado: usa o valor REAL do contrato ativo (ValorAluguel_CadCont,
            -- ja em reais, sem centavos embutidos). Imóvel vago: cai no valor
            -- pretendido do cadastro do imóvel (VlrPretendidoAlug_CadImov, em
            -- centavos). Confirmado contra dados reais (imóvel 49: pretendido = 0,
            -- contrato ativo 8336 = R$ 1.300,00, que e o valor correto hoje).
            IF(cc.ValorAluguel_CadCont > 0, cc.ValorAluguel_CadCont, ROUND(i.VlrPretendidoAlug_CadImov / 100, 2)) AS valor_aluguel,
            ROUND(i.VlrPretendidoCond_CadImov / 100, 2)             AS valor_condominio,
            ROUND(i.VlrPretendidoIptu_CadImov / 100, 2)             AS valor_iptu,
            e.Nome_EspecImov                                        AS tipo_imovel,
            IF(i.MetragemSegFogoImov_CadImov > 0, i.MetragemSegFogoImov_CadImov, i.AreaPrivativaImov_CadImov) AS metragem,
            pl.Nome_Pessoas                                         AS locatario,
            pl.CPFCGC_Pessoas                                       AS cpf_locatario
        FROM loc_cadimov i
        INNER JOIN div_cidades c ON c.Cod_Cid    = i.IdCidEndImovel_CadImov
        INNER JOIN div_bairros b ON b.Id_Bairros = i.IdBairroImovel_CadImov
        LEFT JOIN loc_especimov e ON e.Id_EspecImov = i.EspecImovel_CadImov
        -- Locatário do contrato ATIVO (SituacaoCont_CadCont = 'S') do imóvel,
        -- inquilino principal (TipoPessoas_VincCont = 'I', Principal_VincCont = 'S').
        -- loc_vinccont.CodContImov_VincCont referencia CodContrato_CadCont
        -- (numero do contrato), nao o Id_CadCont (chave interna da tabela) —
        -- confirmado contra a tela real do sistema (imóvel 49, contrato 8336
        -- → Artur Gomes Dadda, CPF 003.677.770-61, igual à ficha do inquilino).
        LEFT JOIN loc_cadcont cc ON cc.CodImovel_CadCont = i.CodImovel_CadImov AND cc.SituacaoCont_CadCont = 'S'
        LEFT JOIN loc_vinccont vc ON vc.CodContImov_VincCont = cc.CodContrato_CadCont AND vc.TipoPessoas_VincCont = 'I' AND vc.Principal_VincCont = 'S'
        LEFT JOIN loc_pessoas pl ON pl.Id_Pessoas = vc.IdPessoas_VincCont
        WHERE i.CodImovel_CadImov = ?
        LIMIT 1
    ";

    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        error_log('[imovel_erp] Falha ao preparar consulta de imóvel: ' . mysqli_error($conn));
        mysqli_close($conn);
        return ['ok' => false, 'erro' => 'Erro ao consultar o imóvel.'];
    }

    mysqli_stmt_bind_param($stmt, 's', $codigo);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $imovel = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);

    if (!$imovel) {
        mysqli_close($conn);
        return ['ok' => false, 'erro' => 'Nenhum imóvel encontrado com esse código.'];
    }

    // Proprietário: Loc_VincPropImov → loc_cadprop → loc_pessoas.
    $proprietario = $cpfProprietario = '';
    $sqlProp = "
        SELECT pe.Nome_Pessoas AS nome_prop, pe.CPFCGC_Pessoas AS cpf_prop
        FROM Loc_VincPropImov vp
        INNER JOIN loc_cadprop cp ON cp.Codigo_CadProp = vp.CodProprietario_VincPropImov
        INNER JOIN loc_pessoas pe ON pe.Id_Pessoas     = cp.IdPessoasProp_CadProp
        WHERE vp.CodImovel_VincPropImov = ?
        LIMIT 1
    ";
    $stmtProp = mysqli_prepare($conn, $sqlProp);
    if ($stmtProp) {
        mysqli_stmt_bind_param($stmtProp, 's', $codigo);
        mysqli_stmt_execute($stmtProp);
        $resProp = mysqli_stmt_get_result($stmtProp);
        if ($resProp && ($rowProp = mysqli_fetch_assoc($resProp))) {
            $proprietario    = imovel_erp_utf8($rowProp['nome_prop'] ?? '');
            $cpfProprietario = (string) ($rowProp['cpf_prop'] ?? '');
        }
        mysqli_stmt_close($stmtProp);
    }

    mysqli_close($conn);

    // O banco do cliente é latin1; converte os textos para UTF-8.
    foreach (['logradouro', 'numero', 'complemento', 'bairro', 'cidade', 'uf', 'tipo_imovel', 'locatario'] as $campo) {
        if (isset($imovel[$campo])) {
            $imovel[$campo] = imovel_erp_utf8($imovel[$campo]);
        }
    }

    return ['ok' => true, 'imovel' => [
        'codigo'           => (string) ($imovel['codigo'] ?? $codigo),
        'logradouro'       => trim((string) ($imovel['logradouro'] ?? '')),
        'numero'           => (string) ($imovel['numero'] ?? ''),
        'complemento'      => (string) ($imovel['complemento'] ?? ''),
        'cep'              => (string) ($imovel['cep'] ?? ''),
        'bairro'           => (string) ($imovel['bairro'] ?? ''),
        'cidade'           => (string) ($imovel['cidade'] ?? ''),
        'uf'               => (string) ($imovel['uf'] ?? ''),
        'valor_aluguel'    => (float) ($imovel['valor_aluguel'] ?? 0),
        'valor_condominio' => (float) ($imovel['valor_condominio'] ?? 0),
        'valor_iptu'       => (float) ($imovel['valor_iptu'] ?? 0),
        'proprietario'     => $proprietario,
        'cpf_proprietario' => $cpfProprietario,
        // Tipo/metragem/locatário confirmados via schema real: loc_especimov,
        // AreaPrivativaImov_CadImov/MetragemSegFogoImov_CadImov, e o inquilino
        // principal do contrato ATIVO (loc_cadcont + loc_vinccont + loc_pessoas
        // — ver JOIN acima, validado com imóvel 49/contrato 8336 em produção).
        // Sem contrato ativo (imóvel vago), fica vazio normalmente.
        'locatario'        => trim((string) ($imovel['locatario'] ?? '')),
        'cpf_locatario'    => (string) ($imovel['cpf_locatario'] ?? ''),
        'tipo_imovel'      => trim((string) ($imovel['tipo_imovel'] ?? '')),
        'metragem'         => isset($imovel['metragem']) && (float) $imovel['metragem'] > 0 ? (float) $imovel['metragem'] : null,
    ]];
}

/** Converte texto latin1 → UTF-8 (idempotente). */
function imovel_erp_utf8($s): string
{
    $s = (string) $s;
    return mb_check_encoding($s, 'UTF-8') ? $s : mb_convert_encoding($s, 'UTF-8', 'ISO-8859-1');
}

/** Nome do estado por extenso → sigla UF (usado no fluxo SAMILOCA). */
function imovel_erp_uf_por_estado(string $nomeEstado): ?string
{
    static $mapa = [
        'Acre' => 'AC', 'Alagoas' => 'AL', 'Amapá' => 'AP', 'Amazonas' => 'AM', 'Bahia' => 'BA',
        'Ceará' => 'CE', 'Distrito Federal' => 'DF', 'Espírito Santo' => 'ES', 'Goiás' => 'GO',
        'Maranhão' => 'MA', 'Mato Grosso' => 'MT', 'Mato Grosso do Sul' => 'MS',
        'Minas Gerais' => 'MG', 'Pará' => 'PA', 'Paraíba' => 'PB', 'Paraná' => 'PR',
        'Pernambuco' => 'PE', 'Piauí' => 'PI', 'Rio de Janeiro' => 'RJ',
        'Rio Grande do Norte' => 'RN', 'Rio Grande do Sul' => 'RS', 'Rondônia' => 'RO',
        'Roraima' => 'RR', 'Santa Catarina' => 'SC', 'São Paulo' => 'SP',
        'Sergipe' => 'SE', 'Tocantins' => 'TO',
    ];
    return $mapa[$nomeEstado] ?? null;
}
