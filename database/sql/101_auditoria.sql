-- Auditoria: tudo o que acontece no sistema, pra responder "o que foi, como
-- foi e quem foi". Só o suporte da Sami vê (os mesmos e-mails da senha do dia).
--   auditoria_requisicoes  cada ação de quem usa o sistema (imobiliária, MPS,
--                          embed, painel) e cada comando automático que mudou dado;
--   auditoria_alteracoes   cada INSERT/UPDATE/DELETE que mudou o banco, com o
--                          antes e o depois por campo.
-- Ver app/Legado/auditoria.php.

CREATE TABLE IF NOT EXISTS auditoria_requisicoes (
    id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    criado_em        DATETIME(3)     NOT NULL,
    canal            VARCHAR(10)     NOT NULL,            -- web | console
    ator_tipo        VARCHAR(20)     NOT NULL,            -- admin | imobiliaria | painel | embed | anonimo | sistema
    ator_id          INT             NULL,
    ator_nome        VARCHAR(160)    NULL,
    ator_email       VARCHAR(160)    NULL,
    imobiliaria_id   INT             NULL,
    via_senha_mestra TINYINT(1)      NOT NULL DEFAULT 0,  -- entrou na conta com a senha do dia
    ip               VARCHAR(45)     NULL,
    user_agent       VARCHAR(255)    NULL,
    metodo           VARCHAR(10)     NOT NULL,
    url              VARCHAR(500)    NOT NULL,
    acao             VARCHAR(160)    NULL,                -- Controller@metodo (ou o comando)
    parametros       MEDIUMTEXT      NULL,                -- JSON, com senhas/cartões mascarados
    status_http      SMALLINT        NULL,
    duracao_ms       INT             NULL,
    alteracoes       INT             NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY idx_criado (criado_em),
    KEY idx_ator (ator_tipo, ator_id),
    KEY idx_imobiliaria (imobiliaria_id),
    KEY idx_ip (ip)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS auditoria_alteracoes (
    id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    requisicao_id  BIGINT UNSIGNED NOT NULL,
    criado_em      DATETIME(3)     NOT NULL,
    tabela         VARCHAR(64)     NOT NULL,
    operacao       VARCHAR(10)     NOT NULL,              -- INSERT | UPDATE | UPSERT (insere ou atualiza) | DELETE
    registro_id    VARCHAR(64)     NULL,                  -- id do registro (quando dá pra saber)
    linhas         INT             NOT NULL DEFAULT 0,
    mudancas       MEDIUMTEXT      NULL,                  -- JSON: {id: {campo: [antes, depois]}} (UPDATE) / linhas (INSERT/DELETE)
    sql_texto      TEXT            NULL,
    valores        TEXT            NULL,                  -- JSON dos parâmetros (mascarados)
    PRIMARY KEY (id),
    KEY idx_requisicao (requisicao_id),
    KEY idx_tabela_registro (tabela, registro_id),
    KEY idx_criado (criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
