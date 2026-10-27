<?php
/**
 * POST /api/admin/tarefas/config/salvar
 * Cria/edita uma entidade de configuração do módulo (gestor-only):
 *   entidade = setor | status | etiqueta | campo
 * Corpo JSON com os campos da entidade.
 */
$current_user = require_admin();
header('Content-Type: application/json; charset=utf-8');
csrf_verify();

$ctx = tarefas_contexto_usuario((int) $current_user->uid);
if (!$ctx['gestor']) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Apenas gestores de tarefas podem gerenciar isto.']);
    exit;
}

$input    = json_decode(file_get_contents('php://input'), true) ?? [];
$entidade = trim($input['entidade'] ?? '');
$id       = (int) ($input['id'] ?? 0);
$nome     = trim($input['nome'] ?? '');
$cor      = trim($input['cor'] ?? '');
$ordem    = (int) ($input['ordem'] ?? 0);

if (!in_array($entidade, ['setor', 'status', 'etiqueta', 'campo'], true)) {
    echo json_encode(['ok' => false, 'message' => 'Entidade inválida.']);
    exit;
}
if ($nome === '') {
    echo json_encode(['ok' => false, 'message' => 'Informe o nome.']);
    exit;
}
if (!preg_match('/^#[0-9a-fA-F]{6}$/', $cor)) $cor = '#64748b';

try {
    switch ($entidade) {
        case 'setor':
            if ($id > 0) {
                db_execute('UPDATE setores SET nome = ?, cor = ?, ordem = ? WHERE id = ?', ['ssii', $nome, $cor, $ordem, $id]);
            } else {
                db_execute('INSERT INTO setores (nome, cor, ordem) VALUES (?, ?, ?)', ['ssi', $nome, $cor, $ordem]);
                $id = (int) db()->insert_id;
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
                db_execute('INSERT INTO tarefas_status (nome, cor, ordem, is_final, ativo) VALUES (?, ?, ?, ?, ?)', ['ssiii', $nome, $cor, $ordem, $is_final, $ativo]);
                $id = (int) db()->insert_id;
            }
            break;

        case 'etiqueta':
            if ($id > 0) {
                db_execute('UPDATE tarefas_etiquetas SET nome = ?, cor = ? WHERE id = ?', ['ssi', $nome, $cor, $id]);
            } else {
                db_execute('INSERT INTO tarefas_etiquetas (nome, cor) VALUES (?, ?)', ['ss', $nome, $cor]);
                $id = (int) db()->insert_id;
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
                db_execute('INSERT INTO tarefas_campos (nome, tipo, opcoes, ordem, ativo) VALUES (?, ?, ?, ?, ?)', ['sssii', $nome, $tipo, $opcoesJson, $ordem, $ativo]);
                $id = (int) db()->insert_id;
            }
            break;
    }

    echo json_encode(['ok' => true, 'id' => $id]);
} catch (\Throwable $e) {
    error_log('[admin-tarefas-config-salvar] ' . $e->getMessage());
    echo json_encode(['ok' => false, 'message' => 'Erro ao salvar.']);
}
