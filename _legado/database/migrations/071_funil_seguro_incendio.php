<?php
/**
 * Migration 071 — Funil de rodízio "Seguro Incêndio", mesmo padrão dos
 * demais produtos (Fiança, Auto, Título, Condomínio) — sem isso o funil
 * nem aparece na tela /admin/rodizio e funil_atribuir_atendente('incendio')
 * nunca atribui ninguém.
 */

try {
    $conn->query("ALTER TABLE funis MODIFY COLUMN tipo_seguro ENUM('fianca','auto','titulo','condominial','incendio') NOT NULL");
    echo "  + Enum tipo_seguro ampliado com 'incendio'." . PHP_EOL;
} catch (Throwable $e) {
    echo "  ~ " . $e->getMessage() . PHP_EOL;
}

$existe = $conn->query("SELECT id FROM funis WHERE tipo_seguro = 'incendio' LIMIT 1")->fetch_assoc();
if (!$existe) {
    $conn->query("INSERT INTO funis (nome, tipo_seguro, proximo_idx, ativo) VALUES ('Seguro Incêndio', 'incendio', 0, 'S')");
    echo "  + Funil 'Seguro Incêndio' criado." . PHP_EOL;
} else {
    echo "  ~ Funil 'Seguro Incêndio' já existia, pulando." . PHP_EOL;
}
