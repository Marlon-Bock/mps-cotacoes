<?php

namespace App\Http\Controllers\Tarefas;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * Checklist (subitens) das tarefas — bloco expansível na linha da lista.
 * Era includes/acao/admin-tarefas-subitens-*.php. Visibilidade por setor.
 */
class SubitensController extends Controller
{
    /** Totais do checklist de uma tarefa (para atualizar o "feitos/total" da linha). */
    private static function totais(int $tarefa_id): array
    {
        $total = db_fetch_one(
            "SELECT COUNT(*) AS total, SUM(concluido = 'S') AS feitos FROM tarefas_subitens WHERE tarefa_id = ?",
            ['i', $tarefa_id]
        );
        return ['total' => (int) ($total['total'] ?? 0), 'feitos' => (int) ($total['feitos'] ?? 0)];
    }

    /** GET /api/admin/tarefas/subitens/listar?tarefa_id= — lista o checklist da tarefa. */
    public function listar(Request $request)
    {
        $current_user = require_admin();

        $ctx       = tarefas_contexto_usuario((int) $current_user->uid);
        $tarefa_id = (int) $request->query('tarefa_id', 0);

        if ($tarefa_id <= 0) return TarefasController::json(['ok' => false, 'message' => 'Tarefa inválida.']);

        $tarefa = db_fetch_one('SELECT id, setor_id FROM tarefas WHERE id = ?', ['i', $tarefa_id]);
        if (!$tarefa) return TarefasController::json(['ok' => false, 'message' => 'Tarefa não encontrada.']);

        if (!TarefasController::podeVer($ctx, $tarefa['setor_id'])) {
            return TarefasController::json(['ok' => false, 'message' => 'Sem permissão para esta tarefa.'], 403);
        }

        try {
            $subitens = db_fetch_all(
                'SELECT id, titulo, concluido, ordem FROM tarefas_subitens WHERE tarefa_id = ? ORDER BY ordem ASC, id ASC',
                ['i', $tarefa_id]
            );
            return TarefasController::json(['ok' => true, 'subitens' => $subitens]);
        } catch (\Throwable $e) {
            error_log('[admin-tarefas-subitens-listar] ' . $e->getMessage());
            return TarefasController::json(['ok' => false, 'message' => 'Erro ao listar subitens.']);
        }
    }

    /**
     * POST /api/admin/tarefas/subitens/salvar
     * { tarefa_id, id (0 = novo), titulo, concluido ('S'|'N') } — cria ou atualiza um subitem.
     */
    public function salvar(Request $request)
    {
        $current_user = require_admin();

        $ctx   = tarefas_contexto_usuario((int) $current_user->uid);
        $input = $request->json()->all();

        $tarefa_id = (int) ($input['tarefa_id'] ?? 0);
        $id        = (int) ($input['id'] ?? 0);
        $titulo    = trim($input['titulo'] ?? '');
        $concluido = ($input['concluido'] ?? 'N') === 'S' ? 'S' : 'N';

        if ($tarefa_id <= 0) return TarefasController::json(['ok' => false, 'message' => 'Tarefa inválida.']);

        $tarefa = db_fetch_one('SELECT id, setor_id FROM tarefas WHERE id = ?', ['i', $tarefa_id]);
        if (!$tarefa) return TarefasController::json(['ok' => false, 'message' => 'Tarefa não encontrada.']);

        if (!TarefasController::podeVer($ctx, $tarefa['setor_id'])) {
            return TarefasController::json(['ok' => false, 'message' => 'Sem permissão para esta tarefa.'], 403);
        }

        try {
            if ($id > 0) {
                $atual = db_fetch_one('SELECT id FROM tarefas_subitens WHERE id = ? AND tarefa_id = ?', ['ii', $id, $tarefa_id]);
                if (!$atual) return TarefasController::json(['ok' => false, 'message' => 'Subitem não encontrado.']);

                if ($titulo === '') {
                    // Sem título novo: trata como toggle de concluído apenas.
                    db_execute('UPDATE tarefas_subitens SET concluido = ? WHERE id = ?', ['si', $concluido, $id]);
                } else {
                    db_execute('UPDATE tarefas_subitens SET titulo = ?, concluido = ? WHERE id = ?', ['ssi', $titulo, $concluido, $id]);
                }
            } else {
                if ($titulo === '') return TarefasController::json(['ok' => false, 'message' => 'Informe o título do subitem.']);
                $prox = db_fetch_one('SELECT COALESCE(MAX(ordem), 0) + 1 AS ordem FROM tarefas_subitens WHERE tarefa_id = ?', ['i', $tarefa_id]);
                $id = (int) db_execute(
                    'INSERT INTO tarefas_subitens (tarefa_id, titulo, concluido, ordem, criado_por) VALUES (?, ?, ?, ?, ?)',
                    ['issii', $tarefa_id, $titulo, $concluido, (int) ($prox['ordem'] ?? 1), (int) $current_user->uid]
                );
            }

            return TarefasController::json(['ok' => true, 'id' => $id] + self::totais($tarefa_id));
        } catch (\Throwable $e) {
            error_log('[admin-tarefas-subitens-salvar] ' . $e->getMessage());
            return TarefasController::json(['ok' => false, 'message' => 'Erro ao salvar o subitem.']);
        }
    }

    /** POST /api/admin/tarefas/subitens/excluir  { id } — exclui um subitem do checklist. */
    public function excluir(Request $request)
    {
        $current_user = require_admin();

        $ctx   = tarefas_contexto_usuario((int) $current_user->uid);
        $input = $request->json()->all();
        $id    = (int) ($input['id'] ?? 0);

        if ($id <= 0) return TarefasController::json(['ok' => false, 'message' => 'Subitem inválido.']);

        $sub = db_fetch_one(
            'SELECT si.id, si.tarefa_id, t.setor_id FROM tarefas_subitens si JOIN tarefas t ON t.id = si.tarefa_id WHERE si.id = ?',
            ['i', $id]
        );
        if (!$sub) return TarefasController::json(['ok' => false, 'message' => 'Subitem não encontrado.']);

        if (!TarefasController::podeVer($ctx, $sub['setor_id'])) {
            return TarefasController::json(['ok' => false, 'message' => 'Sem permissão para esta tarefa.'], 403);
        }

        try {
            db_execute('DELETE FROM tarefas_subitens WHERE id = ?', ['i', $id]);
            return TarefasController::json(['ok' => true] + self::totais((int) $sub['tarefa_id']));
        } catch (\Throwable $e) {
            error_log('[admin-tarefas-subitens-excluir] ' . $e->getMessage());
            return TarefasController::json(['ok' => false, 'message' => 'Erro ao excluir o subitem.']);
        }
    }
}
