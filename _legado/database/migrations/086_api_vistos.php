<?php
/**
 * Migration 086 — "visto por último" da tela das seguradoras.
 *
 * Igual ao chat da cotação: o botão das seguradoras na listagem mostra
 * quantas novidades da API (resultado de cotação, proposta, apólice,
 * recusa...) aconteceram desde a última vez que o usuário abriu a tela.
 * Abrir a tela grava visto_em = agora (ver api_marcar_visto).
 *
 * papel: 'admin' (MPS) | 'imobiliaria' — mesmo esquema de
 * cotacao_mensagens_lidas.
 */

$conn->query("
    CREATE TABLE IF NOT EXISTS api_vistos (
        seguro_fianca_id INT         NOT NULL,
        usuario_id       INT         NOT NULL,
        papel            VARCHAR(20) NOT NULL,
        visto_em         DATETIME    NOT NULL,
        PRIMARY KEY (seguro_fianca_id, usuario_id, papel)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");
