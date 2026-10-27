<?php

namespace App\Http\Controllers\Tarefas;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Gerenciador de Tarefas da equipe MPS: página-shell (GET /admin/tarefas) e os
 * endpoints JSON da lista estilo Monday, offcanvas de detalhes e dashboard.
 * Era pages/admin/tarefas.php + includes/acao/admin-tarefas-*.php.
 *
 * Visibilidade (vale para todos os endpoints): gestor de tarefas vê/edita tudo;
 * não-gestor só tarefas dos setores a que pertence (tarefas_contexto_usuario()).
 */
class TarefasController extends Controller
{
    /** Resposta JSON igual ao echo json_encode() antigo. */
    public static function json(array $dados, int $status = 200): JsonResponse
    {
        return response()->json($dados, $status, [], JSON_UNESCAPED_UNICODE);
    }

    /** Não-gestor só acessa tarefas dos seus setores. */
    public static function podeVer(array $ctx, $setor_id): bool
    {
        return $ctx['gestor'] || in_array((int) $setor_id, $ctx['setores'], true);
    }

    /**
     * GET /admin/tarefas
     * Página-shell única com abas trocadas via JS (Tarefas / Dashboard / Gerenciar)
     * — tudo via AJAX, a URL nunca muda. Lista em TABELA (não kanban). Controle
     * por setor: gestor vê tudo; usuário comum só vê tarefas dos seus setores.
     */
    public function index()
    {
        $current_user = require_admin();

        $ctx    = tarefas_contexto_usuario((int) $current_user->uid);
        $gestor = $ctx['gestor'];

        // Dados para montar filtros, chips e o modal (mesmo padrão de chamados.php).
        $status_all  = [];
        $status_list = [];
        $setores     = [];
        $etiquetas   = [];
        $usuarios    = [];
        $campos      = [];
        try {
            $status_all  = db_fetch_all('SELECT id, nome, cor, ordem, is_final, ativo FROM tarefas_status ORDER BY ordem ASC, id ASC');
            $status_list = array_values(array_filter($status_all, static fn($s) => (int) $s['ativo'] === 1));
            $setores     = db_fetch_all('SELECT id, nome, cor FROM setores ORDER BY ordem ASC, nome ASC');
            if (!$gestor) {
                $setores = array_values(array_filter($setores, static fn($s) => in_array((int) $s['id'], $ctx['setores'], true)));
            }
            $etiquetas   = db_fetch_all('SELECT id, nome, cor FROM tarefas_etiquetas ORDER BY nome ASC');
            $usuarios    = db_fetch_all('SELECT id, username, avatar, tarefas_gestor FROM usuario ORDER BY username ASC');
            $campos      = db_fetch_all('SELECT id, nome, tipo, opcoes FROM tarefas_campos WHERE ativo = 1 ORDER BY ordem ASC, id ASC');
        } catch (\Throwable $e) {}

        // Mapa usuário => [setor_ids] (para filtrar os responsáveis pelo setor da tarefa).
        $usuarios_setores = [];
        try {
            foreach (db_fetch_all('SELECT usuario_id, setor_id FROM tarefas_usuario_setor') as $v) {
                $usuarios_setores[(int) $v['usuario_id']][] = (int) $v['setor_id'];
            }
        } catch (\Throwable $e) {}

        // Opções para os custom_select de filtro e do modal.
        $opc_setor = [];
        foreach ($setores as $s) { $opc_setor[(string) $s['id']] = $s['nome']; }
        $opc_status = [];
        foreach ($status_list as $s) { $opc_status[(string) $s['id']] = $s['nome']; }
        $opc_resp = [];
        foreach ($usuarios as $u) { $opc_resp[(string) $u['id']] = $u['username']; }
        $opc_etq = [];
        foreach ($etiquetas as $et) { $opc_etq[(string) $et['id']] = $et['nome']; }
        $opc_prioridade = ['baixa' => 'Baixa', 'media' => 'Média', 'alta' => 'Alta'];

        // Cores prontas do seletor de cor do setor (+ botão "personalizar" no fim).
        $preset_cores = ['#2563eb', '#16a34a', '#dc2626', '#d97706', '#7c3aed', '#0891b2', '#db2777', '#475569'];

        $page_title      = 'Tarefas';
        $page_heading    = 'Gerenciador de Tarefas';
        $page_subheading = 'Controle das tarefas da equipe MPS por setor';
        $page_back       = null;
        $extra_css       = ['dashboard.css', 'cotacoes.css', 'tarefas.css'];
        $extra_js        = ['admin-tarefas.js'];

        $main_attrs = ' data-csrf="' . e(csrf_token()) . '" data-gestor="' . ($gestor ? '1' : '0') . '"';

        return view('tarefas.index', get_defined_vars());
    }

    /**
     * GET /api/admin/tarefas/listar
     * Lista filtrável de tarefas. Retorna JSON
     * { html, agrupar_por, total, page, per_page, total_pages, contadores }.
     *
     * Visibilidade: gestor vê tudo; não-gestor vê só tarefas dos setores a que
     * pertence (sem setor => nada).
     */
    public function listar(Request $request)
    {
        $current_user = require_admin();

        $ctx = tarefas_contexto_usuario((int) $current_user->uid);
        $q   = static fn(string $k, string $padrao = '') => trim((string) $request->query($k, $padrao));

        // ── Filtros ──────────────────────────────────────────────────────────────
        $f_status      = $q('status');            // '' | 'atrasadas' | 'arquivadas' | id numérico
        $f_setor       = (int) $q('setor', '0');
        $f_responsavel = (int) $q('responsavel', '0');
        $f_etiqueta    = (int) $q('etiqueta', '0');
        $f_prioridade  = $q('prioridade');
        $f_busca       = $q('busca');
        $f_data_de     = $q('data_de');
        $f_data_ate    = $q('data_ate');
        $sort_by       = $q('sort_by');   // '' | 'status' | 'prazo'
        $sort_dir      = strtolower($q('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        if (!in_array($sort_by, ['status', 'prazo'], true)) $sort_by = '';
        if (!in_array($f_prioridade, ['baixa', 'media', 'alta'], true)) $f_prioridade = '';
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $f_data_de))  $f_data_de  = '';
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $f_data_ate)) $f_data_ate = '';

        $page = max(1, (int) $q('page', '1'));

        // ── Cláusula de visibilidade (compartilhada por lista e contadores) ──────
        $vis_sql   = '1=1';
        $vis_tipos = '';
        $vis_param = [];
        if (!$ctx['gestor']) {
            if (empty($ctx['setores'])) {
                $vis_sql = '1=0';
            } else {
                $ph = implode(',', array_fill(0, count($ctx['setores']), '?'));
                $vis_sql   = "t.setor_id IN ({$ph})";
                $vis_tipos = str_repeat('i', count($ctx['setores']));
                $vis_param = $ctx['setores'];
            }
        }

        // ── WHERE dos filtros (sobre a base de visibilidade) ─────────────────────
        $where  = $vis_sql;
        $tipos  = $vis_tipos;
        $params = $vis_param;

        $f_arquivadas = $f_status === 'arquivadas';
        $where .= $f_arquivadas ? ' AND t.arquivada_em IS NOT NULL' : ' AND t.arquivada_em IS NULL';

        if ($f_status === 'atrasadas') {
            $where .= ' AND t.prazo IS NOT NULL AND t.prazo < CURDATE() AND t.concluida_em IS NULL';
        } elseif ($f_status !== '' && $f_status !== 'arquivadas' && ctype_digit($f_status)) {
            $where   .= ' AND t.status_id = ?';
            $tipos   .= 'i';
            $params[] = (int) $f_status;
        }
        if ($f_setor > 0)        { $where .= ' AND t.setor_id = ?';   $tipos .= 'i'; $params[] = $f_setor; }
        if ($f_prioridade !== '') { $where .= ' AND t.prioridade = ?'; $tipos .= 's'; $params[] = $f_prioridade; }
        if ($f_data_de !== '')   { $where .= ' AND t.prazo >= ?';     $tipos .= 's'; $params[] = $f_data_de; }
        if ($f_data_ate !== '')  { $where .= ' AND t.prazo <= ?';     $tipos .= 's'; $params[] = $f_data_ate; }
        if ($f_busca !== '') {
            $where   .= ' AND (t.titulo LIKE ? OR t.descricao LIKE ?)';
            $tipos   .= 'ss';
            $params[] = "%{$f_busca}%";
            $params[] = "%{$f_busca}%";
        }
        if ($f_responsavel > 0) {
            $where   .= ' AND EXISTS (SELECT 1 FROM tarefas_responsaveis tr WHERE tr.tarefa_id = t.id AND tr.usuario_id = ?)';
            $tipos   .= 'i';
            $params[] = $f_responsavel;
        }
        if ($f_etiqueta > 0) {
            $where   .= ' AND EXISTS (SELECT 1 FROM tarefas_etiquetas_vinculo tev WHERE tev.tarefa_id = t.id AND tev.etiqueta_id = ?)';
            $tipos   .= 'i';
            $params[] = $f_etiqueta;
        }

        $tarefas       = [];
        $total         = 0;
        $contadores    = ['todos' => 0, 'atrasadas' => 0, 'arquivadas' => 0, 'status' => []];
        $resp_map      = [];
        $etq_map       = [];
        $anexo_map     = [];
        $sub_map       = [];   // tarefa_id => ['total' => n, 'feitos' => n]
        $coment_map    = [];
        $nao_lido_map  = [];   // tarefa_id => nº de comentários não lidos pelo usuário
        $meu_interesse = [];   // tarefa_id => true quando ele é responsável ou foi mencionado

        try {
            // Visão agrupada (estilo Monday): sem paginação, tudo carregado (teto de segurança).
            // Sem sort_by informado: ordem manual (drag-and-drop) via t.ordem. Com
            // sort_by, ordena por status/prazo (whitelist — nunca da query direto).
            $order_sql = match ($sort_by) {
                'status' => 'st.nome ' . strtoupper($sort_dir) . ', t.ordem ASC',
                'prazo'  => '(t.prazo IS NULL), t.prazo ' . strtoupper($sort_dir) . ', t.ordem ASC',
                default  => 't.ordem ASC, t.id ASC',
            };

            $sql = "SELECT t.id, t.titulo, t.descricao, t.prioridade, t.prazo, t.concluida_em, t.atualizado_em, t.ordem,
                           t.setor_id, s.nome AS setor_nome, s.cor AS setor_cor,
                           t.status_id, st.nome AS status_nome, st.cor AS status_cor
                      FROM tarefas t
                      LEFT JOIN setores s ON s.id = t.setor_id
                      LEFT JOIN tarefas_status st ON st.id = t.status_id
                     WHERE {$where}
                  ORDER BY {$order_sql}
                     LIMIT 500";
            $tarefas = $tipos !== '' ? db_fetch_all($sql, [$tipos, ...$params]) : db_fetch_all($sql);
            $total = count($tarefas);

            // Escopo dos contadores = visibilidade + setor ativo (mas SEM o filtro de
            // status, senão os chips não mostrariam o total de cada status). Assim, num
            // setor os chips refletem só aquele setor; em "Todas", o geral.
            $cvSql   = $vis_sql;
            $cvTipos = $vis_tipos;
            $cvParam = $vis_param;
            if ($f_setor > 0) {
                $cvSql    .= ' AND t.setor_id = ?';
                $cvTipos  .= 'i';
                $cvParam[] = $f_setor;
            }
            $cv = $cvTipos !== '' ? [$cvTipos, ...$cvParam] : [];
            $contadores['todos'] = (int) (db_fetch_one(
                "SELECT COUNT(*) AS total FROM tarefas t WHERE {$cvSql} AND t.arquivada_em IS NULL", $cv
            )['total'] ?? 0);
            $contadores['atrasadas'] = (int) (db_fetch_one(
                "SELECT COUNT(*) AS total FROM tarefas t WHERE {$cvSql} AND t.arquivada_em IS NULL AND t.prazo IS NOT NULL AND t.prazo < CURDATE() AND t.concluida_em IS NULL", $cv
            )['total'] ?? 0);
            $contadores['arquivadas'] = (int) (db_fetch_one(
                "SELECT COUNT(*) AS total FROM tarefas t WHERE {$cvSql} AND t.arquivada_em IS NOT NULL", $cv
            )['total'] ?? 0);
            $porStatus = db_fetch_all(
                "SELECT t.status_id, COUNT(*) AS total FROM tarefas t WHERE {$cvSql} AND t.arquivada_em IS NULL GROUP BY t.status_id", $cv
            );
            foreach ($porStatus as $row) {
                if ($row['status_id'] !== null) {
                    $contadores['status'][(string) (int) $row['status_id']] = (int) $row['total'];
                }
            }

            // Responsáveis, etiquetas, anexos, comentários e checklist das tarefas (batch).
            $ids = array_map(static fn($t) => (int) $t['id'], $tarefas);
            if (!empty($ids)) {
                $inPh = implode(',', array_fill(0, count($ids), '?'));
                $inTp = str_repeat('i', count($ids));

                foreach (db_fetch_all(
                    "SELECT tr.tarefa_id, u.id AS usuario_id, u.username, u.avatar
                       FROM tarefas_responsaveis tr
                       JOIN usuario u ON u.id = tr.usuario_id
                      WHERE tr.tarefa_id IN ({$inPh})
                   ORDER BY u.username ASC",
                    [$inTp, ...$ids]
                ) as $r) { $resp_map[(int) $r['tarefa_id']][] = $r; }

                foreach (db_fetch_all(
                    "SELECT tev.tarefa_id, e.nome, e.cor
                       FROM tarefas_etiquetas_vinculo tev
                       JOIN tarefas_etiquetas e ON e.id = tev.etiqueta_id
                      WHERE tev.tarefa_id IN ({$inPh})
                   ORDER BY e.nome ASC",
                    [$inTp, ...$ids]
                ) as $e) { $etq_map[(int) $e['tarefa_id']][] = $e; }

                foreach (db_fetch_all(
                    "SELECT tarefa_id, COUNT(*) AS total FROM tarefas_anexos WHERE tarefa_id IN ({$inPh}) GROUP BY tarefa_id",
                    [$inTp, ...$ids]
                ) as $a) { $anexo_map[(int) $a['tarefa_id']] = (int) $a['total']; }

                foreach (db_fetch_all(
                    "SELECT tarefa_id, COUNT(*) AS total FROM tarefas_comentarios WHERE tarefa_id IN ({$inPh}) GROUP BY tarefa_id",
                    [$inTp, ...$ids]
                ) as $c) { $coment_map[(int) $c['tarefa_id']] = (int) $c['total']; }

                foreach (db_fetch_all(
                    "SELECT tarefa_id, COUNT(*) AS total, SUM(concluido = 'S') AS feitos
                       FROM tarefas_subitens WHERE tarefa_id IN ({$inPh}) GROUP BY tarefa_id",
                    [$inTp, ...$ids]
                ) as $s) { $sub_map[(int) $s['tarefa_id']] = ['total' => (int) $s['total'], 'feitos' => (int) $s['feitos']]; }

                // ── Não lidos: só valem para quem é responsável ou foi mencionado ──
                $uid_atual = (int) $current_user->uid;

                foreach (db_fetch_all(
                    "SELECT tarefa_id FROM tarefas_responsaveis WHERE usuario_id = ? AND tarefa_id IN ({$inPh})",
                    ['i' . $inTp, $uid_atual, ...$ids]
                ) as $r) { $meu_interesse[(int) $r['tarefa_id']] = true; }

                foreach (db_fetch_all(
                    "SELECT DISTINCT c.tarefa_id
                       FROM tarefas_comentarios_mencoes m
                       JOIN tarefas_comentarios c ON c.id = m.comentario_id
                      WHERE m.usuario_id = ? AND c.tarefa_id IN ({$inPh})",
                    ['i' . $inTp, $uid_atual, ...$ids]
                ) as $r) { $meu_interesse[(int) $r['tarefa_id']] = true; }

                // Comentários de outras pessoas posteriores à última leitura do usuário.
                foreach (db_fetch_all(
                    "SELECT c.tarefa_id, COUNT(*) AS total
                       FROM tarefas_comentarios c
                       LEFT JOIN tarefas_leitura l ON l.tarefa_id = c.tarefa_id AND l.usuario_id = ?
                      WHERE c.tarefa_id IN ({$inPh})
                        AND c.usuario_id <> ?
                        AND (l.lido_em IS NULL OR c.criado_em > l.lido_em)
                      GROUP BY c.tarefa_id",
                    ['i' . $inTp . 'i', $uid_atual, ...$ids, $uid_atual]
                ) as $r) { $nao_lido_map[(int) $r['tarefa_id']] = (int) $r['total']; }
            }
        } catch (\Throwable $e) {
            error_log('[admin-tarefas-listar] ' . $e->getMessage());
        }

        $total_pages = 1;
        $hoje = date('Y-m-d');

        // Agrupamento estilo Monday: setor específico selecionado → agrupa por STATUS;
        // em "Todas" → agrupa por SETOR (cada grupo é um setor).
        $agrupar_por_status = $f_setor > 0;

        $grupos_def = [];
        try {
            $grupos_def = $agrupar_por_status
                ? db_fetch_all('SELECT id, nome, cor FROM tarefas_status ORDER BY ordem ASC, id ASC')
                : db_fetch_all('SELECT id, nome, cor FROM setores ORDER BY ordem ASC, nome ASC');
            if (!$agrupar_por_status && !$ctx['gestor']) {
                $grupos_def = array_values(array_filter($grupos_def, static fn($g) => in_array((int) $g['id'], $ctx['setores'], true)));
            }
        } catch (\Throwable $e) {}

        // Distribui as tarefas por grupo (chave = status_id ou setor_id; 0 = sem).
        $por_grupo = [];
        foreach ($tarefas as $t) {
            $chave = $agrupar_por_status ? (int) ($t['status_id'] ?? 0) : (int) ($t['setor_id'] ?? 0);
            $por_grupo[$chave][] = $t;
        }

        $temFiltro = $f_status !== '' || $f_responsavel > 0 || $f_etiqueta > 0
            || $f_prioridade !== '' || $f_busca !== '' || $f_data_de !== '' || $f_data_ate !== '';

        // Em "Todas" (agrupado por setor) e sem filtro, exibe TODOS os setores — inclusive
        // os sem tarefa — para permitir adicionar tarefa direto em qualquer um.
        $mostrarVazios = !$agrupar_por_status && !$temFiltro;

        $ordem = [];
        foreach ($grupos_def as $g) {
            if (!empty($por_grupo[(int) $g['id']]) || $mostrarVazios) {
                $ordem[] = ['id' => (int) $g['id'], 'nome' => $g['nome'], 'cor' => $g['cor']];
            }
        }
        if (!empty($por_grupo[0])) {
            $ordem[] = ['id' => 0, 'nome' => $agrupar_por_status ? 'Sem status' : 'Sem setor', 'cor' => '#94a3b8'];
        }

        $html = view('tarefas._partials.lista', compact(
            'ordem', 'por_grupo', 'temFiltro', 'agrupar_por_status', 'f_setor', 'f_arquivadas',
            'sort_by', 'sort_dir', 'resp_map', 'anexo_map', 'sub_map', 'coment_map',
            'nao_lido_map', 'meu_interesse', 'hoje'
        ))->render();

        return self::json([
            'html'        => $html,
            'agrupar_por' => $agrupar_por_status ? 'status' : 'setor',
            'total'       => $total,
            'page'        => $page,
            'per_page'    => max(1, $total),
            'total_pages' => $total_pages,
            'contadores'  => $contadores,
        ]);
    }

    /**
     * GET /api/admin/tarefas/obter?id=N
     * Dados completos de uma tarefa (para preencher o modal de edição).
     */
    public function obter(Request $request)
    {
        $current_user = require_admin();

        $ctx = tarefas_contexto_usuario((int) $current_user->uid);
        $id  = (int) $request->query('id', 0);

        if ($id <= 0) {
            return self::json(['ok' => false, 'message' => 'Tarefa inválida.']);
        }

        $tarefa = db_fetch_one(
            'SELECT id, titulo, descricao, setor_id, status_id, prioridade, prazo FROM tarefas WHERE id = ?',
            ['i', $id]
        );
        if (!$tarefa) {
            return self::json(['ok' => false, 'message' => 'Tarefa não encontrada.']);
        }
        if (!self::podeVer($ctx, $tarefa['setor_id'])) {
            return self::json(['ok' => false, 'message' => 'Sem permissão para esta tarefa.']);
        }

        $resp = db_fetch_all('SELECT usuario_id FROM tarefas_responsaveis WHERE tarefa_id = ?', ['i', $id]);
        $etq  = db_fetch_all('SELECT etiqueta_id FROM tarefas_etiquetas_vinculo WHERE tarefa_id = ?', ['i', $id]);
        $val  = db_fetch_all('SELECT campo_id, valor FROM tarefas_campos_valores WHERE tarefa_id = ?', ['i', $id]);

        $campos = [];
        foreach ($val as $v) { $campos[(string) (int) $v['campo_id']] = $v['valor']; }

        return self::json([
            'ok' => true,
            'tarefa' => [
                'id'           => (int) $tarefa['id'],
                'titulo'       => $tarefa['titulo'],
                'descricao'    => $tarefa['descricao'] ?? '',
                'setor_id'     => $tarefa['setor_id'] !== null ? (int) $tarefa['setor_id'] : '',
                'status_id'    => $tarefa['status_id'] !== null ? (int) $tarefa['status_id'] : '',
                'prioridade'   => $tarefa['prioridade'] ?? '',
                'prazo'        => $tarefa['prazo'] ?? '',
                'responsaveis' => array_map(static fn($r) => (int) $r['usuario_id'], $resp),
                'etiquetas'    => array_map(static fn($e) => (int) $e['etiqueta_id'], $etq),
                'campos'       => $campos,
            ],
        ]);
    }

    /**
     * POST /api/admin/tarefas/salvar
     * Cria ou atualiza uma tarefa (JSON no corpo). Também sincroniza responsáveis,
     * etiquetas e os valores dos campos customizados.
     *
     * Permissão: gestor cria/edita qualquer tarefa; não-gestor só dentro dos seus
     * setores (a tarefa precisa pertencer a um setor a que ele pertence).
     */
    public function salvar(Request $request)
    {
        $current_user = require_admin();

        $ctx   = tarefas_contexto_usuario((int) $current_user->uid);
        $input = $request->json()->all();

        $id           = (int) ($input['id'] ?? 0);
        $titulo       = trim($input['titulo'] ?? '');
        $descricao    = trim($input['descricao'] ?? '');
        $setor_id     = (int) ($input['setor_id'] ?? 0);
        $status_id    = (int) ($input['status_id'] ?? 0);
        $prioridade   = trim($input['prioridade'] ?? '');
        $prazo        = trim($input['prazo'] ?? '');
        $responsaveis = array_values(array_unique(array_map('intval', (array) ($input['responsaveis'] ?? []))));
        $etiquetas    = array_values(array_unique(array_map('intval', (array) ($input['etiquetas'] ?? []))));
        $campos       = (array) ($input['campos'] ?? []);

        if (!in_array($prioridade, ['baixa', 'media', 'alta'], true)) $prioridade = '';
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $prazo)) $prazo = '';

        if ($titulo === '') {
            return self::json(['ok' => false, 'message' => 'Informe o título da tarefa.']);
        }
        if ($setor_id <= 0) {
            return self::json(['ok' => false, 'message' => 'Selecione o setor da tarefa.']);
        }

        // Setor precisa existir e (se não-gestor) pertencer ao usuário.
        $setor = db_fetch_one('SELECT id FROM setores WHERE id = ?', ['i', $setor_id]);
        if (!$setor) {
            return self::json(['ok' => false, 'message' => 'Setor inválido.']);
        }
        if (!$ctx['gestor'] && !in_array($setor_id, $ctx['setores'], true)) {
            return self::json(['ok' => false, 'message' => 'Você não pode criar tarefas neste setor.']);
        }

        // concluida_em depende do status escolhido (is_final).
        $statusFinal = false;
        if ($status_id > 0) {
            $st = db_fetch_one('SELECT is_final FROM tarefas_status WHERE id = ?', ['i', $status_id]);
            if (!$st) { $status_id = 0; } else { $statusFinal = (int) $st['is_final'] === 1; }
        }

        try {
            $uid_log    = (int) $current_user->uid;
            $eh_novo    = $id <= 0;
            $antes      = null;
            $resp_antes = [];

            if ($id > 0) {
                $tarefa = db_fetch_one(
                    'SELECT id, titulo, descricao, setor_id, status_id, prazo, concluida_em FROM tarefas WHERE id = ?',
                    ['i', $id]
                );
                $antes = $tarefa;
                if (!$tarefa) {
                    return self::json(['ok' => false, 'message' => 'Tarefa não encontrada.']);
                }
                if (!self::podeVer($ctx, $tarefa['setor_id'])) {
                    return self::json(['ok' => false, 'message' => 'Sem permissão para editar esta tarefa.']);
                }

                // Mantém concluida_em existente; só define/limpa conforme o status final.
                $concluidaSql = $statusFinal
                    ? (empty($tarefa['concluida_em']) ? 'NOW()' : 'concluida_em')
                    : 'NULL';

                db_execute(
                    "UPDATE tarefas
                        SET titulo = ?, descricao = ?, setor_id = ?, status_id = ?, prioridade = ?, prazo = ?,
                            concluida_em = {$concluidaSql}
                      WHERE id = ?",
                    ['ssiissi',
                        $titulo,
                        $descricao !== '' ? $descricao : null,
                        $setor_id,
                        $status_id ?: null,
                        $prioridade !== '' ? $prioridade : null,
                        $prazo !== '' ? $prazo : null,
                        $id,
                    ]
                );
            } else {
                $concluidaSql = $statusFinal ? 'NOW()' : 'NULL';
                // db_execute() do INSERT já devolve o id gerado (era db()->insert_id).
                $id = (int) db_execute(
                    "INSERT INTO tarefas (titulo, descricao, setor_id, status_id, prioridade, prazo, criado_por, concluida_em)
                     VALUES (?, ?, ?, ?, ?, ?, ?, {$concluidaSql})",
                    ['ssiissi',
                        $titulo,
                        $descricao !== '' ? $descricao : null,
                        $setor_id,
                        $status_id ?: null,
                        $prioridade !== '' ? $prioridade : null,
                        $prazo !== '' ? $prazo : null,
                        (int) $current_user->uid,
                    ]
                );
            }

            // ── Histórico: criação ou diff dos campos alterados ─────────────────
            if ($eh_novo) {
                tarefa_log($id, $uid_log, 'criou');
            } elseif ($antes) {
                if (trim((string) $antes['titulo']) !== $titulo) {
                    tarefa_log($id, $uid_log, 'titulo', $titulo);
                }
                if (trim((string) ($antes['descricao'] ?? '')) !== $descricao) {
                    tarefa_log($id, $uid_log, 'descricao');
                }
                if ((int) $antes['setor_id'] !== $setor_id) {
                    $s = db_fetch_one('SELECT nome FROM setores WHERE id = ?', ['i', $setor_id]);
                    tarefa_log($id, $uid_log, 'setor', $s['nome'] ?? '');
                }
                if ((int) ($antes['status_id'] ?? 0) !== $status_id) {
                    $nome_st = '';
                    if ($status_id > 0) {
                        $s = db_fetch_one('SELECT nome FROM tarefas_status WHERE id = ?', ['i', $status_id]);
                        $nome_st = $s['nome'] ?? '';
                    }
                    tarefa_log($id, $uid_log, 'status', $nome_st);
                }
                $prazo_antes = (string) ($antes['prazo'] ?? '');
                if ($prazo_antes !== $prazo) {
                    tarefa_log($id, $uid_log, 'prazo', $prazo !== '' ? date('d/m/Y', strtotime($prazo)) : '');
                }
            }

            // ── Responsáveis (apaga + reinsere) ─────────────────────────────────
            if (!$eh_novo) {
                $rows_antes = db_fetch_all('SELECT usuario_id FROM tarefas_responsaveis WHERE tarefa_id = ?', ['i', $id]);
                $resp_antes = array_map(static fn($r) => (int) $r['usuario_id'], $rows_antes);
            }
            db_execute('DELETE FROM tarefas_responsaveis WHERE tarefa_id = ?', ['i', $id]);
            foreach ($responsaveis as $uid) {
                if ($uid > 0) {
                    db_execute('INSERT IGNORE INTO tarefas_responsaveis (tarefa_id, usuario_id) VALUES (?, ?)', ['ii', $id, $uid]);
                }
            }

            if (!$eh_novo) {
                $frase = tarefa_resp_diff_frase($resp_antes, array_filter($responsaveis, static fn($u) => $u > 0));
                if ($frase !== '') tarefa_log($id, $uid_log, 'responsaveis', $frase);
            }

            // ── Etiquetas (apaga + reinsere) ────────────────────────────────────
            db_execute('DELETE FROM tarefas_etiquetas_vinculo WHERE tarefa_id = ?', ['i', $id]);
            foreach ($etiquetas as $eid) {
                if ($eid > 0) {
                    db_execute('INSERT IGNORE INTO tarefas_etiquetas_vinculo (tarefa_id, etiqueta_id) VALUES (?, ?)', ['ii', $id, $eid]);
                }
            }

            // ── Campos customizados (só os campos ativos existentes) ────────────
            db_execute('DELETE FROM tarefas_campos_valores WHERE tarefa_id = ?', ['i', $id]);
            $idsValidos = array_map(static fn($c) => (int) $c['id'], db_fetch_all('SELECT id FROM tarefas_campos WHERE ativo = 1'));
            foreach ($campos as $campo_id => $valor) {
                $cid = (int) $campo_id;
                if (!in_array($cid, $idsValidos, true)) continue;
                $valor = is_array($valor) ? implode(', ', $valor) : (string) $valor;
                if (trim($valor) === '') continue;
                db_execute(
                    'INSERT INTO tarefas_campos_valores (tarefa_id, campo_id, valor) VALUES (?, ?, ?)',
                    ['iis', $id, $cid, $valor]
                );
            }

            return self::json(['ok' => true, 'id' => $id]);
        } catch (\Throwable $e) {
            error_log('[admin-tarefas-salvar] ' . $e->getMessage());
            return self::json(['ok' => false, 'message' => 'Erro ao salvar a tarefa.']);
        }
    }

    /**
     * POST /api/admin/tarefas/status  { id, status_id }
     * Troca rápida do status (menu inline, estilo Monday); define/limpa
     * concluida_em conforme is_final.
     */
    public function status(Request $request)
    {
        $current_user = require_admin();

        $ctx       = tarefas_contexto_usuario((int) $current_user->uid);
        $input     = $request->json()->all();
        $id        = (int) ($input['id'] ?? 0);
        $status_id = (int) ($input['status_id'] ?? 0);

        if ($id <= 0) return self::json(['ok' => false, 'message' => 'Tarefa inválida.']);

        $tarefa = db_fetch_one('SELECT id, setor_id, status_id FROM tarefas WHERE id = ?', ['i', $id]);
        if (!$tarefa) return self::json(['ok' => false, 'message' => 'Tarefa não encontrada.']);

        if (!self::podeVer($ctx, $tarefa['setor_id'])) {
            return self::json(['ok' => false, 'message' => 'Sem permissão para esta tarefa.'], 403);
        }

        $final   = false;
        $nome_st = '';
        if ($status_id > 0) {
            $st = db_fetch_one('SELECT is_final, nome FROM tarefas_status WHERE id = ?', ['i', $status_id]);
            if (!$st) { $status_id = 0; } else { $final = (int) $st['is_final'] === 1; $nome_st = $st['nome']; }
        }

        // Status de conclusão marca concluida_em (mantém a data se já existir); senão limpa.
        $concluidaSql = $final ? 'COALESCE(concluida_em, NOW())' : 'NULL';

        try {
            db_execute(
                "UPDATE tarefas SET status_id = ?, concluida_em = {$concluidaSql} WHERE id = ?",
                ['ii', $status_id ?: null, $id]
            );
            if ((int) ($tarefa['status_id'] ?? 0) !== $status_id) {
                tarefa_log($id, (int) $current_user->uid, 'status', $nome_st);
            }
            return self::json(['ok' => true]);
        } catch (\Throwable $e) {
            error_log('[admin-tarefas-status] ' . $e->getMessage());
            return self::json(['ok' => false, 'message' => 'Erro ao alterar status.']);
        }
    }

    /**
     * POST /api/admin/tarefas/responsaveis  { id, usuarios: [ids] }
     * Define (substitui) os responsáveis de uma tarefa — usado pelo popup inline.
     */
    public function responsaveis(Request $request)
    {
        $current_user = require_admin();

        $ctx      = tarefas_contexto_usuario((int) $current_user->uid);
        $input    = $request->json()->all();
        $id       = (int) ($input['id'] ?? 0);
        $usuarios = array_values(array_unique(array_map('intval', (array) ($input['usuarios'] ?? []))));

        if ($id <= 0) return self::json(['ok' => false, 'message' => 'Tarefa inválida.']);

        $tarefa = db_fetch_one(
            'SELECT t.id, t.titulo, t.setor_id, t.prazo, s.nome AS setor_nome
               FROM tarefas t LEFT JOIN setores s ON s.id = t.setor_id
              WHERE t.id = ?',
            ['i', $id]
        );
        if (!$tarefa) return self::json(['ok' => false, 'message' => 'Tarefa não encontrada.']);

        if (!self::podeVer($ctx, $tarefa['setor_id'])) {
            return self::json(['ok' => false, 'message' => 'Sem permissão para esta tarefa.'], 403);
        }

        try {
            $rows_antes = db_fetch_all('SELECT usuario_id FROM tarefas_responsaveis WHERE tarefa_id = ?', ['i', $id]);
            $antes      = array_map(static fn($r) => (int) $r['usuario_id'], $rows_antes);

            db_execute('DELETE FROM tarefas_responsaveis WHERE tarefa_id = ?', ['i', $id]);
            foreach ($usuarios as $uid) {
                if ($uid > 0) {
                    db_execute('INSERT IGNORE INTO tarefas_responsaveis (tarefa_id, usuario_id) VALUES (?, ?)', ['ii', $id, $uid]);
                }
            }

            $depois = array_values(array_filter($usuarios, static fn($u) => $u > 0));
            $frase  = tarefa_resp_diff_frase($antes, $depois);
            if ($frase !== '') tarefa_log($id, (int) $current_user->uid, 'responsaveis', $frase);

            // Avisa (sino + e-mail) quem acabou de ser atribuído à tarefa.
            $titulo_tarefa = trim((string) $tarefa['titulo']) !== '' ? $tarefa['titulo'] : 'Tarefa sem título';
            foreach (array_diff($depois, $antes) as $novo) {
                if ((int) $novo === (int) $current_user->uid) continue;   // não notifica quem fez a ação
                tarefa_notificar(
                    (int) $novo,
                    'tarefa_atribuida',
                    'Nova tarefa atribuída a você',
                    ($current_user->nome ?? 'Alguém') . ' atribuiu a você a tarefa "' . $titulo_tarefa . '".',
                    true,
                    tarefa_email_detalhes([
                        'Tarefa' => $titulo_tarefa,
                        'Setor'  => $tarefa['setor_nome'] ?? 'Sem setor',
                        'Prazo'  => $tarefa['prazo'] ? date('d/m/Y', strtotime($tarefa['prazo'])) : 'Sem prazo',
                    ])
                );
            }

            return self::json(['ok' => true]);
        } catch (\Throwable $e) {
            error_log('[admin-tarefas-responsaveis] ' . $e->getMessage());
            return self::json(['ok' => false, 'message' => 'Erro ao salvar responsáveis.']);
        }
    }

    /**
     * POST /api/admin/tarefas/prazo  { id, prazo }  (prazo = 'YYYY-MM-DD' ou '')
     * Define/limpa o prazo de uma tarefa (calendário inline).
     */
    public function prazo(Request $request)
    {
        $current_user = require_admin();

        $ctx   = tarefas_contexto_usuario((int) $current_user->uid);
        $input = $request->json()->all();
        $id    = (int) ($input['id'] ?? 0);
        $prazo = trim($input['prazo'] ?? '');

        if ($id <= 0) return self::json(['ok' => false, 'message' => 'Tarefa inválida.']);
        if ($prazo !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $prazo)) $prazo = '';

        $tarefa = db_fetch_one('SELECT id, setor_id, prazo FROM tarefas WHERE id = ?', ['i', $id]);
        if (!$tarefa) return self::json(['ok' => false, 'message' => 'Tarefa não encontrada.']);

        if (!self::podeVer($ctx, $tarefa['setor_id'])) {
            return self::json(['ok' => false, 'message' => 'Sem permissão para esta tarefa.'], 403);
        }

        try {
            db_execute('UPDATE tarefas SET prazo = ? WHERE id = ?', ['si', $prazo !== '' ? $prazo : null, $id]);
            if ((string) ($tarefa['prazo'] ?? '') !== $prazo) {
                tarefa_log($id, (int) $current_user->uid, 'prazo', $prazo !== '' ? date('d/m/Y', strtotime($prazo)) : '');
            }
            return self::json(['ok' => true]);
        } catch (\Throwable $e) {
            error_log('[admin-tarefas-prazo] ' . $e->getMessage());
            return self::json(['ok' => false, 'message' => 'Erro ao salvar prazo.']);
        }
    }

    /**
     * GET /api/admin/tarefas/detalhe?id=N
     * Tudo que o offcanvas da tarefa precisa numa chamada só: dados da tarefa,
     * responsáveis, anexos, comentários (com menções) e histórico.
     */
    public function detalhe(Request $request)
    {
        $current_user = require_admin();

        $ctx = tarefas_contexto_usuario((int) $current_user->uid);
        $id  = (int) $request->query('id', 0);

        if ($id <= 0) return self::json(['ok' => false, 'message' => 'Tarefa inválida.']);

        try {
            $t = db_fetch_one(
                "SELECT t.id, t.titulo, t.descricao, t.setor_id, t.status_id, t.prazo,
                        t.criado_em, t.concluida_em, t.criado_por,
                        s.nome AS setor_nome, s.cor AS setor_cor,
                        st.nome AS status_nome, st.cor AS status_cor,
                        u.username AS criado_por_nome
                   FROM tarefas t
                   LEFT JOIN setores s          ON s.id  = t.setor_id
                   LEFT JOIN tarefas_status st  ON st.id = t.status_id
                   LEFT JOIN usuario u          ON u.id  = t.criado_por
                  WHERE t.id = ?",
                ['i', $id]
            );
            if (!$t) return self::json(['ok' => false, 'message' => 'Tarefa não encontrada.']);

            if (!self::podeVer($ctx, $t['setor_id'])) {
                return self::json(['ok' => false, 'message' => 'Sem permissão para esta tarefa.'], 403);
            }

            $responsaveis = db_fetch_all(
                "SELECT u.id, u.username, u.avatar
                   FROM tarefas_responsaveis tr JOIN usuario u ON u.id = tr.usuario_id
                  WHERE tr.tarefa_id = ? ORDER BY u.username ASC",
                ['i', $id]
            );

            $anexos = db_fetch_all(
                'SELECT id, url, nome, tipo, tamanho, criado_em FROM tarefas_anexos WHERE tarefa_id = ? ORDER BY id DESC',
                ['i', $id]
            );

            $comentarios = db_fetch_all(
                "SELECT c.id, c.mensagem, c.criado_em, c.usuario_id, u.username, u.avatar
                   FROM tarefas_comentarios c LEFT JOIN usuario u ON u.id = c.usuario_id
                  WHERE c.tarefa_id = ? ORDER BY c.id ASC",
                ['i', $id]
            );

            // Menções de cada comentário (para destacar no texto).
            $mencoes = [];
            if ($comentarios) {
                $ids = array_map(static fn($c) => (int) $c['id'], $comentarios);
                $ph  = implode(',', array_fill(0, count($ids), '?'));
                $rows = db_fetch_all(
                    "SELECT m.comentario_id, u.username
                       FROM tarefas_comentarios_mencoes m JOIN usuario u ON u.id = m.usuario_id
                      WHERE m.comentario_id IN ({$ph})",
                    [str_repeat('i', count($ids)), ...$ids]
                );
                foreach ($rows as $r) { $mencoes[(int) $r['comentario_id']][] = $r['username']; }
            }
            foreach ($comentarios as &$c) {
                $c['id']        = (int) $c['id'];
                $c['mencoes']   = $mencoes[$c['id']] ?? [];
                $c['criado_em'] = date('d/m/Y H:i', strtotime($c['criado_em']));
            }
            unset($c);

            // Abrir a tarefa marca os comentários como lidos (zera o badge do balão).
            db_execute(
                'INSERT INTO tarefas_leitura (tarefa_id, usuario_id, lido_em) VALUES (?, ?, NOW())
                 ON DUPLICATE KEY UPDATE lido_em = NOW()',
                ['ii', $id, (int) $current_user->uid]
            );

            $hist = db_fetch_all(
                "SELECT h.acao, h.detalhe, h.criado_em, h.usuario_id, u.username, u.avatar
                   FROM tarefas_historico h LEFT JOIN usuario u ON u.id = h.usuario_id
                  WHERE h.tarefa_id = ? ORDER BY h.id DESC",
                ['i', $id]
            );
            $historico = array_map(static fn($h) => [
                'usuario'   => $h['username'] ?: 'Sistema',
                'avatar'    => $h['avatar'] ?? null,
                'acao'      => $h['acao'],
                'icone'     => tarefa_historico_icone($h['acao']),
                'frase'     => tarefa_historico_frase($h['acao'], $h['detalhe']),
                'criado_em' => date('d/m/Y H:i', strtotime($h['criado_em'])),
            ], $hist);

            return self::json([
                'ok'    => true,
                'tarefa' => [
                    'id'           => (int) $t['id'],
                    'titulo'       => $t['titulo'],
                    'descricao'    => $t['descricao'] ?? '',
                    'setor_id'     => (int) $t['setor_id'],
                    'setor_nome'   => $t['setor_nome'] ?? '',
                    'setor_cor'    => $t['setor_cor'] ?: '#94a3b8',
                    'status_id'    => (int) ($t['status_id'] ?? 0),
                    'status_nome'  => $t['status_nome'] ?? '',
                    'status_cor'   => $t['status_cor'] ?: '#64748b',
                    'prazo'        => $t['prazo'] ?? '',
                    'prazo_label'  => $t['prazo'] ? date('d/m/Y', strtotime($t['prazo'])) : '',
                    'criado_em'    => $t['criado_em'] ? date('d/m/Y H:i', strtotime($t['criado_em'])) : '',
                    'criado_por'   => $t['criado_por_nome'] ?? '',
                    'concluida_em' => $t['concluida_em'] ? date('d/m/Y H:i', strtotime($t['concluida_em'])) : '',
                ],
                'responsaveis' => array_map(static fn($r) => [
                    'id'       => (int) $r['id'],
                    'username' => $r['username'],
                    'avatar'   => $r['avatar'] ?? null,
                ], $responsaveis),
                'anexos'      => array_map(static fn($a) => [
                    'id'     => (int) $a['id'],
                    'url'    => $a['url'],
                    'nome'   => $a['nome'],
                    'tipo'   => $a['tipo'],
                ], $anexos),
                'comentarios' => $comentarios,
                'historico'   => $historico,
            ]);
        } catch (\Throwable $e) {
            error_log('[admin-tarefas-detalhe] ' . $e->getMessage());
            return self::json(['ok' => false, 'message' => 'Erro ao carregar a tarefa.']);
        }
    }

    /**
     * POST /api/admin/tarefas/comentar  { id, mensagem, mencoes: [ids] }
     * Adiciona um comentário à tarefa e notifica os usuários mencionados.
     * Só aceita menção de quem enxerga a tarefa (gestor ou membro do setor).
     */
    public function comentar(Request $request)
    {
        $current_user = require_admin();

        $ctx   = tarefas_contexto_usuario((int) $current_user->uid);
        $input = $request->json()->all();

        $id       = (int) ($input['id'] ?? 0);
        $mensagem = trim((string) ($input['mensagem'] ?? ''));
        $mencoes  = array_values(array_unique(array_map('intval', (array) ($input['mencoes'] ?? []))));

        if ($id <= 0)         return self::json(['ok' => false, 'message' => 'Tarefa inválida.']);
        if ($mensagem === '') return self::json(['ok' => false, 'message' => 'Escreva um comentário.']);
        if (mb_strlen($mensagem) > 5000) $mensagem = mb_substr($mensagem, 0, 5000);

        $tarefa = db_fetch_one('SELECT id, titulo, setor_id FROM tarefas WHERE id = ?', ['i', $id]);
        if (!$tarefa) return self::json(['ok' => false, 'message' => 'Tarefa não encontrada.']);

        if (!self::podeVer($ctx, $tarefa['setor_id'])) {
            return self::json(['ok' => false, 'message' => 'Sem permissão para esta tarefa.'], 403);
        }

        try {
            $uid = (int) $current_user->uid;

            $comentarioId = (int) db_execute(
                'INSERT INTO tarefas_comentarios (tarefa_id, usuario_id, mensagem, criado_em) VALUES (?, ?, ?, NOW())',
                ['iis', $id, $uid, $mensagem]
            );

            // Só menciona quem enxerga a tarefa: gestor ou membro do setor dela.
            $validos = [];
            if ($mencoes) {
                $ph = implode(',', array_fill(0, count($mencoes), '?'));
                $validos = db_fetch_all(
                    "SELECT u.id, u.username
                       FROM usuario u
                      WHERE u.id IN ({$ph})
                        AND (u.tarefas_gestor = 'S'
                             OR EXISTS (SELECT 1 FROM tarefas_usuario_setor us
                                         WHERE us.usuario_id = u.id AND us.setor_id = ?))",
                    [str_repeat('i', count($mencoes)) . 'i', ...[...$mencoes, (int) $tarefa['setor_id']]]
                );
            }

            $nomes_mencionados = [];
            foreach ($validos as $m) {
                $mid = (int) $m['id'];
                $nomes_mencionados[] = $m['username'];
                db_execute(
                    'INSERT IGNORE INTO tarefas_comentarios_mencoes (comentario_id, usuario_id) VALUES (?, ?)',
                    ['ii', $comentarioId, $mid]
                );
                if ($mid !== $uid) {
                    notificar_mps(
                        'admin',
                        $mid,
                        'tarefa_mencao',
                        'Você foi mencionado em uma tarefa',
                        ($current_user->nome ?? 'Alguém') . ' mencionou você em "' . $tarefa['titulo'] . '".'
                    );
                }
            }

            tarefa_log($id, $uid, 'comentou');

            $autor = db_fetch_one('SELECT username, avatar FROM usuario WHERE id = ?', ['i', $uid]);

            return self::json([
                'ok'         => true,
                'comentario' => [
                    'id'         => $comentarioId,
                    'usuario_id' => $uid,
                    'username'   => $autor['username'] ?? '',
                    'avatar'     => $autor['avatar'] ?? null,
                    'mensagem'   => $mensagem,
                    'mencoes'    => $nomes_mencionados,
                    'criado_em'  => date('d/m/Y H:i'),
                ],
            ]);
        } catch (\Throwable $e) {
            error_log('[admin-tarefas-comentar] ' . $e->getMessage());
            return self::json(['ok' => false, 'message' => 'Erro ao salvar o comentário.']);
        }
    }

    /**
     * POST /api/admin/tarefas/criar-rapido  { setor_id, status_id, depois_de_id }
     * Cria uma tarefa em branco (só com setor/status do grupo) para o usuário
     * preencher no offcanvas — é o "+ Adicionar tarefa" da lista, estilo Monday.
     */
    public function criarRapido(Request $request)
    {
        $current_user = require_admin();

        $ctx   = tarefas_contexto_usuario((int) $current_user->uid);
        $input = $request->json()->all();

        $setor_id     = (int) ($input['setor_id'] ?? 0);
        $status_id    = (int) ($input['status_id'] ?? 0);
        $depois_de_id = (int) ($input['depois_de_id'] ?? 0);

        if ($setor_id <= 0) {
            return self::json(['ok' => false, 'message' => 'Selecione um setor para criar a tarefa.']);
        }

        $setor = db_fetch_one('SELECT id FROM setores WHERE id = ?', ['i', $setor_id]);
        if (!$setor) return self::json(['ok' => false, 'message' => 'Setor inválido.']);

        if (!$ctx['gestor'] && !in_array($setor_id, $ctx['setores'], true)) {
            return self::json(['ok' => false, 'message' => 'Você não pode criar tarefas neste setor.'], 403);
        }

        // Sem status informado, usa o primeiro status ativo (equivale ao "Pendente").
        if ($status_id > 0) {
            $st = db_fetch_one('SELECT id, is_final FROM tarefas_status WHERE id = ?', ['i', $status_id]);
            if (!$st) $status_id = 0;
        }
        if ($status_id <= 0) {
            $st = db_fetch_one('SELECT id FROM tarefas_status WHERE ativo = 1 ORDER BY ordem ASC, id ASC LIMIT 1');
            $status_id = (int) ($st['id'] ?? 0);
        }

        $final = false;
        if ($status_id > 0) {
            $s = db_fetch_one('SELECT is_final FROM tarefas_status WHERE id = ?', ['i', $status_id]);
            $final = (int) ($s['is_final'] ?? 0) === 1;
        }

        try {
            $id = (int) db_execute(
                'INSERT INTO tarefas (titulo, descricao, setor_id, status_id, prioridade, prazo, criado_por, concluida_em)
                 VALUES (?, NULL, ?, ?, NULL, NULL, ?, ' . ($final ? 'NOW()' : 'NULL') . ')',
                ['siii', '', $setor_id, $status_id ?: null, (int) $current_user->uid]
            );

            tarefa_log($id, (int) $current_user->uid, 'criou');

            // "Criar abaixo": insere logo após a tarefa de referência dentro do mesmo
            // grupo (setor, e status quando aplicável) e renumera o restante.
            if ($depois_de_id > 0) {
                $ref = db_fetch_one('SELECT id, setor_id, status_id FROM tarefas WHERE id = ?', ['i', $depois_de_id]);
                if ($ref && (int) $ref['setor_id'] === $setor_id) {
                    $membros = db_fetch_all(
                        'SELECT id FROM tarefas WHERE setor_id = ? AND status_id <=> ? AND arquivada_em IS NULL AND id != ? ORDER BY ordem ASC, id ASC',
                        ['iii', $setor_id, $ref['status_id'], $id]
                    );
                    $ordenados = [];
                    foreach ($membros as $m) {
                        $ordenados[] = (int) $m['id'];
                        if ((int) $m['id'] === $depois_de_id) $ordenados[] = $id;
                    }
                    if (!in_array($id, $ordenados, true)) $ordenados[] = $id;
                    tarefas_renumerar_grupo($ordenados);
                }
            }

            return self::json(['ok' => true, 'id' => $id]);
        } catch (\Throwable $e) {
            error_log('[admin-tarefas-criar-rapido] ' . $e->getMessage());
            return self::json(['ok' => false, 'message' => 'Erro ao criar a tarefa.']);
        }
    }

    /**
     * POST /api/admin/tarefas/campo  { id, campo, valor }
     * Salva título ou descrição direto do offcanvas, sem passar pelo salvar
     * completo — assim não mexe em responsáveis/campos custom.
     */
    public function campo(Request $request)
    {
        $current_user = require_admin();

        $ctx   = tarefas_contexto_usuario((int) $current_user->uid);
        $input = $request->json()->all();

        $id    = (int) ($input['id'] ?? 0);
        $campo = trim((string) ($input['campo'] ?? ''));
        $valor = trim((string) ($input['valor'] ?? ''));

        if ($id <= 0) return self::json(['ok' => false, 'message' => 'Tarefa inválida.']);
        if (!in_array($campo, ['titulo', 'descricao'], true)) {
            return self::json(['ok' => false, 'message' => 'Campo inválido.']);
        }

        $limite = $campo === 'titulo' ? 255 : 20000;
        if (mb_strlen($valor) > $limite) $valor = mb_substr($valor, 0, $limite);

        $tarefa = db_fetch_one('SELECT id, setor_id, titulo, descricao FROM tarefas WHERE id = ?', ['i', $id]);
        if (!$tarefa) return self::json(['ok' => false, 'message' => 'Tarefa não encontrada.']);

        if (!self::podeVer($ctx, $tarefa['setor_id'])) {
            return self::json(['ok' => false, 'message' => 'Sem permissão para esta tarefa.'], 403);
        }

        try {
            if ($campo === 'titulo') {
                db_execute('UPDATE tarefas SET titulo = ? WHERE id = ?', ['si', $valor, $id]);
                if (trim((string) $tarefa['titulo']) !== $valor && $valor !== '') {
                    tarefa_log($id, (int) $current_user->uid, 'titulo', $valor);
                }
            } else {
                db_execute('UPDATE tarefas SET descricao = ? WHERE id = ?', ['si', $valor !== '' ? $valor : null, $id]);
                if (trim((string) ($tarefa['descricao'] ?? '')) !== $valor) {
                    tarefa_log($id, (int) $current_user->uid, 'descricao');
                }
            }
            return self::json(['ok' => true]);
        } catch (\Throwable $e) {
            error_log('[admin-tarefas-campo] ' . $e->getMessage());
            return self::json(['ok' => false, 'message' => 'Erro ao salvar.']);
        }
    }

    /**
     * POST /api/admin/tarefas/excluir  { id }
     * Exclui uma tarefa e seus vínculos (responsáveis, etiquetas, valores de campos).
     */
    public function excluir(Request $request)
    {
        $current_user = require_admin();

        $ctx   = tarefas_contexto_usuario((int) $current_user->uid);
        $input = $request->json()->all();
        $id    = (int) ($input['id'] ?? 0);

        if ($id <= 0) return self::json(['ok' => false, 'message' => 'Tarefa inválida.']);

        $tarefa = db_fetch_one('SELECT id, setor_id FROM tarefas WHERE id = ?', ['i', $id]);
        if (!$tarefa) return self::json(['ok' => false, 'message' => 'Tarefa não encontrada.']);
        if (!self::podeVer($ctx, $tarefa['setor_id'])) {
            return self::json(['ok' => false, 'message' => 'Sem permissão para excluir esta tarefa.']);
        }

        try {
            db_execute('DELETE FROM tarefas_responsaveis WHERE tarefa_id = ?', ['i', $id]);
            db_execute('DELETE FROM tarefas_etiquetas_vinculo WHERE tarefa_id = ?', ['i', $id]);
            db_execute('DELETE FROM tarefas_campos_valores WHERE tarefa_id = ?', ['i', $id]);
            db_execute('DELETE FROM tarefas WHERE id = ?', ['i', $id]);
            return self::json(['ok' => true]);
        } catch (\Throwable $e) {
            error_log('[admin-tarefas-excluir] ' . $e->getMessage());
            return self::json(['ok' => false, 'message' => 'Erro ao excluir a tarefa.']);
        }
    }

    /**
     * POST /api/admin/tarefas/mover
     * { id, agrupar_por: 'status'|'setor', grupo_id, ordenados: [ids em ordem no grupo destino] }
     *
     * Drag-and-drop da lista estilo Monday: reordena dentro do grupo e, quando o
     * grupo mudou, troca setor (visão "Todas") ou status (aba de setor) da tarefa.
     */
    public function mover(Request $request)
    {
        $current_user = require_admin();

        $ctx   = tarefas_contexto_usuario((int) $current_user->uid);
        $input = $request->json()->all();

        $id          = (int) ($input['id'] ?? 0);
        $agrupar_por = trim($input['agrupar_por'] ?? '');
        $grupo_id    = (int) ($input['grupo_id'] ?? 0);
        $ordenados   = array_values(array_filter(array_map('intval', (array) ($input['ordenados'] ?? []))));

        if ($id <= 0) return self::json(['ok' => false, 'message' => 'Tarefa inválida.']);
        if (!in_array($agrupar_por, ['status', 'setor'], true)) return self::json(['ok' => false, 'message' => 'Agrupamento inválido.']);

        $tarefa = db_fetch_one('SELECT id, setor_id, status_id FROM tarefas WHERE id = ?', ['i', $id]);
        if (!$tarefa) return self::json(['ok' => false, 'message' => 'Tarefa não encontrada.']);

        if (!self::podeVer($ctx, $tarefa['setor_id'])) {
            return self::json(['ok' => false, 'message' => 'Sem permissão para esta tarefa.'], 403);
        }

        try {
            $uid_log = (int) $current_user->uid;

            if ($agrupar_por === 'setor') {
                // Visão "Todas": grupo = setor. Trocar de grupo troca a equipe da tarefa.
                if ($grupo_id > 0 && !$ctx['gestor'] && !in_array($grupo_id, $ctx['setores'], true)) {
                    return self::json(['ok' => false, 'message' => 'Sem permissão para o setor de destino.'], 403);
                }
                if ($grupo_id > 0 && (int) $tarefa['setor_id'] !== $grupo_id) {
                    $setor = db_fetch_one('SELECT id, nome FROM setores WHERE id = ?', ['i', $grupo_id]);
                    if (!$setor) return self::json(['ok' => false, 'message' => 'Setor inválido.']);
                    db_execute('UPDATE tarefas SET setor_id = ? WHERE id = ?', ['ii', $grupo_id, $id]);
                    tarefa_log($id, $uid_log, 'setor', $setor['nome']);
                }
            } else {
                // Aba de setor: grupo = status. Trocar de grupo troca o status (kanban).
                $status_id = $grupo_id > 0 ? $grupo_id : null;
                if ((int) ($tarefa['status_id'] ?? 0) !== ($status_id ?? 0)) {
                    $nome_st = '';
                    $final   = false;
                    if ($status_id) {
                        $st = db_fetch_one('SELECT nome, is_final FROM tarefas_status WHERE id = ?', ['i', $status_id]);
                        if (!$st) return self::json(['ok' => false, 'message' => 'Status inválido.']);
                        $nome_st = $st['nome'];
                        $final   = (int) $st['is_final'] === 1;
                    }
                    $concluidaSql = $final ? 'COALESCE(concluida_em, NOW())' : 'NULL';
                    db_execute("UPDATE tarefas SET status_id = ?, concluida_em = {$concluidaSql} WHERE id = ?", ['ii', $status_id, $id]);
                    tarefa_log($id, $uid_log, 'status', $nome_st);
                }
            }

            if (!empty($ordenados)) {
                tarefas_renumerar_grupo($ordenados);
            }

            return self::json(['ok' => true]);
        } catch (\Throwable $e) {
            error_log('[admin-tarefas-mover] ' . $e->getMessage());
            return self::json(['ok' => false, 'message' => 'Erro ao mover a tarefa.']);
        }
    }

    /**
     * POST /api/admin/tarefas/arquivar  { id, arquivar: bool }
     * Arquiva/desarquiva uma tarefa (mesmo endpoint serve os dois sentidos).
     * Tarefas arquivadas somem da lista/contadores padrão até o filtro "Arquivadas".
     */
    public function arquivar(Request $request)
    {
        $current_user = require_admin();

        $ctx      = tarefas_contexto_usuario((int) $current_user->uid);
        $input    = $request->json()->all();
        $id       = (int) ($input['id'] ?? 0);
        $arquivar = !empty($input['arquivar']);

        if ($id <= 0) return self::json(['ok' => false, 'message' => 'Tarefa inválida.']);

        $tarefa = db_fetch_one('SELECT id, setor_id FROM tarefas WHERE id = ?', ['i', $id]);
        if (!$tarefa) return self::json(['ok' => false, 'message' => 'Tarefa não encontrada.']);

        if (!self::podeVer($ctx, $tarefa['setor_id'])) {
            return self::json(['ok' => false, 'message' => 'Sem permissão para esta tarefa.'], 403);
        }

        try {
            db_execute(
                'UPDATE tarefas SET arquivada_em = ' . ($arquivar ? 'NOW()' : 'NULL') . ' WHERE id = ?',
                ['i', $id]
            );
            tarefa_log($id, (int) $current_user->uid, $arquivar ? 'arquivou' : 'desarquivou');
            return self::json(['ok' => true]);
        } catch (\Throwable $e) {
            error_log('[admin-tarefas-arquivar] ' . $e->getMessage());
            return self::json(['ok' => false, 'message' => 'Erro ao arquivar a tarefa.']);
        }
    }

    /**
     * GET /api/admin/tarefas/dashboard
     * KPIs e panoramas do módulo (gestor-only): totais gerais + breakdown por
     * status, por setor e por responsável, prazos próximos e tarefas paradas.
     */
    public function dashboard()
    {
        $current_user = require_admin();

        $ctx = tarefas_contexto_usuario((int) $current_user->uid);
        if (!$ctx['gestor']) {
            return self::json(['ok' => false, 'message' => 'Apenas gestores acessam o dashboard.'], 403);
        }

        $kpis       = ['total' => 0, 'pendentes' => 0, 'concluidas' => 0, 'atrasadas' => 0];
        $por_status = [];
        $por_setor  = [];
        $por_resp   = [];
        $prazos     = ['vence_hoje' => 0, 'em_1_3' => 0, 'em_4_7' => 0, 'lista' => []];
        $sem_inter  = ['b_3_7' => 0, 'b_8_14' => 0, 'b_15' => 0, 'lista' => []];

        try {
            $kpis['total']      = (int) (db_fetch_one('SELECT COUNT(*) AS n FROM tarefas')['n'] ?? 0);
            $kpis['concluidas'] = (int) (db_fetch_one('SELECT COUNT(*) AS n FROM tarefas WHERE concluida_em IS NOT NULL')['n'] ?? 0);
            $kpis['pendentes']  = $kpis['total'] - $kpis['concluidas'];
            $kpis['atrasadas']  = (int) (db_fetch_one(
                'SELECT COUNT(*) AS n FROM tarefas WHERE prazo IS NOT NULL AND prazo < CURDATE() AND concluida_em IS NULL'
            )['n'] ?? 0);

            // Distribuição por status (donut).
            $por_status = db_fetch_all(
                "SELECT st.id, st.nome, st.cor, COUNT(t.id) AS total
                   FROM tarefas_status st
                   LEFT JOIN tarefas t ON t.status_id = st.id
               GROUP BY st.id, st.nome, st.cor
               ORDER BY st.ordem ASC, st.id ASC"
            );

            // Por setor detalhado: concluídas / em aberto / atrasadas (barra empilhada).
            $por_setor = db_fetch_all(
                "SELECT s.id, s.nome, s.cor,
                        COUNT(t.id) AS total,
                        SUM(t.concluida_em IS NOT NULL) AS concluidas,
                        SUM(t.concluida_em IS NULL AND t.prazo IS NOT NULL AND t.prazo < CURDATE()) AS atrasadas,
                        SUM(t.concluida_em IS NULL AND (t.prazo IS NULL OR t.prazo >= CURDATE())) AS abertas
                   FROM setores s
                   LEFT JOIN tarefas t ON t.setor_id = s.id
               GROUP BY s.id, s.nome, s.cor
               ORDER BY total DESC, s.nome ASC"
            );

            $por_resp = db_fetch_all(
                "SELECT u.id, u.username, u.avatar, COUNT(tr.tarefa_id) AS total
                   FROM tarefas_responsaveis tr
                   JOIN usuario u ON u.id = tr.usuario_id
               GROUP BY u.id, u.username, u.avatar
               ORDER BY total DESC, u.username ASC
                  LIMIT 8"
            );

            // Prazos próximos (tarefas em aberto).
            $prazos['vence_hoje'] = (int) (db_fetch_one("SELECT COUNT(*) n FROM tarefas WHERE concluida_em IS NULL AND prazo = CURDATE()")['n'] ?? 0);
            $prazos['em_1_3']     = (int) (db_fetch_one("SELECT COUNT(*) n FROM tarefas WHERE concluida_em IS NULL AND prazo BETWEEN CURDATE() + INTERVAL 1 DAY AND CURDATE() + INTERVAL 3 DAY")['n'] ?? 0);
            $prazos['em_4_7']     = (int) (db_fetch_one("SELECT COUNT(*) n FROM tarefas WHERE concluida_em IS NULL AND prazo BETWEEN CURDATE() + INTERVAL 4 DAY AND CURDATE() + INTERVAL 7 DAY")['n'] ?? 0);
            $prazos['lista'] = db_fetch_all(
                "SELECT t.id, t.titulo, s.nome AS setor, s.cor AS setor_cor, t.prazo,
                        DATEDIFF(t.prazo, CURDATE()) AS dias
                   FROM tarefas t LEFT JOIN setores s ON s.id = t.setor_id
                  WHERE t.concluida_em IS NULL AND t.prazo IS NOT NULL
                    AND t.prazo BETWEEN CURDATE() AND CURDATE() + INTERVAL 7 DAY
               ORDER BY t.prazo ASC LIMIT 8"
            );

            // Tempo sem interação (última entre criação, último comentário e último evento).
            $sem_inter['lista'] = db_fetch_all(
                "SELECT t.id, t.titulo, s.nome AS setor, s.cor AS setor_cor,
                        DATEDIFF(NOW(), GREATEST(
                            t.criado_em,
                            COALESCE((SELECT MAX(c.criado_em) FROM tarefas_comentarios c WHERE c.tarefa_id = t.id), t.criado_em),
                            COALESCE((SELECT MAX(h.criado_em) FROM tarefas_historico h WHERE h.tarefa_id = t.id), t.criado_em)
                        )) AS dias
                   FROM tarefas t LEFT JOIN setores s ON s.id = t.setor_id
                  WHERE t.concluida_em IS NULL
               ORDER BY dias DESC LIMIT 8"
            );
            foreach ($sem_inter['lista'] as $r) {
                $d = (int) $r['dias'];
                if ($d >= 15) $sem_inter['b_15']++;
                elseif ($d >= 8) $sem_inter['b_8_14']++;
                elseif ($d >= 3) $sem_inter['b_3_7']++;
            }
        } catch (\Throwable $e) {
            error_log('[admin-tarefas-dashboard] ' . $e->getMessage());
        }

        $norm = static fn(array $rows): array => array_map(static fn($r) => [
            'id'         => (int) ($r['id'] ?? 0),
            'nome'       => $r['nome'] ?? ($r['username'] ?? ''),
            'cor'        => $r['cor'] ?? null,
            'avatar'     => $r['avatar'] ?? null,
            'total'      => (int) ($r['total'] ?? 0),
            'concluidas' => (int) ($r['concluidas'] ?? 0),
            'atrasadas'  => (int) ($r['atrasadas'] ?? 0),
            'abertas'    => (int) ($r['abertas'] ?? 0),
        ], $rows);

        $norm_tarefas = static fn(array $rows): array => array_map(static fn($r) => [
            'id'        => (int) $r['id'],
            'titulo'    => trim((string) $r['titulo']) !== '' ? $r['titulo'] : 'Sem título',
            'setor'     => $r['setor'] ?? 'Sem setor',
            'setor_cor' => $r['setor_cor'] ?: '#94a3b8',
            'prazo'     => isset($r['prazo']) && $r['prazo'] ? date('d/m/Y', strtotime($r['prazo'])) : '',
            'dias'      => (int) ($r['dias'] ?? 0),
        ], $rows);

        return self::json([
            'ok'         => true,
            'kpis'       => $kpis,
            'por_status' => $norm($por_status),
            'por_setor'  => $norm($por_setor),
            'por_resp'   => $norm($por_resp),
            'prazos'     => [
                'vence_hoje' => $prazos['vence_hoje'],
                'em_1_3'     => $prazos['em_1_3'],
                'em_4_7'     => $prazos['em_4_7'],
                'lista'      => $norm_tarefas($prazos['lista']),
            ],
            'sem_interacao' => [
                'b_3_7'  => $sem_inter['b_3_7'],
                'b_8_14' => $sem_inter['b_8_14'],
                'b_15'   => $sem_inter['b_15'],
                'lista'  => $norm_tarefas($sem_inter['lista']),
            ],
        ]);
    }
}
