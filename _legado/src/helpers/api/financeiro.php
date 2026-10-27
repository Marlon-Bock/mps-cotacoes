<?php
/**
 * Financeiro (MPS) — contratações feitas pela API das seguradoras (Pottencial,
 * Too): prêmio, IOF, comissão da corretora e a situação da apólice. Página
 * /admin/financeiro, o Excel e o PDF dela (mesma tela do dev-cotacoes-2,
 * com a Segimob).
 *
 * Fonte: api_cotacoes com apólice (apolice_id) + a ficha.
 *
 * Comissão: a Pottencial informa na cotação (% e R$ — commissionedAgents);
 * na Too é o percentual que mandamos no cálculo (TOO_COMISSAO) sobre o
 * prêmio líquido. As seguradoras da API não informam a situação do
 * pagamento — a "Situação" é a da apólice: em emissão, emitida (falta a MPS
 * enviar ao cliente), enviada ao cliente, cancelada.
 */

/** Situações da apólice no Financeiro (filtro e coluna). */
const API_FINANCEIRO_SITUACOES = [
    'em_emissao' => 'Em emissão',
    'a_enviar'   => 'Emitida — enviar ao cliente',
    'enviada'    => 'Enviada ao cliente',
    'cancelada'  => 'Cancelada',
];

/**
 * @param array{de?:string, ate?:string, seguradora?:string, imobiliaria?:int, situacao?:string, busca?:string} $filtros
 *        de/ate = Y-m-d (data da contratação); seguradora = slug; situacao = chave de API_FINANCEIRO_SITUACOES;
 *        busca = protocolo, pretendente ou nº da apólice.
 * @return array<int, array<string, mixed>>
 */
function api_financeiro_linhas(array $filtros = []): array
{
    $sql = "SELECT c.seguro_fianca_id, c.seguradora, c.numero_cotacao, c.apolice_numero, c.apolice_status, c.contratado_em,
                   c.premio_total, c.premio_liquido, c.iof, c.forma_pagamento, c.parcelas, c.plano, c.cobertura,
                   c.vigencia_inicio, c.vigencia_fim, c.comissao_percentual, c.comissao_valor,
                   f.protocolo, f.nome_completo, f.tipo_ficha, f.status AS status_ficha, f.imobiliaria_id,
                   f.api_apolice_enviada_em, i.nome AS imobiliaria
            FROM api_cotacoes c
            JOIN (
                SELECT id, protocolo, nome_completo, tipo_ficha, status, imobiliaria_id, api_apolice_enviada_em FROM seguro_fianca
                UNION ALL
                SELECT id, protocolo, nome_completo, 'seguro_residencial', status, imobiliaria_id, api_apolice_enviada_em FROM seguro_residencial
            ) f ON f.id = c.seguro_fianca_id
            LEFT JOIN imobiliarias i ON i.id = f.imobiliaria_id
            WHERE c.apolice_id IS NOT NULL";
    $tipos   = '';
    $valores = [];
    if (!empty($filtros['de'])) {
        $sql .= ' AND c.contratado_em >= ?';
        $tipos .= 's';
        $valores[] = $filtros['de'] . ' 00:00:00';
    }
    if (!empty($filtros['ate'])) {
        $sql .= ' AND c.contratado_em <= ?';
        $tipos .= 's';
        $valores[] = $filtros['ate'] . ' 23:59:59';
    }
    if (!empty($filtros['seguradora'])) {
        $sql .= ' AND c.seguradora = ?';
        $tipos .= 's';
        $valores[] = (string) $filtros['seguradora'];
    }
    if (!empty($filtros['imobiliaria'])) {
        $sql .= ' AND f.imobiliaria_id = ?';
        $tipos .= 'i';
        $valores[] = (int) $filtros['imobiliaria'];
    }
    if (($filtros['busca'] ?? '') !== '') {
        $sql .= ' AND (f.protocolo LIKE ? OR f.nome_completo LIKE ? OR c.apolice_numero LIKE ?)';
        $tipos .= 'sss';
        $like = '%' . $filtros['busca'] . '%';
        array_push($valores, $like, $like, $like);
    }
    $sql .= ' ORDER BY c.contratado_em DESC';

    $linhas = [];
    foreach (db_fetch_all($sql, $valores ? [$tipos, ...$valores] : []) as $r) {
        $situacao = match (true) {
            in_array($r['apolice_status'], ['cancelada', 'expirada'], true) => 'cancelada',
            $r['apolice_status'] !== 'emitida'                              => 'em_emissao',
            api_apolice_enviada(['id' => $r['seguro_fianca_id'], 'status' => $r['status_ficha'], 'api_apolice_enviada_em' => $r['api_apolice_enviada_em']]) => 'enviada',
            default                                                         => 'a_enviar',
        };
        if (($filtros['situacao'] ?? '') !== '' && $filtros['situacao'] !== $situacao) continue;

        $premioTotal   = (float) ($r['premio_total'] ?? 0);
        $premioLiquido = (float) ($r['premio_liquido'] ?? 0);
        $iof           = $r['iof'] !== null ? (float) $r['iof'] : ($premioLiquido > 0 ? $premioTotal - $premioLiquido : 0.0);

        $linhas[] = [
            'fianca_id'      => (int) $r['seguro_fianca_id'],
            'protocolo'      => (string) $r['protocolo'],
            'imobiliaria'    => (string) ($r['imobiliaria'] ?? ''),
            'pretendente'    => (string) $r['nome_completo'],
            'tipo'           => match ($r['tipo_ficha']) {
                'comercial'          => 'Fiança Comercial',
                'seguro_residencial' => 'Seguro Residencial',
                default              => 'Fiança Residencial',
            },
            'seguradora'     => api_seguradora_rotulo((string) $r['seguradora']),
            'seguradora_slug'=> (string) $r['seguradora'],
            'cor'            => (string) (api_seguradora((string) $r['seguradora'])['cor'] ?? '#6b7280'),
            'contratado_em'  => (string) $r['contratado_em'],
            'cotacao'        => (string) ($r['numero_cotacao'] ?? ''),
            'apolice'        => (string) ($r['apolice_numero'] ?? ''),
            'plano'          => (string) ($r['plano'] ?? ''),
            'cobertura'      => (float) ($r['cobertura'] ?? 0),
            'vigencia_inicio'=> $r['vigencia_inicio'],
            'vigencia_fim'   => $r['vigencia_fim'],
            'premio_total'   => $premioTotal,
            'premio_liquido' => $premioLiquido,
            'iof'            => $iof,
            'forma'          => (string) ($r['forma_pagamento'] ?? ''),
            'parcelas'       => max(1, (int) ($r['parcelas'] ?? 1)),
            'comissao_pct'   => $r['comissao_percentual'] !== null ? (float) $r['comissao_percentual'] : null,
            'comissao_valor' => (float) ($r['comissao_valor'] ?? 0),
            'situacao'       => $situacao,
            'situacao_label' => API_FINANCEIRO_SITUACOES[$situacao],
        ];
    }

    return $linhas;
}

/**
 * Totais pros cards e o resumo por seguradora.
 *
 * @return array{qtd:int, premio:float, liquido:float, iof:float, comissao:float, em_emissao:int, a_enviar:int,
 *               por_seguradora: array<string, array{qtd:int, premio:float, liquido:float, comissao:float, cor:string}>}
 */
function api_financeiro_totais(array $linhas): array
{
    $t = ['qtd' => count($linhas), 'premio' => 0.0, 'liquido' => 0.0, 'iof' => 0.0, 'comissao' => 0.0,
          'em_emissao' => 0, 'a_enviar' => 0, 'por_seguradora' => []];

    foreach ($linhas as $l) {
        $t['premio']   += $l['premio_total'];
        $t['liquido']  += $l['premio_liquido'];
        $t['iof']      += $l['iof'];
        $t['comissao'] += $l['comissao_valor'];
        if ($l['situacao'] === 'em_emissao') $t['em_emissao']++;
        if ($l['situacao'] === 'a_enviar') $t['a_enviar']++;

        $s = &$t['por_seguradora'][$l['seguradora']];
        $s ??= ['qtd' => 0, 'premio' => 0.0, 'liquido' => 0.0, 'comissao' => 0.0, 'cor' => $l['cor']];
        $s['qtd']++;
        $s['premio']   += $l['premio_total'];
        $s['liquido']  += $l['premio_liquido'];
        $s['comissao'] += $l['comissao_valor'];
        unset($s);
    }
    uasort($t['por_seguradora'], fn($a, $b) => $b['premio'] <=> $a['premio']);

    return $t;
}

/** Filtros da listagem/Excel/PDF a partir do GET (datas Y-m-d; sem data = todo o período). */
function api_financeiro_filtros(array $get): array
{
    $data = static fn($v): string => is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : '';

    return [
        'de'          => $data($get['de'] ?? ''),
        'ate'         => $data($get['ate'] ?? ''),
        'seguradora'  => api_seguradora((string) ($get['seguradora'] ?? '')) !== null ? (string) $get['seguradora'] : '',
        'imobiliaria' => max(0, (int) ($get['imobiliaria'] ?? 0)),
        'situacao'    => isset(API_FINANCEIRO_SITUACOES[$get['situacao'] ?? '']) ? (string) $get['situacao'] : '',
        'busca'       => mb_substr(trim((string) ($get['busca'] ?? '')), 0, 60),
    ];
}
