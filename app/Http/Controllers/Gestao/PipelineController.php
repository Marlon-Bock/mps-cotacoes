<?php

namespace App\Http\Controllers\Gestao;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Throwable;

/**
 * Pipeline (kanban) das cotações por etapa.
 * Era pages/admin/pipeline.php e includes/acao/admin-pipeline-listar.php.
 */
class PipelineController extends Controller
{
    /** GET /admin/pipeline */
    public function index()
    {
        /**
         * GET /admin/pipeline
         * Pipeline/funil Kanban das cotações — visualização por coluna de status.
         * Filtros (data, imobiliária, atendente) são aplicados via AJAX
         * (/api/admin/pipeline/listar) — a URL nunca muda, mesmo padrão de /admin/cotacoes.
         */
        $current_user = require_admin();

        $data_inicio_padrao = date('Y-m-d', strtotime('-6 months'));
        $data_fim_padrao    = date('Y-m-d');

        // ── Listas para selects de filtro ──────────────────────────────────────────
        $imobiliarias = [];
        try {
            $imobiliarias = db_fetch_all('SELECT id, nome FROM imobiliarias ORDER BY nome ASC');
        } catch (Throwable) {}

        $atendentes = [];
        try {
            $atendentes = db_fetch_all('SELECT id, username FROM usuario ORDER BY username ASC');
        } catch (Throwable) {}

        $opcoes_imob  = [];
        foreach ($imobiliarias as $i) { $opcoes_imob[(string)$i['id']] = $i['nome']; }

        $opcoes_atend = [];
        foreach ($atendentes as $a) { $opcoes_atend[(string)$a['id']] = $a['username']; }

        // ── Colunas do Kanban (só pra render do esqueleto — o conteúdo vem via AJAX;
        //    mesmos 6 estágios das KPIs de /admin/cotacoes) ──────────────────────────
        $colunas = [
            'pre_analise' => ['label' => 'Pré-análise',        'icon' => 'lock'],
            'aguardando'  => ['label' => 'Aguardando Análise',  'icon' => 'clock'],
            'em_analise'  => ['label' => 'Em Análise',          'icon' => 'magnifying-glass'],
            'aprovados'   => ['label' => 'Aprovados',           'icon' => 'check-circle'],
            'recusados'   => ['label' => 'Recusados',           'icon' => 'x'],
            'concluidos'  => ['label' => 'Concluídas',          'icon' => 'check'],
        ];

        $page_title      = 'Pipeline de Cotações';
        $page_heading    = 'Pipeline';
        $page_subheading = 'Visualização kanban das cotações por etapa.';
        $page_back       = null;
        $extra_css       = ['dashboard.css', 'cotacoes.css'];
        $extra_js        = ['admin-pipeline.js'];

        return view('gestao.pipeline.index', get_defined_vars());
    }

    /**
     * GET /api/admin/pipeline/listar
     * Recalcula o Kanban do pipeline conforme os filtros (data, imobiliária,
     * atendente) — mesma fonte de dados de /api/admin/cotacoes/listar
     * (admin_cotacoes_buscar_todas), sem navegar nem alterar a URL de /admin/pipeline.
     * Retorna JSON { html }
     */
    public function listar(Request $request)
    {
        require_admin();

        // ── Filtros ───────────────────────────────────────────────────────────
        $data_inicio  = $request->query('data_inicio', date('Y-m-d', strtotime('-6 months')));
        $data_fim     = $request->query('data_fim', date('Y-m-d'));
        $filtro_imob  = (int) $request->query('imobiliaria_id', 0);
        $filtro_atend = (int) $request->query('atendente_id', 0);

        if (!is_string($data_inicio) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data_inicio)) {
            $data_inicio = date('Y-m-d', strtotime('-6 months'));
        }
        if (!is_string($data_fim) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data_fim)) {
            $data_fim = date('Y-m-d');
        }

        // ── Colunas do Kanban — mesmos 7 estágios das KPIs de /admin/cotacoes,
        //    classificados via classificar_status_cotacao(). ─────────────────
        $colunas = [
            'pre_analise'  => ['label' => 'Pré-análise',        'icon' => 'lock',             'cards' => []],
            'aguardando'   => ['label' => 'Aguardando Análise', 'icon' => 'clock',            'cards' => []],
            'em_analise'   => ['label' => 'Em Análise',         'icon' => 'magnifying-glass', 'cards' => []],
            'aprovados'    => ['label' => 'Aprovados',          'icon' => 'check-circle',     'cards' => []],
            'recusados'    => ['label' => 'Recusados',          'icon' => 'x',                'cards' => []],
            'desistencias' => ['label' => 'Desistências',       'icon' => 'sign-out',         'cards' => []],
            'concluidos'   => ['label' => 'Concluídas',         'icon' => 'check',            'cards' => []],
        ];

        // ── Busca — mesma fonte da listagem de cotações do admin, filtrada por
        //    data de cadastro / imobiliária / atendente ─────────────────────────
        $rows = admin_cotacoes_buscar_todas();

        $rows = array_values(array_filter($rows, function ($r) use ($data_inicio, $data_fim, $filtro_imob, $filtro_atend) {
            $data = substr((string) ($r['data_cadastro'] ?? ''), 0, 10);
            if ($data === '' || $data < $data_inicio || $data > $data_fim) return false;
            if ($filtro_imob > 0 && (int) ($r['imobiliaria_id'] ?? 0) !== $filtro_imob) return false;
            if ($filtro_atend > 0 && (int) ($r['atendente_id'] ?? 0) !== $filtro_atend) return false;
            return true;
        }));

        // ── Distribuição nas colunas ──────────────────────────────────────────
        foreach ($rows as $row) {
            $estagio = classificar_status_cotacao((string) ($row['status'] ?? ''));
            if ($estagio !== null && isset($colunas[$estagio])) {
                $colunas[$estagio]['cards'][] = $row;
            }
        }

        return response()->json(['html' => view('gestao.pipeline._kanban', ['colunas' => $colunas])->render()]);
    }
}
