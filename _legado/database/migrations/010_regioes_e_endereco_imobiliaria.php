<?php
/**
 * Migration 010 —
 * 1) Cria a tabela `regioes` (cadastro de tipos de região pro Agendar Visita:
 *    Ilha, Continente, Online — a região "Online" exige um link de reunião).
 * 2) Adiciona `regiao_id` (FK aplicação-level pra `regioes`) e `link_reuniao`
 *    em `agendamentos_visita`, migra os dados do antigo enum `regiao` e
 *    remove essa coluna antiga.
 * 3) Adiciona colunas que o cadastro de Imobiliárias (list/criar/editar) já
 *    esperava mas nunca existiram de fato: cnpj, email, telefone, ativo,
 *    criado_em, e o endereço completo (usado pro autofill no Agendar Visita).
 * Executar via: php database/migrate_php.php
 */

// ── 1) Tabela de regiões ─────────────────────────────────────────────────────
try {
    $conn->query("
        CREATE TABLE IF NOT EXISTS regioes (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            nome VARCHAR(60) NOT NULL,
            requer_link TINYINT(1) NOT NULL DEFAULT 0,
            criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_regioes_nome (nome)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "  + Tabela regioes criada (ou já existia)." . PHP_EOL;
} catch (mysqli_sql_exception $e) {
    echo "  ~ " . $e->getMessage() . PHP_EOL;
}

$seed = [
    ['nome' => 'Ilha',       'requer_link' => 0],
    ['nome' => 'Continente', 'requer_link' => 0],
    ['nome' => 'Online',     'requer_link' => 1],
];
foreach ($seed as $r) {
    $existe = $conn->query("SELECT id FROM regioes WHERE nome = '{$r['nome']}' LIMIT 1")->fetch_assoc();
    if (!$existe) {
        $conn->query("INSERT INTO regioes (nome, requer_link) VALUES ('{$r['nome']}', {$r['requer_link']})");
        echo "  + Região '{$r['nome']}' cadastrada." . PHP_EOL;
    } else {
        echo "  ~ Região '{$r['nome']}' já existe, pulando." . PHP_EOL;
    }
}

// ── 2) agendamentos_visita: regiao_id + link_reuniao, migra o enum antigo ───
try {
    // Sem "AFTER regiao": a coluna antiga é removida por essa mesma migration
    // logo abaixo, então numa segunda execução "AFTER regiao" já não existiria
    // mais e quebraria o idempotente "Duplicate column name" abaixo.
    $conn->query("ALTER TABLE agendamentos_visita ADD COLUMN regiao_id INT UNSIGNED NULL DEFAULT NULL");
    echo "  + Coluna regiao_id adicionada em agendamentos_visita." . PHP_EOL;
} catch (mysqli_sql_exception $e) {
    if (str_contains($e->getMessage(), 'Duplicate column name')) {
        echo "  ~ Coluna regiao_id já existe em agendamentos_visita, pulando." . PHP_EOL;
    } else {
        throw $e;
    }
}

try {
    $conn->query("ALTER TABLE agendamentos_visita ADD COLUMN link_reuniao VARCHAR(500) NULL DEFAULT NULL AFTER local_visita");
    echo "  + Coluna link_reuniao adicionada em agendamentos_visita." . PHP_EOL;
} catch (mysqli_sql_exception $e) {
    if (str_contains($e->getMessage(), 'Duplicate column name')) {
        echo "  ~ Coluna link_reuniao já existe em agendamentos_visita, pulando." . PHP_EOL;
    } else {
        throw $e;
    }
}

// Migra os valores antigos do enum `regiao` ('ilha'/'continente') pra regiao_id,
// só se a coluna antiga ainda existir (idempotente — já vai ter sido removida
// nas próximas execuções).
$colunaAntiga = $conn->query("SHOW COLUMNS FROM agendamentos_visita LIKE 'regiao'")->fetch_assoc();
if ($colunaAntiga) {
    $ilha       = $conn->query("SELECT id FROM regioes WHERE nome = 'Ilha' LIMIT 1")->fetch_assoc();
    $continente = $conn->query("SELECT id FROM regioes WHERE nome = 'Continente' LIMIT 1")->fetch_assoc();

    if ($ilha) {
        $conn->query("UPDATE agendamentos_visita SET regiao_id = {$ilha['id']} WHERE regiao = 'ilha' AND regiao_id IS NULL");
    }
    if ($continente) {
        $conn->query("UPDATE agendamentos_visita SET regiao_id = {$continente['id']} WHERE regiao = 'continente' AND regiao_id IS NULL");
    }
    echo "  + Dados do enum regiao migrados para regiao_id." . PHP_EOL;

    try {
        $conn->query("ALTER TABLE agendamentos_visita DROP COLUMN regiao");
        echo "  + Coluna antiga regiao removida de agendamentos_visita." . PHP_EOL;
    } catch (mysqli_sql_exception $e) {
        echo "  ~ " . $e->getMessage() . PHP_EOL;
    }
} else {
    echo "  ~ Coluna antiga regiao já não existe mais, pulando migração de dados." . PHP_EOL;
}

// ── 3) Imobiliárias: colunas que o cadastro (list/criar/editar) já esperava ─
$colunas_imob = [
    'cnpj'         => "ALTER TABLE imobiliarias ADD COLUMN cnpj VARCHAR(20) NULL DEFAULT NULL AFTER nome",
    'email'        => "ALTER TABLE imobiliarias ADD COLUMN email VARCHAR(120) NULL DEFAULT NULL AFTER cnpj",
    'telefone'     => "ALTER TABLE imobiliarias ADD COLUMN telefone VARCHAR(20) NULL DEFAULT NULL AFTER email",
    'ativo'        => "ALTER TABLE imobiliarias ADD COLUMN ativo TINYINT(1) NOT NULL DEFAULT 1 AFTER telefone",
    'criado_em'    => "ALTER TABLE imobiliarias ADD COLUMN criado_em DATETIME NULL DEFAULT NULL AFTER created_at",
    'cep'          => "ALTER TABLE imobiliarias ADD COLUMN cep VARCHAR(9) NULL DEFAULT NULL",
    'endereco'     => "ALTER TABLE imobiliarias ADD COLUMN endereco VARCHAR(200) NULL DEFAULT NULL",
    'numero'       => "ALTER TABLE imobiliarias ADD COLUMN numero VARCHAR(20) NULL DEFAULT NULL",
    'complemento'  => "ALTER TABLE imobiliarias ADD COLUMN complemento VARCHAR(60) NULL DEFAULT NULL",
    'bairro'       => "ALTER TABLE imobiliarias ADD COLUMN bairro VARCHAR(100) NULL DEFAULT NULL",
    'cidade'       => "ALTER TABLE imobiliarias ADD COLUMN cidade VARCHAR(100) NULL DEFAULT NULL",
    'uf'           => "ALTER TABLE imobiliarias ADD COLUMN uf CHAR(2) NULL DEFAULT NULL",
];
foreach ($colunas_imob as $col => $sql) {
    try {
        $conn->query($sql);
        echo "  + Coluna {$col} adicionada em imobiliarias." . PHP_EOL;
    } catch (mysqli_sql_exception $e) {
        if (str_contains($e->getMessage(), 'Duplicate column name')) {
            echo "  ~ Coluna {$col} já existe em imobiliarias, pulando." . PHP_EOL;
        } else {
            throw $e;
        }
    }
}

// Backfill de criado_em a partir do created_at existente (só quando ainda nulo).
$conn->query("UPDATE imobiliarias SET criado_em = created_at WHERE criado_em IS NULL");
echo "  + criado_em preenchido a partir de created_at onde estava nulo." . PHP_EOL;
