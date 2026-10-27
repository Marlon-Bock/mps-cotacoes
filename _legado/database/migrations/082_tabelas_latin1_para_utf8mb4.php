<?php
/**
 * Migration 082 — Converte para utf8mb4 as tabelas que ficaram em latin1
 * (o resto do banco já é utf8mb4). Com STRICT_TRANS_TABLES, qualquer emoji
 * gravado nelas dava "Incorrect string value":
 *   - seguro_fianca_rascunho → salvar rascunho de Fiança dava "Erro interno";
 *   - historico              → motivo de recusa/desistência com emoji sumia
 *                              do histórico (o erro é engolido em historico_registrar);
 *   - campanhas              → título/observação com emoji não salvava;
 *   - sistema_erros / zapi_notificacoes → logs com emoji perdidos.
 * Idempotente: só converte tabela que ainda não está em utf8mb4.
 */

$tabelas = [
    'historico',
    'seguro_fianca_rascunho',
    'campanhas',
    'campanha_imobiliarias',
    'sistema_erros',
    'zapi_notificacoes',
    'agendamentos_visita_corretores',
    'relatorio_visitas_itens_corretores',
];

foreach ($tabelas as $tabela) {
    $info = $conn->query(
        "SELECT CCSA.character_set_name AS charset
           FROM information_schema.tables T
           JOIN information_schema.collation_character_set_applicability CCSA
             ON CCSA.collation_name = T.table_collation
          WHERE T.table_schema = DATABASE() AND T.table_name = '" . $conn->real_escape_string($tabela) . "'"
    )->fetch_assoc();

    if (!$info) {
        echo "  = Tabela {$tabela} não existe neste banco — ignorada." . PHP_EOL;
        continue;
    }
    if ($info['charset'] === 'utf8mb4') {
        echo "  = Tabela {$tabela} já está em utf8mb4." . PHP_EOL;
        continue;
    }

    $conn->query("ALTER TABLE `{$tabela}` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "  + Tabela {$tabela} convertida de {$info['charset']} para utf8mb4." . PHP_EOL;
}
