<?php
/**
 * Acesso às telas/ações da Segimob: a ficha de Fiança pelo protocolo, com a
 * mesma regra do resto do sistema (cotacao_pode_acessar — admin vê tudo,
 * imobiliária só as próprias, operador comum só as dele).
 */

/**
 * Ficha de Fiança autorizada pro usuário, ou encerra a requisição (404).
 */
function segimob_ficha_autorizada(object $user, string $protocolo): array
{
    $resolvido = $protocolo !== '' ? cotacao_resolver($protocolo) : null;
    if (!$resolvido || $resolvido['tipo'] !== 'fianca' || !cotacao_pode_acessar($resolvido, $user)) {
        json_response(['success' => false, 'message' => 'Cotação não encontrada.'], 404);
    }
    return $resolvido['row'];
}
