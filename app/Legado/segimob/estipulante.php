<?php
/**
 * Estipulante: o código da imobiliária na Segimob (sem ele a ficha não é
 * cotada lá). O cadastro da imobiliária escolhe o código numa lista das
 * imobiliárias com seguro fiança habilitado na Segimob.
 */

/**
 * Códigos que podem ficar em mais de uma imobiliária: a REGENTE (11538) é a
 * imobiliária de teste da homologação, usada pelas imobiliárias de teste.
 */
const SEGIMOB_ESTIPULANTES_COMPARTILHADOS = [11538];

/**
 * Imobiliárias com fiança habilitado na Segimob pro cadastro — sem os códigos
 * que já estão em OUTRA imobiliária do sistema (o da própria imobiliária em
 * edição continua aparecendo), exceto os compartilhados. A lista da Segimob
 * fica 10 min em cache: muda pouco e a tela pede a cada abertura.
 *
 * @return array{ok: bool, erro?: string, itens?: list<array{codigo: int, nome: string}>}
 */
function segimob_imobiliarias_fianca(int $imobiliariaAtual): array
{
    $cache = BASE_PATH . '/storage/cache/segimob-imobiliarias-fianca.json';
    $lista = is_file($cache) && time() - filemtime($cache) < 600
        ? json_decode((string) file_get_contents($cache), true)
        : null;

    if (!is_array($lista)) {
        segimob_estipulante(null, true); // a consulta de domínio não depende de imobiliária
        $r = segimob_request('GET', '/dominio/imobiliaria?' . http_build_query(['tipoProduto' => 'FIANCA']));
        if (!$r['ok']) {
            error_log('[segimob/estipulante] ' . ($r['erro'] ?? ''));
            return ['ok' => false, 'erro' => 'Não foi possível consultar agora. Tente de novo em instantes.'];
        }
        $lista = [];
        foreach (is_array($r['data']) ? $r['data'] : [] as $i) {
            if (empty($i['id'])) {
                continue;
            }
            $lista[(int) $i['id']] = [
                'codigo' => (int) $i['id'],
                'nome'   => mb_convert_case(mb_strtolower(trim((string) ($i['nome'] ?? ''))), MB_CASE_TITLE),
            ];
        }
        $lista = array_values($lista);
        usort($lista, fn($a, $b) => strcmp($a['nome'], $b['nome']));
        @file_put_contents($cache, json_encode($lista, JSON_UNESCAPED_UNICODE));
        @chmod($cache, 0666);   // site e cron gravam o mesmo cache
    }

    $usados = array_map('intval', array_column(db_fetch_all(
        'SELECT segimob_estipulante FROM imobiliarias WHERE segimob_estipulante IS NOT NULL AND id <> ?',
        ['i', $imobiliariaAtual]
    ), 'segimob_estipulante'));

    return [
        'ok'    => true,
        'itens' => array_values(array_filter(
            $lista,
            fn($i) => !in_array($i['codigo'], $usados, true) || in_array($i['codigo'], SEGIMOB_ESTIPULANTES_COMPARTILHADOS, true)
        )),
    ];
}
