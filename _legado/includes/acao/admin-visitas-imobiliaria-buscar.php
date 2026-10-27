<?php
/**
 * AJAX endpoint — GET /api/admin/visitas/imobiliaria-buscar?q=...
 * Autocomplete de imobiliária por nome, usado no Relatório de Visitas,
 * no Agendar Visita e no painel de Comissões do Histórico.
 * Inclui o endereço salvo (se houver) pro Agendar Visita preencher
 * automaticamente CEP/rua/número/bairro/cidade/UF ao selecionar uma opção.
 * Retorna JSON { itens: [{ id, nome, cep, endereco, numero, complemento, bairro, cidade, uf }] }
 */
$current_user = require_admin();
header('Content-Type: application/json; charset=utf-8');

$q = trim($_GET['q'] ?? '');
$itens = [];

if (mb_strlen($q) >= 2) {
    try {
        $itens = db_fetch_all(
            "SELECT id, nome, cep, endereco, numero, complemento, bairro, cidade, uf
             FROM imobiliarias WHERE nome LIKE ? ORDER BY nome ASC LIMIT 10",
            ['s', '%' . $q . '%']
        );
    } catch (Throwable) {
        $itens = [];
    }
}

echo json_encode(['itens' => $itens]);
