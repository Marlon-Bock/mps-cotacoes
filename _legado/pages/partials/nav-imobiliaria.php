<?php
/**
 * Itens de navegação da área da Imobiliária.
 * Retorna $nav_groups — incluído por toda página autenticada desse perfil.
 *
 * Itens com 'submenu' são colapsáveis — usar para agrupar produtos de seguro
 * (hoje Fiança Residencial/Comercial; novos produtos entram aqui no futuro
 * sem precisar virar item solto na sidebar).
 *
 * 'icone' é o nome do arquivo em public/assets/icons/ (Phosphor Icons).
 */
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

$nav_groups = [
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
            $usa_embed ? ['label' => 'Embed de Cotações', 'url' => '/embed', 'icone' => 'monitor'] : null,
            ['label' => 'Meu Perfil', 'url' => '/perfil', 'icone' => 'user'],
        ]),
    ],
];
