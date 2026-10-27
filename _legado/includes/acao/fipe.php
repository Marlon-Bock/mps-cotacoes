<?php
/**
 * GET /api/fipe — Consulta FIPE: marcas, modelos, anos.
 * Porta fiel do mps-antigo/includes/acao/acao-automovel.php.
 */

require_auth(); // apenas usuários autenticados podem consultar

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=3600');

function fipe_json(array $data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function fipe_fetch_all(string $sql, string $types, mixed ...$params): array {
    $conn = db();
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) return [];
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $rows = [];
    while ($row = mysqli_fetch_assoc($res)) $rows[] = $row;
    mysqli_stmt_close($stmt);
    return $rows;
}

$action = isset($_GET['action']) ? (string)$_GET['action'] : '';
$tipo   = in_array($_GET['tipo'] ?? '', ['carros','motos','caminhoes'], true) ? $_GET['tipo'] : 'carros';

// Referência FIPE mais recente
$refRow = db_fetch_one("SELECT codigo FROM automovel_referencias ORDER BY codigo DESC LIMIT 1");
$ref    = $refRow ? (int)$refRow['codigo'] : 0;
if ($ref <= 0) fipe_json(['ok' => false, 'message' => 'Referência FIPE não disponível.'], 503);

try {
    if ($action === 'marcas') {
        $rows = fipe_fetch_all("SELECT id, nome FROM automovel_marcas WHERE tipo_veiculo=? AND codigo_referencia=? ORDER BY nome ASC", 'si', $tipo, $ref);
        fipe_json(['ok' => true, 'items' => $rows]);
    }

    if ($action === 'modelos') {
        $marcaId = (int)($_GET['marca_id'] ?? 0);
        if ($marcaId <= 0) fipe_json(['ok' => false, 'message' => 'marca_id obrigatório'], 400);

        $rows = fipe_fetch_all("SELECT id, nome FROM automovel_modelos WHERE marca_id=? AND codigo_referencia=? ORDER BY nome ASC", 'ii', $marcaId, $ref);
        fipe_json(['ok' => true, 'items' => $rows]);
    }

    if ($action === 'anos') {
        $modeloId = (int)($_GET['modelo_id'] ?? 0);
        if ($modeloId <= 0) fipe_json(['ok' => false, 'message' => 'modelo_id obrigatório'], 400);

        // Busca codigo_externo para chamar API FIPE
        $info = fipe_fetch_all(
            "SELECT m.codigo_externo AS modelo_ext, b.codigo_externo AS marca_ext
             FROM automovel_modelos m JOIN automovel_marcas b ON b.id=m.marca_id
             WHERE m.id=? AND m.codigo_referencia=? AND b.tipo_veiculo=? AND b.codigo_referencia=? LIMIT 1",
            'iisi', $modeloId, $ref, $tipo, $ref
        );
        if (!$info) fipe_json(['ok' => false, 'message' => 'Modelo não encontrado.'], 404);

        $modeloExt = (int)$info[0]['modelo_ext'];
        $marcaExt  = (int)$info[0]['marca_ext'];
        $tiposApi  = ['carros' => 'cars', 'motos' => 'motorcycles', 'caminhoes' => 'trucks'];
        $tipoApi   = $tiposApi[$tipo];

        // Não passamos `reference` aqui de propósito: a API só exige plano pago
        // quando se pede uma referência antiga explicitamente ("histórico de
        // preços estendido"). Sem o parâmetro, ela usa a referência mais atual
        // dela — que é o que precisamos mesmo, já que nosso cache local de
        // marcas/modelos/anos (tabela automovel_referencias) não é mantido
        // atualizado meses a meses.
        $token   = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1c2VySWQiOiI2YTViNzkzMi0yOTQxLTQ0MzEtOGIxZC1iYjNiMmYxNzUxY2UiLCJlbWFpbCI6Im1hcmxvbmJvY2syMzNAZ21haWwuY29tIiwiaWF0IjoxNzcwOTIyODE4fQ.100ES6VXkEtLs4pJUOvVPxWos_17SvDtHZTq45hN1xs';
        $url     = "https://fipe.parallelum.com.br/api/v2/{$tipoApi}/brands/{$marcaExt}/models/{$modeloExt}/years";

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_NOSIGNAL       => true,
            CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4,
            CURLOPT_HTTPHEADER     => ['Accept: application/json', "X-Subscription-Token: {$token}"],
        ]);
        $body = curl_exec($ch);
        $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        unset($ch);

        if ($body !== false && $http < 400) {
            $dados = json_decode($body, true);
            if (is_array($dados) && count($dados) > 0) {
                $items = array_map(fn($a) => ['id' => (string)$a['code'], 'nome' => (string)$a['name']], $dados);
                usort($items, fn($a, $b) => strcmp($b['nome'], $a['nome']));
                fipe_json(['ok' => true, 'source' => 'api', 'items' => $items]);
            }
        }

        // Fallback: banco de dados
        $rows = fipe_fetch_all("SELECT codigo_externo AS id, nome FROM automovel_anos WHERE modelo_id=? AND codigo_referencia=? ORDER BY nome DESC", 'ii', $modeloId, $ref);
        fipe_json(['ok' => true, 'source' => 'db', 'items' => $rows]);
    }

    fipe_json(['ok' => false, 'message' => 'Ação inválida. Use: marcas|modelos|anos'], 400);

} catch (Throwable $e) {
    fipe_json(['ok' => false, 'message' => 'Erro interno.'], 500);
}
