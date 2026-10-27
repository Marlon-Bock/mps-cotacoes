<?php
/**
 * Migration 039 — Cria a tabela de corretores adicionais por item de
 * relatório de visita. `relatorio_visitas_itens` (via `relatorios_visitas.
 * usuario_id`) continua sendo o corretor principal/quem registrou a
 * visita; esta tabela guarda apenas colegas que foram junto — sem qualquer
 * sincronização com Google Calendar (relatório não é evento de agenda).
 */

$sql = "CREATE TABLE relatorio_visitas_itens_corretores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT UNSIGNED NOT NULL,
    usuario_id INT NOT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_item_usuario (item_id, usuario_id),
    KEY idx_usuario (usuario_id),
    CONSTRAINT fk_rvic_item FOREIGN KEY (item_id)
        REFERENCES relatorio_visitas_itens (id) ON DELETE CASCADE
)";

try {
    $conn->query($sql);
    echo "  + Tabela relatorio_visitas_itens_corretores criada." . PHP_EOL;
} catch (mysqli_sql_exception $e) {
    if (str_contains($e->getMessage(), 'already exists')) {
        echo "  ~ Tabela relatorio_visitas_itens_corretores já existe, pulando." . PHP_EOL;
    } else {
        throw $e;
    }
}
