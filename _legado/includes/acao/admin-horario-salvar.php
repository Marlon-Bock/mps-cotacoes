<?php
/**
 * POST /api/admin/horario-atendimento/salvar
 * Upsert do horário de atendimento de um produto (uq_tipo_seguro cuida do
 * "1 linha por produto" — INSERT ... ON DUPLICATE KEY UPDATE).
 */
$current_user = require_admin();
header('Content-Type: application/json; charset=utf-8');
csrf_verify();

$tiposValidos = ['todos', 'fianca', 'auto', 'titulo_capitalizacao', 'condominial'];

$input = json_decode(file_get_contents('php://input'), true) ?? [];

$tipoSeguro = trim($input['tipo_seguro'] ?? '');
$horaInicio = trim($input['hora_inicio'] ?? '');
$horaFim    = trim($input['hora_fim'] ?? '');
$diasSemana = $input['dias_semana'] ?? [];
$ativo      = !empty($input['ativo']) ? 'S' : 'N';

if (!in_array($tipoSeguro, $tiposValidos, true)) {
    json_response(['ok' => false, 'message' => 'Produto inválido.'], 422);
}
if (!preg_match('/^\d{2}:\d{2}$/', $horaInicio) || !preg_match('/^\d{2}:\d{2}$/', $horaFim)) {
    json_response(['ok' => false, 'message' => 'Informe os horários de início e fim.'], 422);
}
if (!is_array($diasSemana) || !$diasSemana) {
    json_response(['ok' => false, 'message' => 'Selecione ao menos um dia da semana.'], 422);
}

$diasSemana = array_values(array_unique(array_map('intval', $diasSemana)));
sort($diasSemana);
$diasSemanaStr = implode(',', $diasSemana);

db_execute(
    'INSERT INTO horarios_atendimento (tipo_seguro, hora_inicio, hora_fim, dias_semana, ativo)
     VALUES (?, ?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE hora_inicio = VALUES(hora_inicio), hora_fim = VALUES(hora_fim),
                              dias_semana = VALUES(dias_semana), ativo = VALUES(ativo)',
    ['sssss', $tipoSeguro, $horaInicio . ':00', $horaFim . ':00', $diasSemanaStr, $ativo]
);

json_response(['ok' => true]);
