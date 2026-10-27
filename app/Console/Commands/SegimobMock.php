<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Simula a resposta das seguradoras na Segimob — SÓ HOMOLOGAÇÃO.
 *
 * Em homologação ninguém analisa as cotações: elas ficam "Em análise" até
 * alguém responder pelo endpoint de teste da Segimob
 * (POST /fianca/mock/resposta-cotacao). Este comando faz isso por protocolo
 * e já atualiza a ficha no sistema. Era o bin/segimob-mock.php do segimob/
 * (que aqui continua existindo como atalho pra este comando).
 *
 * Uso:
 *   php artisan segimob:mock MPS2026-30916                 → aprova todas
 *   php artisan segimob:mock MPS2026-30916 recusar         → recusa todas
 *   php artisan segimob:mock MPS2026-30916 porto=aprovar tokio=recusar too=aprovar
 *   php artisan segimob:mock MPS2026-30916 reenviar        → reenvia a ficha (código novo) e aprova todas
 *
 * Seguradoras: porto (4), tokio (2), too (31).
 * Atenção: recusada não volta — pra aprovar de novo, use "reenviar".
 */
class SegimobMock extends Command
{
    protected $signature = 'segimob:mock {protocolo : Protocolo da ficha (canal Segimob)} {respostas?* : aprovar | recusar | reenviar | porto=aprovar tokio=recusar too=aprovar}';

    protected $description = 'Homologação: responde pelas seguradoras na Segimob (aprovar/recusar) e atualiza a ficha.';

    private const CODIGOS = ['porto' => 4, 'tokio' => 2, 'too' => 31];
    private const STATUS  = ['aprovar' => 2, 'recusar' => 3];

    public function handle(): int
    {
        if (!segimob_configurada()) {
            $this->error('Segimob não configurada no .env.');
            return self::FAILURE;
        }
        if (!str_contains(segimob_url_base(), '.par.')) {
            $this->error('Recusado: SEGIMOB_API_URL não é a de homologação (' . segimob_url_base() . ').');
            return self::FAILURE;
        }

        $protocolo = (string) $this->argument('protocolo');
        $ficha = db_fetch_one('SELECT id, origem FROM seguro_fianca WHERE protocolo = ?', ['s', $protocolo]);
        if (!$ficha) {
            $this->error("Ficha {$protocolo} não encontrada.");
            return self::FAILURE;
        }
        if (($ficha['origem'] ?? '') !== SEGIMOB_ORIGEM) {
            $this->error("A ficha {$protocolo} não foi cotada pela Segimob (canal: " . ($ficha['origem'] ?: 'manual') . ').');
            return self::FAILURE;
        }
        $fiancaId = (int) $ficha['id'];

        // O que responder pra cada seguradora.
        $respostas = array_fill_keys(array_keys(self::CODIGOS), 'aprovar');
        $reenviar  = false;
        foreach ((array) $this->argument('respostas') as $arg) {
            if ($arg === 'reenviar') {
                $reenviar = true;
            } elseif (isset(self::STATUS[$arg])) {
                $respostas = array_fill_keys(array_keys(self::CODIGOS), $arg);
            } elseif (preg_match('/^(\w+)=(aprovar|recusar)$/', $arg, $m) && isset(self::CODIGOS[$m[1]])) {
                $respostas[$m[1]] = $m[2];
            } else {
                $this->error("Argumento inválido: {$arg}");
                return self::INVALID;
            }
        }

        if ($reenviar) {
            $r = segimob_cotar_ficha($fiancaId);
            if (!$r['ok']) {
                $this->error('Falha ao reenviar: ' . $r['erro']);
                return self::FAILURE;
            }
            $this->line('Ficha reenviada pra Segimob.');
            sleep(3);
        }

        $codigoSeguro = (int) (segimob_ficha($fiancaId)['codigo_seguro'] ?? 0);
        if (!$codigoSeguro) {
            $this->error("A ficha {$protocolo} não tem cotação na Segimob (use \"reenviar\").");
            return self::FAILURE;
        }

        segimob_usar_ficha($fiancaId);
        foreach ($respostas as $nome => $acao) {
            $r = segimob_request('POST', '/fianca/mock/resposta-cotacao', [
                'codigoSeguro'        => $codigoSeguro,
                'codigoSeguradora'    => self::CODIGOS[$nome],
                'codigoCotacaoStatus' => self::STATUS[$acao],
                'isPreAprovado'       => false,
            ]);
            $this->line(str_pad($nome, 6) . ' → ' . $acao . ': ' . ($r['ok'] ? 'ok' : $r['erro']));
        }

        // A Segimob processa em segundo plano — espera e atualiza a ficha.
        $this->line('Aguardando a Segimob processar...');
        for ($tentativa = 0; $tentativa < 12; $tentativa++) {
            sleep(5);
            segimob_atualizar_status($fiancaId);
            $pendentes = array_filter(segimob_cotacoes_da_ficha($fiancaId), fn($c) => segimob_situacao($c) === 'pendente');
            if (!$pendentes) break;
        }

        foreach (segimob_cotacoes_da_ficha($fiancaId) as $c) {
            $this->line('  ' . str_pad(segimob_seguradora_nome((string) $c['seguradora_nome']), 14) . ' ' . $c['status_descricao']
                . ($c['premio_total'] ? ' — R$ ' . number_format((float) $c['premio_total'], 2, ',', '.') : ''));
        }
        $this->info('Pronto — recarregue a tela das seguradoras.');

        return self::SUCCESS;
    }
}
