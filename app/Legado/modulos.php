<?php
/**
 * Helpers por módulo (app/Legado/modulos/*.php) — carregados todos aqui, pra
 * cada módulo poder ter os seus sem mexer no composer.json.
 */
foreach (glob(__DIR__ . '/modulos/*.php') ?: [] as $__arquivo_modulo) {
    require_once $__arquivo_modulo;
}
unset($__arquivo_modulo);
