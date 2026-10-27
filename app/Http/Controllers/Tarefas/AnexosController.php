<?php

namespace App\Http\Controllers\Tarefas;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * Anexos das tarefas (envio, listagem, exclusão). Visibilidade por setor.
 * Era includes/acao/admin-tarefas-anexo*.php.
 */
class AnexosController extends Controller
{
    /** POST /api/admin/tarefas/anexo   (multipart: id + arquivo) — envia um arquivo para a tarefa. */
    public function enviar(Request $request)
    {
        $current_user = require_admin();

        if ($m = upload_erro_limite()) return TarefasController::json(['ok' => false, 'message' => $m]);

        $ctx = tarefas_contexto_usuario((int) $current_user->uid);
        $id  = (int) $request->input('id', 0);

        if ($id <= 0) return TarefasController::json(['ok' => false, 'message' => 'Tarefa inválida.']);

        $tarefa = db_fetch_one('SELECT id, setor_id FROM tarefas WHERE id = ?', ['i', $id]);
        if (!$tarefa) return TarefasController::json(['ok' => false, 'message' => 'Tarefa não encontrada.']);
        if (!TarefasController::podeVer($ctx, $tarefa['setor_id'])) {
            return TarefasController::json(['ok' => false, 'message' => 'Sem permissão para esta tarefa.'], 403);
        }

        $arquivo = $_FILES['arquivo'] ?? null;
        if (empty($arquivo) || $arquivo['error'] !== UPLOAD_ERR_OK) {
            $msg = (!empty($arquivo) && in_array($arquivo['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true))
                ? 'Arquivo excede o tamanho máximo permitido pelo servidor.'
                : 'Selecione um arquivo para enviar.';
            return TarefasController::json(['ok' => false, 'message' => $msg]);
        }

        $MB = 1024 * 1024;
        $allowed = [
            'application/pdf' => 20 * $MB,
            'image/jpeg' => 20 * $MB, 'image/png' => 20 * $MB, 'image/webp' => 20 * $MB, 'image/gif' => 20 * $MB,
            'application/msword' => 20 * $MB,
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 20 * $MB,
            'application/vnd.ms-excel' => 20 * $MB,
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 20 * $MB,
            'application/vnd.ms-powerpoint' => 20 * $MB,
            'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 20 * $MB,
            'application/zip' => 20 * $MB, 'application/x-zip-compressed' => 20 * $MB,
            'text/plain' => 20 * $MB, 'text/csv' => 20 * $MB,
        ];

        try {
            $url = media_upload($arquivo, 'tarefas/' . $id, $allowed);
        } catch (\Illuminate\Http\Exceptions\HttpResponseException $e) {
            throw $e;
        } catch (\Throwable $e) {
            return TarefasController::json(['ok' => false, 'message' => $e->getMessage()]);
        }

        $nome    = mb_substr($arquivo['name'], 0, 255);
        $tipo    = strtolower(pathinfo($nome, PATHINFO_EXTENSION));
        $tamanho = (int) $arquivo['size'];

        $anexoId = db_execute(
            'INSERT INTO tarefas_anexos (tarefa_id, url, nome, tipo, tamanho, criado_por) VALUES (?, ?, ?, ?, ?, ?)',
            ['isssii', $id, $url, $nome, $tipo, $tamanho, (int) $current_user->uid]
        );

        tarefa_log($id, (int) $current_user->uid, 'anexo_add', $nome);

        return TarefasController::json([
            'ok'    => true,
            'anexo' => ['id' => (int) $anexoId, 'url' => $url, 'nome' => $nome, 'tipo' => $tipo, 'tamanho' => $tamanho],
        ]);
    }

    /** GET /api/admin/tarefas/anexos?id=N — lista os anexos de uma tarefa. */
    public function listar(Request $request)
    {
        $current_user = require_admin();

        $ctx = tarefas_contexto_usuario((int) $current_user->uid);
        $id  = (int) $request->query('id', 0);

        if ($id <= 0) return TarefasController::json(['ok' => false, 'anexos' => []]);

        $tarefa = db_fetch_one('SELECT id, setor_id FROM tarefas WHERE id = ?', ['i', $id]);
        if (!$tarefa || !TarefasController::podeVer($ctx, $tarefa['setor_id'])) {
            return TarefasController::json(['ok' => false, 'anexos' => []]);
        }

        $anexos = [];
        try {
            $anexos = array_map(static fn($a) => [
                'id'      => (int) $a['id'],
                'url'     => $a['url'],
                'nome'    => $a['nome'],
                'tipo'    => $a['tipo'],
                'tamanho' => (int) $a['tamanho'],
            ], db_fetch_all('SELECT id, url, nome, tipo, tamanho FROM tarefas_anexos WHERE tarefa_id = ? ORDER BY criado_em DESC', ['i', $id]));
        } catch (\Throwable $e) {
            error_log('[admin-tarefas-anexos-listar] ' . $e->getMessage());
        }

        return TarefasController::json(['ok' => true, 'anexos' => $anexos]);
    }

    /** POST /api/admin/tarefas/anexo/excluir  { anexo_id } — remove um anexo (registro + arquivo). */
    public function excluir(Request $request)
    {
        $current_user = require_admin();

        $ctx      = tarefas_contexto_usuario((int) $current_user->uid);
        $input    = $request->json()->all();
        $anexo_id = (int) ($input['anexo_id'] ?? 0);

        if ($anexo_id <= 0) return TarefasController::json(['ok' => false, 'message' => 'Anexo inválido.']);

        $anexo = db_fetch_one(
            'SELECT a.id, a.url, a.nome, a.tarefa_id, t.setor_id
               FROM tarefas_anexos a JOIN tarefas t ON t.id = a.tarefa_id
              WHERE a.id = ?',
            ['i', $anexo_id]
        );
        if (!$anexo) return TarefasController::json(['ok' => false, 'message' => 'Anexo não encontrado.']);
        if (!TarefasController::podeVer($ctx, $anexo['setor_id'])) {
            return TarefasController::json(['ok' => false, 'message' => 'Sem permissão.'], 403);
        }

        try {
            db_execute('DELETE FROM tarefas_anexos WHERE id = ?', ['i', $anexo_id]);
            // Remove o arquivo físico: local (/uploads) ou no media server.
            if (is_string($anexo['url']) && $anexo['url'] !== '') {
                if (str_starts_with($anexo['url'], '/uploads/')) {
                    $path = BASE_PATH . '/public' . $anexo['url'];
                    if (is_file($path)) @unlink($path);
                } else {
                    media_delete($anexo['url']);
                }
            }
            tarefa_log((int) $anexo['tarefa_id'], (int) $current_user->uid, 'anexo_del', $anexo['nome'] ?? '');
            return TarefasController::json(['ok' => true]);
        } catch (\Throwable $e) {
            error_log('[admin-tarefas-anexo-excluir] ' . $e->getMessage());
            return TarefasController::json(['ok' => false, 'message' => 'Erro ao excluir anexo.']);
        }
    }
}
