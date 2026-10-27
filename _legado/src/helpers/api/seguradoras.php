<?php
/**
 * Registro das seguradoras integradas na API — por produto ('produto':
 * 'fianca' é o padrão; 'residencial' = Seguro Residencial). Cada ficha só
 * vai pras seguradoras do produto dela (api_seguradoras_da_ficha).
 *
 * Cada seguradora é identificada por um slug (api_cotacoes.seguradora) e
 * aponta pras funções do seu adapter em src/helpers/api/seguradoras/<slug>.php.
 * Integrar uma seguradora nova = escrever o adapter seguindo o mesmo
 * contrato e adicionar uma entrada aqui — o resto do fluxo (fila, tela,
 * listagens, encaminhamento pra MPS) não muda.
 *
 * Contrato do adapter (todas devolvem ['ok' => bool, 'erro' => ?string, ...]):
 *   configurada()                                        → bool: credenciais no .env (sem isso a seguradora fica desligada)
 *   atende(array $ficha)                                 → bool: a seguradora cota esse tipo de ficha?
 *   cotar(array $ficha, array $pretendentes)            → resultado de cotação
 *   consultar(string $cotacaoId)                         → resultado de cotação
 *   reanalisar(string $cotacaoId, string $inicio, string $fim, array $ficha, array $solidarios)
 *                                                        → resultado de cotação + 'solidarios'
 *   emitir(array $cotacao, array $ficha, array $proprietario, string $inicio, string $fim)
 *                                                        → proposta_id, apolice_id, apolice_numero, apolice_status
 *   consultar_apolice(string $apoliceId)                 → apolice_numero, apolice_status
 *   biometria(string $cotacaoId)                         → url
 *   documento(string $tipo, string $id)                  → conteudo (PDF) | pendente
 *
 * "Resultado de cotação" = status (código interno, ver api/status.php),
 * status_seguradora, cotacao_externa_id, numero_cotacao, premio_total,
 * premio_liquido, iof, forma_pagamento, parcelas, cobertura, plano,
 * vigencia_inicio, vigencia_fim, detalhes_json [, tentativas_anteriores].
 * Falha: 'erro' + 'erro_codigo' (ver api_erro_codigos() em api/status.php).
 */

require_once __DIR__ . '/seguradoras/pottencial.php';
require_once __DIR__ . '/seguradoras/too.php';
require_once __DIR__ . '/seguradoras/pottencial_pj.php';
require_once __DIR__ . '/seguradoras/pottencial_res.php';
require_once __DIR__ . '/seguradoras/too_res.php';

/**
 * Todas as seguradoras integradas (ligadas ou não) — pra mostrar nome/logo
 * de cotações já feitas mesmo se a seguradora for desligada depois.
 */
function api_seguradoras_catalogo(): array
{
    return [
        'pottencial' => [
            'nome'  => 'Pottencial',
            'sigla' => 'PT',
            'cor'   => '#F26522',
            'logo'  => '/assets/img/api/logos/seguradoras/logo-pottencial.svg',
            'adapter' => [
                'atende'            => 'pottencial_atende',
                'cotar'             => 'pottencial_cotar',
                'consultar'         => 'pottencial_consultar',
                'reanalisar'        => 'pottencial_reanalisar',
                'emitir'            => 'pottencial_emitir',
                'consultar_apolice' => 'pottencial_consultar_apolice',
                'biometria'         => 'pottencial_biometria',
                'documento'         => 'pottencial_documento',
            ],
        ],
        // Pottencial Fiança PJ (pretendente CNPJ) — produto/rotas próprios na
        // Pottencial; pro cliente é a mesma "Pottencial". 'rotulo' separa as
        // duas onde a MPS compara (Financeiro). Sem reanálise nem biometria.
        'pottencial_pj' => [
            'nome'   => 'Pottencial',
            'rotulo' => 'Pottencial PJ',
            'sigla'  => 'PT',
            'cor'    => '#F26522',
            'logo'   => '/assets/img/api/logos/seguradoras/logo-pottencial.svg',
            'adapter' => [
                'configurada'       => 'pottencial_pj_configurada',
                'atende'            => 'pottencial_pj_atende',
                'cotar'             => 'pottencial_pj_cotar',
                'consultar'         => 'pottencial_pj_consultar',
                'emitir'            => 'pottencial_pj_emitir',
                'consultar_apolice' => 'pottencial_pj_consultar_apolice',
                'documento'         => 'pottencial_pj_documento',
            ],
        ],
        // ── Seguro Residencial ──
        'pottencial_res' => [
            'produto' => 'residencial',
            'nome'    => 'Pottencial',
            'rotulo'  => 'Pottencial Residencial',
            'sigla'   => 'PT',
            'cor'     => '#F26522',
            'logo'    => '/assets/img/api/logos/seguradoras/logo-pottencial.svg',
            'adapter' => [
                'configurada'       => 'pottencial_res_configurada',
                'atende'            => 'pottencial_res_atende',
                'cotar'             => 'pottencial_res_cotar',
                'consultar'         => 'pottencial_res_consultar',
                'emitir'            => 'pottencial_res_emitir',
                'consultar_apolice' => 'pottencial_res_consultar_apolice',
                'documento'         => 'pottencial_res_documento',
            ],
        ],
        'too_res' => [
            'produto' => 'residencial',
            'nome'    => 'Too Seguros',
            'rotulo'  => 'Too Residencial',
            'sigla'   => 'TO',
            'cor'     => '#00BABE',
            'logo'    => '/assets/img/api/logos/seguradoras/logo-too.svg',
            'adapter' => [
                'configurada'       => 'too_res_configurada',
                'atende'            => 'too_res_atende',
                'cotar'             => 'too_res_cotar',
                'consultar'         => 'too_res_consultar',
                'emitir'            => 'too_res_emitir',
                'consultar_apolice' => 'too_res_consultar_apolice',
                'documento'         => 'too_res_documento',
            ],
        ],
        // Too Seguros: só pessoa física residencial (too_atende).
        'too' => [
            'nome'  => 'Too Seguros',
            'sigla' => 'TO',
            'cor'   => '#00BABE',
            'logo'  => '/assets/img/api/logos/seguradoras/logo-too.svg',
            'adapter' => [
                'configurada'       => 'too_configurada',
                'atende'            => 'too_atende',
                'cotar'             => 'too_cotar',
                'consultar'         => 'too_consultar',
                'emitir'            => 'too_emitir',
                'consultar_apolice' => 'too_consultar_apolice',
                'biometria'         => 'too_biometria',
                'documento'         => 'too_documento',
            ],
        ],
    ];
}

/** Seguradoras ligadas (com credenciais no .env), na ordem em que aparecem. */
function api_seguradoras(): array
{
    return array_filter(api_seguradoras_catalogo(), static function (array $s): bool {
        $configurada = $s['adapter']['configurada'] ?? null;
        return $configurada === null || $configurada();
    });
}

/**
 * Slugs das seguradoras ligadas que cotam ESSA ficha (ex: a Too só pessoa
 * física residencial). É pra essas que a ficha é enviada.
 *
 * @return string[]
 */
function api_seguradoras_da_ficha(array $ficha): array
{
    $produto = (string) ($ficha['produto'] ?? 'fianca');
    return array_keys(array_filter(api_seguradoras(), static function (array $s) use ($ficha, $produto): bool {
        if (($s['produto'] ?? 'fianca') !== $produto) {
            return false;
        }
        $atende = $s['adapter']['atende'] ?? null;
        return $atende === null || $atende($ficha);
    }));
}

function api_seguradora(string $slug): ?array
{
    return api_seguradoras_catalogo()[$slug] ?? null;
}

function api_seguradora_nome(string $slug): string
{
    return api_seguradora($slug)['nome'] ?? $slug;
}

/** Nome que distingue variantes da mesma seguradora (ex: "Pottencial PJ") — telas da MPS. */
function api_seguradora_rotulo(string $slug): string
{
    return api_seguradora($slug)['rotulo'] ?? api_seguradora_nome($slug);
}

/** Função do adapter pra uma operação (ver contrato acima), ou null se a seguradora não suporta. */
function api_adapter(string $slug, string $operacao): ?string
{
    return api_seguradora($slug)['adapter'][$operacao] ?? null;
}

/** Pasta de cache da API (token das seguradoras, PDFs, estado do cron). */
function api_cache_dir(string $sub = ''): string
{
    $dir = BASE_PATH . '/storage/cache' . ($sub !== '' ? '/' . $sub : '');
    // @: sem permissão de escrita só deixa de cachear — não pode virar Warning.
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    return $dir;
}
