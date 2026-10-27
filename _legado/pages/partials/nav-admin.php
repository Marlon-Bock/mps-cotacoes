<?php
/**
 * Itens de navegação da área Admin (MPS).
 * Retorna $nav_groups — incluído por toda página autenticada desse perfil.
 *
 * Cobre os mesmos links do sidebar admin do sistema antigo (Dashboard,
 * Cotações/Pipeline, Visitas, Campanhas, Suporte/FAQ, Gestão, Administrativo,
 * Perfil), apenas reorganizados em grupos/submenus mais claros.
 *
 * 'icone' é o nome do arquivo em public/assets/icons/ (Phosphor Icons).
 */
$nav_groups = [
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
            ['label' => 'Financeiro',        'url' => '/admin/financeiro',  'icone' => 'currency-dollar'],
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
                    ['label' => 'Gerenciar Usuários',       'url' => '/admin/usuarios'],
                    ['label' => 'Criar Usuário',            'url' => '/admin/usuarios/criar'],
                    ['label' => 'Horário de Atendimento',   'url' => '/admin/horarios'],
                ],
            ],
            ['label' => 'Meu Perfil', 'url' => '/admin/perfil', 'icone' => 'user'],
        ],
    ],
];
