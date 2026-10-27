<?php
/**
 * Migration 094 — Seguro Residencial (produto novo, cotado na API das
 * seguradoras — Pottencial e Too — com o mesmo fluxo da Fiança: a
 * imobiliária envia, as seguradoras cotam em segundo plano, a MPS monta as
 * propostas, o cliente aceita e a MPS envia a apólice).
 *
 *   - seguro_residencial: segurado (PF), imóvel, coberturas (importância
 *     segurada por cobertura) e as mesmas colunas de fluxo da API que
 *     seguro_fianca tem (origem, api_*, encaminhado_mps_*, recusa_motivo,
 *     data_status_*), pra camada src/helpers/api/* tratar os dois produtos.
 *   - IDs a partir de 50.000.001: as tabelas da API (api_cotacoes, api_fila,
 *     api_propostas...) guardam o id da ficha em seguro_fianca_id — com a
 *     faixa separada o id nunca colide com o da Fiança e o produto sai do
 *     próprio id (api_ficha_produto, API_RESIDENCIAL_ID_INICIO).
 *   - cotacoes_anexos_residencial: anexos/chat (ações genéricas).
 *   - Permissões: users.perm_seguro_residencial (imobiliária),
 *     usuario.perm_seguro_residencial + recebe_email_seguro_residencial (MPS).
 *     O nome não pode ser perm_residencial: essa já é a sub-permissão de
 *     Fiança Residencial.
 *   - Rodízio: funil 'residencial'.
 * Idempotente.
 */

$tentar = static function (string $sql, string $ok) use ($conn): void {
    try {
        $conn->query($sql);
        echo "  + {$ok}" . PHP_EOL;
    } catch (Throwable $e) {
        if (preg_match('/Duplicate column name|already exists|Duplicate key name/i', $e->getMessage())) {
            echo "  ~ {$ok} (já existia)" . PHP_EOL;
        } else {
            throw $e;
        }
    }
};

$tentar(
    "CREATE TABLE IF NOT EXISTS seguro_residencial (
        id                      INT UNSIGNED NOT NULL AUTO_INCREMENT,
        protocolo               VARCHAR(30)  NOT NULL,
        imobiliaria_id          INT          NULL,
        usuario_id              INT          NULL,
        atendente_id            INT          NULL,
        status                  VARCHAR(40)  NOT NULL DEFAULT 'Cotação API',
        origem                  VARCHAR(10)  NOT NULL DEFAULT 'api',

        -- Segurado (pessoa física)
        nome_completo           VARCHAR(150) NOT NULL,
        cpf_cnpj                VARCHAR(20)  NOT NULL,
        data_nascimento         DATE         NULL,
        sexo                    VARCHAR(10)  NULL COMMENT 'masculino / feminino',
        email                   VARCHAR(150) NULL,
        tel_celular             VARCHAR(20)  NULL,
        pessoa_politicamente_exposta TINYINT(1) NOT NULL DEFAULT 0,
        segurado_proprietario   TINYINT(1)   NOT NULL DEFAULT 0 COMMENT 'o segurado é dono do imóvel',
        -- Endereço do segurado (correspondência) — igual ao do imóvel quando endereco_segurado_igual
        endereco_segurado_igual TINYINT(1)   NOT NULL DEFAULT 1,
        seg_cep                 VARCHAR(15)  NULL,
        seg_endereco            VARCHAR(255) NULL,
        seg_numero              VARCHAR(20)  NULL,
        seg_complemento         VARCHAR(100) NULL,
        seg_bairro              VARCHAR(100) NULL,
        seg_cidade              VARCHAR(100) NULL,
        seg_uf                  VARCHAR(2)   NULL,

        -- Imóvel segurado
        cep                     VARCHAR(15)  NOT NULL,
        endereco                VARCHAR(255) NOT NULL,
        numero                  VARCHAR(20)  NULL,
        complemento             VARCHAR(100) NULL,
        bairro                  VARCHAR(100) NULL,
        cidade                  VARCHAR(100) NULL,
        uf                      VARCHAR(2)   NULL,
        tipo_imovel             VARCHAR(20)  NOT NULL COMMENT 'casa / apartamento / casa_condominio / chacara',
        tipo_construcao         VARCHAR(20)  NOT NULL DEFAULT 'alvenaria' COMMENT 'alvenaria / madeira',
        uso_imovel              VARCHAR(20)  NOT NULL DEFAULT 'habitual' COMMENT 'habitual / veraneio / desocupado',

        -- Coberturas: importância segurada (R$); NULL/0 = não contratada. Incêndio é obrigatória.
        cob_incendio            DECIMAL(12,2) NOT NULL,
        cob_danos_eletricos     DECIMAL(12,2) NULL,
        cob_vendaval            DECIMAL(12,2) NULL,
        cob_roubo               DECIMAL(12,2) NULL,
        cob_vidros              DECIMAL(12,2) NULL,
        cob_vazamento           DECIMAL(12,2) NULL,
        cob_rc_familiar         DECIMAL(12,2) NULL,
        cob_perda_aluguel       DECIMAL(12,2) NULL,
        cob_impacto_veiculos    DECIMAL(12,2) NULL,

        vigencia_inicio         DATE         NULL,
        vigencia_meses          TINYINT UNSIGNED NOT NULL DEFAULT 12,
        observacoes             TEXT         NULL,

        -- Fluxo da API (mesmas colunas de seguro_fianca)
        api_enviado_em          DATETIME     NULL,
        api_analise_concluida_em DATETIME    NULL,
        api_proposta_enviada_em DATETIME     NULL,
        api_apolice_enviada_em  DATETIME     NULL,
        encaminhado_mps_em      DATETIME     NULL,
        encaminhado_mps_motivo  VARCHAR(40)  NULL,
        encaminhado_mps_detalhe VARCHAR(255) NULL,
        recusa_motivo           VARCHAR(255) NULL,

        data_cadastro           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        data_atualizacao        DATETIME     NULL,
        data_status_aguardando  DATETIME     NULL,
        data_status_em_analise  DATETIME     NULL,
        data_status_aprovado    DATETIME     NULL,
        data_status_recusado    DATETIME     NULL,
        data_status_concluido   DATETIME     NULL,

        PRIMARY KEY (id),
        UNIQUE KEY uk_seguro_residencial_protocolo (protocolo),
        KEY idx_seguro_residencial_imobiliaria (imobiliaria_id),
        KEY idx_seguro_residencial_status (status),
        KEY idx_seguro_residencial_cadastro (data_cadastro)
    ) ENGINE=InnoDB AUTO_INCREMENT=50000001 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    'Tabela seguro_residencial'
);

// A faixa de ids é o que separa os produtos na API — garante mesmo se a
// tabela já existia (o MySQL ignora AUTO_INCREMENT menor que o MAX(id)+1).
$conn->query('ALTER TABLE seguro_residencial AUTO_INCREMENT = 50000001');

$tentar(
    "CREATE TABLE IF NOT EXISTS cotacoes_anexos_residencial (
        id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
        protocolo        VARCHAR(30)  NOT NULL,
        nome_arquivo     VARCHAR(255) NOT NULL,
        caminho_arquivo  VARCHAR(255) NOT NULL,
        tipo_arquivo     VARCHAR(60)  NULL,
        data_envio       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        usuario_envio    VARCHAR(100) NULL,
        status_aprovacao VARCHAR(30)  NOT NULL DEFAULT 'Aguardando',
        PRIMARY KEY (id),
        KEY idx_protocolo (protocolo),
        CONSTRAINT cotacoes_anexos_residencial_fk_protocolo FOREIGN KEY (protocolo)
            REFERENCES seguro_residencial (protocolo) ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    'Tabela cotacoes_anexos_residencial'
);

$tentar("ALTER TABLE users ADD COLUMN perm_seguro_residencial ENUM('S','N') NOT NULL DEFAULT 'N'", 'users.perm_seguro_residencial');
$tentar("ALTER TABLE usuario ADD COLUMN perm_seguro_residencial ENUM('S','N') NOT NULL DEFAULT 'N'", 'usuario.perm_seguro_residencial');
$tentar("ALTER TABLE usuario ADD COLUMN recebe_email_seguro_residencial ENUM('S','N') NOT NULL DEFAULT 'N'", 'usuario.recebe_email_seguro_residencial');

$conn->query("ALTER TABLE funis MODIFY COLUMN tipo_seguro ENUM('fianca','auto','titulo','condominial','incendio','residencial') NOT NULL");
echo "  + Enum funis.tipo_seguro com 'residencial'." . PHP_EOL;
if (!$conn->query("SELECT id FROM funis WHERE tipo_seguro = 'residencial' LIMIT 1")->fetch_assoc()) {
    $conn->query("INSERT INTO funis (nome, tipo_seguro, proximo_idx, ativo) VALUES ('Seguro Residencial', 'residencial', 0, 'S')");
    echo "  + Funil 'Seguro Residencial' criado." . PHP_EOL;
}
