<?php
/**
 * AJAX endpoint — POST /api/admin/imobiliarias/testar-samiloca
 * Body JSON: { url? , nome?, site? }
 *
 * Valida a URL base da API SAMILOCA de uma imobiliária:
 *   - com `url`  → testa exatamente essa URL;
 *   - sem `url`  → tenta os padrões conhecidos (site próprio do cliente,
 *                  <apelido>.samiloca.com.br e <apelido>.app.samiloca.com.br)
 *                  e devolve a primeira que responder o JSON do widesys.
 *
 * Retorna { ok, url, erro?, tentativas: [{url, erro}] }
 */
$current_user = require_admin();
header('Content-Type: application/json; charset=utf-8');
csrf_verify();

require_once BASE_PATH . '/src/helpers/imovel_erp.php';

$input   = json_decode(file_get_contents('php://input'), true) ?? [];
$url     = trim((string) ($input['url']  ?? ''));
$nome    = trim((string) ($input['nome'] ?? ''));
$site    = trim((string) ($input['site'] ?? ''));
$apiUser = trim((string) ($input['api_user'] ?? ''));
$apiPass = trim((string) ($input['api_pass'] ?? ''));

// Teste direto de uma URL informada pelo admin.
if ($url !== '') {
    json_response(samiloca_api_testar($url, 15, $apiUser, $apiPass));
}

// Detecção automática: timeout curto por candidato para a tela não travar.
$candidatas = samiloca_url_candidatas($nome, $site);
if (!$candidatas) {
    json_response(['ok' => false, 'url' => '', 'erro' => 'Sem dados para deduzir a URL. Informe manualmente.']);
}

$tentativas = [];
foreach ($candidatas as $candidata) {
    $r = samiloca_api_testar($candidata, 8, $apiUser, $apiPass);
    if ($r['ok']) {
        json_response(['ok' => true, 'url' => $r['url'], 'tentativas' => $tentativas]);
    }
    $tentativas[] = ['url' => $candidata, 'erro' => $r['erro'] ?? ''];
}

json_response([
    'ok'         => false,
    'url'        => '',
    'erro'       => 'Nenhum dos endereços conhecidos respondeu a API. Informe a URL manualmente.',
    'tentativas' => $tentativas,
]);
