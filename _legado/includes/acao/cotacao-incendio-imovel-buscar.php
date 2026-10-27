<?php
/**
 * AJAX endpoint — GET /api/cotacao/incendio/imovel-buscar?codigo=...
 * Mesma busca por código usada no Seguro Fiança (src/helpers/imovel_erp.php),
 * reaproveitada aqui pro Seguro Incêndio. Endpoint próprio (não o mesmo do
 * fiança) só pra manter a permissão certa (perm_incendio).
 */
$current_user = require_perm('perm_cotar', 'perm_incendio');
header('Content-Type: application/json; charset=utf-8');

require_once BASE_PATH . '/src/helpers/imovel_erp.php';

$codigo = trim($_GET['codigo'] ?? '');

$imob = db_fetch_one(
    'SELECT eh_cliente_sami, sigla_erp, sistema, site_samiloca, samiloca_api_user, samiloca_api_pass FROM imobiliarias WHERE id = ?',
    ['i', $current_user->imobiliaria_id]
);

if (!$imob || (int) $imob['eh_cliente_sami'] !== 1 || empty($imob['sigla_erp'])) {
    json_response(['ok' => false, 'erro' => 'Busca por código disponível apenas para clientes Sami.'], 403);
}

if ($codigo === '') {
    json_response(['ok' => false, 'erro' => 'Informe o código do imóvel.'], 422);
}

json_response(imovel_erp_buscar(
    $imob['sigla_erp'],
    $codigo,
    $imob['sistema'] ?? '',
    $imob['site_samiloca'] ?? '',
    $imob['samiloca_api_user'] ?? '',
    $imob['samiloca_api_pass'] ?? ''
));
