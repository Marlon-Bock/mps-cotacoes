<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Worker da fila da API das seguradoras (tabela api_fila): processa
 * cotações, reanálises, emissões e avisos fora do PHP-FPM.
 *
 * O cron dispara a cada minuto, uma instância por "vaga" (FILA_VAGAS):
 *   * * * * *  cd /u01/home/devmps/www && (for i in 1 2 3 4; do php cron/api-fila.php & done; wait) >> /var/log/mps-api-fila.log 2>&1
 *
 * Cada instância pega uma vaga (lock de arquivo — sem vaga livre, sai na
 * hora) e fica ~55s olhando a fila a cada 2s, então um trabalho novo começa
 * em segundos, não no próximo minuto. Cada seguradora cota num trabalho
 * próprio, então com 4 vagas até 4 seguradoras (ou 4 operações) andam em
 * paralelo; o resto espera na fila sem pesar no servidor.
 */
class ApiFila extends Command
{
    protected $signature = 'api:fila';

    protected $description = 'Worker da fila da API das seguradoras (api_fila): cotações, reanálises, emissões e avisos.';

    private const FILA_VAGAS = 4;
    private const FILA_JANELA_SEG = 55;
    private const FILA_INTERVALO_SEG = 2;

    public function handle(): int
    {
        $log = static fn(string $m) => print('[' . date('Y-m-d H:i:s') . '] [' . getmypid() . '] ' . $m . PHP_EOL);

        $lock = null;
        for ($vaga = 1; $vaga <= self::FILA_VAGAS; $vaga++) {
            $f = fopen(api_cache_dir() . "/api-fila-{$vaga}.lock", 'c');
            if ($f && flock($f, LOCK_EX | LOCK_NB)) {
                $lock = $f;
                break;
            }
        }
        if (!$lock) {
            return 0; // todas as vagas ocupadas (trabalho longo da rodada anterior)
        }

        // Um trabalho pode passar dos 55s (emissão ~80s) — sem limite do PHP aqui;
        // cada chamada à seguradora já tem timeout próprio.
        set_time_limit(0);

        $inicio = time();
        $token  = bin2hex(random_bytes(8)) . '-' . getmypid();

        $travados = api_fila_liberar_travados();
        if ($travados > 0) {
            $log("{$travados} trabalho(s) travado(s) marcados como erro.");
        }

        while (time() - $inicio < self::FILA_JANELA_SEG) {
            $job = api_fila_pegar($token);
            if (!$job) {
                sleep(self::FILA_INTERVALO_SEG);
                continue;
            }

            $log("Iniciando #{$job['id']} ({$job['tipo']}, ficha {$job['seguro_fianca_id']})");
            $t = microtime(true);
            try {
                $r = api_fila_executar($job);
            } catch (Throwable $e) {
                $r = ['ok' => false, 'erro' => $e->getMessage()];
            }
            api_fila_concluir((int) $job['id'], $r['ok'], $r['erro'] ?? null);
            $log("Fim #{$job['id']} em " . round(microtime(true) - $t, 1) . 's — ' . ($r['ok'] ? 'ok' : 'erro: ' . ($r['erro'] ?? '')));
        }

        return 0;
    }
}
