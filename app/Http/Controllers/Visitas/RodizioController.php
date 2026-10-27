<?php

namespace App\Http\Controllers\Visitas;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Rodízio de atendentes (funis de distribuição automática de cotações).
 * Era pages/admin/rodizio.php + includes/acao/admin-rodizio-*.php.
 */
class RodizioController extends Controller
{
    /**
     * GET+POST /admin/rodizio
     * Configuração dos funis de rodízio de atendentes. Lista, adicionar,
     * remover, reordenar e ativar/desativar atendentes são tudo via AJAX
     * (/api/admin/rodizio/*) — a URL nunca muda.
     */
    public function pagina()
    {
        $current_user = require_admin();

        $page_title      = 'Rodízio de Atendentes';
        $page_heading    = 'Rodízio de Atendentes';
        $page_subheading = 'Configuração dos funis de distribuição automática de cotações.';
        $page_back       = null;
        $extra_css       = ['dashboard.css', 'cotacoes.css'];
        $extra_js        = ['admin-rodizio.js'];

        return view('visitas.rodizio.pagina', get_defined_vars());
    }

    /**
     * GET /api/admin/rodizio/listar
     * Renderiza os painéis dos funis de rodízio (Fiança, Auto, Título de
     * Capitalização, Seguro Condomínio), um por baixo do outro em largura
     * cheia — mesmo padrão visual de "Rascunhos"/"Aguardando Liberação"
     * (admin-cot-row/admin-cot-cell) — com a lista de atendentes de cada um.
     * Chamado no carregamento da página e depois de qualquer ação (adicionar/
     * remover/reordenar/status), sem navegar nem alterar a URL de /admin/rodizio.
     * Retorna JSON { html }
     */
    public function listar()
    {
        $current_user = require_admin();

        $funis = [];
        try {
            $funis = db_fetch_all('SELECT id, nome, tipo_seguro, proximo_idx, ativo FROM funis ORDER BY id ASC');
            foreach ($funis as &$funil) {
                try {
                    $funil['corretores'] = db_fetch_all(
                        'SELECT fc.id, fc.user_id, fc.ordem, fc.ativo, u.username, u.email, u.avatar
                         FROM funil_corretores fc
                         JOIN usuario u ON u.id = fc.user_id
                         WHERE fc.funil_id = ? ORDER BY fc.ordem ASC',
                        ['i', $funil['id']]
                    );
                } catch (\Throwable) {
                    $funil['corretores'] = [];
                }
            }
            unset($funil);
        } catch (\Throwable) {
            $funis = [];
        }

        $todos_usuarios = [];
        try {
            $todos_usuarios = db_fetch_all('SELECT id, username FROM usuario ORDER BY username ASC');
        } catch (\Throwable) {}

        $icone_por_tipo = [
            'fianca'      => 'house',
            'auto'        => 'car',
            'titulo'      => 'piggy-bank',
            'condominial' => 'buildings',
            'incendio'    => 'fire',
            'residencial' => 'house-line',
        ];

        // Prepara os dados de cada funil (próximo da fila, opções do select) —
        // era calculado no meio do template antigo.
        foreach ($funis as &$funil) {
            $corretores   = $funil['corretores'] ?? [];
            $ativos       = array_values(array_filter($corretores, fn($c) => $c['ativo'] === 'S'));
            $total_ativos = count($ativos);
            $proximo_idx  = $total_ativos > 0 ? ((int) $funil['proximo_idx'] % $total_ativos) : -1;

            $ativo_posicao = [];
            foreach ($ativos as $pos => $a) {
                $ativo_posicao[$a['user_id']] = $pos;
            }

            // (int): o mysqli antigo (prepared + get_result) devolvia INT nativo e o
            // in_array estrito abaixo dependia disso; o PDO daqui devolve string.
            $ids_no_funil       = array_map('intval', array_column($corretores, 'user_id'));
            $opcoes_disponiveis = [];
            foreach ($todos_usuarios as $u) {
                if (!in_array((int) $u['id'], $ids_no_funil, true)) {
                    $opcoes_disponiveis[(string) $u['id']] = $u['username'];
                }
            }

            foreach ($corretores as &$corretor) {
                $eh_ativo   = $corretor['ativo'] === 'S';
                $posicao    = $ativo_posicao[$corretor['user_id']] ?? -1;
                $corretor['_eh_ativo']   = $eh_ativo;
                $corretor['_eh_proximo'] = $eh_ativo && $total_ativos > 0 && $posicao === $proximo_idx;
            }
            unset($corretor);

            $funil['corretores']          = $corretores;
            $funil['_opcoes_disponiveis'] = $opcoes_disponiveis;
            $funil['_icone']              = $icone_por_tipo[$funil['tipo_seguro']] ?? 'arrows-clockwise';
        }
        unset($funil);

        $html = view('visitas.rodizio._lista', ['funis' => $funis])->render();

        return response()->json(['html' => $html]);
    }

    /**
     * POST /api/admin/rodizio/adicionar
     * Adiciona um atendente ao final da fila de um funil de rodízio.
     * Body JSON: { "funil_id": 1, "user_id": 9 }
     */
    public function adicionar(Request $request)
    {
        $current_user = require_admin();

        $funil_id = (int) $request->input('funil_id', 0);
        $user_id  = (int) $request->input('user_id', 0);

        if ($funil_id <= 0 || $user_id <= 0) {
            json_response(['success' => false, 'message' => 'Selecione um atendente.'], 400);
        }

        $funil = db_fetch_one('SELECT id FROM funis WHERE id = ?', ['i', $funil_id]);
        if (!$funil) {
            json_response(['success' => false, 'message' => 'Funil não encontrado.'], 404);
        }

        $usuario = db_fetch_one('SELECT id FROM usuario WHERE id = ?', ['i', $user_id]);
        if (!$usuario) {
            json_response(['success' => false, 'message' => 'Atendente não encontrado.'], 404);
        }

        $ja_existe = db_fetch_one(
            'SELECT id FROM funil_corretores WHERE funil_id = ? AND user_id = ?',
            ['ii', $funil_id, $user_id]
        );
        if ($ja_existe) {
            json_response(['success' => false, 'message' => 'Este atendente já está neste funil.'], 422);
        }

        try {
            $max = db_fetch_one('SELECT MAX(ordem) AS max_ordem FROM funil_corretores WHERE funil_id = ?', ['i', $funil_id]);
            $proxima_ordem = ((int) ($max['max_ordem'] ?? 0)) + 1;

            db_execute(
                "INSERT INTO funil_corretores (funil_id, user_id, ordem, ativo) VALUES (?, ?, ?, 'S')",
                ['iii', $funil_id, $user_id, $proxima_ordem]
            );
        } catch (\Throwable $e) {
            error_log('[rodizio-adicionar] ' . $e->getMessage());
            json_response(['success' => false, 'message' => 'Erro ao adicionar atendente.'], 500);
        }

        return response()->json(['success' => true, 'message' => 'Atendente adicionado ao funil.']);
    }

    /**
     * POST /api/admin/rodizio/remover
     * Remove um atendente de um funil de rodízio e reordena os que restaram
     * (mantém a coluna "Ordem" sempre contígua: 1, 2, 3...).
     * Body JSON: { "id": 42 }  ← id da linha em funil_corretores
     */
    public function remover(Request $request)
    {
        $current_user = require_admin();

        $id = (int) $request->input('id', 0);

        if ($id <= 0) {
            json_response(['success' => false, 'message' => 'Requisição inválida.'], 400);
        }

        $linha = db_fetch_one('SELECT id, funil_id FROM funil_corretores WHERE id = ?', ['i', $id]);
        if (!$linha) {
            json_response(['success' => false, 'message' => 'Atendente não encontrado neste funil.'], 404);
        }

        try {
            DB::transaction(function () use ($id, $linha) {
                db_execute('DELETE FROM funil_corretores WHERE id = ?', ['i', $id]);

                $restantes = db_fetch_all(
                    'SELECT id FROM funil_corretores WHERE funil_id = ? ORDER BY ordem ASC',
                    ['i', $linha['funil_id']]
                );
                foreach ($restantes as $posicao => $r) {
                    db_execute('UPDATE funil_corretores SET ordem = ? WHERE id = ?', ['ii', $posicao + 1, (int) $r['id']]);
                }
            });
        } catch (\Throwable $e) {
            error_log('[rodizio-remover] ' . $e->getMessage());
            json_response(['success' => false, 'message' => 'Erro ao remover atendente.'], 500);
        }

        return response()->json(['success' => true, 'message' => 'Atendente removido do funil.']);
    }

    /**
     * POST /api/admin/rodizio/reordenar
     * Aplica a nova ordem de atendentes de um funil depois de um arrastar-e-soltar.
     * Body JSON: { "funil_id": 1, "ordem": [42, 17, 9, ...] }  ← ids de
     * funil_corretores na ordem final desejada (topo → base).
     */
    public function reordenar(Request $request)
    {
        $current_user = require_admin();

        $funil_id  = (int) $request->input('funil_id', 0);
        $ordem_ids = array_values(array_unique(array_map('intval', (array) ($request->input('ordem') ?? []))));

        if ($funil_id <= 0 || empty($ordem_ids)) {
            json_response(['success' => false, 'message' => 'Requisição inválida.'], 400);
        }

        $placeholders = implode(',', array_fill(0, count($ordem_ids), '?'));
        $rows = db_fetch_all(
            "SELECT id FROM funil_corretores WHERE funil_id = ? AND id IN ({$placeholders})",
            [str_repeat('i', count($ordem_ids) + 1), $funil_id, ...$ordem_ids]
        );
        if (count($rows) !== count($ordem_ids)) {
            json_response(['success' => false, 'message' => 'Lista de reordenação inválida.'], 422);
        }

        try {
            DB::transaction(function () use ($ordem_ids, $funil_id) {
                foreach ($ordem_ids as $posicao => $id) {
                    db_execute(
                        'UPDATE funil_corretores SET ordem = ? WHERE id = ? AND funil_id = ?',
                        ['iii', $posicao + 1, $id, $funil_id]
                    );
                }
            });
        } catch (\Throwable $e) {
            error_log('[rodizio-reordenar] ' . $e->getMessage());
            json_response(['success' => false, 'message' => 'Erro ao reordenar.'], 500);
        }

        return response()->json(['success' => true, 'message' => 'Ordem atualizada.']);
    }

    /**
     * POST /api/admin/rodizio/status
     * Ativa/desativa um atendente dentro de um funil (fica fora do rodízio
     * enquanto inativo, sem precisar removê-lo da lista).
     * Body JSON: { "id": 42, "ativo": true }
     */
    public function status(Request $request)
    {
        $current_user = require_admin();

        $id    = (int) $request->input('id', 0);
        $ativo = !empty($request->input('ativo')) ? 'S' : 'N';

        if ($id <= 0) {
            json_response(['success' => false, 'message' => 'Requisição inválida.'], 400);
        }

        $linha = db_fetch_one('SELECT id FROM funil_corretores WHERE id = ?', ['i', $id]);
        if (!$linha) {
            json_response(['success' => false, 'message' => 'Atendente não encontrado neste funil.'], 404);
        }

        try {
            db_execute('UPDATE funil_corretores SET ativo = ? WHERE id = ?', ['si', $ativo, $id]);
        } catch (\Throwable $e) {
            error_log('[rodizio-status] ' . $e->getMessage());
            json_response(['success' => false, 'message' => 'Erro ao atualizar status.'], 500);
        }

        return response()->json(['success' => true, 'message' => 'Status atualizado.']);
    }
}
