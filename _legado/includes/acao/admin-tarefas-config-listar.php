<?php
/**
 * GET /api/admin/tarefas/config
 * Devolve os dados de configuração do módulo de Tarefas (setores, status,
 * etiquetas, campos customizados) + a lista de usuários MPS com seus setores.
 * Usado pela aba "Gerenciar" e para popular os selects do modal de tarefa.
 *
 * Qualquer admin pode LER (precisa dos setores/status para montar o modal);
 * as mutações (salvar/excluir/atribuir) é que exigem gestor.
 */
$current_user = require_admin();
header('Content-Type: application/json; charset=utf-8');

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
    $vinculos = db_fetch_all('SELECT usuario_id, setor_id FROM tarefas_usuario_setor');
    $mapa = [];
    foreach ($vinculos as $v) {
        $mapa[(int) $v['usuario_id']][] = (int) $v['setor_id'];
    }
    foreach ($usuarios as &$u) {
        $u['id']             = (int) $u['id'];
        $u['gestor']         = ($u['tarefas_gestor'] ?? 'N') === 'S';
        $u['setores']        = $mapa[$u['id']] ?? [];
        unset($u['tarefas_gestor']);
    }
    unset($u);

    // Normaliza tipos numéricos das listas de config.
    foreach ($setores as &$s)   { $s['id'] = (int) $s['id']; $s['ordem'] = (int) $s['ordem']; } unset($s);
    foreach ($status as &$s)    { $s['id'] = (int) $s['id']; $s['ordem'] = (int) $s['ordem']; $s['is_final'] = (int) $s['is_final']; } unset($s);
    foreach ($etiquetas as &$et){ $et['id'] = (int) $et['id']; } unset($et);
    foreach ($campos as &$c)    {
        $c['id'] = (int) $c['id'];
        $c['ordem'] = (int) $c['ordem'];
        $c['ativo'] = (int) $c['ativo'];
        $c['opcoes'] = $c['opcoes'] ? (json_decode($c['opcoes'], true) ?: []) : [];
    }
    unset($c);
} catch (\Throwable $e) {
    error_log('[admin-tarefas-config-listar] ' . $e->getMessage());
}

echo json_encode([
    'gestor'    => $ctx['gestor'],
    'setores'   => $setores,
    'status'    => $status,
    'etiquetas' => $etiquetas,
    'campos'    => $campos,
    'usuarios'  => $usuarios,
], JSON_UNESCAPED_UNICODE);
