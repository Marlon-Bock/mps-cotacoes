<?php
/**
 * Migration 089 — fluxo da API das seguradoras com a MPS no meio (mesmo
 * fluxo do dev-cotacoes-3 com a Segimob).
 *
 * A imobiliária envia a ficha e só acompanha a pré-análise (Em análise /
 * Pré-aprovado / Recusado). Quando as seguradoras terminam de analisar, a
 * MPS faz a cotação: escolhe quais seguradoras vão pro cliente e envia as
 * propostas. O cliente aceita a melhor (dados do proprietário → emissão),
 * a ficha fica 'Aprovado', e a MPS envia a apólice quando sair → 'Concluída'.
 *
 *   api_propostas                            propostas enviadas ao cliente (1 linha por seguradora por envio)
 *   seguro_fianca.api_analise_concluida_em   quando a análise terminou (avisa a MPS uma vez só)
 *   seguro_fianca.api_apolice_enviada_em     apólice liberada pra imobiliária
 *   historico.interno                        evento só da MPS (resultado de cada seguradora...)
 */

$conn->query(
    "CREATE TABLE IF NOT EXISTS api_propostas (
        id                INT AUTO_INCREMENT PRIMARY KEY,
        seguro_fianca_id  INT NOT NULL,
        seguradora        VARCHAR(40) NOT NULL,
        biometria         TINYINT(1) NOT NULL DEFAULT 0,
        premio_total      DECIMAL(12,2) DEFAULT NULL,
        status            VARCHAR(20) NOT NULL DEFAULT 'Aguardando',
        enviado_em        DATETIME NOT NULL,
        enviado_por       INT DEFAULT NULL,
        enviado_por_nome  VARCHAR(120) DEFAULT NULL,
        decidido_em       DATETIME DEFAULT NULL,
        KEY idx_ficha_status (seguro_fianca_id, status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);

foreach (['api_analise_concluida_em', 'api_apolice_enviada_em'] as $coluna) {
    if (!$conn->query("SHOW COLUMNS FROM seguro_fianca LIKE '{$coluna}'")->num_rows) {
        $conn->query("ALTER TABLE seguro_fianca ADD COLUMN {$coluna} DATETIME DEFAULT NULL");
    }
}

if (!$conn->query("SHOW COLUMNS FROM historico LIKE 'interno'")->num_rows) {
    $conn->query('ALTER TABLE historico ADD COLUMN interno TINYINT(1) NOT NULL DEFAULT 0');
}

// Eventos já gravados com o resultado de cada seguradora passam a ser internos.
$conn->query(
    "UPDATE historico SET interno = 1
     WHERE api_evento = 1 AND status_novo REGEXP ' (na|pela|da) (Pottencial|Too Seguros)'"
);
