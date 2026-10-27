<?php
/**
 * GET /api/faq/fluxo
 * Retorna o fluxo de blocos do chat guiado do FAQ (ou {flow: []} se nunca
 * foi configurado). O motor de execução roda inteiramente no client-side.
 */
$current_user = require_auth();
header('Content-Type: application/json; charset=utf-8');

$flow = [];
try {
    $row = db_fetch_one('SELECT json_data FROM faq_fluxo_json WHERE id = 1');
    if ($row) {
        $decoded = json_decode($row['json_data'], true);
        $flow = is_array($decoded['flow'] ?? null) ? $decoded['flow'] : [];
    }
} catch (Throwable $e) {
    error_log('[faq-fluxo-obter] ' . $e->getMessage());
}

echo json_encode(['flow' => $flow]);
