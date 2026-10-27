<?php

namespace App\Http\Controllers\Tarefas;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * Configuração do módulo de Tarefas: setores, status, etiquetas, campos
 * customizados e atribuição de usuários a setores.
 * Era includes/acao/admin-tarefas-config-*.php e admin-tarefas-usuario-setores.php.
 *
 * Qualquer admin pode LER (precisa dos setores/status para montar o modal);
 * as mutações (salvar/excluir/atribuir) exigem gestor de tarefas.
 */
class ConfigController extends Controller
{
    /** GET /api/admin/tarefas/config — setores, status, etiquetas, campos e usuários MPS com seus setores. */
    public function listar()
    {
        $current_user = require_admin();

        $ctx = tarefas_contexto_usuario((int) $current_user->uid);

        $setores   = [];
        $status    = [];
        $etiquetas = [];
        $campos    = [];
        $usuarios  = [];

        try {
            $setores   = db_fetch_all('SELECT id, nome, cor, ordem FROM setores ORDER BY ordem ASC, nome ASC');
            $status    = db_fetch_all('SELECT id, nome, cor, ordem, is_final FROM tarefas_status ORDER BY ordem ASC, id ASC');
            $etiquetas = db_fetch_all('SELECT id, nome, cor FROM tarefas_etiquetas ORDER BY nome ASC');
            $campos    = db_fetch_all('SELECT id, nome, tipo, opcoes, ordem, ativo FROM tarefas_campos ORDER BY ordem ASC, id ASC');

            $usuarios = db_fetch_all(
                'SELECT id, username, email, avatar, tarefas_gestor FROM usuario ORDER BY username ASC'
            );

            // Setores de cada usuário (para a aba de atribuição) — um mapa usuario_id => [setor_id].
            $mapa = [];
            foreach (db_fetch_all('SELECT usuario_id, setor_id FROM tarefas_usuario_setor') as $v) {
                $mapa[(int) $v['usuario_id']][] = (int) $v['setor_id'];
            }
            foreach ($usuarios as &$u) {
                $u['id']      = (int) $u['id'];
                $u['gestor']  = ($u['tarefas_gestor'] ?? 'N') === 'S';
                $u['setores'] = $mapa[$u['id']] ?? [];
                unset($u['tarefas_gestor']);
            }
            unset($u);

            // Normaliza tipos numéricos das listas de config.
            foreach ($setores as &$s)    { $s['id'] = (int) $s['id']; $s['ordem'] = (int) $s['ordem']; } unset($s);
            foreach ($status as &$s)     { $s['id'] = (int) $s['id']; $s['ordem'] = (int) $s['ordem']; $s['is_final'] = (int) $s['is_final']; } unset($s);
            foreach ($etiquetas as &$et) { $et['id'] = (int) $et['id']; } unset($et);
            foreach ($campos as &$c) {
                $c['id']     = (int) $c['id'];
                $c['ordem']  = (int) $c['ordem'];
                $c['ativo']  = (int) $c['ativo'];
                $c['opcoes'] = $c['opcoes'] ? (json_decode($c['opcoes'], true) ?: []) : [];
            }
            unset($c);
        } catch (\Throwable $e) {
            error_log('[admin-tarefas-config-listar] ' . $e->getMessage());
        }

        return TarefasController::json([
            'gestor'    => $ctx['gestor'],
            'setores'   => $setores,
            'status'    => $status,
            'etiquetas' => $etiquetas,
            'campos'    => $campos,
            'usuarios'  => $usuarios,
        ]);
    }

    /**
     * POST /api/admin/tarefas/config/salvar
     * Cria/edita uma entidade de configuração (gestor-only):
     *   entidade = setor | status | etiqueta | campo — corpo JSON com os campos da entidade.
     */
    public function salvar(Request $request)
    {
        $current_user = require_admin();

        $ctx = tarefas_contexto_usuario((int) $current_user->uid);
        if (!$ctx['gestor']) {
            return TarefasController::json(['ok' => false, 'message' => 'Apenas gestores de tarefas podem gerenciar isto.'], 403);
        }

        $input    = $request->json()->all();
        $entidade = trim($input['entidade'] ?? '');
        $id       = (int) ($input['id'] ?? 0);
        $nome     = trim($input['nome'] ?? '');
        $cor      = trim($input['cor'] ?? '');
        $ordem    = (int) ($input['ordem'] ?? 0);

        if (!in_array($entidade, ['setor', 'status', 'etiqueta', 'campo'], true)) {
            return TarefasController::json(['ok' => false, 'message' => 'Entidade inválida.']);
        }
        if ($nome === '') {
            return TarefasController::json(['ok' => false, 'message' => 'Informe o nome.']);
        }
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $cor)) $cor = '#64748b';

        // Nos INSERTs, db_execute() já devolve o id gerado (era db()->insert_id).
        try {
            switch ($entidade) {
                case 'setor':
                    if ($id > 0) {
                        db_execute('UPDATE setores SET nome = ?, cor = ?, ordem = ? WHERE id = ?', ['ssii', $nome, $cor, $ordem, $id]);
                    } else {
                        $id = (int) db_execute('INSERT INTO setores (nome, cor, ordem) VALUES (?, ?, ?)', ['ssi', $nome, $cor, $ordem]);
                    }

                    // Vincula os usuários escolhidos a este setor (só a associação deste
                    // setor é reescrita; os demais setores de cada usuário ficam intactos).
                    if (isset($input['usuarios']) && is_array($input['usuarios'])) {
                        $usuarios = array_values(array_unique(array_map('intval', $input['usuarios'])));
                        db_execute('DELETE FROM tarefas_usuario_setor WHERE setor_id = ?', ['i', $id]);
                        foreach ($usuarios as $uid) {
                            if ($uid > 0) {
                                db_execute('INSERT IGNORE INTO tarefas_usuario_setor (usuario_id, setor_id) VALUES (?, ?)', ['ii', $uid, $id]);
                            }
                        }
                    }
                    break;

                case 'status':
                    $is_final = !empty($input['is_final']) ? 1 : 0;
                    $ativo    = isset($input['ativo']) ? (!empty($input['ativo']) ? 1 : 0) : 1;
                    if ($id > 0) {
                        db_execute('UPDATE tarefas_status SET nome = ?, cor = ?, ordem = ?, is_final = ?, ativo = ? WHERE id = ?', ['ssiiii', $nome, $cor, $ordem, $is_final, $ativo, $id]);
                    } else {
                        $id = (int) db_execute('INSERT INTO tarefas_status (nome, cor, ordem, is_final, ativo) VALUES (?, ?, ?, ?, ?)', ['ssiii', $nome, $cor, $ordem, $is_final, $ativo]);
                    }
                    break;

                case 'etiqueta':
                    if ($id > 0) {
                        db_execute('UPDATE tarefas_etiquetas SET nome = ?, cor = ? WHERE id = ?', ['ssi', $nome, $cor, $id]);
                    } else {
                        $id = (int) db_execute('INSERT INTO tarefas_etiquetas (nome, cor) VALUES (?, ?)', ['ss', $nome, $cor]);
                    }
                    break;

                case 'campo':
                    $tipo = trim($input['tipo'] ?? 'texto');
                    if (!in_array($tipo, ['texto', 'numero', 'data', 'selecao', 'checkbox'], true)) $tipo = 'texto';
                    $ativo = isset($input['ativo']) ? (!empty($input['ativo']) ? 1 : 0) : 1;

                    // Opções só fazem sentido para 'selecao'; guarda como JSON de strings.
                    $opcoesJson = null;
                    if ($tipo === 'selecao') {
                        $opcoes = (array) ($input['opcoes'] ?? []);
                        $opcoes = array_values(array_filter(array_map(static fn($o) => trim((string) $o), $opcoes), static fn($o) => $o !== ''));
                        $opcoesJson = json_encode($opcoes, JSON_UNESCAPED_UNICODE);
                    }

                    if ($id > 0) {
                        db_execute('UPDATE tarefas_campos SET nome = ?, tipo = ?, opcoes = ?, ordem = ?, ativo = ? WHERE id = ?', ['sssiii', $nome, $tipo, $opcoesJson, $ordem, $ativo, $id]);
                    } else {
                        $id = (int) db_execute('INSERT INTO tarefas_campos (nome, tipo, opcoes, ordem, ativo) VALUES (?, ?, ?, ?, ?)', ['sssii', $nome, $tipo, $opcoesJson, $ordem, $ativo]);
                    }
                    break;
            }

            return TarefasController::json(['ok' => true, 'id' => $id]);
        } catch (\Throwable $e) {
            error_log('[admin-tarefas-config-salvar] ' . $e->getMessage());
            return TarefasController::json(['ok' => false, 'message' => 'Erro ao salvar.']);
        }
    }

    /**
     * POST /api/admin/tarefas/config/excluir  { entidade, id }
     * Exclui uma entidade de configuração (gestor-only) e limpa os vínculos.
     */
    public function excluir(Request $request)
    {
        $current_user = require_admin();

        $ctx = tarefas_contexto_usuario((int) $current_user->uid);
        if (!$ctx['gestor']) {
            return TarefasController::json(['ok' => false, 'message' => 'Apenas gestores de tarefas podem gerenciar isto.'], 403);
        }

        $input    = $request->json()->all();
        $entidade = trim($input['entidade'] ?? '');
        $id       = (int) ($input['id'] ?? 0);

        if (!in_array($entidade, ['setor', 'status', 'etiqueta', 'campo'], true) || $id <= 0) {
            return TarefasController::json(['ok' => false, 'message' => 'Requisição inválida.']);
        }

        try {
            switch ($entidade) {
                case 'setor':
                    // Bloqueia se houver tarefas usando o setor (evita órfãs).
                    $emUso = (int) (db_fetch_one('SELECT COUNT(*) AS n FROM tarefas WHERE setor_id = ?', ['i', $id])['n'] ?? 0);
                    if ($emUso > 0) {
                        return TarefasController::json(['ok' => false, 'message' => "Não é possível excluir: há {$emUso} tarefa(s) neste setor."]);
                    }
                    db_execute('DELETE FROM tarefas_usuario_setor WHERE setor_id = ?', ['i', $id]);
                    db_execute('DELETE FROM setores WHERE id = ?', ['i', $id]);
                    break;

                case 'status':
                    $emUso = (int) (db_fetch_one('SELECT COUNT(*) AS n FROM tarefas WHERE status_id = ?', ['i', $id])['n'] ?? 0);
                    if ($emUso > 0) {
                        return TarefasController::json(['ok' => false, 'message' => "Não é possível excluir: há {$emUso} tarefa(s) com este status."]);
                    }
                    db_execute('DELETE FROM tarefas_status WHERE id = ?', ['i', $id]);
                    break;

                case 'etiqueta':
                    db_execute('DELETE FROM tarefas_etiquetas_vinculo WHERE etiqueta_id = ?', ['i', $id]);
                    db_execute('DELETE FROM tarefas_etiquetas WHERE id = ?', ['i', $id]);
                    break;

                case 'campo':
                    db_execute('DELETE FROM tarefas_campos_valores WHERE campo_id = ?', ['i', $id]);
                    db_execute('DELETE FROM tarefas_campos WHERE id = ?', ['i', $id]);
                    break;
            }

            return TarefasController::json(['ok' => true]);
        } catch (\Throwable $e) {
            error_log('[admin-tarefas-config-excluir] ' . $e->getMessage());
            return TarefasController::json(['ok' => false, 'message' => 'Erro ao excluir.']);
        }
    }

    /**
     * POST /api/admin/tarefas/usuario-setores  { usuario_id, gestor, setores: [ids] }
     * Define o flag "Gestor de Tarefas" e os setores de um usuário MPS (gestor-only).
     * Espelha o que o cadastro de usuário faz, mas direto pela aba Gerenciar.
     */
    public function usuarioSetores(Request $request)
    {
        $current_user = require_admin();

        $ctx = tarefas_contexto_usuario((int) $current_user->uid);
        if (!$ctx['gestor']) {
            return TarefasController::json(['ok' => false, 'message' => 'Apenas gestores de tarefas podem atribuir setores.'], 403);
        }

        $input      = $request->json()->all();
        $usuario_id = (int) ($input['usuario_id'] ?? 0);
        $gestor     = !empty($input['gestor']) ? 'S' : 'N';
        $setores    = array_values(array_unique(array_map('intval', (array) ($input['setores'] ?? []))));

        $usuario = db_fetch_one('SELECT id FROM usuario WHERE id = ?', ['i', $usuario_id]);
        if (!$usuario) {
            return TarefasController::json(['ok' => false, 'message' => 'Usuário não encontrado.']);
        }

        try {
            db_execute('UPDATE usuario SET tarefas_gestor = ? WHERE id = ?', ['si', $gestor, $usuario_id]);

            db_execute('DELETE FROM tarefas_usuario_setor WHERE usuario_id = ?', ['i', $usuario_id]);
            if ($gestor !== 'S') {
                // Gestor vê tudo — não precisa de setores; só grava vínculos para não-gestores.
                foreach ($setores as $sid) {
                    if ($sid <= 0) continue;
                    $existe = db_fetch_one('SELECT id FROM setores WHERE id = ?', ['i', $sid]);
                    if ($existe) {
                        db_execute('INSERT IGNORE INTO tarefas_usuario_setor (usuario_id, setor_id) VALUES (?, ?)', ['ii', $usuario_id, $sid]);
                    }
                }
            }

            return TarefasController::json(['ok' => true]);
        } catch (\Throwable $e) {
            error_log('[admin-tarefas-usuario-setores] ' . $e->getMessage());
            return TarefasController::json(['ok' => false, 'message' => 'Erro ao salvar atribuição.']);
        }
    }
}
