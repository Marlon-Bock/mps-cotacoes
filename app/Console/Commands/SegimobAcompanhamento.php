<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Acompanhamento da Segimob em segundo plano: reconsulta o resultado das
 * seguradoras das fichas que ainda esperam resposta, pra tela de
 * seguradoras atualizar sozinha (ela só lê o banco — segimob/situacao).
 *
 * Roda a cada minuto pelo agendador (routes/console.php) — era o
 * cron/segimob-acompanhamento.php do sistema segimob/.
 *
 * Por quê: a Segimob não avisa (sem webhook) — a ficha nasce "orçamento
 * solicitado / em análise" e cada seguradora responde segundos ou minutos
 * depois (análise manual pode levar horas).
 *
 * Ritmo pra não martelar a Segimob:
 *   - fichas enviadas há menos de RECENTE_MIN: a cada ~INTERVALO_SEG (várias
 *     passadas dentro do mesmo minuto) — é quando a tela está aberta;
 *   - mais antigas: no máximo a cada ANTIGA_MIN minutos;
 *   - só fichas ainda em análise ('Cotação API') enviadas nos últimos 30 dias
 *     (o resultado pré-aprova ou recusa a ficha e ela sai daqui);
 *   - lock: nunca roda duas instâncias ao mesmo tempo;
 *   - orçamento de tempo: para antes de ORCAMENTO_SEG (próximo minuto assume).
 *
 * Envio em segundo plano: a ficha é enviada depois de
 * responder a imobiliária. Se o envio falhou por motivo técnico (Segimob fora
 * do ar, tempo esgotado) ou o processo morreu antes de gravar, reenvia aqui —
 * no máximo a cada REENVIO_MIN minutos e só nas últimas 24h. Erro de DADO
 * não reenvia: a imobiliária corrige ("Corrigir dados").
 *
 * Use --dry-run para só listar o que seria consultado, sem gravar nada.
 */
class SegimobAcompanhamento extends Command
{
    protected $signature = 'segimob:acompanhamento {--dry-run : Só lista o que faria, sem gravar}';

    protected $description = 'Reconsulta na Segimob as fichas de Fiança em análise nas seguradoras; reenvia as que falharam.';

    private const ORCAMENTO_SEG      = 50;
    private const INTERVALO_SEG      = 10;
    private const RECENTE_MIN        = 30;
    private const ANTIGA_MIN         = 5;
    private const REENVIO_MIN        = 5;

    public function handle(): int
    {
        // Sem credenciais no .env não há o que acompanhar.
        if (!segimob_configurada()) {
            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $log    = fn(string $m) => $this->line('[' . date('Y-m-d H:i:s') . '] ' . $m);
        $inicio = time();

        $lock = fopen(BASE_PATH . '/storage/cache/segimob-acompanhamento.lock', 'c');
        if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
            return self::SUCCESS;
        }
        @chmod(BASE_PATH . '/storage/cache/segimob-acompanhamento.lock', 0666);

        set_time_limit(120);

        // ── Envio que não chegou na Segimob (falha técnica ou processo interrompido) ──
        $semEnvio = db_fetch_all(
            "SELECT f.id, f.protocolo, sf.erro
             FROM seguro_fianca f
             LEFT JOIN segimob_fichas sf ON sf.seguro_fianca_id = f.id
             WHERE f.status = ? AND f.origem = ? AND sf.codigo_seguro IS NULL
               AND f.data_cadastro >= NOW() - INTERVAL 1 DAY
               AND COALESCE(sf.atualizado_em, f.data_cadastro) <= NOW() - INTERVAL " . self::REENVIO_MIN . " MINUTE
             ORDER BY f.data_cadastro DESC",
            ['ss', SEGIMOB_STATUS_FICHA, SEGIMOB_ORIGEM]
        );
        foreach ($semEnvio as $f) {
            if (time() - $inicio >= self::ORCAMENTO_SEG || segimob_erro_de_dados($f['erro'])) continue;
            if ($dryRun) {
                $log("Reenviaria a ficha {$f['protocolo']}");
                continue;
            }
            $r = segimob_cotar_ficha((int) $f['id']);
            $log("Ficha {$f['protocolo']}: " . ($r['ok'] ? 'enviada para as seguradoras.' : 'reenvio falhou: ' . ($r['erro'] ?? '')));
        }

        /** Fichas que ainda esperam alguma seguradora e estão na vez de consultar. */
        $pendentes = static function (): array {
            $fichas = db_fetch_all(
                "SELECT sf.seguro_fianca_id, f.protocolo,
                        sf.enviado_em >= NOW() - INTERVAL " . self::RECENTE_MIN . " MINUTE AS recente,
                        sf.atualizado_em <= NOW() - INTERVAL " . self::ANTIGA_MIN . " MINUTE AS na_vez
                 FROM segimob_fichas sf
                 JOIN seguro_fianca f ON f.id = sf.seguro_fianca_id
                 WHERE sf.codigo_seguro IS NOT NULL AND f.status = ?
                   AND sf.enviado_em >= NOW() - INTERVAL 30 DAY
                 ORDER BY sf.enviado_em DESC",
                ['s', SEGIMOB_STATUS_FICHA]
            );
            return array_values(array_filter($fichas, static fn($f): bool => ($f['recente'] || $f['na_vez'])
                && segimob_aguardando(segimob_ficha((int) $f['seguro_fianca_id']), segimob_cotacoes_da_ficha((int) $f['seguro_fianca_id']))));
        };

        $passada = 0;
        do {
            if ($passada++ > 0) {
                sleep(self::INTERVALO_SEG);
            }
            $fichas = $pendentes();
            // Depois da 1ª passada, só as recentes (as antigas já foram nesta rodada).
            if ($passada > 1) {
                $fichas = array_filter($fichas, static fn($f) => $f['recente']);
            }

            foreach ($fichas as $f) {
                if (time() - $inicio >= self::ORCAMENTO_SEG) break 2;
                $fiancaId = (int) $f['seguro_fianca_id'];

                if ($dryRun) {
                    $log("Consultaria a ficha {$f['protocolo']}");
                    continue;
                }

                $antes = segimob_assinatura(segimob_cotacoes_da_ficha($fiancaId));
                try {
                    $r = segimob_atualizar_status($fiancaId);
                } catch (Throwable $e) {
                    $r = ['ok' => false, 'erro' => $e->getMessage()];
                }
                if (!$r['ok']) {
                    $log("Falha ao consultar a ficha {$f['protocolo']}: " . ($r['erro'] ?? ''));
                    continue;
                }
                if (segimob_assinatura(segimob_cotacoes_da_ficha($fiancaId)) !== $antes) {
                    $log("Ficha {$f['protocolo']}: resultado das seguradoras mudou.");
                }
            }
        } while (!$dryRun && $fichas && time() - $inicio + self::INTERVALO_SEG < self::ORCAMENTO_SEG);

        return self::SUCCESS;
    }
}
