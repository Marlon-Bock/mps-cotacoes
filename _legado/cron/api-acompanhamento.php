<?php
/**
 * Acompanhamento da API das seguradoras em segundo plano: cotações que
 * ainda dependem da seguradora (análise manual, biometria) e apólices em
 * emissão — mais o cache dos PDFs.
 *
 * Roda a cada 2 minutos via cron:
 *   *\/2 * * * *  php /u01/home/devmps/www/cron/api-acompanhamento.php >> /var/log/mps-api-acompanhamento.log 2>&1
 *
 * Por quê: as seguradoras não avisam (sem webhook) — a apólice nasce "em
 * emissão" e só fica "emitida" (com PDF) minutos depois; a análise manual
 * do subscritor (comum depois de uma reanálise) e a biometria também levam
 * tempo. Aqui:
 *   - reconsulta cada cotação em análise / aguardando biometria; quando o
 *     status muda, grava e avisa a imobiliária ou encaminha pra MPS
 *     (api_aplicar_status_cotacao — um aviso só);
 *   - reconsulta cada apólice em emissão; ao ficar emitida, notifica
 *     (api_gravar_status_apolice — uma notificação só);
 *   - baixa os PDFs da proposta/apólice pro cache (a seguradora leva ~50s
 *     pra gerar cada um), pro clique na tela abrir na hora.
 *
 * Proteções pra não pesar no servidor nem martelar a seguradora:
 *   - lock: nunca roda duas instâncias ao mesmo tempo;
 *   - orçamento de tempo: para de iniciar chamadas depois de ORCAMENTO_SEG;
 *   - backoff: cada falha espera 2, 4, 8... até 60 min antes de tentar de
 *     novo; PDF desiste depois de MAX_TENTATIVAS_PDF (o clique ainda busca);
 *   - janela fixa: só fichas enviadas nos últimos 30 dias; fichas
 *     encerradas (desistência, concluída...) ficam de fora.
 *
 * Use --dry-run para só listar o que seria consultado, sem gravar nada.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Este script roda somente via CLI.\n");
}

require_once dirname(__DIR__) . '/src/bootstrap.php';
require_once BASE_PATH . '/src/helpers/api.php';

const ORCAMENTO_SEG      = 80;
// Com backoff de até 60 min entre tentativas, ~20 tentativas ≈ 1 dia.
const MAX_TENTATIVAS_PDF = 20;
const BACKOFF_MAX_SEG    = 3600;

$dryRun = in_array('--dry-run', $argv ?? [], true);
$log    = static fn(string $m) => print('[' . date('Y-m-d H:i:s') . '] ' . $m . PHP_EOL);
$inicio = time();

$lock = fopen(api_cache_dir() . '/api-acompanhamento.lock', 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
    exit(0);
}

set_time_limit(300);

// Estado do backoff: { chave: { tentativas, proxima } } — só guarda o que
// está falhando (sucesso remove a chave).
$estadoArquivo = api_cache_dir() . '/api-acompanhamento-estado.json';
$estado = is_file($estadoArquivo) ? (json_decode((string) file_get_contents($estadoArquivo), true) ?: []) : [];

$podeTentar = static fn(string $chave): bool => ($estado[$chave]['proxima'] ?? 0) <= time();
$falhou = static function (string $chave) use (&$estado): int {
    $n = ($estado[$chave]['tentativas'] ?? 0) + 1;
    $estado[$chave] = ['tentativas' => $n, 'proxima' => time() + min(BACKOFF_MAX_SEG, 60 * (2 ** $n))];
    return $n;
};
$deuCerto = static function (string $chave) use (&$estado): void { unset($estado[$chave]); };
$semTempo = static fn(): bool => time() - $inicio >= ORCAMENTO_SEG;

$fichaAtiva = static fn(array $l): bool => !api_ficha_encerrada(['status' => $l['status_ficha']]);

// ── Cotações aguardando a seguradora (análise manual / biometria) ──
$pendentes = db_fetch_all(
    "SELECT c.seguro_fianca_id, c.seguradora, c.cotacao_externa_id, c.numero_cotacao, c.status, f.status AS status_ficha
     FROM api_cotacoes c
     JOIN (
         SELECT id, status, api_enviado_em FROM seguro_fianca
         UNION ALL
         SELECT id, status, api_enviado_em FROM seguro_residencial
     ) f ON f.id = c.seguro_fianca_id
     WHERE c.cotacao_externa_id IS NOT NULL AND c.apolice_id IS NULL
       AND c.status IN ('em_analise', 'aguardando_biometria')
       AND f.api_enviado_em >= NOW() - INTERVAL 30 DAY
     ORDER BY c.id DESC"
);

foreach (array_filter($pendentes, $fichaAtiva) as $c) {
    if ($semTempo()) break;
    $funcao = api_adapter((string) $c['seguradora'], 'consultar');
    $chave  = "cotacao:{$c['cotacao_externa_id']}";
    if (!$funcao || !$podeTentar($chave)) continue;

    if ($dryRun) {
        $log("Consultaria cotação {$c['numero_cotacao']} ({$c['seguradora']}, status atual: {$c['status']})");
        continue;
    }

    try {
        $r = $funcao((string) $c['cotacao_externa_id']);
    } catch (Throwable $e) {
        $r = ['ok' => false, 'erro' => $e->getMessage()];
    }
    if (empty($r['ok'])) {
        $n = $falhou($chave);
        $log("Falha ao consultar cotação {$c['numero_cotacao']} ({$n}ª): " . ($r['erro'] ?? ''));
        continue;
    }
    $deuCerto($chave);

    api_aplicar_status_cotacao((int) $c['seguro_fianca_id'], (string) $c['seguradora'], $r);
    if ($r['status'] !== $c['status']) {
        $log("Cotação {$c['numero_cotacao']}: {$c['status']} → {$r['status']}");
    }
}

// ── Apólices em emissão + cache dos PDFs ──
$emitidas = db_fetch_all(
    "SELECT c.seguro_fianca_id, c.seguradora, c.proposta_id, c.apolice_id, c.apolice_numero, c.apolice_status, f.status AS status_ficha
     FROM api_cotacoes c
     JOIN (
         SELECT id, status, api_enviado_em FROM seguro_fianca
         UNION ALL
         SELECT id, status, api_enviado_em FROM seguro_residencial
     ) f ON f.id = c.seguro_fianca_id
     WHERE c.apolice_id IS NOT NULL
       AND f.api_enviado_em >= NOW() - INTERVAL 30 DAY
     ORDER BY (c.apolice_status <=> 'emitida'), c.id DESC"
);

foreach ($emitidas as $l) {
    if ($semTempo()) {
        $log('Orçamento de tempo esgotado — o restante fica pra próxima rodada.');
        break;
    }

    $fiancaId   = (int) $l['seguro_fianca_id'];
    $seguradora = (string) $l['seguradora'];
    $emitida    = $l['apolice_status'] === 'emitida';

    if (!$emitida) {
        $funcao = api_adapter($seguradora, 'consultar_apolice');
        $chave  = "apolice:{$l['apolice_id']}";
        if (!$funcao || !$podeTentar($chave)) continue;

        if ($dryRun) {
            $log("Consultaria apólice {$l['apolice_numero']} (status atual: {$l['apolice_status']})");
            continue;
        }

        try {
            $r = $funcao((string) $l['apolice_id']);
        } catch (Throwable $e) {
            $r = ['ok' => false, 'erro' => $e->getMessage()];
        }
        if (empty($r['ok'])) {
            $n = $falhou($chave);
            $log("Falha ao consultar apólice {$l['apolice_numero']} ({$n}ª): " . ($r['erro'] ?? ''));
            continue;
        }
        $deuCerto($chave);

        if (api_gravar_status_apolice($fiancaId, $seguradora, (string) $r['apolice_numero'], (string) $r['apolice_status'])) {
            $log("Apólice {$r['apolice_numero']} emitida — notificado.");
        }
        $emitida = $r['apolice_status'] === 'emitida';
    }

    if ($dryRun) continue;

    // Aquece o cache dos PDFs (a apólice só depois de emitida).
    $docs = ['proposta' => (string) $l['proposta_id']];
    if ($emitida) $docs['apolice'] = (string) $l['apolice_id'];

    foreach ($docs as $tipo => $id) {
        $chave = "pdf:{$tipo}:{$id}";
        if ($id === '' || api_documento_em_cache($seguradora, $tipo, $id) || !$podeTentar($chave)) continue;
        if (($estado[$chave]['tentativas'] ?? 0) >= MAX_TENTATIVAS_PDF) continue;
        if ($semTempo()) break 2;

        $doc = api_documento($fiancaId, $seguradora, $tipo, true);
        if ($doc['ok']) {
            $deuCerto($chave);
            $log("PDF em cache: {$tipo} da apólice {$l['apolice_numero']}");
        } else {
            $n = $falhou($chave);
            $log("PDF indisponível ({$n}ª): {$tipo} da apólice {$l['apolice_numero']} — " . ($doc['erro'] ?? ''));
        }
    }
}

if (!$dryRun) {
    @file_put_contents($estadoArquivo, json_encode($estado));
}
