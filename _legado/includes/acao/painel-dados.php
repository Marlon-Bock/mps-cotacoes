<?php
/**
 * GET /api/painel/dados
 * Números do painel de TV (/painel): contagem por status nos 4 produtos e as
 * cotações mais recentes — usadas pelo painel pra detectar cotação nova e
 * disparar o alerta com som.
 *
 * Acesso público: a TV que exibe o painel não faz login.
 */

// Polling (painel.js, a cada 15s na TV) — bootstrap.php sempre abre sessão
// mesmo em página pública, e esse endpoint não usa nem escreve nela. Fecha
// logo cedo pra liberar o lock do arquivo de sessão.
session_write_close();

/**
 * Produtos e as diferenças de schema entre as tabelas (a data de criação tem
 * nome diferente em título/condominial, herdado do sistema legado).
 */
$produtos = [
    'fianca'      => ['label' => 'Fiança',      'tabela' => 'seguro_fianca',        'data' => 'data_cadastro'],
    'auto'        => ['label' => 'Auto',        'tabela' => 'seguro_auto',          'data' => 'data_cadastro'],
    'titulo'      => ['label' => 'Título',      'tabela' => 'titulo_capitalizacao', 'data' => 'criado_em'],
    'condominial' => ['label' => 'Condominial', 'tabela' => 'seguro_condominial',   'data' => 'criado_em'],
];

$estagios = ['pre_analise', 'aguardando', 'em_analise', 'aprovados', 'concluidos', 'recusados', 'desistencias'];

$zerado = array_fill_keys($estagios, 0);
$totais = $zerado;
$por_produto = [];
$hoje_total  = 0;

foreach ($produtos as $key => $p) {
    $linha = $zerado + ['key' => $key, 'label' => $p['label'], 'total' => 0, 'hoje' => 0];

    try {
        $rows = db_fetch_all("SELECT status, COUNT(*) AS total FROM {$p['tabela']} GROUP BY status");
    } catch (Throwable $e) {
        $rows = [];   // tabela opcional (ambientes antigos) — não derruba o painel
    }

    foreach ($rows as $r) {
        $estagio = classificar_status_cotacao((string) ($r['status'] ?? ''));
        if ($estagio === null || !isset($linha[$estagio])) {
            continue;
        }
        $n = (int) $r['total'];
        $linha[$estagio] += $n;
        $totais[$estagio] += $n;
        $linha['total']   += $n;
    }

    try {
        $h = db_fetch_one("SELECT COUNT(*) AS total FROM {$p['tabela']} WHERE DATE({$p['data']}) = CURDATE()");
        $linha['hoje'] = (int) ($h['total'] ?? 0);
    } catch (Throwable $e) {}

    $hoje_total += $linha['hoje'];
    $por_produto[] = $linha;
}

// ── Últimas cotações cadastradas (fonte do alerta de cotação nova) ────────────
$partes = [];
foreach ($produtos as $key => $p) {
    $partes[] = "SELECT '{$key}' AS produto, t.protocolo, t.status, t.{$p['data']} AS criado_em,
                        COALESCE(im.nome, '') AS imobiliaria
                   FROM {$p['tabela']} t
                   LEFT JOIN imobiliarias im ON im.id = t.imobiliaria_id
                  WHERE t.protocolo IS NOT NULL AND t.protocolo <> ''";
}

$recentes = [];
try {
    $recentes = db_fetch_all(
        '(' . implode(') UNION ALL (', $partes) . ') ORDER BY criado_em DESC LIMIT 12'
    );
} catch (Throwable $e) {
    $recentes = [];
}

$lista = [];
foreach ($recentes as $r) {
    $ts = $r['criado_em'] ? strtotime($r['criado_em']) : null;
    $lista[] = [
        'produto'       => $r['produto'],
        'produto_label' => $produtos[$r['produto']]['label'] ?? $r['produto'],
        'protocolo'     => $r['protocolo'],
        'status'        => $r['status'],
        'imobiliaria'   => $r['imobiliaria'] !== '' ? $r['imobiliaria'] : '—',
        'criado_em'     => $r['criado_em'],
        'hora'          => $ts ? date('d/m H:i', $ts) : '—',
    ];
}

json_response([
    'success'     => true,
    'atualizado'  => date('H:i:s'),
    'hoje'        => $hoje_total,
    'totais'      => $totais,
    'por_produto' => $por_produto,
    'recentes'    => $lista,
]);
