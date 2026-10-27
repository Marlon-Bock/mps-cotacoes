<?php
/**
 * POST /api/perfil/avatar
 * Upload do avatar do usuário logado via media server (helper media_upload()).
 */
header('Content-Type: application/json; charset=utf-8');
csrf_verify();

if ($m = upload_erro_limite()) json_response(['message' => $m], 413);

$current_user = require_auth();
$tabela = $current_user->role === 'admin' ? 'usuario' : 'users';

if (empty($_FILES['avatar'])) {
    json_response(['message' => 'Nenhum arquivo enviado.'], 422);
}

$atual = db_fetch_one("SELECT avatar FROM {$tabela} WHERE id = ?", ['i', $current_user->uid]);

try {
    $url = media_upload($_FILES['avatar'], 'avatars');
} catch (RuntimeException $e) {
    json_response(['message' => $e->getMessage()], 422);
}

db_execute("UPDATE {$tabela} SET avatar = ? WHERE id = ?", ['si', $url, $current_user->uid]);

if (!empty($atual['avatar'])) {
    try { media_delete($atual['avatar']); } catch (Throwable $e) {}
}

json_response(['message' => 'Avatar atualizado com sucesso.', 'avatar' => $url]);
