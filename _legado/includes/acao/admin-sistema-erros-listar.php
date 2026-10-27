<?php
/**
 * GET /api/admin/sistema-erros/listar
 * Alimenta a tela /admin/sistema-erros via AJAX — filtros de tipo/status não
 * navegam nem alteram a URL, seguindo o mesmo padrão do resto do painel
 * admin (ver public/assets/js/admin-chamados-listar.js).
 */
$current_user = require_admin();
header('Content-Type: application/json; charset=utf-8');

if (($current_user->nome ?? '') !== 'Suporte Sami 2') {
    json_response(['success' => false, 'message' => 'Sem acesso.'], 403);
}

$tipo_filtro   = trim((string) ($_GET['tipo'] ?? ''));
$status_filtro = trim((string) ($_GET['status'] ?? 'pendentes')); // pendentes|resolvidos|todos

$condicoes  = [];
$bindTypes  = '';
$bindValues = [];
if (in_array($tipo_filtro, ['error', 'exception', 'fatal'], true)) {
    $condicoes[]  = 'tipo = ?';
    $bindTypes   .= 's';
    $bindValues[] = $tipo_filtro;
}
if ($status_filtro === 'pendentes') {
    $condicoes[] = 'resolvido = 0';
} elseif ($status_filtro === 'resolvidos') {
    $condicoes[] = 'resolvido = 1';
}

$sql = "SELECT * FROM sistema_erros";
if ($condicoes) $sql .= ' WHERE ' . implode(' AND ', $condicoes);
$sql .= " ORDER BY id DESC LIMIT 300";

$erros = $bindValues ? db_fetch_all($sql, [$bindTypes, ...$bindValues]) : db_fetch_all($sql);

ob_start();
require BASE_PATH . '/pages/admin/_partials/sistema-erros-linhas.php';
$html = ob_get_clean();

$total_por_tipo   = db_fetch_all("SELECT tipo, COUNT(*) AS total FROM sistema_erros WHERE resolvido = 0 GROUP BY tipo");
$totais_tipo      = array_column($total_por_tipo, 'total', 'tipo');
$total_pendentes  = (int) (db_fetch_one("SELECT COUNT(*) AS c FROM sistema_erros WHERE resolvido = 0")['c'] ?? 0);
$total_resolvidos = (int) (db_fetch_one("SELECT COUNT(*) AS c FROM sistema_erros WHERE resolvido = 1")['c'] ?? 0);

json_response([
    'success'    => true,
    'html'       => $html,
    'pendentes'  => $total_pendentes,
    'resolvidos' => $total_resolvidos,
    'todos'      => $total_pendentes + $total_resolvidos,
    'tipos'      => [
        'exception' => (int) ($totais_tipo['exception'] ?? 0),
        'error'     => (int) ($totais_tipo['error'] ?? 0),
        'fatal'     => (int) ($totais_tipo['fatal'] ?? 0),
    ],
]);
