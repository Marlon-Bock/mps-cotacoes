<?php
/**
 * AJAX endpoint — GET /api/admin/visitas/regiao-listar
 * Lista os tipos de região cadastrados (Ilha, Continente, Online, ...), pro
 * dropdown de Região no Agendar Visita e pro modal de cadastro de regiões.
 * Retorna JSON { itens: [{ id, nome, requer_link }] }
 */
$current_user = require_admin();
header('Content-Type: application/json; charset=utf-8');

$itens = [];
try {
    $itens = db_fetch_all('SELECT id, nome, requer_link FROM regioes ORDER BY nome ASC');
} catch (Throwable) {
    $itens = [];
}

echo json_encode(['itens' => $itens]);
