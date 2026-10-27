<?php
/**
 * POST /api/admin/faq-fluxo/salvar
 * Salva o fluxo de blocos inteiro (substitui o anterior). Body JSON: {flow: [...]}.
 */
$current_user = require_admin();
header('Content-Type: application/json; charset=utf-8');
csrf_verify();

const FLUXO_TIPOS_VALIDOS   = ['mensagem', 'mensagem_botao', 'resposta_texto', 'opcoes', 'chamado'];
const CAPTURA_TIPOS_VALIDOS = ['texto', 'nome', 'telefone', 'imobiliaria'];

/** Valida recursivamente a árvore de blocos (tipo válido + campos coerentes). */
function fluxo_validar(array $blocos): bool
{
    foreach ($blocos as $bloco) {
        if (!is_array($bloco) || !in_array($bloco['type'] ?? '', FLUXO_TIPOS_VALIDOS, true)) {
            return false;
        }
        if ($bloco['type'] === 'resposta_texto' && isset($bloco['tipo_captura'])
            && !in_array($bloco['tipo_captura'], CAPTURA_TIPOS_VALIDOS, true)) {
            return false;
        }
        if ($bloco['type'] === 'opcoes') {
            if (!isset($bloco['opcoes']) || !is_array($bloco['opcoes'])) {
                return false;
            }
            foreach ($bloco['opcoes'] as $opcao) {
                if (!is_array($opcao) || !isset($opcao['nome'])) {
                    return false;
                }
                if (isset($opcao['minifluxo'])) {
                    if (!is_array($opcao['minifluxo']) || !fluxo_validar($opcao['minifluxo'])) {
                        return false;
                    }
                }
            }
        }
    }
    return true;
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$flow  = $input['flow'] ?? null;

if (!is_array($flow)) {
    json_response(['ok' => false, 'message' => 'Fluxo inválido.'], 422);
}
if (!fluxo_validar($flow)) {
    json_response(['ok' => false, 'message' => 'Estrutura do fluxo inválida.'], 422);
}

$json = json_encode(['flow' => $flow], JSON_UNESCAPED_UNICODE);

db_execute(
    'INSERT INTO faq_fluxo_json (id, json_data) VALUES (1, ?)
     ON DUPLICATE KEY UPDATE json_data = VALUES(json_data)',
    ['s', $json]
);

json_response(['ok' => true]);
