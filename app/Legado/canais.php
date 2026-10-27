<?php
/**
 * Canal de cotação — por onde a cotação de cada produto de uma imobiliária é
 * feita. É o ÚNICO lugar que decide isso: o envio da ficha pergunta o canal
 * aqui e entrega pro módulo certo; daí em diante a ficha guarda o canal em
 * seguro_fianca.origem ('manual' | 'api' | 'segimob') e cada tela/ação usa o
 * módulo da origem da ficha.
 *
 *   manual  → ficha vai direto pra fila da MPS, sem integração;
 *   api     → seguradoras diretas (app/Legado/api — Pottencial, Too…);
 *   segimob → agregador Segimob (app/Legado/segimob).
 *
 * Gravado em imobiliaria_canais (uma linha por imobiliária + produto). Sem
 * linha = 'manual'. Pra liberar canal em outro produto (Residencial, Incêndio…),
 * basta incluí-lo em canais_por_produto() — e o envio daquele produto passar a
 * consultar canal_da_imobiliaria().
 */

const CANAL_MANUAL  = 'manual';
const CANAL_API     = 'api';
const CANAL_SEGIMOB = 'segimob';

/**
 * Produtos com canal configurável e os canais que cada um aceita (na ordem
 * em que aparecem no cadastro da imobiliária). O canal API só entra com
 * FEATURE_CANAL_API ligada (por enquanto: só Manual e Segimob) — sem ela,
 * imobiliária que estivesse em API volta a ser Manual (canal_da_imobiliaria).
 *
 * @return array<string, array{label: string, canais: array<string, string>}>
 */
function canais_por_produto(): array
{
    return [
        'fianca' => [
            'label'  => 'Seguro Fiança',
            'canais' => array_filter([
                CANAL_MANUAL  => 'Manual (fila da MPS)',
                CANAL_API     => FEATURE_CANAL_API ? 'API das seguradoras' : null,
                CANAL_SEGIMOB => 'Segimob',
            ]),
        ],
    ];
}

/** Canal configurado da imobiliária pro produto ('manual' se não houver ou se for inválido). */
function canal_da_imobiliaria(int $imobiliariaId, string $produto): string
{
    $validos = canais_por_produto()[$produto]['canais'] ?? null;
    if ($imobiliariaId <= 0 || $validos === null) {
        return CANAL_MANUAL;
    }

    $row = db_fetch_one(
        'SELECT canal FROM imobiliaria_canais WHERE imobiliaria_id = ? AND produto = ?',
        ['is', $imobiliariaId, $produto]
    );
    $canal = (string) ($row['canal'] ?? CANAL_MANUAL);

    return isset($validos[$canal]) ? $canal : CANAL_MANUAL;
}

/**
 * Canais de todos os produtos configuráveis da imobiliária (cadastro).
 *
 * @return array<string, string> produto => canal
 */
function canais_da_imobiliaria(int $imobiliariaId): array
{
    $canais = [];
    foreach (array_keys(canais_por_produto()) as $produto) {
        $canais[$produto] = canal_da_imobiliaria($imobiliariaId, $produto);
    }
    return $canais;
}

/**
 * Grava os canais vindos do cadastro da imobiliária. Ignora produto ou canal
 * desconhecido (valor fora da lista vira 'manual'). Só grava o que mudou —
 * salvar o cadastro sem mexer nos canais não regrava nada (nem aparece na
 * auditoria como alteração). Sem linha já vale 'manual'.
 *
 * @param array<string, string> $canais produto => canal
 */
function canais_salvar(int $imobiliariaId, array $canais): void
{
    foreach (canais_por_produto() as $produto => $cfg) {
        if (!array_key_exists($produto, $canais)) {
            continue;
        }
        $canal = (string) $canais[$produto];
        if (!isset($cfg['canais'][$canal])) {
            $canal = CANAL_MANUAL;
        }
        if ($canal === canal_da_imobiliaria($imobiliariaId, $produto)) {
            continue;
        }
        db_execute(
            'INSERT INTO imobiliaria_canais (imobiliaria_id, produto, canal) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE canal = VALUES(canal)',
            ['iss', $imobiliariaId, $produto, $canal]
        );
    }
}

/** A ficha foi cotada automaticamente (API das seguradoras ou Segimob)? Pelo seguro_fianca.origem. */
function canal_automatico(string $origem): bool
{
    return $origem === CANAL_API || $origem === CANAL_SEGIMOB;
}

/**
 * Filtro "Canal" dos números da MPS (dashboard, Todas as Cotações, Central de
 * Gestão). Produto sem canal configurável (Auto, Título...) conta como manual.
 *
 * @return array<string, string> canal => rótulo
 */
function canais_filtro(): array
{
    return [
        CANAL_MANUAL  => 'Manual',
        CANAL_API     => 'API das seguradoras',
        CANAL_SEGIMOB => 'Segimob',
    ];
}

/** Valor do filtro vindo da URL: um canal conhecido ou '' (todos). */
function canal_filtro_valido(mixed $valor): string
{
    return is_string($valor) && isset(canais_filtro()[$valor]) ? $valor : '';
}

/**
 * Canal de uma cotação qualquer: Fiança e Residencial guardam em `origem`;
 * os demais produtos (e origem vazia) são manuais.
 */
function canal_da_cotacao(string $produto, ?string $origem): string
{
    return in_array($produto, ['fianca', 'residencial'], true) && isset(canais_filtro()[(string) $origem])
        ? (string) $origem
        : CANAL_MANUAL;
}

/** Rótulo do canal pra telas ("API das seguradoras", "Segimob"...). */
function canal_rotulo(string $produto, string $canal): string
{
    return canais_por_produto()[$produto]['canais'][$canal] ?? 'Manual (fila da MPS)';
}

/**
 * Status da ficha pra tela — pelo módulo do canal que a cotou (a ficha em
 * 'Cotação API' mostra a etapa nas seguradoras; o resto passa direto).
 * Mesma assinatura de api_status_exibicao() / segimob_status_exibicao().
 *
 * @return array{label: string, pill: string}
 */
function canal_status_exibicao(string $origem, string $status, string $protocolo, string $tipo = 'fianca', bool $paraMps = true): array
{
    return $origem === CANAL_SEGIMOB
        ? segimob_status_exibicao($status, $protocolo, $tipo, $paraMps)
        : api_status_exibicao($status, $protocolo, $tipo, $paraMps);
}
