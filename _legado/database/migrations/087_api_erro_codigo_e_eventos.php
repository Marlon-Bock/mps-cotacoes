<?php
/**
 * Migration 087 — erro classificado + marca dos eventos da API no histórico.
 *
 * api_cotacoes.erro_codigo / api_cotacoes_tentativas.erro_codigo:
 *   o ADAPTER da seguradora classifica a falha num código interno (ver
 *   api_erro_codigos() em src/helpers/api/status.php) — tela, encaminhamento
 *   pra MPS e "Corrigir dados" usam o código, não o texto (em inglês) que a
 *   seguradora devolve.
 *
 * historico.api_evento:
 *   1 = evento da API das seguradoras (gravado por api_historico) — é o que
 *   o contador do botão "Seguradoras" nas listagens conta.
 *
 * Os registros existentes são preenchidos com as mesmas regras que o
 * sistema usava até aqui (pelo texto).
 */

$coluna = static function (string $tabela, string $coluna) use ($conn): bool {
    return (bool) $conn->query("SHOW COLUMNS FROM `{$tabela}` LIKE '{$coluna}'")->num_rows;
};

foreach (['api_cotacoes', 'api_cotacoes_tentativas'] as $tabela) {
    if (!$coluna($tabela, 'erro_codigo')) {
        $conn->query("ALTER TABLE `{$tabela}` ADD COLUMN erro_codigo VARCHAR(20) DEFAULT NULL AFTER erro");
    }
}
if (!$coluna('historico', 'api_evento')) {
    $conn->query("ALTER TABLE historico ADD COLUMN api_evento TINYINT(1) NOT NULL DEFAULT 0");
}

// ── Erros já gravados → código ──────────────────────────────────────────────
$classificar = "
    CASE
        WHEN t.erro IS NULL THEN NULL
        WHEN t.erro LIKE '%zipcode%' AND t.erro LIKE CONCAT('%', sf.cep, '%') THEN 'cep'
        WHEN t.erro LIKE '%zipcode%'                         THEN 'cadastro'
        WHEN t.erro LIKE '%restricted list%'                 THEN 'nome'
        WHEN t.erro LIKE '%Informe o nome completo%'         THEN 'sobrenome'
        WHEN t.erro LIKE '%email must be informed%'          THEN 'email'
        WHEN t.erro LIKE '%must be a valid CPF%'             THEN 'cpf'
        WHEN t.erro LIKE '%cannot exceed 2x the rent%'       THEN 'encargos'
        WHEN t.erro LIKE '%Insured Amount%must be greater%'  THEN 'aluguel'
        WHEN t.erro LIKE '%Falha ao conectar%' OR t.erro LIKE '%timed out%'
          OR t.erro REGEXP 'HTTP (5[0-9]{2}|408|429):'       THEN 'indisponivel'
        WHEN t.erro REGEXP 'HTTP 4[0-9]{2}: '
         AND t.erro NOT REGEXP 'HTTP (401|403|404):'         THEN 'dados'
        ELSE NULL
    END";
foreach (['api_cotacoes', 'api_cotacoes_tentativas'] as $tabela) {
    $conn->query("
        UPDATE `{$tabela}` t JOIN seguro_fianca sf ON sf.id = t.seguro_fianca_id
           SET t.erro_codigo = {$classificar}
         WHERE t.erro IS NOT NULL AND t.erro_codigo IS NULL
    ");
}

// ── Eventos da API já gravados no histórico ─────────────────────────────────
$conn->query("
    UPDATE historico SET api_evento = 1
     WHERE api_evento = 0
       AND (usuario_nome IN ('API das seguradoras', 'Sistema (API das seguradoras)')
            OR status_anterior = 'Cotação API'
            OR status_novo IN ('Enviada para as seguradoras', 'Dados corrigidos', 'Dados corrigidos e cotação reenviada')
            OR status_novo LIKE 'Proposta da % escolhida'
            OR status_novo LIKE 'Reanálise solicitada%')
");
