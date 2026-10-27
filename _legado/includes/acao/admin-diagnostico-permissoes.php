<?php
/**
 * POST /api/admin/diagnostico-permissoes
 * Testa, na hora e rodando como o processo real do PHP (www-data), se os
 * diretórios de cache/log conseguem ser escritos. Criado depois de um erro
 * real de permissão (storage/cache/pottencial_token.json) pra dar uma forma
 * rápida de confirmar se a correção realmente resolveu, sem depender de
 * simular usuário/permissão manualmente.
 */
$current_user = require_admin();
header('Content-Type: application/json; charset=utf-8');
csrf_verify();

if (($current_user->nome ?? '') !== 'Suporte Sami 2') {
    json_response(['success' => false, 'message' => 'Sem acesso.'], 403);
}

$alvos = [
    'storage/cache' => BASE_PATH . '/storage/cache/.diagnostico_teste',
    'storage/logs'  => BASE_PATH . '/storage/logs/.diagnostico_teste',
];

$resultados = [];
foreach ($alvos as $label => $arquivo) {
    $conteudo = 'teste ' . date('Y-m-d H:i:s');
    $ok = @file_put_contents($arquivo, $conteudo) !== false;
    $erro = $ok ? null : (error_get_last()['message'] ?? 'motivo desconhecido');

    if ($ok) {
        @unlink($arquivo); // só testando — não deixa lixo pra trás
    }

    $resultados[] = [
        'local'   => $label,
        'ok'      => $ok,
        'erro'    => $erro,
        'usuario' => (function_exists('posix_getpwuid') ? (posix_getpwuid(posix_geteuid())['name'] ?? null) : null) ?? ('uid ' . getmyuid()),
    ];
}

json_response(['success' => true, 'resultados' => $resultados]);
