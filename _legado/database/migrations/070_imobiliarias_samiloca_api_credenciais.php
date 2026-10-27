<?php
/**
 * Migration 070 — Credenciais próprias da API SAMILOCA por imobiliária.
 * Cada cliente Samiloca tem seu próprio usuário/senha de API (confirmado
 * com dados reais da Bom fim imóveis: api@bomfimimoveis.com.br), não um
 * usuário compartilhado — o sistema até agora só tinha um par global via
 * .env (SAMILOCA_API_USER/SAMILOCA_API_PASS), usado como fallback quando
 * a imobiliária não tiver credencial própria cadastrada aqui.
 */

$colunas = [
    'samiloca_api_user' => "ALTER TABLE imobiliarias ADD COLUMN samiloca_api_user VARCHAR(150) DEFAULT NULL AFTER site_samiloca",
    'samiloca_api_pass' => "ALTER TABLE imobiliarias ADD COLUMN samiloca_api_pass VARCHAR(150) DEFAULT NULL AFTER samiloca_api_user",
];

foreach ($colunas as $nome => $sql) {
    try {
        $conn->query($sql);
        echo "  + Coluna {$nome} adicionada em imobiliarias." . PHP_EOL;
    } catch (Throwable $e) {
        if (str_contains($e->getMessage(), 'Duplicate column name')) {
            echo "  = Coluna {$nome} já existia em imobiliarias." . PHP_EOL;
        } else {
            throw $e;
        }
    }
}
