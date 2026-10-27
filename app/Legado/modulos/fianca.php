<?php
/**
 * Helpers do módulo Seguro Fiança.
 */

/**
 * Formulário da ficha conforme o canal de Fiança da imobiliária (ver
 * app/Legado/canais.php): a Segimob exige campos e listas próprias
 * (form-segimob); Manual e API das seguradoras usam o formulário padrão.
 * Pré-análise e "completar pré-análise" seguem o fluxo manual, mas a tela
 * é a mesma do canal — o envio é que decide (FiancaController::enviar).
 */
function fianca_partial_formulario(int $imobiliariaId): string
{
    return canal_da_imobiliaria($imobiliariaId, 'fianca') === CANAL_SEGIMOB && segimob_configurada()
        ? 'fianca._partials.form-segimob'
        : 'fianca._partials.form';
}
