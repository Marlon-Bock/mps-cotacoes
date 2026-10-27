<?php
/**
 * POST /api/cotacao/rascunho/salvar
 * Salva (insere ou atualiza) um rascunho de cotação — fiança, auto, título ou condominial.
 * Aceita FormData com: tipo, nome_rascunho, rascunho (protocolo, opcional) + campos do produto.
 */
$current_user = require_auth();
header('Content-Type: application/json; charset=utf-8');

if (($current_user->role ?? '') !== 'imobiliaria') {
    echo json_encode(['ok' => false, 'message' => 'Apenas imobiliárias podem salvar rascunhos.']);
    exit;
}

csrf_verify();

$tipo = trim($_POST['tipo'] ?? '');
$cfg  = rascunho_config($tipo);
if (!$cfg) {
    echo json_encode(['ok' => false, 'message' => 'Tipo de cotação inválido.']);
    exit;
}

$nome_rascunho = trim($_POST['nome_rascunho'] ?? '');
if ($nome_rascunho === '') {
    $nome_rascunho = 'Rascunho sem nome';
}

$rascunho_protocolo = trim($_POST['rascunho'] ?? '');

// Se veio de uma cotação em pré-análise (fluxo "completar cotação"), vincula o
// protocolo real ao rascunho e tira a cotação original de "Aguardando Liberação".
$cotacao_protocolo = trim($_POST['cotacao'] ?? '');
$protocolo_origem  = null;
if ($cotacao_protocolo !== '' && $tipo === 'fianca') {
    $cotacao_origem = db_fetch_one(
        'SELECT protocolo FROM seguro_fianca WHERE protocolo = ? AND imobiliaria_id = ?',
        ['si', $cotacao_protocolo, $current_user->imobiliaria_id]
    );
    if ($cotacao_origem) {
        $protocolo_origem = $cotacao_origem['protocolo'];
    }
}

/** Converte valor mascarado (ex: "R$ 3.333,33") para decimal puro (ex: "3333.33"). */
function money_from_br(string $v): ?string
{
    $digitos = preg_replace('/\D/', '', $v);
    if ($digitos === '') return null;
    // Sem depender da extensão bcmath (pode não estar disponível no servidor):
    // os dígitos já representam centavos, então dá pra formatar direto sem
    // conversão para float (evita qualquer imprecisão para valores grandes).
    $digitos = ltrim($digitos, '0');
    if ($digitos === '') $digitos = '0';
    $digitos = str_pad($digitos, 3, '0', STR_PAD_LEFT);
    $centavos = substr($digitos, -2);
    $inteiros = substr($digitos, 0, -2);
    return $inteiros . '.' . $centavos;
}

// Monta apenas os campos da whitelist presentes no POST
$campos_data    = $cfg['campos_data'] ?? [];
$campos_decimal = $cfg['campos_decimal'] ?? [];
$colunas = [];
$valores = [];
foreach ($cfg['campos'] as $campo) {
    if (array_key_exists($campo, $_POST)) {
        $v = trim((string) $_POST[$campo]);
        if (in_array($campo, $campos_data, true) && $v !== '') {
            // Campo é DATE no banco — o form envia DD/MM/AAAA (mascarado).
            $v = date_from_br($v);
        } elseif (in_array($campo, $campos_decimal, true) && $v !== '') {
            // Campo é DECIMAL no banco — o form envia mascarado (ex: "R$ 3.333,33").
            $v = money_from_br($v);
        }
        $colunas[]  = $campo;
        $valores[]  = ($v === '' || $v === null) ? null : $v;
    }
}

if ($rascunho_protocolo !== '') {
    // Atualização de um rascunho existente — verifica posse.
    $existente = db_fetch_one(
        "SELECT id, protocolo FROM {$cfg['tabela']} WHERE protocolo = ? AND imobiliaria_id = ?",
        ['si', $rascunho_protocolo, $current_user->imobiliaria_id]
    );
    if (!$existente) {
        echo json_encode(['ok' => false, 'message' => 'Rascunho não encontrado.']);
        exit;
    }

    // Se este rascunho ainda não tinha um protocolo vinculado a uma cotação real
    // e agora veio um (fluxo completar cotação), grava o vínculo.
    if ($protocolo_origem) {
        $colunas[] = 'protocolo';
        $valores[] = $protocolo_origem;
    }

    $sets   = array_map(fn($c) => "`{$c}` = ?", $colunas);
    $sets[] = '`nome_rascunho` = ?';
    $types  = str_repeat('s', count($colunas)) . 's';
    $binds  = [...$valores, $nome_rascunho];

    db_execute(
        "UPDATE {$cfg['tabela']} SET " . implode(', ', $sets) . " WHERE id = ?",
        [$types . 'i', ...$binds, $existente['id']]
    );

    $protocolo_final = $protocolo_origem ?: $existente['protocolo'];
} else {
    // Inserção nova — protocolo é o da cotação de origem (se vier de "completar
    // cotação") ou um novo, gerado só para este rascunho.
    $protocolo_final = $protocolo_origem
        ?: gerar_protocolo($cfg['prefixo_protocolo'], $cfg['tabela']);

    $colunas[] = 'protocolo';
    $valores[] = $protocolo_final;
    $colunas[] = 'nome_rascunho';
    $valores[] = $nome_rascunho;
    $colunas[] = 'usuario_id';
    $valores[] = $current_user->uid;
    $colunas[] = 'imobiliaria_id';
    $valores[] = $current_user->imobiliaria_id;

    $placeholders = implode(', ', array_fill(0, count($colunas), '?'));
    $colunasSql   = implode(', ', array_map(fn($c) => "`{$c}`", $colunas));
    // colunas = [...campos(s), protocolo(s), nome_rascunho(s), usuario_id(i), imobiliaria_id(i)]
    $types        = str_repeat('s', count($colunas) - 2) . 'ii';

    db_execute(
        "INSERT INTO {$cfg['tabela']} ({$colunasSql}) VALUES ({$placeholders})",
        [$types, ...$valores]
    );
}

// Tira a cotação original de "Aguardando Liberação" — ela agora vive como rascunho.
if ($protocolo_origem) {
    db_execute(
        "UPDATE seguro_fianca SET status = 'Rascunho' WHERE protocolo = ? AND imobiliaria_id = ?",
        ['si', $protocolo_origem, $current_user->imobiliaria_id]
    );
}

echo json_encode(['ok' => true, 'protocolo' => $protocolo_final]);
