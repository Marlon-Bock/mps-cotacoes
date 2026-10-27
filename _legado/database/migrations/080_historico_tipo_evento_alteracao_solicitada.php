<?php
/**
 * Migration 080 — Novo tipo de evento no histórico: ALTERACAO_SOLICITADA.
 * Usado quando a imobiliária pede pra MPS ajustar valores de uma cotação de
 * Seguro Incêndio já enviada, antes de aprovar ou recusar de vez (ver
 * cotacao-incendio-imovel-alteracao-solicitar.php). `historico.tipo_evento`
 * é um ENUM fixo, então precisa alterar a coluna pra aceitar o valor novo.
 */

try {
    $conn->query(
        "ALTER TABLE historico MODIFY COLUMN tipo_evento
         ENUM('CRIACAO','ALTERACAO_STATUS','ENVIO_COTACAO','APROVACAO_COTACAO','RECUSA_COTACAO','ALTERACAO_SOLICITADA','TRANSFERENCIA')
         NOT NULL"
    );
    echo "  + Enum tipo_evento do histórico atualizado com ALTERACAO_SOLICITADA." . PHP_EOL;
} catch (Throwable $e) {
    echo "  ! Erro ao atualizar enum tipo_evento: " . $e->getMessage() . PHP_EOL;
    throw $e;
}
