<?php
/**
 * Itens do menu lateral (eram pages/partials/nav-admin.php e nav-imobiliaria.php).
 * Cada grupo: ['titulo' => ?string, 'itens' => [...]]; item = link
 * ['label','url','icone'], submenu ['label','icone','submenu' => [...]] ou
 * em breve ['label','icone','disabled' => true]. 'icone' = public/assets/icons/.
 */

/** Menu da equipe MPS. */
function nav_admin_grupos(object $current_user): array
{
    $grupos = nav_filtrar_mps_admin([
        [
            'titulo' => null,
            'itens' => [
                ['label' => 'Dashboard', 'url' => '/admin', 'icone' => 'gauge'],
            ],
        ],
        [
            'titulo' => 'Cotações',
            'itens' => [
                ['label' => 'Todas as Cotações',  'url' => '/admin/cotacoes', 'icone' => 'clipboard-text'],
                ['label' => 'Pipeline de Cotações', 'url' => '/admin/pipeline', 'icone' => 'chart-bar'],
                ['label' => 'Funis de Rodízio',    'url' => '/admin/rodizio',  'icone' => 'arrows-clockwise'],
            ],
        ],
        [
            'titulo' => 'Tarefas',
            'itens' => [
                ['label' => 'Gerenciador de Tarefas', 'url' => '/admin/tarefas', 'icone' => 'list-checks'],
            ],
        ],
        [
            'titulo' => 'Comercial',
            'itens' => [
                ['label' => 'Leads', 'url' => '/admin/leads', 'icone' => 'monitor'],
                [
                    'label' => 'Visitas',
                    'icone' => 'calendar-blank',
                    'submenu' => [
                        ['label' => 'Relatório de Visitas',     'url' => '/admin/visitas/relatorio'],
                        ['label' => 'Histórico de Visitas',     'url' => '/admin/visitas'],
                        ['label' => 'Agendar Visita',           'url' => '/admin/visitas/agendar'],
                    ],
                ],
            ],
        ],
        [
            'titulo' => 'Gestão',
            'itens' => [
                [
                    'label' => 'Imobiliárias',
                    'icone' => 'buildings',
                    'submenu' => [
                        ['label' => 'Gerenciar Imobiliárias', 'url' => '/admin/imobiliarias'],
                        ['label' => 'Criar Imobiliária',       'url' => '/admin/imobiliarias/criar'],
                    ],
                ],
                ['label' => 'Central de Gestão', 'url' => '/admin/gerenciador', 'icone' => 'trend-up'],
            ],
        ],
        [
            'titulo' => 'Campanhas',
            'itens' => [
                ['label' => 'Gerenciar Campanhas',  'url' => '/admin/campanhas',       'icone' => 'megaphone'],
                ['label' => 'Criar Nova Campanha',  'url' => '/admin/campanhas/criar', 'icone' => 'megaphone'],
            ],
        ],
        [
            'titulo' => 'Suporte e Dúvidas',
            'itens' => [
                ['label' => 'Chamados',       'url' => '/admin/chamados', 'icone' => 'ticket'],
                ['label' => 'Gerenciar FAQ',  'url' => '/admin/faq',      'icone' => 'question'],
                ['label' => 'Fluxo do FAQ',   'url' => '/admin/faq/fluxo', 'icone' => 'chat-circle'],
            ],
        ],
        [
            'titulo' => 'Configurações',
            'itens' => [
                [
                    'label' => 'Administrativo',
                    'icone' => 'users',
                    'submenu' => [
                        ['label' => 'Gerenciar Usuários',       'url' => '/admin/usuarios', 'so_mps_admin' => true],
                        ['label' => 'Criar Usuário',            'url' => '/admin/usuarios/criar', 'so_mps_admin' => true],
                        ['label' => 'Horário de Atendimento',   'url' => '/admin/horarios'],
                    ],
                ],
                ['label' => 'Meu Perfil', 'url' => '/admin/perfil', 'icone' => 'user'],
                ['label' => 'Auditoria',  'url' => '/admin/auditoria', 'icone' => 'list-checks', 'so_suporte_sami' => true],
            ],
        ],
    ], !empty($current_user->mps_admin));

    // Auditoria: só o suporte da Sami (os e-mails da senha do dia).
    if (!usuario_suporte_sami($current_user)) {
        foreach ($grupos as &$grupo) {
            $grupo['itens'] = array_values(array_filter($grupo['itens'], fn($item) => empty($item['so_suporte_sami'])));
        }
        unset($grupo);
    }
    return $grupos;
}

/**
 * Tira do menu os itens marcados 'so_mps_admin' (gestão da equipe)
 * quando o usuário da MPS não é admin — as rotas também barram (require_mps_admin).
 */
function nav_filtrar_mps_admin(array $grupos, bool $mps_admin): array
{
    if ($mps_admin) {
        return $grupos;
    }
    foreach ($grupos as &$grupo) {
        $grupo['itens'] = array_values(array_filter(array_map(function ($item) {
            if (isset($item['submenu'])) {
                $item['submenu'] = array_values(array_filter($item['submenu'], fn($s) => empty($s['so_mps_admin'])));
            }
            return $item;
        }, $grupo['itens']), fn($item) => empty($item['so_mps_admin'])));
    }
    return $grupos;
}


/** Menu da imobiliária (itens conforme as permissões do usuário). */
function nav_imobiliaria_grupos(object $current_user): array
{
    $is_imob_admin = (bool) ($current_user->imob_admin ?? false);
    
    $usa_embed = false;
    if (FEATURE_EMBED_UI) {
        try {
            $usa_embed = (bool) (db_fetch_one(
                'SELECT usa_embed FROM imobiliarias WHERE id = ?',
                ['i', $current_user->imobiliaria_id]
            )['usa_embed'] ?? false);
        } catch (Throwable) {}
    }
    
    /** Usuário tem a permissão? Admin da imobiliária sempre tem acesso total. */
    $tem_perm = fn(string $perm): bool => $is_imob_admin || ($current_user->{$perm} ?? 'N') === 'S';
    $tem_cotar = $tem_perm('perm_cotar');
    
    return [
        [
            'titulo' => null,
            'itens' => [
                ['label' => 'Dashboard', 'url' => '/', 'icone' => 'gauge'],
            ],
        ],
        [
            'titulo' => 'Cotações',
            'itens' => array_filter([
                ['label' => 'Minhas Cotações', 'url' => '/cotacoes', 'icone' => 'clipboard-text'],
                ($tem_cotar && $tem_perm('perm_fianca')) ? [
                    'label' => 'Seguro Fiança',
                    'icone' => 'house',
                    'submenu' => [
                        ['label' => 'Residencial', 'url' => '/cotacao/fianca/residencial'],
                        ['label' => 'Comercial',   'url' => '/cotacao/fianca/comercial'],
                    ],
                ] : null,
                ($tem_cotar && $tem_perm('perm_auto'))
                    ? ['label' => 'Seguro Auto', 'url' => '/cotacao/auto', 'icone' => 'car'] : null,
                ($tem_cotar && $tem_perm('perm_titulo'))
                    ? ['label' => 'Título de Capitalização', 'url' => '/cotacao/titulo', 'icone' => 'piggy-bank'] : null,
                ($tem_cotar && $tem_perm('perm_condo'))
                    ? ['label' => 'Seguro Condominial', 'url' => '/cotacao/condominial', 'icone' => 'buildings'] : null,
                ($tem_cotar && $tem_perm('perm_incendio'))
                    ? ['label' => 'Seguro Incêndio', 'url' => '/cotacao/incendio', 'icone' => 'fire'] : null,
                ($tem_cotar && $tem_perm('perm_seguro_residencial'))
                    ? ['label' => 'Seguro Residencial', 'url' => '/cotacao/residencial', 'icone' => 'house-line'] : null,
                $tem_cotar ? ['label' => 'Rascunhos', 'url' => '/cotacao/rascunhos', 'icone' => 'note-pencil'] : null,
            ]),
        ],
        [
            'titulo' => 'Suporte',
            'itens' => array_filter([
                $tem_perm('perm_suporte') ? ['label' => 'Meus Chamados', 'url' => '/chamados',      'icone' => 'ticket']  : null,
                $tem_perm('perm_suporte') ? ['label' => 'Abrir Chamado', 'url' => '/chamados/novo', 'icone' => 'headset'] : null,
                ['label' => 'Campanhas',     'url' => '/campanhas',     'icone' => 'megaphone'],
                ['label' => 'FAQ',           'url' => '/faq',           'icone' => 'question'],
            ]),
        ],
        [
            'titulo' => 'Configurações',
            'itens' => array_filter([
                $is_imob_admin ? [
                    'label' => 'Administrativo',
                    'icone' => 'users',
                    'submenu' => [
                        ['label' => 'Gerenciar Usuários', 'url' => '/usuarios'],
                        ['label' => 'Criar Usuário',       'url' => '/usuarios/criar'],
                    ],
                ] : null,
                // Admin da imobiliária vê sempre (é na tela /embed que ele ativa);
                // os demais só depois de ativo, pra copiar os códigos.
                FEATURE_EMBED_UI && ($is_imob_admin || $usa_embed)
                    ? ['label' => 'Embed de Cotações', 'url' => '/embed', 'icone' => 'monitor'] : null,
                ['label' => 'Meu Perfil', 'url' => '/perfil', 'icone' => 'user'],
            ]),
        ],
    ];
}
