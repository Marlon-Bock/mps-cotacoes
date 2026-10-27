<?php
/**
 * Migration 085 — API das seguradoras (Fiança).
 *
 * A Fiança passa a ter uma ficha só: ela é cotada direto na API das
 * seguradoras integradas (hoje a Pottencial; outras entram como novos
 * adapters em src/helpers/api/seguradoras/). A MPS só entra quando a
 * cotação é recusada em todas ou a imobiliária pede pra cotar em outras
 * seguradoras (ver src/helpers/api/encaminhamento.php).
 *
 * Substitui as migrations da integração antiga (Segimob + "canal direto"),
 * removidas do projeto — nunca rodaram em produção.
 *
 * Tabelas:
 *   api_cotacoes            — a cotação de cada seguradora pra uma ficha
 *                             (1 linha por ficha + seguradora).
 *   api_cotacoes_tentativas — histórico de tentativas (cotação inicial,
 *                             reanálises com solidário).
 *   api_fila                — trabalhos que chamam a seguradora (lentos),
 *                             processados pelo worker cron/api-fila.php.
 *
 * seguro_fianca ganha:
 *   origem                  — 'manual' (fichas antigas, cotadas pela MPS) |
 *                             'api' (cotadas na API das seguradoras).
 *   api_enviado_em          — quando a ficha foi pra API.
 *   encaminhado_mps_*       — motivo/texto/data em que a ficha API entrou
 *                             na fila da MPS ('Cotação API' → 'Aguardando análise').
 *
 * Status (api_cotacoes.status) é um código interno, igual pra todas as
 * seguradoras — cada adapter traduz o status da sua API pra ele:
 *   em_analise | aguardando_biometria | biometria_concluida | aprovada |
 *   recusada | biometria_recusada | proposta_escolhida | cancelada |
 *   expirada | erro
 * apolice_status: em_emissao | emitida | cancelada | expirada.
 */

$conn->query("
    CREATE TABLE IF NOT EXISTS api_cotacoes (
        id                   INT AUTO_INCREMENT PRIMARY KEY,
        seguro_fianca_id     INT           NOT NULL,
        seguradora           VARCHAR(30)   NOT NULL,
        status               VARCHAR(30)   NOT NULL DEFAULT 'erro',
        status_seguradora    VARCHAR(40)   DEFAULT NULL,
        erro                 TEXT          DEFAULT NULL,
        cotacao_externa_id   VARCHAR(80)   DEFAULT NULL,
        numero_cotacao       VARCHAR(30)   DEFAULT NULL,
        premio_total         DECIMAL(10,2) DEFAULT NULL,
        premio_liquido       DECIMAL(10,2) DEFAULT NULL,
        iof                  DECIMAL(10,2) DEFAULT NULL,
        forma_pagamento      VARCHAR(30)   DEFAULT NULL,
        parcelas             INT           DEFAULT NULL,
        cobertura            DECIMAL(12,2) DEFAULT NULL,
        plano                VARCHAR(120)  DEFAULT NULL,
        vigencia_inicio      DATE          DEFAULT NULL,
        vigencia_fim         DATE          DEFAULT NULL,
        solidarios_json      TEXT          DEFAULT NULL,
        detalhes_json        TEXT          DEFAULT NULL,
        proposta_id          VARCHAR(80)   DEFAULT NULL,
        proposta_status      VARCHAR(40)   DEFAULT NULL,
        apolice_id           VARCHAR(80)   DEFAULT NULL,
        apolice_numero       VARCHAR(60)   DEFAULT NULL,
        apolice_status       VARCHAR(20)   DEFAULT NULL,
        respondido_em        DATETIME      DEFAULT NULL,
        criado_em            DATETIME      NOT NULL,
        atualizado_em        DATETIME      DEFAULT NULL,
        UNIQUE KEY uq_ficha_seguradora (seguro_fianca_id, seguradora),
        KEY idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");
echo "  + tabela api_cotacoes" . PHP_EOL;

$conn->query("
    CREATE TABLE IF NOT EXISTS api_cotacoes_tentativas (
        id                INT AUTO_INCREMENT PRIMARY KEY,
        seguro_fianca_id  INT           NOT NULL,
        seguradora        VARCHAR(30)   NOT NULL,
        tentativa         INT           NOT NULL,
        pretendentes      VARCHAR(255)  DEFAULT NULL,
        status            VARCHAR(30)   NOT NULL,
        premio_total      DECIMAL(10,2) DEFAULT NULL,
        erro              TEXT          DEFAULT NULL,
        criado_em         DATETIME      NOT NULL,
        KEY idx_ficha_seguradora (seguro_fianca_id, seguradora)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");
echo "  + tabela api_cotacoes_tentativas" . PHP_EOL;

$conn->query("
    CREATE TABLE IF NOT EXISTS api_fila (
        id                INT AUTO_INCREMENT PRIMARY KEY,
        tipo              VARCHAR(20)   NOT NULL,
        seguro_fianca_id  INT           NOT NULL,
        seguradora        VARCHAR(30)   DEFAULT NULL,
        payload           MEDIUMTEXT    NOT NULL,
        status            VARCHAR(15)   NOT NULL DEFAULT 'pendente',
        token             VARCHAR(40)   DEFAULT NULL,
        tentativas        INT           NOT NULL DEFAULT 0,
        erro              TEXT          DEFAULT NULL,
        criado_em         DATETIME      NOT NULL,
        iniciado_em       DATETIME      DEFAULT NULL,
        concluido_em      DATETIME      DEFAULT NULL,
        KEY idx_status_id (status, id),
        KEY idx_ficha (seguro_fianca_id, tipo)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");
echo "  + tabela api_fila" . PHP_EOL;

$colunas = [
    "ADD COLUMN origem VARCHAR(10) NOT NULL DEFAULT 'manual'",
    "ADD COLUMN api_enviado_em DATETIME DEFAULT NULL",
    "ADD COLUMN encaminhado_mps_motivo VARCHAR(30) DEFAULT NULL",
    "ADD COLUMN encaminhado_mps_detalhe VARCHAR(255) DEFAULT NULL",
    "ADD COLUMN encaminhado_mps_em DATETIME DEFAULT NULL",
];
foreach ($colunas as $sql) {
    try {
        $conn->query("ALTER TABLE seguro_fianca {$sql}");
        echo "  + seguro_fianca {$sql}" . PHP_EOL;
    } catch (mysqli_sql_exception $e) {
        if (!str_contains($e->getMessage(), 'Duplicate column name')) {
            throw $e;
        }
        echo "  ~ já existe: {$sql}" . PHP_EOL;
    }
}
