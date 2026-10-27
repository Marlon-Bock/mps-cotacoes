<?php

namespace App\Http\Controllers\Gestao;

use App\Http\Controllers\Controller;
use Throwable;

/**
 * Painel de exibição (TV da empresa): login próprio, tela cheia e os números
 * que ela consulta a cada 15s.
 * Era pages/painel/login.php, pages/painel/index.php e includes/acao/painel-dados.php.
 */
class PainelController extends Controller
{
    /**
     * GET /painel/login
     * Entrada exclusiva do painel de TV. Usa o mesmo endpoint de login do sistema
     * (/api/auth/login) — quem tem a flag `painel` cai direto em /painel.
     */
    public function loginForm()
    {
        if ($current_user = auth_user()) {
            return redirect(url(match ($current_user->role ?? '') {
                'painel' => '/painel',
                'admin'  => '/admin',
                default  => '/',
            }));
        }

        return view('gestao.painel.login');
    }

    /**
     * GET /painel
     * Painel de exibição (TV da empresa). Tela cheia, sem menu e sem navegação:
     * mostra a contagem de cotações por status e avisa, com som e alerta central,
     * quando uma cotação nova entra. Os dados vêm de /api/painel/dados.
     * Como no antigo, não exige login (auth_user() só pra mostrar o nome no rodapé).
     */
    public function index()
    {
        // Painel de TV: só leitura e exibição — aberto sem login, como no antigo
        // (decisão do usuário em 23/10/2026). O login da conta painel continua
        // existindo, mas não é exigido.
        $current_user = auth_user();

        $cards = [
            ['key' => 'pre_analise', 'label' => 'Pré-análise',        'icon' => 'clipboard-text', 'destaque' => true],
            ['key' => 'aguardando',  'label' => 'Aguardando análise', 'icon' => 'clock',          'destaque' => true],
            ['key' => 'em_analise',  'label' => 'Em análise',         'icon' => 'gauge',          'destaque' => true],
            ['key' => 'aprovados',   'label' => 'Aprovadas',          'icon' => 'check-circle',   'destaque' => true],
            ['key' => 'concluidos',  'label' => 'Concluídas',         'icon' => 'trend-up',       'destaque' => false],
            ['key' => 'recusados',   'label' => 'Recusadas',          'icon' => 'x',              'destaque' => false],
        ];

        return view('gestao.painel.index', compact('current_user', 'cards'));
    }

    /**
     * GET /api/painel/dados
     * Números do painel de TV (/painel): contagem por status nos 4 produtos e as
     * cotações mais recentes — usadas pelo painel pra detectar cotação nova e
     * disparar o alerta com som.
     *
     * Acesso público (só leitura): a TV que exibe o painel não faz login.
     *
     * Além das contagens e das últimas cotações (com o status atual), devolve os
     * eventos da API das seguradoras — ficha aprovada (MPS precisa fazer a
     * cotação) ou recusada por todas — pra TV avisar também o resultado, não só
     * a chegada.
     */
    public function dados()
    {
        /**
         * Produtos e as diferenças de schema entre as tabelas (a data de criação tem
         * nome diferente em título/condominial, herdado do sistema legado).
         */
        $produtos = [
            'fianca'      => ['label' => 'Fiança',      'tabela' => 'seguro_fianca',        'data' => 'data_cadastro'],
            'auto'        => ['label' => 'Auto',        'tabela' => 'seguro_auto',          'data' => 'data_cadastro'],
            'titulo'      => ['label' => 'Título',      'tabela' => 'titulo_capitalizacao', 'data' => 'criado_em'],
            'condominial' => ['label' => 'Condominial', 'tabela' => 'seguro_condominial',   'data' => 'criado_em'],
            'incendio'    => ['label' => 'Incêndio',    'tabela' => 'seguro_incendio',      'data' => 'data_cadastro'],
            'residencial' => ['label' => 'Residencial', 'tabela' => 'seguro_residencial',   'data' => 'data_cadastro'],
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
                // 'Cotação API' = seguradoras ainda analisando a ficha.
                $estagio = ($r['status'] ?? '') === 'Cotação API'
                    ? 'em_analise'
                    : classificar_status_cotacao((string) ($r['status'] ?? ''));
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

        // ── Últimas cotações cadastradas (fonte do alerta de cotação nova) ────
        $partes = [];
        foreach ($produtos as $key => $p) {
            // Fiança e Residencial podem ser cotadas automaticamente (origem 'api' ou
            // 'segimob'): essas não avisam na chegada, só no resultado (ver 'eventos').
            $origem = in_array($key, ['fianca', 'residencial'], true) ? "COALESCE(t.origem, 'manual')" : "'manual'";
            $partes[] = "SELECT '{$key}' AS produto, t.protocolo, t.status, t.{$p['data']} AS criado_em, {$origem} AS origem,
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
                'situacao'      => self::situacao((string) $r['status']),
                'via_api'       => canal_automatico((string) ($r['origem'] ?? '')),
                'imobiliaria'   => $r['imobiliaria'] !== '' ? $r['imobiliaria'] : '—',
                'criado_em'     => $r['criado_em'],
                'hora'          => $ts ? date('d/m H:i', $ts) : '—',
            ];
        }

        // ── Resultado da API das seguradoras ou da Segimob (fonte do alerta de aprovada/recusada) ──
        $eventos = [];
        try {
            $rows = db_fetch_all(
                "SELECT h.id, h.protocolo, h.status_novo, h.data_evento,
                        COALESCE(imf.nome, imr.nome, '') AS imobiliaria
                   FROM historico h
                   LEFT JOIN seguro_fianca f      ON f.protocolo = h.protocolo
                   LEFT JOIN imobiliarias imf     ON imf.id = f.imobiliaria_id
                   LEFT JOIN seguro_residencial r ON r.protocolo = h.protocolo
                   LEFT JOIN imobiliarias imr     ON imr.id = r.imobiliaria_id
                  WHERE h.api_evento = 1
                    AND h.status_novo IN ('Análise das seguradoras concluída', 'Recusada por todas as seguradoras')
               ORDER BY h.id DESC LIMIT 12"
            );
            foreach ($rows as $e) {
                $ts = $e['data_evento'] ? strtotime($e['data_evento']) : null;
                $eventos[] = [
                    'id'            => (int) $e['id'],
                    'tipo'          => $e['status_novo'] === 'Recusada por todas as seguradoras' ? 'recusada' : 'aprovada',
                    'protocolo'     => $e['protocolo'],
                    'produto'       => str_starts_with((string) $e['protocolo'], 'RES') ? 'residencial' : 'fianca',
                    'produto_label' => str_starts_with((string) $e['protocolo'], 'RES') ? 'Residencial' : 'Fiança',
                    'imobiliaria'   => $e['imobiliaria'] !== '' ? $e['imobiliaria'] : '—',
                    'hora'          => $ts ? date('d/m H:i', $ts) : '—',
                ];
            }
        } catch (Throwable $e) {
            $eventos = [];
        }

        return response()->json([
            'success'     => true,
            'atualizado'  => date('H:i:s'),
            'hoje'        => $hoje_total,
            'totais'      => $totais,
            'por_produto' => $por_produto,
            'recentes'    => $lista,
            'eventos'     => $eventos,
        ]);
    }

    /**
     * Situação resumida de uma cotação pro selo da lista "Últimas cotações":
     * [classe, texto]. 'Cotação API' = seguradoras analisando.
     */
    private static function situacao(string $status): array
    {
        if ($status === 'Cotação API') {
            return ['analise', 'Analisando nas seguradoras'];
        }
        return match (classificar_status_cotacao($status)) {
            'recusados'    => ['recusada', 'Recusada'],
            'aprovados'    => ['aprovada', 'Aprovada'],
            'concluidos'   => ['aprovada', 'Concluída'],
            'em_analise'   => ['analise', 'Em análise'],
            'desistencias' => ['neutra', 'Desistência'],
            'pre_analise'  => ['analise', 'Pré-análise'],
            default        => ['neutra', $status !== '' ? $status : 'Aguardando'],
        };
    }
}
