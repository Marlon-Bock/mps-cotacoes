<?php
/**
 * POST /api/admin/gerar-erro-teste
 * Dispara um erro de verdade (warning) só pra confirmar, na prática, que o
 * pipeline inteiro funciona: o handler captura → grava em sistema_erros →
 * aparece em /admin/sistema-erros. Roda no servidor real (www-data), então
 * confirma isso de ponta a ponta, não só a parte de permissão de arquivo.
 */
$current_user = require_admin();
header('Content-Type: application/json; charset=utf-8');
csrf_verify();

if (($current_user->nome ?? '') !== 'Suporte Sami 2') {
    json_response(['success' => false, 'message' => 'Sem acesso.'], 403);
}

trigger_error(
    'Erro de teste gerado manualmente por ' . ($current_user->nome ?? '?') . ' às ' . date('d/m/Y H:i:s'),
    E_USER_WARNING
);

json_response(['success' => true, 'message' => 'Erro de teste disparado — recarregue a lista abaixo pra ver se apareceu.']);
