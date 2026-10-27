<?php

namespace App\Http\Controllers\Visitas;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Visitas (área MPS): Histórico, Relatório diário, Agendar visita (com
 * Google Agenda), comissões/regiões/imobiliária compartilhados.
 * Era pages/admin/visitas.php, pages/admin/visitas/*.php e
 * includes/acao/admin-visitas-*.php.
 */
class VisitasController extends Controller
{
    /** Tipos de seguro das comissões de visita (era const VISITA_TIPOS_SEGURO). */
    private const VISITA_TIPOS_SEGURO = ['fianca', 'cap', 'incendio', 'vida', 'auto'];

    // ─────────────────────────────────────────────────────────────────────────
    // Histórico
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * GET /admin/visitas
     * Histórico de Visitas — lista somente-leitura e paginada do que já foi
     * registrado no Relatório de Visitas, com filtros por período, corretor e
     * imobiliária. Filtros e paginação são tudo via AJAX
     * (/api/admin/visitas-historico/*), a URL nunca muda.
     */
    public function historico()
    {
        $current_user = require_admin();

        $corretores = [];
        try {
            $corretores = db_fetch_all('SELECT id, username FROM usuario ORDER BY username ASC');
        } catch (\Throwable) {}

        $opcoes_corretor = [];
        foreach ($corretores as $c) {
            $opcoes_corretor[(string) $c['id']] = $c['username'];
        }

        $page_title      = 'Histórico de Visitas';
        $page_heading    = 'Histórico de Visitas';
        $page_subheading = 'Todas as visitas registradas pelos atendentes.';
        $page_back       = null;
        $extra_css       = ['dashboard.css', 'cotacoes.css', 'visitas.css', 'tarefas.css'];
        $extra_js        = ['admin-visitas-historico.js'];

        return view('visitas.historico', get_defined_vars());
    }

    /**
     * GET /api/admin/visitas-historico/listar
     * Lista paginada e filtrável de relatorio_visitas_itens (o log real de
     * visitas), somente leitura. Sem navegar nem alterar a URL de /admin/visitas.
     * Retorna JSON { html, total, page, per_page, total_pages }
     */
    public function historicoListar(Request $request)
    {
        $current_user = require_admin();

        $data_de     = trim((string) $request->query('data_de', ''));
        $data_ate    = trim((string) $request->query('data_ate', ''));
        $corretor_id = (int) $request->query('corretor_id', 0);
        $imobiliaria = trim((string) $request->query('imobiliaria', ''));
        $page        = max(1, (int) $request->query('page', 1));
        $per_page    = 20;
        $offset      = ($page - 1) * $per_page;

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data_de))  $data_de  = '';
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data_ate)) $data_ate = '';

        // Sem data informada = sem restrição de período (mesma regra dos outros filtros).
        $where  = '1=1';
        $tipos  = '';
        $params = [];

        if ($data_de !== '' && $data_ate !== '') {
            $where   .= ' AND rv.data_relatorio BETWEEN ? AND ?';
            $tipos   .= 'ss';
            $params[] = $data_de;
            $params[] = $data_ate;
        } elseif ($data_de !== '') {
            $where   .= ' AND rv.data_relatorio >= ?';
            $tipos   .= 's';
            $params[] = $data_de;
        } elseif ($data_ate !== '') {
            $where   .= ' AND rv.data_relatorio <= ?';
            $tipos   .= 's';
            $params[] = $data_ate;
        }

        if ($corretor_id > 0) {
            // Casa tanto quem registrou o relatório (principal) quanto quem foi
            // junto como corretor adicional (relatorio_visitas_itens_corretores) —
            // mesmo padrão do filtro corretor_id em agendarListar().
            $where   .= ' AND (rv.usuario_id = ? OR EXISTS (SELECT 1 FROM relatorio_visitas_itens_corretores c WHERE c.item_id = rvi.id AND c.usuario_id = ?))';
            $tipos   .= 'ii';
            $params[] = $corretor_id;
            $params[] = $corretor_id;
        }
        if ($imobiliaria !== '') {
            $where   .= ' AND rvi.imobiliaria_nome LIKE ?';
            $tipos   .= 's';
            $params[] = '%' . $imobiliaria . '%';
        }

        $itens = [];
        $total = 0;
        try {
            $itens = db_fetch_all(
                "SELECT rvi.*, rv.data_relatorio, COALESCE(u.username, u.email) AS corretor_nome, u.avatar AS corretor_avatar
                 FROM relatorio_visitas_itens rvi
                 JOIN relatorios_visitas rv ON rv.id = rvi.relatorio_id
                 JOIN usuario u ON u.id = rv.usuario_id
                 WHERE {$where}
                 ORDER BY rv.data_relatorio DESC, rvi.id DESC
                 LIMIT ? OFFSET ?",
                [$tipos . 'ii', ...$params, $per_page, $offset]
            );
            $sqlCount = "SELECT COUNT(*) AS total FROM relatorio_visitas_itens rvi
                 JOIN relatorios_visitas rv ON rv.id = rvi.relatorio_id
                 JOIN usuario u ON u.id = rv.usuario_id
                 WHERE {$where}";
            $total = (int) ($tipos !== ''
                ? (db_fetch_one($sqlCount, [$tipos, ...$params])['total'] ?? 0)
                : (db_fetch_one($sqlCount)['total'] ?? 0));
        } catch (\Throwable $e) {
            error_log('[visitas-historico-listar] ' . $e->getMessage());
        }

        // Corretores adicionais por item (batch, mesmo padrão de $extras_map em agendarListar()).
        $extras_map = [];
        $item_ids = array_map(static fn($i) => (int) $i['id'], $itens);
        if (!empty($item_ids)) {
            $inPh = implode(',', array_fill(0, count($item_ids), '?'));
            $inTp = str_repeat('i', count($item_ids));
            try {
                $extras = db_fetch_all(
                    "SELECT c.item_id, u.id AS usuario_id, u.username, u.avatar
                       FROM relatorio_visitas_itens_corretores c
                       JOIN usuario u ON u.id = c.usuario_id
                      WHERE c.item_id IN ({$inPh})
                   ORDER BY u.username ASC",
                    [$inTp, ...$item_ids]
                );
                foreach ($extras as $ex) { $extras_map[(int) $ex['item_id']][] = $ex; }
            } catch (\Throwable $e) {
                error_log('[visitas-historico-listar] extras: ' . $e->getMessage());
            }
        }

        $total_pages = $total > 0 ? (int) ceil($total / $per_page) : 1;

        $seguros_labels = [
            'seguro_fianca'   => 'Fiança',
            'seguro_cap'      => 'Cap',
            'seguro_incendio' => 'Incêndio',
            'seguro_vida'     => 'Vida',
            'seguro_auto'     => 'Auto',
        ];

        // Era a função visita_sim_nao() declarada no arquivo.
        $visita_sim_nao = static fn(bool $sim): string => $sim
            ? '<span class="status-pill status-pill-aprovado">Sim</span>'
            : '<span class="admin-cot-cell-vazio">Não</span>';

        $html = view('visitas._partials.historico-lista', compact('itens', 'extras_map', 'seguros_labels', 'visita_sim_nao'))->render();

        return response()->json([
            'html'        => $html,
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $per_page,
            'total_pages' => $total_pages,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Relatório diário
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * GET /admin/visitas/relatorio (aceita ?ag_id=N)
     * Relatório diário de visitas — cada atendente registra as imobiliárias que
     * visitou hoje. Lista, salvar, editar e excluir são tudo via AJAX
     * (/api/admin/visitas-relatorio/*), a URL nunca muda.
     * Se veio de um agendamento pendente (?ag_id=N, ver Agendar Visita), o
     * formulário pré-preenche a imobiliária e, ao salvar, marca o agendamento
     * como concluído.
     */
    public function relatorio(Request $request)
    {
        $current_user = require_admin();

        $ag_id = (int) $request->query('ag_id', 0);
        $agendamento = null;
        $corretores_extra_default = [];
        if ($ag_id > 0) {
            try {
                $agendamento = db_fetch_one(
                    "SELECT id, imobiliaria_id, imobiliaria_nome, titulo, status
                     FROM agendamentos_visita WHERE id = ? AND para_usuario_id = ?",
                    ['ii', $ag_id, (int) $current_user->uid]
                );
                if ($agendamento) {
                    $extras = db_fetch_all(
                        'SELECT usuario_id FROM agendamentos_visita_corretores WHERE agendamento_id = ?',
                        ['i', $ag_id]
                    );
                    $corretores_extra_default = array_map(static fn($e) => (int) $e['usuario_id'], $extras);
                }
            } catch (\Throwable) {
                $agendamento = null;
            }
        }

        // Corretores que foram junto (multi-seleção) — o corretor principal é
        // sempre o usuário logado (implícito via relatorios_visitas.usuario_id),
        // esse campo é só pros colegas adicionais. Ver mecânica em
        // admin-visitas-agendar.js/#para-usuario-select — aqui não existe conceito
        // de "principal", é uma seleção múltipla simples.
        $corretores = [];
        try {
            $corretores = db_fetch_all('SELECT id, username, avatar FROM usuario WHERE id != ? ORDER BY username ASC', ['i', (int) $current_user->uid]);
        } catch (\Throwable) {}
        $opcoes_corretor_extra = [];
        foreach ($corretores as $c) {
            $opcoes_corretor_extra[(string) $c['id']] = $c['username'];
        }

        $page_title      = 'Relatório de Visitas';
        $page_heading    = 'Relatório de Visitas';
        $page_subheading = 'Registre as imobiliárias que você visitou hoje.';
        $page_back       = null;
        $extra_css       = ['dashboard.css', 'cotacoes.css', 'cotacao.css', 'visitas.css'];
        $extra_js        = ['admin-visitas-relatorio.js'];

        return view('visitas.relatorio', get_defined_vars());
    }

    /**
     * GET /api/admin/visitas-relatorio/listar
     * Lista as visitas já registradas hoje pelo usuário logado.
     * Retorna JSON { html, total }
     */
    public function relatorioListar()
    {
        $current_user = require_admin();

        $itens = [];
        try {
            $itens = db_fetch_all(
                "SELECT rvi.*
                 FROM relatorio_visitas_itens rvi
                 JOIN relatorios_visitas rv ON rv.id = rvi.relatorio_id
                 WHERE rv.usuario_id = ? AND rv.data_relatorio = CURDATE()
                 ORDER BY rvi.id DESC",
                ['i', (int) $current_user->uid]
            );
        } catch (\Throwable $e) {
            error_log('[visitas-relatorio-listar] ' . $e->getMessage());
        }

        // Corretores que foram junto, por item (batch, mesmo padrão de $extras_map
        // em agendarListar()) — só pra prefill do formulário de edição, não
        // aparece nesta lista.
        $extras_map = [];
        $item_ids = array_map(static fn($i) => (int) $i['id'], $itens);
        if (!empty($item_ids)) {
            $inPh = implode(',', array_fill(0, count($item_ids), '?'));
            $inTp = str_repeat('i', count($item_ids));
            try {
                $extras = db_fetch_all(
                    "SELECT item_id, usuario_id FROM relatorio_visitas_itens_corretores WHERE item_id IN ({$inPh})",
                    [$inTp, ...$item_ids]
                );
                foreach ($extras as $ex) { $extras_map[(int) $ex['item_id']][] = (int) $ex['usuario_id']; }
            } catch (\Throwable $e) {
                error_log('[visitas-relatorio-listar] extras: ' . $e->getMessage());
            }
        }

        // Colunas INT como INT no data-item (como o mysqli antigo devolvia).
        $itens = array_map(static fn($i) => self::tiparInts($i, ['id', 'relatorio_id', 'imobiliaria_id', 'falta_material', 'seguro_fianca', 'seguro_cap', 'seguro_incendio', 'seguro_vida', 'seguro_auto']), $itens);

        $seguros_labels = [
            'seguro_fianca'   => 'Fiança',
            'seguro_cap'      => 'Título Cap.',
            'seguro_incendio' => 'Incêndio',
            'seguro_vida'     => 'Vida',
            'seguro_auto'     => 'Auto',
        ];

        $html = view('visitas._partials.relatorio-lista', compact('itens', 'extras_map', 'seguros_labels'))->render();

        return response()->json(['html' => $html, 'total' => count($itens)]);
    }

    /**
     * POST /api/admin/visitas-relatorio/salvar
     * Cria ou atualiza um item do relatório diário de visitas do usuário logado.
     * Se vier "ag_id" (de um agendamento pendente do próprio usuário), marca
     * esse agendamento como concluído e liga o relatorio_item_id gerado.
     * Se a imobiliária tiver id real, também upserta as comissões marcadas.
     */
    public function relatorioSalvar(Request $request)
    {
        $current_user = require_admin();
        $uid = (int) $current_user->uid;

        $input = $request->all();

        $item_id             = (int) ($input['item_id'] ?? 0);
        $ag_id               = (int) ($input['ag_id'] ?? 0);
        $imobiliaria_id      = (int) ($input['imobiliaria_id'] ?? 0);
        $imobiliaria_nome    = trim((string) ($input['imobiliaria_nome'] ?? ''));
        $gerente             = trim((string) ($input['gerente'] ?? ''));
        $atendente           = trim((string) ($input['atendente'] ?? ''));
        $horario_entrada     = (string) ($input['horario_entrada'] ?? '');
        $horario_saida       = (string) ($input['horario_saida'] ?? '');
        $motivo_visita       = trim((string) ($input['motivo_visita'] ?? ''));
        $motivo_descricao    = trim((string) ($input['motivo_descricao'] ?? ''));
        $outros_seguros      = trim((string) ($input['outros_seguros'] ?? ''));
        $falta_material      = !empty($input['falta_material']) ? 1 : 0;
        $materiais_faltantes = $falta_material ? trim((string) ($input['materiais_faltantes'] ?? '')) : '';
        $observacoes         = trim((string) ($input['observacoes'] ?? ''));
        $seguros             = is_array($input['seguros'] ?? null) ? $input['seguros'] : [];

        // Corretores que foram junto (participantes adicionais) — deduplicados e
        // sem o próprio usuário logado (já é implícito como dono via
        // relatorios_visitas.usuario_id, não precisa entrar na tabela de extras).
        $corretores_extra = array_values(array_unique(array_filter(
            array_map('intval', (array) ($input['corretores_extra'] ?? [])),
            fn($id) => $id > 0 && $id !== $uid
        )));

        if ($imobiliaria_nome === '' || $gerente === '' || $motivo_visita === '') {
            json_response(['success' => false, 'message' => 'Preencha imobiliária, gerente e motivo da visita.'], 422);
        }
        if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $horario_entrada) || !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $horario_saida)) {
            json_response(['success' => false, 'message' => 'Informe os horários de entrada e saída.'], 422);
        }

        $tipos_seguro = self::VISITA_TIPOS_SEGURO;
        $flags_seguro = [];
        foreach ($tipos_seguro as $tipo) {
            $flags_seguro[$tipo] = !empty($seguros[$tipo]['checked']) ? 1 : 0;
        }

        try {
            $item_id = DB::transaction(function () use (
                $uid, $item_id, $ag_id, $imobiliaria_id, $imobiliaria_nome, $gerente, $atendente,
                $horario_entrada, $horario_saida, $falta_material, $materiais_faltantes, $flags_seguro,
                $outros_seguros, $observacoes, $motivo_visita, $motivo_descricao, $corretores_extra,
                $tipos_seguro, $seguros
            ) {
                // ── Get-or-create do cabeçalho do dia ────────────────────────
                $relatorio = db_fetch_one(
                    "SELECT id FROM relatorios_visitas WHERE usuario_id = ? AND data_relatorio = CURDATE()",
                    ['i', $uid]
                );
                if ($relatorio) {
                    $relatorio_id = (int) $relatorio['id'];
                } else {
                    $relatorio_id = self::visitaExecute(
                        "INSERT INTO relatorios_visitas (usuario_id, data_relatorio, criado_em) VALUES (?, CURDATE(), NOW())",
                        [$uid]
                    );
                }

                // ── Insert ou update do item ─────────────────────────────────
                if ($item_id > 0) {
                    // Só permite editar item que pertence a um relatório do próprio usuário.
                    $existente = db_fetch_one(
                        "SELECT rvi.id FROM relatorio_visitas_itens rvi
                         JOIN relatorios_visitas rv ON rv.id = rvi.relatorio_id
                         WHERE rvi.id = ? AND rv.usuario_id = ?",
                        ['ii', $item_id, $uid]
                    );
                    if (!$existente) {
                        throw new \RuntimeException('Visita não encontrada.');
                    }

                    self::visitaExecute(
                        "UPDATE relatorio_visitas_itens SET
                            imobiliaria_id = ?, imobiliaria_nome = ?, gerente = ?, atendente = ?,
                            horario_entrada = ?, horario_saida = ?,
                            falta_material = ?, materiais_faltantes = ?,
                            seguro_fianca = ?, seguro_cap = ?, seguro_incendio = ?, seguro_vida = ?, seguro_auto = ?,
                            outros_seguros = ?, observacoes = ?, motivo_visita = ?, motivo_descricao = ?,
                            atualizado_em = NOW()
                         WHERE id = ?",
                        [
                            $imobiliaria_id ?: null, $imobiliaria_nome, $gerente, $atendente ?: null,
                            $horario_entrada, $horario_saida,
                            $falta_material, $materiais_faltantes ?: null,
                            $flags_seguro['fianca'], $flags_seguro['cap'], $flags_seguro['incendio'], $flags_seguro['vida'], $flags_seguro['auto'],
                            $outros_seguros ?: null, $observacoes ?: null, $motivo_visita, $motivo_descricao ?: null,
                            $item_id,
                        ]
                    );
                } else {
                    $item_id = self::visitaExecute(
                        "INSERT INTO relatorio_visitas_itens (
                            relatorio_id, imobiliaria_id, imobiliaria_nome, gerente, atendente,
                            horario_entrada, horario_saida, falta_material, materiais_faltantes,
                            seguro_fianca, seguro_cap, seguro_incendio, seguro_vida, seguro_auto,
                            outros_seguros, observacoes, motivo_visita, motivo_descricao, criado_em
                         ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?, NOW())",
                        [
                            $relatorio_id, $imobiliaria_id ?: null, $imobiliaria_nome, $gerente, $atendente ?: null,
                            $horario_entrada, $horario_saida, $falta_material, $materiais_faltantes ?: null,
                            $flags_seguro['fianca'], $flags_seguro['cap'], $flags_seguro['incendio'], $flags_seguro['vida'], $flags_seguro['auto'],
                            $outros_seguros ?: null, $observacoes ?: null, $motivo_visita, $motivo_descricao ?: null,
                        ]
                    );
                }

                // ── Corretores que foram junto (sincroniza do zero, baixo volume) ──
                self::visitaExecute('DELETE FROM relatorio_visitas_itens_corretores WHERE item_id = ?', [$item_id]);
                foreach ($corretores_extra as $usuario_id_extra) {
                    self::visitaExecute(
                        'INSERT INTO relatorio_visitas_itens_corretores (item_id, usuario_id, criado_em) VALUES (?, ?, NOW())',
                        [$item_id, $usuario_id_extra]
                    );
                }

                // ── Comissões (só se a imobiliária tem id real) ──────────────
                if ($imobiliaria_id > 0) {
                    foreach ($tipos_seguro as $tipo) {
                        if (empty($seguros[$tipo]['checked'])) continue;
                        $numero = (float) ($seguros[$tipo]['numero'] ?? 0);
                        if ($numero <= 0) continue;

                        $modo       = ($seguros[$tipo]['modo'] ?? 'percentual') === 'valor' ? 'valor' : 'percentual';
                        $percentual = $modo === 'percentual' ? $numero : 0;
                        $valor      = $modo === 'valor' ? $numero : null;

                        self::visitaExecute(
                            "INSERT INTO comissoes_imobiliarias (imobiliaria_id, tipo_seguro, percentual, modo, valor, atualizado_por)
                             VALUES (?, ?, ?, ?, ?, ?)
                             ON DUPLICATE KEY UPDATE percentual = VALUES(percentual), modo = VALUES(modo), valor = VALUES(valor), atualizado_por = VALUES(atualizado_por)",
                            [$imobiliaria_id, $tipo, $percentual, $modo, $valor, $uid]
                        );
                    }
                }

                // ── Liga o agendamento de origem, se veio de um "Registrar visita" ──
                if ($ag_id > 0) {
                    self::visitaExecute(
                        "UPDATE agendamentos_visita SET status = 'concluida', relatorio_item_id = ?
                         WHERE id = ? AND para_usuario_id = ? AND status = 'pendente'",
                        [$item_id, $ag_id, $uid]
                    );
                }

                return $item_id;
            });
        } catch (\Throwable $e) {
            error_log('[visitas-relatorio-salvar] ' . $e->getMessage());
            json_response(['success' => false, 'message' => 'Erro ao salvar a visita.'], 500);
        }

        return response()->json(['success' => true, 'message' => 'Visita salva com sucesso.', 'item_id' => $item_id]);
    }

    /**
     * POST /api/admin/visitas-relatorio/excluir
     * Exclui um item do relatório de visitas do próprio usuário.
     * Body JSON: { "item_id": 42 }
     */
    public function relatorioExcluir(Request $request)
    {
        $current_user = require_admin();

        $item_id = (int) $request->input('item_id', 0);

        if ($item_id <= 0) {
            json_response(['success' => false, 'message' => 'Requisição inválida.'], 400);
        }

        $existente = db_fetch_one(
            "SELECT rvi.id FROM relatorio_visitas_itens rvi
             JOIN relatorios_visitas rv ON rv.id = rvi.relatorio_id
             WHERE rvi.id = ? AND rv.usuario_id = ?",
            ['ii', $item_id, (int) $current_user->uid]
        );
        if (!$existente) {
            json_response(['success' => false, 'message' => 'Visita não encontrada.'], 404);
        }

        try {
            db_execute('DELETE FROM relatorio_visitas_itens WHERE id = ?', ['i', $item_id]);
        } catch (\Throwable $e) {
            error_log('[visitas-relatorio-excluir] ' . $e->getMessage());
            json_response(['success' => false, 'message' => 'Erro ao excluir a visita.'], 500);
        }

        return response()->json(['success' => true, 'message' => 'Visita excluída.']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Agendar visita
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * GET /admin/visitas/agendar
     * Agenda de visitas futuras, com sincronização de 2 vias com o Google
     * Calendar do atendente-alvo. Lista, salvar, editar e excluir são tudo via
     * AJAX (/api/admin/visitas-agendar/*), a URL nunca muda.
     */
    public function agendar()
    {
        $current_user = require_admin();
        $is_mps_admin = !empty($current_user->mps_admin);

        $flash_messages   = flash_get();
        $google_conectado = google_has_token((int) $current_user->uid);

        $corretores = [];
        try {
            $corretores = db_fetch_all('SELECT id, username, avatar FROM usuario ORDER BY username ASC');
        } catch (\Throwable) {}
        $opcoes_corretor = [];
        foreach ($corretores as $c) {
            $opcoes_corretor[(string) $c['id']] = $c['username'];
        }

        $regioes = [];
        try {
            $regioes = db_fetch_all('SELECT id, nome, requer_link FROM regioes ORDER BY nome ASC');
        } catch (\Throwable) {}
        $opcoes_regiao = [];
        $regioes_requer_link = [];
        foreach ($regioes as $r) {
            $opcoes_regiao[(string) $r['id']] = $r['nome'];
            $regioes_requer_link[(string) $r['id']] = (bool) $r['requer_link'];
        }

        $page_title      = 'Agendar Visita';
        $page_heading    = 'Agendar Visita';
        $page_subheading = 'Agenda de visitas futuras, sincronizada com o Google Calendar.';
        $page_back       = null;
        $extra_css       = ['dashboard.css', 'cotacoes.css', 'cotacao.css', 'visitas.css', 'tarefas.css'];
        $extra_js        = ['admin-visitas-agendar.js'];
        // Flash (conexão com o Google): mostrado pelo mpsAlert no JS, como nas outras telas.
        $main_attrs      = ' data-mps-admin="' . ($is_mps_admin ? '1' : '0') . '" data-uid="' . (int) $current_user->uid . '"'
                         . ' data-flash="' . e(json_encode($flash_messages)) . '"';

        return view('visitas.agendar', get_defined_vars());
    }

    /**
     * GET /api/admin/visitas-agendar/listar
     * Lista os agendamentos de visita. Admin MPS vê de todos os corretores
     * (com filtro opcional); demais usuários só veem os próprios.
     * Retorna JSON { html, contadores }
     */
    public function agendarListar(Request $request)
    {
        $current_user = require_admin();

        $is_mps_admin = !empty($current_user->mps_admin);

        $status_filtro = (string) $request->query('status', '');
        if (!in_array($status_filtro, ['pendente', 'concluida'], true)) {
            $status_filtro = '';
        }
        $data_filtro = (string) $request->query('data', '');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data_filtro)) {
            $data_filtro = '';
        }
        $corretor_filtro = $is_mps_admin ? (int) $request->query('corretor_id', 0) : (int) $current_user->uid;

        $where  = '1=1';
        $tipos  = '';
        $params = [];

        // Casa tanto quem é o corretor principal quanto quem é corretor adicional
        // (agendamentos_visita_corretores) — vale pro filtro manual do admin e pra
        // visão forçada do não-admin (que só vê os próprios).
        if (!$is_mps_admin || $corretor_filtro > 0) {
            $where   .= ' AND (av.para_usuario_id = ? OR EXISTS (SELECT 1 FROM agendamentos_visita_corretores c WHERE c.agendamento_id = av.id AND c.usuario_id = ?))';
            $tipos   .= 'ii';
            $params[] = $corretor_filtro;
            $params[] = $corretor_filtro;
        }
        if ($data_filtro !== '') {
            $where   .= ' AND av.data_visita = ?';
            $tipos   .= 's';
            $params[] = $data_filtro;
        }

        $rows = [];
        try {
            $sql = "SELECT av.*, u.username AS corretor_nome, u.avatar AS corretor_avatar, r.nome AS regiao_nome
                    FROM agendamentos_visita av
                    JOIN usuario u ON u.id = av.para_usuario_id
                    LEFT JOIN regioes r ON r.id = av.regiao_id
                    WHERE {$where}
                    ORDER BY av.criado_em DESC, av.id DESC";
            $rows = $tipos !== '' ? db_fetch_all($sql, [$tipos, ...$params]) : db_fetch_all($sql);
        } catch (\Throwable $e) {
            error_log('[visitas-agendar-listar] ' . $e->getMessage());
        }

        // Colunas INT como INT (o mysqli antigo devolvia assim e o JS compara com ===,
        // ex.: para_usuario_id no editar do admin-visitas-agendar.js).
        $rows = array_map(static fn($r) => self::tiparInts($r, ['id', 'usuario_id', 'para_usuario_id', 'imobiliaria_id', 'relatorio_item_id', 'email_confirmacao', 'lembrete_email', 'regiao_id']), $rows);

        $contadores = ['todos' => count($rows), 'pendente' => 0, 'concluida' => 0];
        foreach ($rows as $r) {
            if (isset($contadores[$r['status']])) $contadores[$r['status']]++;
        }

        if ($status_filtro !== '') {
            $rows = array_values(array_filter($rows, fn($r) => $r['status'] === $status_filtro));
        }

        // Corretores adicionais por agendamento (batch, mesmo padrão de $resp_map
        // da listagem de tarefas).
        $extras_map = [];
        $ag_ids = array_map(static fn($r) => (int) $r['id'], $rows);
        if (!empty($ag_ids)) {
            $inPh = implode(',', array_fill(0, count($ag_ids), '?'));
            $inTp = str_repeat('i', count($ag_ids));
            try {
                $extras = db_fetch_all(
                    "SELECT c.agendamento_id, u.id AS usuario_id, u.username, u.avatar
                       FROM agendamentos_visita_corretores c
                       JOIN usuario u ON u.id = c.usuario_id
                      WHERE c.agendamento_id IN ({$inPh})
                   ORDER BY u.username ASC",
                    [$inTp, ...$ag_ids]
                );
                foreach ($extras as $ex) { $extras_map[(int) $ex['agendamento_id']][] = $ex; }
            } catch (\Throwable $e) {
                error_log('[visitas-agendar-listar] extras: ' . $e->getMessage());
            }
        }

        $html = view('visitas._partials.agendar-lista', compact('rows', 'extras_map'))->render();

        return response()->json(['html' => $html, 'contadores' => $contadores]);
    }

    /**
     * POST /api/admin/visitas-agendar/salvar
     * Cria ou atualiza um agendamento de visita. A sincronização com o Google
     * Calendar do corretor principal (para_usuario_id) é a única que bloqueia o
     * salvamento — corretores adicionais (corretores_extra) são sincronizados
     * best-effort por cima, cada um na própria agenda.
     *
     * Regras (mesmas do sistema de referência):
     *  - Criação: se o atendente-alvo tem Google conectado, o evento PRECISA
     *    ser criado lá com sucesso, senão a gravação inteira é rejeitada. Se
     *    não tem Google conectado, salva só internamente e manda e-mail de
     *    confirmação pro atendente.
     *  - Edição: se o Google falhar (update ou create tardio), a atualização
     *    no banco segue mesmo assim — só a criação bloqueia, a edição não.
     *  - Corretores extras: a cada salvamento, os eventos/linhas antigas são
     *    apagados e recriados do zero a partir de corretores_extra — evita ter
     *    que fazer diff por corretor, aceitável dado o baixo volume da tela.
     */
    public function agendarSalvar(Request $request)
    {
        $current_user = require_admin();

        $is_mps_admin = !empty($current_user->mps_admin);
        $input = $request->all();

        $ag_id            = (int) ($input['id'] ?? 0);
        $para_usuario_id  = $is_mps_admin ? (int) ($input['para_usuario_id'] ?? 0) : (int) $current_user->uid;
        $titulo           = trim((string) ($input['titulo'] ?? ''));
        $regiao_id        = (int) ($input['regiao_id'] ?? 0) ?: null;
        $link_reuniao     = trim((string) ($input['link_reuniao'] ?? ''));
        $imobiliaria_id   = (int) ($input['imobiliaria_id'] ?? 0);
        $imobiliaria_nome = trim((string) ($input['imobiliaria_nome'] ?? ''));
        $data_visita      = (string) ($input['data_visita'] ?? '');
        $hora_inicio      = (string) ($input['hora_inicio'] ?? '');
        $hora_fim         = (string) ($input['hora_fim'] ?? '');
        $descricao        = trim((string) ($input['descricao'] ?? ''));

        // Corretores adicionais (participantes, sem permissão de editar/excluir) —
        // deduplicados e sem o principal (evita duplicar como "extra").
        $corretores_extra = array_values(array_unique(array_filter(
            array_map('intval', (array) ($input['corretores_extra'] ?? [])),
            fn($id) => $id > 0 && $id !== $para_usuario_id
        )));

        $endereco = trim(implode(', ', array_filter([
            trim((string) ($input['endereco'] ?? '')) . (($input['numero'] ?? '') !== '' ? ', ' . $input['numero'] : ''),
            trim((string) ($input['bairro'] ?? '')),
            trim((string) ($input['cidade'] ?? '')) . (($input['uf'] ?? '') !== '' ? ' - ' . $input['uf'] : ''),
            trim((string) ($input['cep'] ?? '')),
        ])), ', ');

        if ($para_usuario_id <= 0 || $titulo === '') {
            json_response(['success' => false, 'message' => 'Preencha o corretor e o título.'], 422);
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data_visita) || !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $hora_inicio) || !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $hora_fim)) {
            json_response(['success' => false, 'message' => 'Informe data e horários válidos.'], 422);
        }

        // Se a região exige link (ex: Online), ele é obrigatório.
        if ($regiao_id) {
            $regiao_selecionada = db_fetch_one('SELECT requer_link FROM regioes WHERE id = ?', ['i', $regiao_id]);
            if ($regiao_selecionada && (int) $regiao_selecionada['requer_link'] === 1 && $link_reuniao === '') {
                json_response(['success' => false, 'message' => 'Informe o link da reunião para essa região.'], 422);
            }
        }

        // A imobiliária pode não existir formalmente no cadastro — o agente pode
        // digitar o nome livremente. imobiliaria_id só fica preenchido se veio de
        // uma sugestão clicada.
        if ($imobiliaria_id > 0) {
            $imob = db_fetch_one('SELECT id FROM imobiliarias WHERE id = ?', ['i', $imobiliaria_id]);
            if (!$imob) $imobiliaria_id = 0;
        }

        $evento_descricao = $link_reuniao !== '' ? trim($descricao . "\n\nLink da reunião: {$link_reuniao}") : $descricao;
        $evento_local     = $link_reuniao !== '' ? $link_reuniao : $endereco;
        $evento = self::montarEventoGoogle($titulo, $evento_descricao, $evento_local, $data_visita, substr($hora_inicio, 0, 5), substr($hora_fim, 0, 5));
        $token  = google_get_valid_token($para_usuario_id);

        $google_event_id   = null;
        $google_event_link = null;
        $precisa_email     = false;

        if ($ag_id > 0) {
            // ── Edição ───────────────────────────────────────────────────────
            $existente = db_fetch_one('SELECT * FROM agendamentos_visita WHERE id = ?', ['i', $ag_id]);
            if (!$existente) {
                json_response(['success' => false, 'message' => 'Agendamento não encontrado.'], 404);
            }
            if (!$is_mps_admin && (int) $existente['para_usuario_id'] !== (int) $current_user->uid) {
                json_response(['success' => false, 'message' => 'Você não tem permissão para editar este agendamento.'], 403);
            }

            $google_event_id   = $existente['google_event_id'] ?: null;
            $google_event_link = $existente['google_event_link'] ?: null;

            if ($token) {
                if ($google_event_id) {
                    google_calendar_update_event($token, $google_event_id, $evento); // falha aqui não bloqueia a edição
                } else {
                    $criado = google_calendar_create_event($token, $evento);
                    if ($criado) {
                        $google_event_id   = $criado['id'];
                        $google_event_link = $criado['htmlLink'] ?? null;
                    }
                }
            }

            self::visitaExecute(
                "UPDATE agendamentos_visita SET
                    para_usuario_id = ?, titulo = ?, descricao = ?, local_visita = ?,
                    imobiliaria_id = ?, imobiliaria_nome = ?, data_visita = ?, hora_inicio = ?, hora_fim = ?,
                    regiao_id = ?, link_reuniao = ?, google_event_id = ?, google_event_link = ?
                 WHERE id = ?",
                [
                    $para_usuario_id, $titulo, $descricao ?: null, $endereco ?: null,
                    $imobiliaria_id ?: null, $imobiliaria_nome ?: null, $data_visita, $hora_inicio, $hora_fim,
                    $regiao_id, $link_reuniao ?: null, $google_event_id, $google_event_link,
                    $ag_id,
                ]
            );
        } else {
            // ── Criação ──────────────────────────────────────────────────────
            if ($token) {
                $criado = google_calendar_create_event($token, $evento);
                if (!$criado) {
                    json_response(['success' => false, 'message' => 'Não foi possível criar o evento no Google Agenda. Tente novamente.'], 502);
                }
                $google_event_id   = $criado['id'];
                $google_event_link = $criado['htmlLink'] ?? null;
            } else {
                $precisa_email = true;
            }

            $ag_id = self::visitaExecute(
                "INSERT INTO agendamentos_visita (
                    usuario_id, para_usuario_id, titulo, descricao, local_visita,
                    imobiliaria_id, imobiliaria_nome, data_visita, hora_inicio, hora_fim,
                    regiao_id, link_reuniao, google_event_id, google_event_link, status, email_confirmacao, criado_em
                ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?, 'pendente', ?, NOW())",
                [
                    (int) $current_user->uid, $para_usuario_id, $titulo, $descricao ?: null, $endereco ?: null,
                    $imobiliaria_id ?: null, $imobiliaria_nome ?: null, $data_visita, $hora_inicio, $hora_fim,
                    $regiao_id, $link_reuniao ?: null, $google_event_id, $google_event_link, $precisa_email ? 1 : 0,
                ]
            );

            if ($precisa_email) {
                self::notificarCorretor($para_usuario_id, $titulo, $data_visita, $hora_inicio, $hora_fim, $imobiliaria_nome, $endereco, $descricao);
            }
        }

        self::sincronizarExtras($ag_id, $corretores_extra, $evento, $titulo, $data_visita, $hora_inicio, $hora_fim, $imobiliaria_nome, $endereco, $descricao);

        return response()->json(['success' => true, 'message' => 'Agendamento salvo.', 'id' => $ag_id]);
    }

    /**
     * POST /api/admin/visitas-agendar/excluir
     * Exclui um agendamento. Não-admins só podem excluir os próprios
     * (para_usuario_id). Tenta apagar o evento do Google Agenda do principal e
     * de cada corretor adicional (best-effort — não impede a exclusão local se
     * o Google falhar). As linhas da tabela de extras somem via ON DELETE
     * CASCADE.
     * Body JSON: { "id": 42 }
     */
    public function agendarExcluir(Request $request)
    {
        $current_user = require_admin();

        $is_mps_admin = !empty($current_user->mps_admin);
        $id = (int) $request->input('id', 0);

        if ($id <= 0) {
            json_response(['success' => false, 'message' => 'Requisição inválida.'], 400);
        }

        $ag = db_fetch_one('SELECT * FROM agendamentos_visita WHERE id = ?', ['i', $id]);
        if (!$ag) {
            json_response(['success' => false, 'message' => 'Agendamento não encontrado.'], 404);
        }
        if (!$is_mps_admin && (int) $ag['para_usuario_id'] !== (int) $current_user->uid) {
            json_response(['success' => false, 'message' => 'Você não tem permissão para excluir este agendamento.'], 403);
        }

        if (!empty($ag['google_event_id'])) {
            $token = google_get_valid_token((int) $ag['para_usuario_id']);
            if ($token) {
                google_calendar_delete_event($token, $ag['google_event_id']);
            }
        }

        $extras = db_fetch_all('SELECT usuario_id, google_event_id FROM agendamentos_visita_corretores WHERE agendamento_id = ?', ['i', $id]);
        foreach ($extras as $extra) {
            if (empty($extra['google_event_id'])) continue;
            $token_extra = google_get_valid_token((int) $extra['usuario_id']);
            if ($token_extra) {
                google_calendar_delete_event($token_extra, $extra['google_event_id']);
            }
        }

        try {
            db_execute('DELETE FROM agendamentos_visita WHERE id = ?', ['i', $id]);
        } catch (\Throwable $e) {
            error_log('[visitas-agendar-excluir] ' . $e->getMessage());
            json_response(['success' => false, 'message' => 'Erro ao excluir o agendamento.'], 500);
        }

        return response()->json(['success' => true, 'message' => 'Agendamento excluído.']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Compartilhados: imobiliária, comissão, região
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * GET /api/admin/visitas/imobiliaria-buscar?q=...
     * Autocomplete de imobiliária por nome, usado no Relatório de Visitas,
     * no Agendar Visita e no painel de Comissões do Histórico.
     * Inclui o endereço salvo (se houver) pro Agendar Visita preencher
     * automaticamente CEP/rua/número/bairro/cidade/UF ao selecionar uma opção.
     * Retorna JSON { itens: [{ id, nome, cep, endereco, numero, complemento, bairro, cidade, uf }] }
     */
    public function imobiliariaBuscar(Request $request)
    {
        $current_user = require_admin();

        $q = trim((string) $request->query('q', ''));
        $itens = [];

        if (mb_strlen($q) >= 2) {
            try {
                $itens = db_fetch_all(
                    "SELECT id, nome, cep, endereco, numero, complemento, bairro, cidade, uf, regiao_id
                     FROM imobiliarias WHERE nome LIKE ? ORDER BY nome ASC LIMIT 10",
                    ['s', '%' . $q . '%']
                );
                // id como INT, como vinha do mysqli antigo.
                foreach ($itens as &$it) { $it['id'] = (int) $it['id']; $it['regiao_id'] = $it['regiao_id'] !== null ? (int) $it['regiao_id'] : null; }
                unset($it);
            } catch (\Throwable) {
                $itens = [];
            }
        }

        return response()->json(['itens' => $itens]);
    }

    /**
     * GET+POST /api/admin/visitas/comissao
     * Compartilhado entre Relatório de Visitas e Histórico de Visitas.
     *
     * GET  ?action=get&imobiliaria_id=N
     *      → { comissoes: { fianca: {modo,percentual,valor}, cap: {...}, ... } }
     *        (sempre os 5 tipos, com default modo=percentual/0 se não existir linha)
     *
     * POST { action: "save", imobiliaria_id, tipo_seguro, modo, valor }
     *      → upsert em comissoes_imobiliarias + notifica os admins da mudança.
     */
    public function comissao(Request $request)
    {
        $current_user = require_admin();

        if ($request->isMethod('get')) {
            $imobiliaria_id = (int) $request->query('imobiliaria_id', 0);
            if ($imobiliaria_id <= 0) {
                json_response(['success' => false, 'message' => 'Imobiliária inválida.'], 400);
            }

            $comissoes = [];
            foreach (self::VISITA_TIPOS_SEGURO as $tipo) {
                $comissoes[$tipo] = ['modo' => 'percentual', 'percentual' => 0, 'valor' => null];
            }

            try {
                $rows = db_fetch_all(
                    "SELECT tipo_seguro, modo, percentual, valor FROM comissoes_imobiliarias WHERE imobiliaria_id = ?",
                    ['i', $imobiliaria_id]
                );
                foreach ($rows as $row) {
                    if (isset($comissoes[$row['tipo_seguro']])) {
                        $comissoes[$row['tipo_seguro']] = [
                            'modo'       => $row['modo'],
                            'percentual' => (float) $row['percentual'],
                            'valor'      => $row['valor'] !== null ? (float) $row['valor'] : null,
                        ];
                    }
                }
            } catch (\Throwable $e) {
                error_log('[visitas-comissao] ' . $e->getMessage());
            }

            return response()->json(['success' => true, 'comissoes' => $comissoes]);
        }

        // ── POST: salvar uma comissão (MPS admin ou comercial; ler é liberado pro relatório) ──
        require_mps_admin_ou_comercial();

        $imobiliaria_id = (int) $request->input('imobiliaria_id', 0);
        $tipo_seguro    = (string) $request->input('tipo_seguro', '');
        $modo           = ($request->input('modo') ?? 'percentual') === 'valor' ? 'valor' : 'percentual';
        $numero         = (float) $request->input('numero', 0);

        if ($imobiliaria_id <= 0 || !in_array($tipo_seguro, self::VISITA_TIPOS_SEGURO, true)) {
            json_response(['success' => false, 'message' => 'Requisição inválida.'], 400);
        }

        $percentual = $modo === 'percentual' ? $numero : 0;
        $valor      = $modo === 'valor' ? $numero : null;

        try {
            db_execute(
                "INSERT INTO comissoes_imobiliarias (imobiliaria_id, tipo_seguro, percentual, modo, valor, atualizado_por)
                 VALUES (?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE percentual = VALUES(percentual), modo = VALUES(modo), valor = VALUES(valor), atualizado_por = VALUES(atualizado_por)",
                ['isdsdi', $imobiliaria_id, $tipo_seguro, $percentual, $modo, $valor, (int) $current_user->uid]
            );

            $imob = db_fetch_one('SELECT nome FROM imobiliarias WHERE id = ?', ['i', $imobiliaria_id]);
            $rotulo = $modo === 'percentual' ? "{$numero}%" : 'R$ ' . number_format($numero, 2, ',', '.');
            notificar_todos_admins(
                'comissao_alterada',
                'Comissão atualizada — ' . ($imob['nome'] ?? "imobiliária #{$imobiliaria_id}"),
                "Comissão de {$tipo_seguro} definida para {$rotulo} por " . ($current_user->nome ?? 'um atendente') . '.'
            );
        } catch (\Throwable $e) {
            error_log('[visitas-comissao] ' . $e->getMessage());
            json_response(['success' => false, 'message' => 'Erro ao salvar comissão.'], 500);
        }

        return response()->json(['success' => true, 'message' => 'Comissão salva.']);
    }

    /**
     * GET /api/admin/visitas/regiao-listar
     * Lista os tipos de região cadastrados (Ilha, Continente, Online, ...), pro
     * dropdown de Região no Agendar Visita e pro modal de cadastro de regiões.
     * Retorna JSON { itens: [{ id, nome, requer_link }] }
     */
    public function regiaoListar()
    {
        $current_user = require_admin();

        $itens = [];
        try {
            $itens = array_map(self::regiaoTipada(...), db_fetch_all('SELECT id, nome, requer_link FROM regioes ORDER BY nome ASC'));
        } catch (\Throwable) {
            $itens = [];
        }

        return response()->json(['itens' => $itens]);
    }

    /**
     * POST /api/admin/visitas/regiao-criar
     * Cadastra um novo tipo de região (ex: "Online"), usado no modal de cadastro
     * de regiões do Agendar Visita.
     * Body: { nome, requer_link }
     * Retorna JSON { success, message, item: { id, nome, requer_link } }
     */
    public function regiaoCriar(Request $request)
    {
        $current_user = require_admin();

        $nome        = trim((string) $request->input('nome', ''));
        $requer_link = !empty($request->input('requer_link'));

        if ($nome === '') {
            json_response(['success' => false, 'message' => 'Informe o nome da região.'], 422);
        }

        $existente = db_fetch_one('SELECT id FROM regioes WHERE nome = ?', ['s', $nome]);
        if ($existente) {
            json_response(['success' => false, 'message' => 'Já existe uma região com esse nome.'], 422);
        }

        try {
            db_execute(
                'INSERT INTO regioes (nome, requer_link) VALUES (?, ?)',
                ['si', $nome, $requer_link ? 1 : 0]
            );
        } catch (\Throwable $e) {
            error_log('[visitas-regiao-criar] ' . $e->getMessage());
            json_response(['success' => false, 'message' => 'Não foi possível cadastrar a região.'], 500);
        }

        $item = db_fetch_one('SELECT id, nome, requer_link FROM regioes WHERE nome = ?', ['s', $nome]);

        return response()->json(['success' => true, 'message' => 'Região cadastrada com sucesso.', 'item' => $item ? self::regiaoTipada($item) : null]);
    }

    /**
     * POST /api/admin/visitas/regiao-excluir
     * Remove um tipo de região do cadastro. Recusa se houver agendamentos
     * usando essa região, pra não deixar registros antigos órfãos.
     * Body: { id }
     * Retorna JSON { success, message }
     */
    public function regiaoExcluir(Request $request)
    {
        $current_user = require_admin();

        $id = (int) $request->input('id', 0);

        if ($id <= 0) {
            json_response(['success' => false, 'message' => 'Região inválida.'], 422);
        }

        $em_uso = (int) (db_fetch_one('SELECT COUNT(*) AS total FROM agendamentos_visita WHERE regiao_id = ?', ['i', $id])['total'] ?? 0);
        if ($em_uso > 0) {
            json_response(['success' => false, 'message' => "Essa região está em uso em {$em_uso} agendamento(s) e não pode ser excluída."], 422);
        }

        try {
            db_execute('DELETE FROM regioes WHERE id = ?', ['i', $id]);
        } catch (\Throwable $e) {
            error_log('[visitas-regiao-excluir] ' . $e->getMessage());
            json_response(['success' => false, 'message' => 'Não foi possível excluir a região.'], 500);
        }

        return response()->json(['success' => true, 'message' => 'Região excluída.']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Google Agenda (OAuth)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * GET /admin/visitas/google/conectar
     * Redireciona pro consentimento OAuth do Google (sempre conecta a conta do
     * próprio usuário logado — não dá pra consentir em nome de outra pessoa).
     */
    public function googleConectar()
    {
        $current_user = require_admin();

        return redirect()->away(google_auth_url((int) $current_user->uid));
    }

    /**
     * GET /admin/visitas/google/callback?code=...&state=...
     * Callback do OAuth do Google — troca o code por token e salva.
     * O "state" já garante que o code pertence a quem clicou em Conectar
     * (assinado com o JWT_SECRET, ver google_auth_url()).
     */
    public function googleCallback(Request $request)
    {
        $current_user = require_admin();

        $code  = (string) $request->query('code', '');
        $state = (string) $request->query('state', '');
        $erro  = (string) $request->query('error', '');

        if ($erro !== '') {
            flash('error', 'Conexão com o Google cancelada ou recusada.');
            return redirect(url('/admin/visitas/agendar'));
        }

        $userIdDoState = google_validar_state($state);
        if (!$userIdDoState || $userIdDoState !== (int) $current_user->uid) {
            error_log(sprintf(
                '[google-callback] state inválido — uid_logado=%s uid_do_state=%s state_vazio=%s',
                (string) $current_user->uid,
                $userIdDoState === null ? 'null (expirado/assinatura inválida/malformado)' : (string) $userIdDoState,
                $state === '' ? 'sim' : 'não'
            ));
            flash('error', 'Não foi possível validar a solicitação de conexão. Tente novamente.');
            return redirect(url('/admin/visitas/agendar'));
        }

        if ($code === '') {
            flash('error', 'O Google não retornou um código de autorização.');
            return redirect(url('/admin/visitas/agendar'));
        }

        $tokenData = google_exchange_code($code);
        if (!$tokenData) {
            flash('error', 'Não foi possível concluir a conexão com o Google. Tente novamente.');
            return redirect(url('/admin/visitas/agendar'));
        }

        google_save_token((int) $current_user->uid, $tokenData);

        flash('success', 'Conta Google conectada com sucesso! Seus agendamentos agora sincronizam automaticamente.');
        return redirect(url('/admin/visitas/agendar'));
    }

    /**
     * POST /api/admin/visitas/google-desconectar
     * Desconecta a conta Google do usuário logado (revoga o token e apaga o
     * registro local).
     */
    public function googleDesconectar()
    {
        $current_user = require_admin();

        google_disconnect((int) $current_user->uid);

        return response()->json(['success' => true, 'message' => 'Conta Google desconectada.']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Auxiliares (eram funções declaradas dentro dos arquivos antigos)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Liga tudo como string — MySQL converte certinho pras colunas numéricas
     * (ver o mesmo padrão em cotacao-auto.php) — evita ter que contar/manter uma
     * string de tipos "iissi..." em sincronia manual com a lista de colunas.
     * Era visita_execute()/visita_ag_execute().
     */
    private static function visitaExecute(string $sql, array $valores): int
    {
        return db_execute($sql, [str_repeat('s', count($valores)), ...$valores]);
    }

    /** Converte as colunas indicadas pra INT (mantém NULL) — o PDO daqui devolve tudo string. */
    private static function tiparInts(array $row, array $cols): array
    {
        foreach ($cols as $c) {
            if (isset($row[$c])) {
                $row[$c] = (int) $row[$c];
            }
        }
        return $row;
    }

    /** id/requer_link como INT, como vinham do mysqli antigo (o JS compara). */
    private static function regiaoTipada(array $r): array
    {
        return ['id' => (int) $r['id'], 'nome' => $r['nome'], 'requer_link' => (int) $r['requer_link']];
    }

    /** Monta o corpo do evento no formato esperado pela Calendar API v3. Era montar_evento_google(). */
    private static function montarEventoGoogle(string $titulo, string $descricao, string $endereco, string $data, string $inicio, string $fim): array
    {
        $tz = 'America/Sao_Paulo';
        return [
            'summary'     => $titulo,
            'description' => $descricao,
            'location'    => $endereco,
            'start'       => ['dateTime' => "{$data}T{$inicio}:00", 'timeZone' => $tz],
            'end'         => ['dateTime' => "{$data}T{$fim}:00", 'timeZone' => $tz],
        ];
    }

    /**
     * Manda o e-mail de aviso de agendamento pra um corretor sem Google
     * conectado (usado tanto pro principal quanto pros extras).
     * Era visita_notificar_corretor().
     */
    private static function notificarCorretor(int $usuario_id, string $titulo, string $data_visita, string $hora_inicio, string $hora_fim, ?string $imobiliaria_nome, ?string $endereco, ?string $descricao): void
    {
        $corretor = db_fetch_one('SELECT email, username FROM usuario WHERE id = ?', ['i', $usuario_id]);
        if (!$corretor || !$corretor['email']) return;

        $corpo = '<p style="margin:0 0 14px;">Olá, ' . e($corretor['username']) . '!</p>'
            . '<p style="margin:0 0 14px;">Um novo agendamento de visita foi criado pra você:</p>'
            . tarefa_email_detalhes([
                'Título'      => $titulo,
                'Data'        => date_to_br($data_visita),
                'Hora'        => substr($hora_inicio, 0, 5) . ' – ' . substr($hora_fim, 0, 5),
                'Imobiliária' => $imobiliaria_nome ?: '—',
                'Local'       => $endereco ?: '—',
                'Descrição'   => $descricao ?: '—',
            ])
            . '<p style="margin:14px 0 0;color:#757575;font-size:13px;">Sua conta Google não está conectada, então este compromisso não sincroniza automaticamente com o Google Agenda.</p>';

        send_email($corretor['email'], "Novo agendamento: {$titulo}", $corpo);
    }

    /**
     * Sincroniza os corretores adicionais de um agendamento: apaga (best-effort)
     * os eventos do Google e as linhas antigas, depois recria do zero a partir
     * de $corretores_extra. Best-effort — falha ao criar um evento extra não
     * bloqueia o salvamento (diferente da regra do corretor principal).
     * Era visita_sincronizar_extras().
     */
    private static function sincronizarExtras(int $ag_id, array $corretores_extra, array $evento, string $titulo, string $data_visita, string $hora_inicio, string $hora_fim, ?string $imobiliaria_nome, ?string $endereco, ?string $descricao): void
    {
        $antigos = db_fetch_all('SELECT usuario_id, google_event_id FROM agendamentos_visita_corretores WHERE agendamento_id = ?', ['i', $ag_id]);
        foreach ($antigos as $antigo) {
            if (empty($antigo['google_event_id'])) continue;
            $token_antigo = google_get_valid_token((int) $antigo['usuario_id']);
            if ($token_antigo) {
                google_calendar_delete_event($token_antigo, $antigo['google_event_id']);
            }
        }
        db_execute('DELETE FROM agendamentos_visita_corretores WHERE agendamento_id = ?', ['i', $ag_id]);

        foreach ($corretores_extra as $usuario_id) {
            $token_extra      = google_get_valid_token($usuario_id);
            $extra_event_id   = null;
            $extra_event_link = null;

            if ($token_extra) {
                $criado = google_calendar_create_event($token_extra, $evento);
                if ($criado) {
                    $extra_event_id   = $criado['id'];
                    $extra_event_link = $criado['htmlLink'] ?? null;
                }
            } else {
                self::notificarCorretor($usuario_id, $titulo, $data_visita, $hora_inicio, $hora_fim, $imobiliaria_nome, $endereco, $descricao);
            }

            db_execute(
                'INSERT INTO agendamentos_visita_corretores (agendamento_id, usuario_id, google_event_id, google_event_link) VALUES (?, ?, ?, ?)',
                ['iiss', $ag_id, $usuario_id, $extra_event_id, $extra_event_link]
            );
        }
    }
}
