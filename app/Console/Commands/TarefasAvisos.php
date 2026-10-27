<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Avisos automáticos de prazo das tarefas (sino + e-mail).
 *
 * Roda uma vez por dia via cron:
 *   0 8 * * *  php /u01/home/devmps/www/cron/tarefas-avisos.php >> /var/log/mps-tarefas.log 2>&1
 *
 * Dois avisos, cada um enviado UMA única vez por tarefa (controle em tarefas_avisos):
 *   - prazo_3d : faltam 3 dias para o prazo   -> avisa os responsáveis
 *   - atraso   : passou do prazo sem concluir -> avisa os responsáveis e os gestores
 *
 * Use --dry-run para só listar o que seria enviado, sem notificar nem gravar nada.
 */
class TarefasAvisos extends Command
{
    protected $signature = 'tarefas:avisos {--dry-run : Só lista o que faria, sem gravar/notificar}';

    protected $description = 'Avisos de prazo/atraso das tarefas.';


    public function handle(): int
    {
        $dryRun  = in_array('--dry-run', $argv ?? [], true);
        $hoje    = date('Y-m-d');
        $em3dias = date('Y-m-d', strtotime('+3 days'));
        $log     = static fn(string $m) => print('[' . date('Y-m-d H:i:s') . '] ' . $m . PHP_EOL);

        if ($dryRun) $log('MODO DRY-RUN: nada será notificado nem gravado.');




        $enviados = ['prazo_3d' => 0, 'atraso' => 0];

        // ── 1) Faltam 3 dias para o prazo ────────────────────────────────────────────
        foreach (avisos_tarefas('t.prazo = ?', ['s', $em3dias]) as $t) {
            $id     = (int) $t['id'];
            $titulo = trim((string) $t['titulo']) !== '' ? $t['titulo'] : 'Tarefa sem título';

            $responsaveis = avisos_responsaveis($id);
            if (!$responsaveis) continue;
            if (!avisos_registrar($id, 'prazo_3d', $dryRun)) continue;

            $detalhes = tarefa_email_detalhes([
                'Tarefa' => $titulo,
                'Setor'  => $t['setor_nome'] ?? 'Sem setor',
                'Prazo'  => date('d/m/Y', strtotime($t['prazo'])),
            ]);

            foreach ($responsaveis as $r) {
                if ($dryRun) { $log("  -> [dry] sino+email para {$r['username']}"); continue; }
                tarefa_notificar(
                    (int) $r['id'],
                    'tarefa_prazo',
                    'Faltam 3 dias para o prazo',
                    'A tarefa "' . $titulo . '" vence em ' . date('d/m/Y', strtotime($t['prazo'])) . '.',
                    true,
                    $detalhes
                );
            }
            $enviados['prazo_3d']++;
            $log("prazo_3d: tarefa #{$id} ({$titulo}) para " . count($responsaveis) . ' responsável(is).');
        }

        // ── 2) Tarefa atrasada ───────────────────────────────────────────────────────
        foreach (avisos_tarefas('t.prazo < ?', ['s', $hoje]) as $t) {
            $id     = (int) $t['id'];
            $titulo = trim((string) $t['titulo']) !== '' ? $t['titulo'] : 'Tarefa sem título';
            $setor  = $t['setor_nome'] ?? 'Sem setor';

            if (!avisos_registrar($id, 'atraso', $dryRun)) continue;

            $detalhes = tarefa_email_detalhes([
                'Tarefa' => $titulo,
                'Setor'  => $setor,
                'Prazo'  => date('d/m/Y', strtotime($t['prazo'])),
            ]);

            $destinatarios = [];
            foreach (avisos_responsaveis($id) as $r) { $destinatarios[(int) $r['id']] = true; }
            // Gestores de tarefas também são avisados, com o setor no texto.
            foreach (db_fetch_all("SELECT id FROM usuario WHERE tarefas_gestor = 'S'") as $g) {
                $destinatarios[(int) $g['id']] = true;
            }
            if (!$destinatarios) continue;

            foreach (array_keys($destinatarios) as $uid) {
                if ($dryRun) { $log("  -> [dry] sino+email para usuario #{$uid}"); continue; }
                tarefa_notificar(
                    $uid,
                    'tarefa_atraso',
                    'Tarefa em atraso',
                    'A tarefa "' . $titulo . '" do setor ' . $setor . ' passou do prazo ('
                        . date('d/m/Y', strtotime($t['prazo'])) . ') e não foi concluída.',
                    true,
                    $detalhes
                );
            }
            $enviados['atraso']++;
            $log("atraso: tarefa #{$id} ({$titulo}, setor {$setor}) para " . count($destinatarios) . ' pessoa(s).');
        }

        $log("Concluído. prazo_3d={$enviados['prazo_3d']} atraso={$enviados['atraso']}");

        return 0;
    }
}
