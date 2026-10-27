<?php
/**
 * POST /api/admin/bloqueio-horario/salvar
 * Cria (id vazio) ou atualiza (id presente) um bloqueio temporário.
 */
$current_user = require_admin();
header('Content-Type: application/json; charset=utf-8');
csrf_verify();

$tiposValidos = ['fianca', 'auto', 'titulo_capitalizacao', 'condominial'];

$input = json_decode(file_get_contents('php://input'), true) ?? [];

$id           = (int) ($input['id'] ?? 0);
$titulo       = trim($input['titulo'] ?? '');
$mensagem     = trim($input['mensagem'] ?? '');
$dataInicioD  = trim($input['data_inicio'] ?? '');
$horaInicio   = trim($input['hora_inicio'] ?? '');
$dataFimD     = trim($input['data_fim'] ?? '');
$horaFim      = trim($input['hora_fim'] ?? '');
$produtos     = $input['tipo_seguro'] ?? [];
$ativo        = !empty($input['ativo']) ? 'S' : 'N';

if ($titulo === '' || $mensagem === '') {
    json_response(['ok' => false, 'message' => 'Preencha título e mensagem.'], 422);
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataInicioD) || !preg_match('/^\d{2}:\d{2}$/', $horaInicio)
    || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataFimD) || !preg_match('/^\d{2}:\d{2}$/', $horaFim)) {
    json_response(['ok' => false, 'message' => 'Informe o período do bloqueio.'], 422);
}
$dataInicio = "{$dataInicioD} {$horaInicio}:00";
$dataFim    = "{$dataFimD} {$horaFim}:00";
if (strtotime($dataFim) <= strtotime($dataInicio)) {
    json_response(['ok' => false, 'message' => 'O fim do bloqueio precisa ser depois do início.'], 422);
}
if (!is_array($produtos) || !$produtos) {
    json_response(['ok' => false, 'message' => 'Selecione ao menos um produto afetado.'], 422);
}
$produtos = array_values(array_intersect($produtos, $tiposValidos));
if (!$produtos) {
    json_response(['ok' => false, 'message' => 'Produto inválido.'], 422);
}
$produtosStr = implode(',', $produtos);

$dataInicioSql = $dataInicio;
$dataFimSql    = $dataFim;

if ($id > 0) {
    db_execute(
        'UPDATE bloqueios_horario
            SET titulo = ?, mensagem = ?, data_inicio = ?, data_fim = ?, tipo_seguro = ?, ativo = ?
          WHERE id = ?',
        ['ssssssi', $titulo, $mensagem, $dataInicioSql, $dataFimSql, $produtosStr, $ativo, $id]
    );
} else {
    db_execute(
        'INSERT INTO bloqueios_horario (titulo, mensagem, data_inicio, data_fim, tipo_seguro, ativo, criado_por)
         VALUES (?, ?, ?, ?, ?, ?, ?)',
        ['ssssssi', $titulo, $mensagem, $dataInicioSql, $dataFimSql, $produtosStr, $ativo, (int) ($current_user->uid ?? 0)]
    );
}

json_response(['ok' => true]);
