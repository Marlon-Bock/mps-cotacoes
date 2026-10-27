<?php
/**
 * Sidebar de navegação — compartilhada entre área da imobiliária e admin.
 * Espera: $nav_groups (array de grupos: ['titulo' => string, 'itens' => [...]])
 *
 * Cada item pode ser:
 *   - link direto:    ['label','url','icone']
 *   - submenu:        ['label','icone','submenu' => [['label','url'], ...]]
 *     (colapsável — usado para agrupar produtos de seguro sob um mesmo item)
 *   - em breve:        ['label','icone','disabled' => true]
 *     (produto ainda não disponível — aparece esmaecido, sem link)
 */
// As 'url' dos itens são rotas lógicas, sem base path — por isso a comparação
// usa request_path(), que também vem sem prefixo. O prefixo entra só no href.
$current_path = request_path();

// Determina a URL ativa = o link mais específico (mais longo) que bate com o caminho atual.
// Evita que um item "raiz" (ex: /admin) fique marcado como ativo em todas as suas subpáginas
// (ex: /admin/cotacoes) só porque o caminho começa com o mesmo prefixo.
$__active_url = null;
foreach ($nav_groups as $__g) {
    foreach ($__g['itens'] as $__it) {
        $__urls = !empty($__it['submenu']) ? array_column($__it['submenu'], 'url') : (array) ($__it['url'] ?? null);
        foreach (array_filter($__urls) as $__u) {
            $__matches = $current_path === $__u || ($__u !== '/' && str_starts_with($current_path, $__u . '/'));
            if ($__matches && ($__active_url === null || strlen($__u) > strlen($__active_url))) {
                $__active_url = $__u;
            }
        }
    }
}

$sidebar_submenu_seq = 0;
?>
<div class="app-sidebar-overlay" data-toggle="sidebar"></div>
<aside class="app-sidebar">
    <div class="app-sidebar-logo">
        <img src="<?= e(url('/assets/img/logo-mark.png')) ?>" alt="" width="32" height="32">
        <strong>MPS Cotações</strong>
    </div>

    <nav class="app-sidebar-scroll">
        <?php foreach ($nav_groups as $group): ?>
        <div class="app-nav-group">
            <?php if (!empty($group['titulo'])): ?>
            <div class="app-nav-group-title"><?= e($group['titulo']) ?></div>
            <?php endif; ?>

            <?php foreach ($group['itens'] as $item): ?>
                <?php if (!empty($item['disabled'])): ?>
                    <span class="app-nav-link is-disabled">
                        <span class="app-nav-icon"><?= icon($item['icone']) ?></span>
                        <span><?= e($item['label']) ?></span>
                    </span>
                <?php elseif (!empty($item['submenu'])): ?>
                    <?php
                        $sidebar_submenu_seq++;
                        $submenu_id = "submenu-{$sidebar_submenu_seq}";
                        $has_active_child = array_reduce(
                            $item['submenu'],
                            fn($carry, $sub) => $carry || $sub['url'] === $__active_url,
                            false
                        );
                    ?>
                    <button
                        type="button"
                        class="app-nav-link app-nav-submenu-toggle <?= $has_active_child ? 'is-active' : '' ?>"
                        data-toggle="submenu"
                        aria-expanded="<?= $has_active_child ? 'true' : 'false' ?>"
                        aria-controls="<?= $submenu_id ?>"
                    >
                        <span class="app-nav-icon"><?= icon($item['icone']) ?></span>
                        <span><?= e($item['label']) ?></span>
                        <?= icon('caret-down', 'app-nav-submenu-caret') ?>
                    </button>
                    <div class="app-nav-submenu <?= $has_active_child ? 'is-open' : '' ?>" id="<?= $submenu_id ?>">
                        <?php foreach ($item['submenu'] as $sub): ?>
                        <a class="app-nav-submenu-item <?= $sub['url'] === $__active_url ? 'is-active' : '' ?>" href="<?= e(url($sub['url'])) ?>">
                            <?= e($sub['label']) ?>
                        </a>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <?php $is_active = $item['url'] === $__active_url; ?>
                    <a class="app-nav-link <?= $is_active ? 'is-active' : '' ?>" href="<?= e(url($item['url'])) ?>">
                        <span class="app-nav-icon"><?= icon($item['icone']) ?></span>
                        <span><?= e($item['label']) ?></span>
                    </a>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
    </nav>

    <div class="app-sidebar-footer">
        &copy; <?= date('Y') ?> Mesquita Prime e Sami
    </div>
</aside>
