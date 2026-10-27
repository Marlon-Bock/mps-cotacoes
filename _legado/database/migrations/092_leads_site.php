<?php
/**
 * Migration 092 — Tabela leads_site: recebe os formulários de cotação do site
 * institucional (mesquita-next, páginas /<seguro>/cotar). Cada envio é um JSON
 * com formato próprio por seguro, guardado inteiro em `dados`; os campos de
 * contato e o seguro ficam também em colunas para listar e filtrar.
 * Todos os seguros do site entram aqui como LEAD (não viram cotação); o
 * protocolo é simples, LD-00001, gerado pelo site a partir do id.
 * Idempotente (CREATE TABLE IF NOT EXISTS + protocolo aceitando NULL).
 */

$conn->query(
    "CREATE TABLE IF NOT EXISTS leads_site (
        id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        protocolo     VARCHAR(30)  NULL COMMENT 'LD-00001... (gerado do id pelo site)',
        seguro        VARCHAR(80)  NOT NULL COMMENT 'slug do seguro, ex.: seguro-fianca-locaticia',
        origem        VARCHAR(30)  NOT NULL DEFAULT 'site',
        perfil        VARCHAR(30)  NULL COMMENT 'inquilino / imobiliaria / proprietario...',
        nome          VARCHAR(150) NULL,
        email         VARCHAR(150) NULL,
        telefone      VARCHAR(30)  NULL,
        dados         JSON         NOT NULL COMMENT 'JSON completo enviado pelo formulário',
        status        ENUM('novo','em_atendimento','cotado','convertido','descartado') NOT NULL DEFAULT 'novo',
        pagina_url    VARCHAR(255) NULL,
        ip            VARCHAR(45)  NULL,
        user_agent    VARCHAR(255) NULL,
        criado_em     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        atualizado_em DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uk_leads_site_protocolo (protocolo),
        KEY idx_leads_site_seguro (seguro),
        KEY idx_leads_site_status (status),
        KEY idx_leads_site_criado (criado_em)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
);

$conn->query("ALTER TABLE leads_site MODIFY protocolo VARCHAR(30) NULL");
echo "  + Tabela leads_site pronta." . PHP_EOL;
