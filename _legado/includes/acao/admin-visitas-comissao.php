<?php
/**
 * AJAX endpoint — GET+POST /api/admin/visitas/comissao
 * Compartilhado entre Relatório de Visitas e Histórico de Visitas.
 *
 * GET  ?action=get&imobiliaria_id=N
 *      → { comissoes: { fianca: {modo,percentual,valor}, cap: {...}, ... } }
 *        (sempre os 5 tipos, com default modo=percentual/0 se não existir linha)
 *
 * POST { action: "save", imobiliaria_id, tipo_seguro, modo, valor }
 *      → upsert em comissoes_imobiliarias + notifica os admins da mudança.
 */
$current_user = require_admin();
header('Content-Type: application/json; charset=utf-8');

const VISITA_TIPOS_SEGURO = ['fianca', 'cap', 'incendio', 'vida', 'auto'];

$metodo = $_SERVER['REQUEST_METHOD'];

if ($metodo === 'GET') {
    $imobiliaria_id = (int) ($_GET['imobiliaria_id'] ?? 0);
    if ($imobiliaria_id <= 0) {
        json_response(['success' => false, 'message' => 'Imobiliária inválida.'], 400);
    }

    $comissoes = [];
    foreach (VISITA_TIPOS_SEGURO as $tipo) {
        $comissoes[$tipo] = ['modo' => 'percentual', 'percentual' => 0, 'valor' => null];
    }

    try {
        $rows = db_fetch_all(
            "SELECT tipo_seguro, modo, percentual, valor FROM comissoes_imobiliarias WHERE imobiliaria_id = ?",
            ['i', $imobiliaria_id]
        );
        foreach ($rows as $row) {
            if (isset($comissoes[$row['tipo_seguro']])) {
                $comissoes[$row['tipo_seguro']] = [
                    'modo'       => $row['modo'],
                    'percentual' => (float) $row['percentual'],
                    'valor'      => $row['valor'] !== null ? (float) $row['valor'] : null,
                ];
            }
        }
    } catch (Throwable $e) {
        error_log('[visitas-comissao] ' . $e->getMessage());
    }

    json_response(['success' => true, 'comissoes' => $comissoes]);
}

// ── POST: salvar uma comissão ────────────────────────────────────────────────
csrf_verify();

$input          = json_decode(file_get_contents('php://input'), true) ?? [];
$imobiliaria_id = (int) ($input['imobiliaria_id'] ?? 0);
$tipo_seguro    = (string) ($input['tipo_seguro'] ?? '');
$modo           = ($input['modo'] ?? 'percentual') === 'valor' ? 'valor' : 'percentual';
$numero         = (float) ($input['numero'] ?? 0);

if ($imobiliaria_id <= 0 || !in_array($tipo_seguro, VISITA_TIPOS_SEGURO, true)) {
    json_response(['success' => false, 'message' => 'Requisição inválida.'], 400);
}

$percentual = $modo === 'percentual' ? $numero : 0;
$valor      = $modo === 'valor' ? $numero : null;

try {
    db_execute(
        "INSERT INTO comissoes_imobiliarias (imobiliaria_id, tipo_seguro, percentual, modo, valor, atualizado_por)
         VALUES (?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE percentual = VALUES(percentual), modo = VALUES(modo), valor = VALUES(valor), atualizado_por = VALUES(atualizado_por)",
        ['isdsdi', $imobiliaria_id, $tipo_seguro, $percentual, $modo, $valor, (int) $current_user->uid]
    );

    $imob = db_fetch_one('SELECT nome FROM imobiliarias WHERE id = ?', ['i', $imobiliaria_id]);
    $rotulo = $modo === 'percentual' ? "{$numero}%" : 'R$ ' . number_format($numero, 2, ',', '.');
    notificar_todos_admins(
        'comissao_alterada',
        'Comissão atualizada — ' . ($imob['nome'] ?? "imobiliária #{$imobiliaria_id}"),
        "Comissão de {$tipo_seguro} definida para {$rotulo} por " . ($current_user->nome ?? 'um atendente') . '.'
    );
} catch (Throwable $e) {
    error_log('[visitas-comissao] ' . $e->getMessage());
    json_response(['success' => false, 'message' => 'Erro ao salvar comissão.'], 500);
}

json_response(['success' => true, 'message' => 'Comissão salva.']);
