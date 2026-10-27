<?php

/*
 * Rotas do sistema. Cada módulo tem o próprio arquivo em routes/modulos/
 * (carregados abaixo). Middlewares de acesso: 'mps.auth' (logado),
 * 'mps.admin' (equipe MPS), 'mps.perm:perm_x,perm_y' (imobiliária com permissão).
 */

foreach (glob(__DIR__ . '/modulos/*.php') as $arquivo) {
    require $arquivo;
}
