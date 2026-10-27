<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Avisa a equipe MPS (sino) quando chega lead novo do site na tabela leads_site.
 * O site grava direto no banco, então o mps descobre os leads novos aqui.
 *
 * Roda a cada minuto via cron:
 *   * * * * *  php /u01/home/marlon/www/dev-cotacoes/cron/leads-site-avisos.php 2>&1 | grep -v snmp.so >> /u01/home/marlon/www/dev-cotacoes/storage/logs/leads-site-avisos.log
 *
 * Só recebe quem pode ver o lead (lead_site_visivel: produtos liberados do
 * operador; seguro fora do sistema só administrador).
 *
 * Cada lead é avisado UMA única vez: o controle é a própria notificação
 * (tipo lead_site + url do lead) em notificacoes_mps. Só olha leads das
 * últimas 24h, pra não disparar aviso de lead antigo.
 *
 * Lead suspeito de spam (origem 'suspeito', gravado pelo site) não gera aviso.
 *
 * Use --dry-run para só listar o que seria enviado, sem notificar nada.
 */
class LeadsSiteAvisos extends Command
{
    protected $signature = 'leads:avisos {--dry-run : Só lista o que faria, sem gravar/notificar}';

    protected $description = 'Avisa a equipe MPS (sino) dos leads novos do site.';


    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $log    = static fn(string $m) => print('[' . date('Y-m-d H:i:s') . '] ' . $m . PHP_EOL);

        try {
            $leads = db_fetch_all(
                "SELECT ls.protocolo, ls.seguro, ls.nome, ls.dados
                   FROM leads_site ls
                  WHERE ls.criado_em >= NOW() - INTERVAL 1 DAY
                    AND ls.origem <> 'suspeito' -- spam (isca/envio rápido): fica só na listagem, sem aviso
                    AND NOT EXISTS (
                        SELECT 1 FROM notificacoes_mps n
                         WHERE n.tipo = 'lead_site'
                           AND n.url COLLATE utf8mb4_unicode_ci = CONCAT('/admin/leads/', ls.protocolo)
                    )
                  ORDER BY ls.id ASC"
            );
        } catch (Throwable $e) {
            $log('Erro ao buscar leads: ' . $e->getMessage());
            return 1;
        }

        if (!$leads) return 0;

        // Equipe MPS no mesmo formato do usuário logado (ver login.php), pra reaproveitar lead_site_visivel().
        $equipe = array_map(static fn(array $u) => (object) [
            'uid'              => (int) $u['id'],
            'mps_admin'        => $u['admin'] === 'S',
            'perm_fianca'      => $u['perm_fianca'],
            'perm_auto'        => $u['perm_auto'],
            'perm_titulo'      => $u['perm_titulo'],
            'perm_condominial' => $u['perm_condominial'],
            'perm_incendio'    => $u['perm_incendio'],
            'perm_seguro_residencial' => $u['perm_seguro_residencial'],
        ], db_fetch_all(
            'SELECT id, admin, perm_fianca, perm_auto, perm_titulo, perm_condominial, perm_incendio, perm_seguro_residencial
               FROM usuario WHERE ativo = 1'
        ));

        foreach ($leads as $lead) {
            $dados = json_decode((string) $lead['dados'], true);
            [$origem, $produto] = lead_site_produto((string) $lead['seguro'], is_array($dados) ? $dados : []);
            $nome  = trim((string) $lead['nome']) !== '' ? $lead['nome'] : 'Cliente';
            $destinatarios = array_filter($equipe, fn($u) => lead_site_visivel($u, $origem));

            if ($dryRun) {
                $log("[dry-run] {$lead['protocolo']} — {$nome} ({$produto}) → " . count($destinatarios) . ' usuário(s)');
                continue;
            }

            foreach ($destinatarios as $u) {
                notificar_mps(
                    'admin',
                    $u->uid,
                    'lead_site',
                    'Novo lead do site',
                    "{$nome} enviou um pedido de {$produto} pelo site.",
                    null,
                    '/admin/leads/' . $lead['protocolo']
                );
            }
            $log("Avisado: {$lead['protocolo']} — {$nome} ({$produto}) → " . count($destinatarios) . ' usuário(s)');
        }

        return 0;
    }
}
