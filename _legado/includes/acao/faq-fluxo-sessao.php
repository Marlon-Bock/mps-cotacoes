<?php
/**
 * POST /api/faq/fluxo/sessao
 * Upsert progressivo da sessão do chat guiado do FAQ — cada resposta do
 * usuário (nome/telefone/imobiliária, opção escolhida, chamado aberto,
 * conclusão) chama esse endpoint de novo com o mesmo sessao_token, então o
 * atendimento aparece em /admin/faq/atendimentos mesmo se a pessoa abandonar
 * no meio do fluxo.
 */
$current_user = require_auth();
header('Content-Type: application/json; charset=utf-8');
csrf_verify();

$input = json_decode(file_get_contents('php://input'), true) ?? [];

$sessaoToken = trim((string) ($input['sessao_token'] ?? ''));
if ($sessaoToken === '' || strlen($sessaoToken) > 64) {
    json_response(['ok' => false, 'message' => 'Sessão inválida.'], 422);
}

$existente = db_fetch_one(
    'SELECT id, caminho FROM faq_atendimentos WHERE sessao_token = ? LIMIT 1',
    ['s', $sessaoToken]
);

$nome        = isset($input['nome'])        ? mb_substr(trim((string) $input['nome']), 0, 150)        : null;
$telefone    = isset($input['telefone'])    ? mb_substr(trim((string) $input['telefone']), 0, 30)      : null;
$imobiliaria = isset($input['imobiliaria']) ? mb_substr(trim((string) $input['imobiliaria']), 0, 150)  : null;
$caminhoAdd  = isset($input['caminho_add']) ? trim((string) $input['caminho_add']) : null;
$chamadoAberto = !empty($input['chamado_aberto']) ? 1 : null;
$concluido     = !empty($input['concluido'])     ? 1 : null;

if (!$existente) {
    $usuarioId   = (int) ($current_user->uid ?? 0);
    $usuarioTipo = ($current_user->role ?? '') === 'admin' ? 'admin' : 'imobiliaria';
    $usuarioNome = mb_substr((string) ($current_user->nome ?? ''), 0, 150);
    $imobiliariaId = $usuarioTipo === 'imobiliaria' ? ($current_user->imobiliaria_id ?? null) : null;

    db_execute(
        'INSERT INTO faq_atendimentos
            (sessao_token, usuario_id, usuario_tipo, usuario_nome, imobiliaria_id,
             nome_informado, telefone_informado, imobiliaria_informada, caminho,
             chamado_aberto, concluido)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [
            'sississssii',
            $sessaoToken, $usuarioId, $usuarioTipo, $usuarioNome, $imobiliariaId,
            $nome, $telefone, $imobiliaria, $caminhoAdd,
            $chamadoAberto ?? 0, $concluido ?? 0,
        ]
    );
    json_response(['ok' => true]);
}

$sets   = [];
$tipos  = '';
$params = [];

if ($nome !== null)        { $sets[] = 'nome_informado = ?';        $tipos .= 's'; $params[] = $nome; }
if ($telefone !== null)    { $sets[] = 'telefone_informado = ?';    $tipos .= 's'; $params[] = $telefone; }
if ($imobiliaria !== null) { $sets[] = 'imobiliaria_informada = ?'; $tipos .= 's'; $params[] = $imobiliaria; }
if ($chamadoAberto !== null) { $sets[] = 'chamado_aberto = ?'; $tipos .= 'i'; $params[] = $chamadoAberto; }
if ($concluido !== null)     { $sets[] = 'concluido = ?';     $tipos .= 'i'; $params[] = $concluido; }

if ($caminhoAdd !== null && $caminhoAdd !== '') {
    $caminhoAtual = trim((string) ($existente['caminho'] ?? ''));
    $novoCaminho  = $caminhoAtual !== '' ? "{$caminhoAtual} → {$caminhoAdd}" : $caminhoAdd;
    $sets[] = 'caminho = ?';
    $tipos .= 's';
    $params[] = $novoCaminho;
}

if ($sets) {
    $tipos .= 'i';
    $params[] = (int) $existente['id'];
    db_execute('UPDATE faq_atendimentos SET ' . implode(', ', $sets) . ' WHERE id = ?', [$tipos, ...$params]);
}

json_response(['ok' => true]);
