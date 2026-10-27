<?php
/**
 * Migration 091 — dados do Financeiro (/admin/financeiro) na API das seguradoras.
 *
 *   api_cotacoes.comissao_percentual / comissao_valor — comissão da corretora:
 *       Pottencial informa na cotação (commissionedAgents, papel Broker);
 *       Too: o percentual que enviamos no cálculo (TOO_COMISSAO) sobre o prêmio líquido.
 *   api_cotacoes.contratado_em — quando a emissão foi feita (a apólice nasceu).
 *
 * Contratações já feitas: a data vem do trabalho de emissão concluído na fila
 * (ou, sem ele, da última atualização da cotação). A comissão das antigas é
 * preenchida pelo script database/api_financeiro_preencher.php (consulta a
 * seguradora).
 */

foreach ([
    'comissao_percentual' => 'DECIMAL(6,2) DEFAULT NULL',
    'comissao_valor'      => 'DECIMAL(12,2) DEFAULT NULL',
    'contratado_em'       => 'DATETIME DEFAULT NULL',
] as $coluna => $tipo) {
    if (!$conn->query("SHOW COLUMNS FROM api_cotacoes LIKE '{$coluna}'")->num_rows) {
        $conn->query("ALTER TABLE api_cotacoes ADD COLUMN {$coluna} {$tipo}");
    }
}

$conn->query(
    "UPDATE api_cotacoes c
     SET c.contratado_em = COALESCE(
         (SELECT MAX(q.concluido_em) FROM api_fila q
          WHERE q.seguro_fianca_id = c.seguro_fianca_id AND q.seguradora = c.seguradora AND q.tipo = 'emissao' AND q.status = 'concluido'),
         c.atualizado_em)
     WHERE c.apolice_id IS NOT NULL AND c.contratado_em IS NULL"
);
