<?php
/**
 * Helper de ícones — Phosphor Icons, auto-hospedados em /public/assets/icons/.
 * Nunca usar emoji no sistema — sempre via icon().
 *
 * Uso: <?= icon('house') ?>
 *      <?= icon('bell', 'icon-lg') ?>
 */

function icon(string $name, string $class = ''): string
{
    static $cache = [];

    if (!isset($cache[$name])) {
        $path = BASE_PATH . "/public/assets/icons/{$name}.svg";

        if (!is_file($path)) {
            error_log("[icon] Ícone não encontrado: {$name}");
            return '';
        }

        $cache[$name] = file_get_contents($path);
    }

    $svg = $cache[$name];
    $classAttr = 'icon' . ($class ? " {$class}" : '');

    // injeta a classe no elemento <svg ...> sem duplicar atributos
    return preg_replace('/<svg /', "<svg class=\"{$classAttr}\" aria-hidden=\"true\" ", $svg, 1);
}
