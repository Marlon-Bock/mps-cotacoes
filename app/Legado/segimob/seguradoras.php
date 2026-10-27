<?php
/**
 * Seguradoras da Segimob: a Segimob manda o nome em texto livre (não um
 * código fixo nosso).
 */

/** Nome pra tela: a Segimob manda em caixa alta ("PORTO SEGURO" → "Porto Seguro"). */
function segimob_seguradora_nome(string $nome): string
{
    return mb_convert_case(mb_strtolower(trim($nome)), MB_CASE_TITLE);
}
