<?php

namespace App\Http\Controllers\Gestao;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Throwable;

/**
 * Central de gestão (gerenciador): métricas, rankings, tempos por cotação e linha do tempo.
 * Era pages/admin/gerenciador.php e includes/acao/admin-gerenciador-{stats,tempos,timeline}.php.
 */
class GerenciadorController extends Controller
{
    /** GET /admin/gerenciador */
    public function index()
    {
        /**
         * GET /admin/gerenciador
         * Visão consolidada de métricas de negócio — total por produto (Fiança, Auto,
         * Título de Capitalização, Condominial), breakdown por status, ranking de
         * imobiliárias e de atendentes. Shell puro: toda a consulta acontece via AJAX
         * em /api/admin/gerenciador/stats (mesmo padrão de /admin/cotacoes).
         */
        $current_user = require_admin();

        $atendentes = [];
        try {
            $atendentes = db_fetch_all('SELECT id, username FROM usuario ORDER BY username ASC');
        } catch (Throwable) {}
        $opcoes_atendente = [];
        foreach ($atendentes as $a) { $opcoes_atendente[(string) $a['id']] = $a['username']; }
        $opcoes_canal = canais_filtro();

        $data_inicio_padrao = date('Y-m-d', strtotime('-30 days'));
        $data_fim_padrao    = date('Y-m-d');

        $page_title      = 'Gerenciador';
        $page_heading    = 'Gerenciador';
        $page_subheading = 'Métricas consolidadas de negócio';
        $page_back       = null;
        $extra_css       = ['dashboard.css', 'cotacoes.css', 'cotacao-detalhe.css', 'tarefas.css'];
        $extra_js        = ['admin-gerenciador.js'];

        return view('gestao.gerenciador.index', get_defined_vars());
    }

    /** Filtros comuns do Gerenciador (período por data de criação, atendente, imobiliária). */
    private static function filtrar(array $rows, string $data_de, string $data_ate, int $atendente_id, string $imobiliaria, string $canal): array
    {
        $rows = admin_cotacoes_filtrar_canal($rows, $canal);
        if ($data_de !== '')     $rows = array_values(array_filter($rows, fn($r) => substr($r['criado_em'] ?? '', 0, 10) >= $data_de));
        if ($data_ate !== '')    $rows = array_values(array_filter($rows, fn($r) => substr($r['criado_em'] ?? '', 0, 10) <= $data_ate));
        if ($atendente_id > 0)   $rows = array_values(array_filter($rows, fn($r) => (int) ($r['atendente_id'] ?? 0) === $atendente_id));
        if ($imobiliaria !== '') $rows = array_values(array_filter($rows, fn($r) => mb_stripos($r['imob_nome'] ?? '', $imobiliaria) !== false));
        return $rows;
    }

    /**
     * Ficha ainda nas seguradoras ('Cotação API') entra nos números pelo status
     * equivalente à etapa no canal dela — API ou Segimob (em análise / aprovada /
     * recusada) — sem isso
     * ela não caía em nenhum contador da Central.
     */
    private static function statusApiEquivalente(array $rows): array
    {
        $rows = admin_cotacoes_marcar_api($rows);
        foreach ($rows as &$r) {
            if (($r['status'] ?? '') === 'Cotação API') {
                $etapa = (string) ($r['api_etapa'] ?? '');
                $r['status'] = ($r['origem_ficha'] ?? '') === SEGIMOB_ORIGEM
                    ? segimob_status_equivalente(segimob_status_categoria($etapa))
                    : api_status_equivalente(api_status_categoria($etapa));
            }
        }
        unset($r);
        return $rows;
    }

    /** Lê os filtros da query string (datas inválidas viram ''). */
    private static function lerFiltros(Request $request): array
    {
        $data_de  = trim((string) $request->query('data_de', ''));
        $data_ate = trim((string) $request->query('data_ate', ''));
        if ($data_de  && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data_de))  $data_de  = '';
        if ($data_ate && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data_ate)) $data_ate = '';

        return [
            $data_de,
            $data_ate,
            max(0, (int) $request->query('atendente_id', 0)),
            trim((string) $request->query('imobiliaria', '')),
            trim((string) $request->query('status_rapido', '')),
            canal_filtro_valido($request->query('canal')),   // manual / API / Segimob ('' = todos)
        ];
    }

    /** Tempo decorrido da criação até um timestamp de etapa (null se a etapa não foi alcançada). */
    private static function tempoAte(?string $criado_em, ?string $etapa): ?int
    {
        if (!$criado_em || !$etapa) return null;
        $segundos = strtotime($etapa) - strtotime($criado_em);
        return $segundos >= 0 ? $segundos : null;
    }

    /**
     * GET /api/admin/gerenciador/stats
     * Estatísticas consolidadas de negócio: total por produto, breakdown por
     * status e rankings de imobiliárias/atendentes, filtrados por período
     * (data de criação), atendente e imobiliária.
     * Retorna JSON { produto_totais, contadores, ranking_imobiliarias_html, ranking_atendentes_html }
     */
    public function stats(Request $request)
    {
        require_admin();
        [$data_de, $data_ate, $atendente_id, $imobiliaria, $status_rapido, $canal] = self::lerFiltros($request);

        $rows = self::statusApiEquivalente(self::filtrar(admin_cotacoes_buscar_tempos(), $data_de, $data_ate, $atendente_id, $imobiliaria, $canal));

        // ── Totais por produto + breakdown de status (ANTES do filtro de status rápido) ──
        $produto_totais = ['fianca' => 0, 'auto' => 0, 'titulo' => 0, 'condominial' => 0, 'incendio' => 0, 'residencial' => 0, 'geral' => count($rows)];
        foreach ($rows as $r) {
            $origem = $r['origem'] ?? 'fianca';
            if (isset($produto_totais[$origem])) $produto_totais[$origem]++;
        }

        $cont = admin_cotacoes_contadores($rows);

        // ── Filtro de status rápido (afeta só os rankings) ────────────────────
        $rows_ranking  = admin_cotacoes_filtrar_status($rows, $status_rapido);
        $total_ranking = count($rows_ranking);

        // ── Ranking de imobiliárias ───────────────────────────────────────────
        $grupo_imob = [];
        foreach ($rows_ranking as $r) {
            $id = (int) ($r['imobiliaria_id'] ?? 0);
            if ($id <= 0) continue;
            if (!isset($grupo_imob[$id])) $grupo_imob[$id] = ['nome' => $r['imob_nome'] ?? '—', 'total' => 0];
            $grupo_imob[$id]['total']++;
        }
        usort($grupo_imob, fn($a, $b) => $b['total'] <=> $a['total']);
        $ranking_imob = array_slice($grupo_imob, 0, 10);

        // ── Ranking de atendentes (+ tempo médio de resolução) ────────────────
        $grupo_atend = [];
        foreach ($rows_ranking as $r) {
            $id = (int) ($r['atendente_id'] ?? 0);
            if ($id <= 0) continue;
            if (!isset($grupo_atend[$id])) {
                $grupo_atend[$id] = [
                    'nome' => $r['atendente_nome'] ?? '—', 'avatar' => $r['atendente_avatar'] ?? null,
                    'total' => 0, 'soma_segundos' => 0, 'qtd_finalizadas' => 0,
                ];
            }
            $grupo_atend[$id]['total']++;

            $data_fim = $r['data_status_concluido'] ?? $r['data_status_aprovado'] ?? $r['data_status_recusado'] ?? null;
            if ($data_fim && !empty($r['criado_em'])) {
                $segundos = strtotime($data_fim) - strtotime($r['criado_em']);
                if ($segundos > 0) {
                    $grupo_atend[$id]['soma_segundos'] += $segundos;
                    $grupo_atend[$id]['qtd_finalizadas']++;
                }
            }
        }
        foreach ($grupo_atend as $id => $g) {
            $grupo_atend[$id]['tempo_medio_segundos'] = $g['qtd_finalizadas'] > 0 ? (int) ($g['soma_segundos'] / $g['qtd_finalizadas']) : null;
        }
        usort($grupo_atend, fn($a, $b) => $b['total'] <=> $a['total']);
        $ranking_atend = array_slice($grupo_atend, 0, 10);

        // ── Render HTML dos rankings ──────────────────────────────────────────
        $ranking_imobiliarias_html = view('gestao.gerenciador._ranking', ['itens' => $ranking_imob, 'total' => $total_ranking, 'atendentes' => false])->render();
        $ranking_atendentes_html   = view('gestao.gerenciador._ranking', ['itens' => $ranking_atend, 'total' => $total_ranking, 'atendentes' => true])->render();

        return response()->json([
            'produto_totais'            => $produto_totais,
            'contadores'                => $cont,
            'ranking_imobiliarias_html' => $ranking_imobiliarias_html,
            'ranking_atendentes_html'   => $ranking_atendentes_html,
        ]);
    }

    /**
     * GET /api/admin/gerenciador/tempos
     * Lista paginada "Tempo por cotação": tempo (desde a criação) até cada etapa
     * do fluxo — pré-análise, aguardando análise, em análise, biometria,
     * aprovado/recusado, envio da cotação, concluído — e o tempo total de
     * resolução (ou "em andamento" se ainda aberta). Título/Condominial não têm
     * pré-análise/aguardando/biometria/envio (mostram "—"). Mesmos filtros de
     * período/atendente/imobiliária/status do resto do Gerenciador.
     * Retorna JSON { html, total, page, per_page, total_pages }
     */
    public function tempos(Request $request)
    {
        require_admin();
        [$data_de, $data_ate, $atendente_id, $imobiliaria, $status_rapido, $canal] = self::lerFiltros($request);
        $page     = max(1, (int) $request->query('page', 1));
        $per_page = 20;

        $rows = self::statusApiEquivalente(self::filtrar(admin_cotacoes_buscar_tempos(), $data_de, $data_ate, $atendente_id, $imobiliaria, $canal));
        $rows = admin_cotacoes_filtrar_status($rows, $status_rapido);

        usort($rows, fn($a, $b) => strcmp((string) ($b['criado_em'] ?? ''), (string) ($a['criado_em'] ?? '')));

        $total       = count($rows);
        $total_pages = max(1, (int) ceil($total / $per_page));
        if ($page > $total_pages) $page = $total_pages;
        $offset      = ($page - 1) * $per_page;
        $rows_pagina = array_slice($rows, $offset, $per_page);

        // Tempos de cada etapa calculados aqui; a view só exibe.
        $linhas = [];
        foreach ($rows_pagina as $row) {
            $criado_em = $row['criado_em'] ?? null;

            // Aprovado/Recusado — só um dos dois acontece por cotação.
            $tempo_aprovado = self::tempoAte($criado_em, $row['data_status_aprovado'] ?? null);
            $tempo_recusado = self::tempoAte($criado_em, $row['data_status_recusado'] ?? null);

            $data_fim    = $row['data_status_concluido'] ?? $row['data_status_aprovado'] ?? $row['data_status_recusado'] ?? null;
            $tempo_total = null;
            if ($criado_em) {
                $tempo_total = ($data_fim ? strtotime($data_fim) : time()) - strtotime($criado_em);
            }

            $status_raw = (string) ($row['status'] ?? '');
            $linhas[] = [
                'row'               => $row,
                'tempo_pre_analise' => self::tempoAte($criado_em, $row['data_pre_analise'] ?? null),
                'tempo_aguardando'  => self::tempoAte($criado_em, $row['data_status_aguardando'] ?? null),
                'tempo_em_analise'  => self::tempoAte($criado_em, $row['data_status_em_analise'] ?? null),
                'tempo_biometria'   => self::tempoAte($criado_em, $row['biometria_concluida_em'] ?? null),
                'tempo_envio'       => self::tempoAte($criado_em, $row['data_envio_cotacao'] ?? null),
                'tempo_concluido'   => self::tempoAte($criado_em, $row['data_status_concluido'] ?? null),
                'decisao_label'     => $tempo_aprovado !== null ? 'Aprovado' : ($tempo_recusado !== null ? 'Recusado' : null),
                'tempo_decisao'     => $tempo_aprovado ?? $tempo_recusado,
                'em_aberto'         => $data_fim === null,
                'tempo_total'       => $tempo_total,
                'label'             => admin_status_label($status_raw),
                'pill'              => status_pill_class($status_raw),
            ];
        }

        return response()->json([
            'html'        => view('gestao.gerenciador._tempos', ['linhas' => $linhas])->render(),
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $per_page,
            'total_pages' => $total_pages,
        ]);
    }

    /**
     * GET /api/admin/gerenciador/timeline?protocolo=X
     * Linha do tempo de uma cotação: eventos de `historico` (mesma fonte usada
     * nas páginas de detalhe de cotação) com o tempo decorrido entre um evento
     * e o anterior. Retorna JSON { html }
     */
    public function timeline(Request $request)
    {
        require_admin();

        $protocolo = trim((string) $request->query('protocolo', ''));

        $historico = [];
        if ($protocolo !== '') {
            try {
                $historico = db_fetch_all(
                    'SELECT * FROM historico WHERE protocolo = ? ORDER BY data_evento ASC',
                    ['s', $protocolo]
                );
            } catch (Throwable) {}
        }

        $tempo_total = $historico
            ? strtotime(end($historico)['data_evento']) - strtotime($historico[0]['data_evento'])
            : 0;

        return response()->json([
            'html' => view('gestao.gerenciador._timeline', ['historico' => $historico, 'tempo_total' => $tempo_total])->render(),
        ]);
    }
}
