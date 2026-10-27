<?php
/**
 * Migration 036 — Ordenação manual, arquivamento e subitens (checklist) em tarefas.
 *
 * - `tarefas.ordem`: posição manual dentro do grupo (setor ou status), usada pelo
 *   drag-and-drop da lista estilo Monday. Backfill: ordem sequencial por setor,
 *   seguindo a ordem de criação atual (id ASC), pra não embaralhar nada existente.
 * - `tarefas.arquivada_em`: quando preenchida, a tarefa some da lista/contadores
 *   padrão (só aparece no filtro "Arquivadas").
 * - `tarefas_subitens`: checklist expansível direto na linha da tarefa.
 */

$alterations = [
    "ALTER TABLE tarefas ADD COLUMN ordem INT NOT NULL DEFAULT 0, ADD INDEX (ordem)",
    "ALTER TABLE tarefas ADD COLUMN arquivada_em DATETIME NULL DEFAULT NULL, ADD INDEX (arquivada_em)",
];

foreach ($alterations as $sql) {
    try {
        $conn->query($sql);
        $col = explode('ADD COLUMN ', $sql)[1];
        $col = explode(' ', $col)[0];
        echo "  + Coluna {$col} adicionada." . PHP_EOL;
    } catch (mysqli_sql_exception $e) {
        if (str_contains($e->getMessage(), 'Duplicate column name')) {
            $col = explode('ADD COLUMN ', $sql)[1];
            $col = explode(' ', $col)[0];
            echo "  ~ Coluna {$col} já existe, pulando." . PHP_EOL;
        } else {
            throw $e;
        }
    }
}

// Garante a definição/índice mesmo quando a coluna já existia de antes (ex.: uma
// tentativa anterior sem índice) — MODIFY e ADD INDEX são idempotentes aqui.
try {
    $conn->query("UPDATE tarefas SET ordem = 0 WHERE ordem IS NULL");
    $conn->query("ALTER TABLE tarefas MODIFY COLUMN ordem INT NOT NULL DEFAULT 0");
    echo "  + Coluna ordem normalizada (NOT NULL DEFAULT 0)." . PHP_EOL;
} catch (\Throwable $e) {
    echo "  ! Erro ao normalizar ordem: " . $e->getMessage() . PHP_EOL;
}

foreach (['ordem' => 'idx_tarefas_ordem', 'arquivada_em' => 'idx_tarefas_arquivada_em'] as $coluna => $indice) {
    try {
        $conn->query("ALTER TABLE tarefas ADD INDEX {$indice} ({$coluna})");
        echo "  + Índice {$indice} adicionado." . PHP_EOL;
    } catch (mysqli_sql_exception $e) {
        if (str_contains($e->getMessage(), 'Duplicate key name')) {
            echo "  ~ Índice {$indice} já existe, pulando." . PHP_EOL;
        } else {
            throw $e;
        }
    }
}

try {
    $conn->query(
        "CREATE TABLE tarefas_subitens (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            tarefa_id INT NOT NULL,
            titulo VARCHAR(255) NOT NULL,
            concluido ENUM('S','N') NOT NULL DEFAULT 'N',
            ordem INT NOT NULL DEFAULT 0,
            criado_por INT NULL,
            criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX (tarefa_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    echo "  + Tabela tarefas_subitens criada." . PHP_EOL;
} catch (mysqli_sql_exception $e) {
    if (str_contains($e->getMessage(), 'already exists')) {
        echo "  ~ Tabela tarefas_subitens já existe, pulando." . PHP_EOL;
    } else {
        throw $e;
    }
}

// Backfill de `ordem`: sequencial por setor, seguindo a ordem de criação atual.
try {
    $result = $conn->query('SELECT id, setor_id FROM tarefas ORDER BY setor_id ASC, id ASC');
    $porSetor = [];
    $contadorZerados = 0;
    while ($row = $result->fetch_assoc()) {
        $setorId = (int) ($row['setor_id'] ?? 0);
        $porSetor[$setorId] = ($porSetor[$setorId] ?? 0) + 1;
        $conn->query('UPDATE tarefas SET ordem = ' . (int) $porSetor[$setorId] . ' WHERE id = ' . (int) $row['id']);
        $contadorZerados++;
    }
    echo "  + Backfill de ordem concluído ({$contadorZerados} tarefas)." . PHP_EOL;
} catch (\Throwable $e) {
    echo "  ! Erro no backfill de ordem: " . $e->getMessage() . PHP_EOL;
}
