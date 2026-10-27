-- Dois fatores no login (todo login, depois da senha e do Turnstile):
--   imobiliária → código por e-mail;
--   equipe MPS  → escolhe Google Authenticator (TOTP) ou e-mail.
-- No primeiro login a pessoa configura: informa o e-mail que vai receber os
-- códigos (mesmo que já tenha um cadastrado) ou lê o QR code no Authenticator.
-- Ver app/Legado/dois_fatores.php.

-- Configuração de cada conta (tabela_origem: 'usuario' = MPS, 'users' = imobiliária).
CREATE TABLE IF NOT EXISTS dois_fatores (
    tabela_origem     VARCHAR(10)  NOT NULL,
    usuario_id        INT          NOT NULL,
    metodo            VARCHAR(10)  NOT NULL,              -- email | totp
    email             VARCHAR(160) NULL,                  -- onde chegam os códigos (método email)
    totp_segredo      TEXT         NULL,                  -- segredo do Authenticator (criptografado com a APP_KEY)
    totp_ultimo_passo BIGINT       NULL,                  -- último código usado (não aceita o mesmo duas vezes)
    configurado_em    DATETIME     NOT NULL,
    atualizado_em     DATETIME     NOT NULL,
    PRIMARY KEY (tabela_origem, usuario_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Códigos enviados por e-mail (guardados só como hash).
CREATE TABLE IF NOT EXISTS dois_fatores_codigos (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tabela_origem VARCHAR(10)  NOT NULL,
    usuario_id    INT          NOT NULL,
    email         VARCHAR(160) NOT NULL,
    codigo_hash   CHAR(64)     NOT NULL,
    tentativas    INT          NOT NULL DEFAULT 0,
    expira_em     DATETIME     NOT NULL,
    usado_em      DATETIME     NULL,
    ip            VARCHAR(45)  NULL,
    criado_em     DATETIME     NOT NULL,
    PRIMARY KEY (id),
    KEY idx_conta (tabela_origem, usuario_id, criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
