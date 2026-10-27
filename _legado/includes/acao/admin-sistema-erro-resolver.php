<?php
/**
 * POST /api/admin/sistema-erros/{id}/resolver
 * Marca (ou desmarca) um erro como corrigido — pra distinguir na tela o que
 * já foi tratado do que ainda precisa de atenção.
 */
$current_user = require_admin();
header('Content-Type: application/json; charset=utf-8');
csrf_verify();

if (($current_user->nome ?? '') !== 'Suporte Sami 2') {
    json_response(['success' => false, 'message' => 'Sem acesso.'], 403);
}

$id = (int) ($params['id'] ?? 0);
if (!$id) {
    json_response(['success' => false, 'message' => 'Id inválido.'], 400);
}

$erro = db_fetch_one('SELECT id, resolvido FROM sistema_erros WHERE id = ?', ['i', $id]);
if (!$erro) {
    json_response(['success' => false, 'message' => 'Registro não encontrado.'], 404);
}

$novoResolvido = !((int) $erro['resolvido']);

db_execute(
    'UPDATE sistema_erros SET resolvido = ?, resolvido_em = ?, resolvido_por = ? WHERE id = ?',
    ['issi',
     $novoResolvido ? 1 : 0,
     $novoResolvido ? date('Y-m-d H:i:s') : null,
     $novoResolvido ? $current_user->nome : null,
     $id]
);

json_response(['success' => true, 'resolvido' => $novoResolvido]);
