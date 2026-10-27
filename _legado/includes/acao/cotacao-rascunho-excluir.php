<?php
/**
 * POST /api/cotacao/rascunho/excluir
 * Exclui um rascunho de cotação, validando posse pela imobiliária.
 */
$current_user = require_auth();
header('Content-Type: application/json; charset=utf-8');

if (($current_user->role ?? '') !== 'imobiliaria') {
    echo json_encode(['ok' => false, 'message' => 'Sem permissão.']);
    exit;
}

csrf_verify();

$input     = json_decode(file_get_contents('php://input'), true) ?? [];
$tipo      = trim($input['tipo'] ?? '');
$protocolo = trim($input['protocolo'] ?? '');

$cfg = rascunho_config($tipo);
if (!$cfg || $protocolo === '') {
    echo json_encode(['ok' => false, 'message' => 'Dados inválidos.']);
    exit;
}

$existente = db_fetch_one(
    "SELECT * FROM {$cfg['tabela']} WHERE protocolo = ? AND imobiliaria_id = ?",
    ['si', $protocolo, $current_user->imobiliaria_id]
);
if (!$existente) {
    echo json_encode(['ok' => false, 'message' => 'Rascunho não encontrado.']);
    exit;
}

// Se este rascunho veio de uma cotação real (fluxo "completar cotação"), a
// cotação foi marcada como 'Rascunho' para sumir de "Aguardando Liberação".
// Excluir o rascunho não pode deixá-la travada nesse status sem volta —
// ela precisa voltar a ficar acessível para completar/aceitar de novo.
// (No-op se este protocolo não corresponder a nenhuma cotação real.)
if ($tipo === 'fianca') {
    db_execute(
        "UPDATE seguro_fianca SET status = 'Aguardando preenchimento'
          WHERE protocolo = ? AND imobiliaria_id = ? AND status = 'Rascunho'",
        ['si', $protocolo, $current_user->imobiliaria_id]
    );
}

db_execute("DELETE FROM {$cfg['tabela']} WHERE id = ?", ['i', $existente['id']]);

echo json_encode(['ok' => true]);
