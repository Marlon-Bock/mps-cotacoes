<?php
/**
 * AJAX endpoint — POST /api/embed/ativar
 * Autoatendimento: a própria imobiliária ativa/edita/desativa o embed,
 * informando a URL do seu site — substitui o cadastro manual pela MPS.
 * Body JSON: { ativar: bool, url?: string }
 */
$current_user = require_auth();
header('Content-Type: application/json; charset=utf-8');
csrf_verify();

if (!FEATURE_EMBED_UI) {
    json_response(['success' => false, 'message' => 'Recurso indisponível.'], 404);
}

if (($current_user->role ?? '') !== 'imobiliaria' || empty($current_user->imob_admin)) {
    json_response(['success' => false, 'message' => 'Sem permissão.'], 403);
}

require_once BASE_PATH . '/src/core/embed.php';

$input  = json_decode(file_get_contents('php://input'), true) ?? [];
$ativar = !empty($input['ativar']);
$url    = trim((string) ($input['url'] ?? ''));

if (!$ativar) {
    // Desativar e excluir domínio são a mesma operação aqui: sem embed_dominio
    // o CSP (embed_apply_frame_headers) já não libera nenhum site externo.
    db_execute('UPDATE imobiliarias SET usa_embed = 0, embed_dominio = NULL WHERE id = ?', ['i', $current_user->imobiliaria_id]);
    json_response(['success' => true, 'usa_embed' => false]);
}

$teste = embed_dominio_testar($url);
if (!$teste['ok']) {
    json_response(['success' => false, 'message' => $teste['erro']], 422);
}

$imob  = db_fetch_one('SELECT embed_token FROM imobiliarias WHERE id = ?', ['i', $current_user->imobiliaria_id]);
$token = $imob['embed_token'] ?? null;
if (!$token) {
    $token = embed_gerar_token();
}

db_execute(
    'UPDATE imobiliarias SET usa_embed = 1, embed_token = ?, embed_dominio = ? WHERE id = ?',
    ['ssi', $token, $teste['dominio'], $current_user->imobiliaria_id]
);

json_response(['success' => true, 'usa_embed' => true, 'dominio' => $teste['dominio']]);
