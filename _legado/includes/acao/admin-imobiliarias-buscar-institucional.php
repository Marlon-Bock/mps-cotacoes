<?php
/**
 * AJAX endpoint — GET /api/admin/imobiliarias/buscar-institucional?q=...
 * Busca clientes no cadastro institucional da SAMI (somente leitura), pro
 * cadastro de Imobiliárias preencher nome/sistema/endereço automaticamente
 * quando a imobiliária já é cliente Sami.
 * Retorna JSON { itens: [{ sigla, nome, fantasia, sistema, site, cep,
 * endereco, bairro, cidade, uf, fone }] }
 */
$current_user = require_admin();
header('Content-Type: application/json; charset=utf-8');

$q = trim($_GET['q'] ?? '');

json_response(['itens' => institucional_buscar_clientes($q)]);
