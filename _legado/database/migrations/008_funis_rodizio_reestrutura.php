<?php
/**
 * Migration 008 — Reestrutura os funis de rodízio: Fiança Residencial +
 * Comercial viram um único funil "Fiança"; Título de Capitalização e Seguro
 * Condomínio ganham funil próprio (total final: Fiança, Auto, Título,
 * Condomínio). Também adiciona atendente_id em titulo_capitalizacao e
 * seguro_condominial, pra essas cotações passarem a ter atendente atribuído
 * pelo rodízio (antes só fiança e auto tinham essa coluna).
 * Executar via: php database/migrate_php.php
 */

// ── 1) Amplia o enum pra caber os tipos antigos + novos durante a migração ──
try {
    $conn->query("ALTER TABLE funis MODIFY COLUMN tipo_seguro ENUM('fianca_residencial','fianca_comercial','fianca','auto','titulo','condominial') NOT NULL");
    echo "  + Enum tipo_seguro ampliado." . PHP_EOL;
} catch (mysqli_sql_exception $e) {
    echo "  ~ " . $e->getMessage() . PHP_EOL;
}

// ── 2) Mescla Fiança Residencial + Comercial num único funil "Fiança" ───────
$residencial = $conn->query("SELECT id FROM funis WHERE tipo_seguro = 'fianca_residencial' LIMIT 1")->fetch_assoc();
$comercial   = $conn->query("SELECT id FROM funis WHERE tipo_seguro = 'fianca_comercial' LIMIT 1")->fetch_assoc();
$fianca      = $conn->query("SELECT id FROM funis WHERE tipo_seguro = 'fianca' LIMIT 1")->fetch_assoc();

if (!$fianca && $residencial) {
    $fiancaId = (int) $residencial['id'];
    $conn->query("UPDATE funis SET nome = 'Fiança', tipo_seguro = 'fianca' WHERE id = {$fiancaId}");
    echo "  + Funil Residencial (id={$fiancaId}) virou o funil único 'Fiança'." . PHP_EOL;

    if ($comercial) {
        $comercialId = (int) $comercial['id'];
        $max = $conn->query("SELECT COALESCE(MAX(ordem),0) AS m FROM funil_corretores WHERE funil_id = {$fiancaId}")->fetch_assoc();
        $proximaOrdem = (int) $max['m'] + 1;

        $rows = $conn->query("SELECT user_id FROM funil_corretores WHERE funil_id = {$comercialId} ORDER BY ordem ASC");
        while ($row = $rows->fetch_assoc()) {
            $userId = (int) $row['user_id'];
            $existe = $conn->query("SELECT id FROM funil_corretores WHERE funil_id = {$fiancaId} AND user_id = {$userId}")->fetch_assoc();
            if (!$existe) {
                $conn->query("INSERT INTO funil_corretores (funil_id, user_id, ordem, ativo) VALUES ({$fiancaId}, {$userId}, {$proximaOrdem}, 'S')");
                $proximaOrdem++;
            }
        }
        $conn->query("DELETE FROM funis WHERE id = {$comercialId}");
        echo "  + Atendentes do funil Comercial mesclados; funil Comercial (id={$comercialId}) removido." . PHP_EOL;
    }
} elseif ($fianca) {
    echo "  ~ Funil único 'Fiança' já existe, pulando merge." . PHP_EOL;
} else {
    echo "  ~ Nenhum funil de fiança encontrado pra mesclar." . PHP_EOL;
}

// ── 3) Nomeia o funil de Auto (já existia) ──────────────────────────────────
$conn->query("UPDATE funis SET nome = 'Seguro Auto' WHERE tipo_seguro = 'auto'");

// ── 4) Cria funis de Título e Condomínio, se ainda não existirem ───────────
$novos = [
    'titulo'      => 'Título de Capitalização',
    'condominial' => 'Seguro Condomínio',
];
foreach ($novos as $tipo => $nome) {
    $existe = $conn->query("SELECT id FROM funis WHERE tipo_seguro = '{$tipo}' LIMIT 1")->fetch_assoc();
    if (!$existe) {
        $conn->query("INSERT INTO funis (nome, tipo_seguro, proximo_idx, ativo) VALUES ('{$nome}', '{$tipo}', 0, 'S')");
        echo "  + Funil '{$nome}' criado." . PHP_EOL;
    } else {
        echo "  ~ Funil '{$nome}' já existe, pulando." . PHP_EOL;
    }
}

// ── 5) Restringe o enum aos 4 tipos finais, só se não sobrou nenhum funil
//      com os valores antigos (residencial/comercial separados) ───────────
$sobrou = $conn->query("SELECT COUNT(*) AS c FROM funis WHERE tipo_seguro IN ('fianca_residencial','fianca_comercial')")->fetch_assoc();
if ((int) $sobrou['c'] === 0) {
    try {
        $conn->query("ALTER TABLE funis MODIFY COLUMN tipo_seguro ENUM('fianca','auto','titulo','condominial') NOT NULL");
        echo "  + Enum tipo_seguro restrito aos 4 tipos finais." . PHP_EOL;
    } catch (mysqli_sql_exception $e) {
        echo "  ~ " . $e->getMessage() . PHP_EOL;
    }
} else {
    echo "  ~ Ainda há funis com tipo antigo — enum mantido amplo." . PHP_EOL;
}

// ── 6) Adiciona atendente_id nas tabelas que ainda não têm essa coluna, pro
//      rodízio também poder atribuir atendente em título e condomínio ─────
$colunas_novas = [
    'titulo_capitalizacao' => "ALTER TABLE titulo_capitalizacao ADD COLUMN atendente_id INT UNSIGNED DEFAULT NULL",
    'seguro_condominial'   => "ALTER TABLE seguro_condominial ADD COLUMN atendente_id INT UNSIGNED DEFAULT NULL",
];
foreach ($colunas_novas as $tabela => $sql) {
    try {
        $conn->query($sql);
        echo "  + Coluna atendente_id adicionada em {$tabela}." . PHP_EOL;
    } catch (mysqli_sql_exception $e) {
        if (str_contains($e->getMessage(), 'Duplicate column name')) {
            echo "  ~ Coluna atendente_id já existe em {$tabela}, pulando." . PHP_EOL;
        } else {
            throw $e;
        }
    }
}
