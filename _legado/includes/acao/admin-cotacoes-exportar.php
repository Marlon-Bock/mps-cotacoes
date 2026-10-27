<?php
/**
 * GET /api/admin/cotacoes/exportar
 * Gera um CSV com as cotações filtradas (sem paginação) — botão "Tirar relatório".
 */
$current_user = require_admin();

$status_rapido = trim($_GET['status_rapido'] ?? '');
$protocolo     = trim($_GET['protocolo']     ?? '');
$corretor_id   = max(0, (int)($_GET['corretor_id'] ?? 0));
$imobiliaria   = trim($_GET['imobiliaria']   ?? '');
$produto       = trim($_GET['produto']       ?? '');
$data_de       = trim($_GET['data_de']       ?? '');
$data_ate      = trim($_GET['data_ate']      ?? '');

if ($data_de  && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data_de))  $data_de  = '';
if ($data_ate && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data_ate)) $data_ate = '';

$rows = admin_cotacoes_buscar_todas($produto);

if ($protocolo !== '')   $rows = array_values(array_filter($rows, fn($r) => mb_stripos($r['protocolo'] ?? '', $protocolo) !== false));
if ($corretor_id > 0)    $rows = array_values(array_filter($rows, fn($r) => (int)($r['atendente_id'] ?? 0) === $corretor_id));
if ($imobiliaria !== '') $rows = array_values(array_filter($rows, fn($r) => mb_stripos($r['imob_nome'] ?? '', $imobiliaria) !== false));
if ($data_de !== '')     $rows = array_values(array_filter($rows, fn($r) => substr($r['atualizado_em'] ?? '', 0, 10) >= $data_de));
if ($data_ate !== '')    $rows = array_values(array_filter($rows, fn($r) => substr($r['atualizado_em'] ?? '', 0, 10) <= $data_ate));

// API das seguradoras: fichas esperando a MPS (filtro "Aguardando MPS").
require_once BASE_PATH . '/src/helpers/api.php';
$rows = admin_cotacoes_marcar_api($rows);

$rows = admin_cotacoes_filtrar_status($rows, $status_rapido);

usort($rows, fn($a, $b) => strcmp($b['atualizado_em'] ?? '', $a['atualizado_em'] ?? ''));

$nome_arquivo = 'cotacoes-mps-' . date('Y-m-d_His') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $nome_arquivo . '"');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF"); // BOM — acentos corretos ao abrir no Excel

fputcsv($out, ['Produto', 'Protocolo', 'Pretendente/Segurado', 'Status', 'Imobiliária', 'Atendente', 'Data criação', 'Última atualização'], ';');

foreach ($rows as $row) {
    $origem = $row['origem'] ?? 'fianca';
    $produto_label = match ($origem) {
        'auto'        => 'Seguro Auto',
        'titulo'      => 'Título de Capitalização',
        'condominial' => 'Seguro Condomínio',
        'incendio'    => 'Seguro Incêndio',
        'residencial' => 'Seguro Residencial',
        default       => 'Fiança ' . (strtolower($row['tipo_ficha'] ?? '') === 'comercial' ? 'Comercial' : 'Residencial'),
    };

    fputcsv($out, [
        $produto_label,
        $row['protocolo'] ?? '',
        $row['pretendente'] ?? '',
        admin_status_label((string)($row['status'] ?? ''), $origem),
        $row['imob_nome'] ?? '',
        $row['atendente_nome'] ?? 'Não atribuído',
        !empty($row['data_cadastro'])  ? date('d/m/Y H:i', strtotime($row['data_cadastro']))  : '',
        !empty($row['atualizado_em'])  ? date('d/m/Y H:i', strtotime($row['atualizado_em']))  : '',
    ], ';');
}

fclose($out);
exit;
