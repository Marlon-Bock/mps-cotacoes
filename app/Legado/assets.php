<?php
/**
 * Cache-busting de assets estáticos (CSS/JS) — anexa ?v=<mtime> ao path,
 * pra navegador e proxies nunca servirem uma versão antiga após deploy.
 *
 * Uso: <link rel="stylesheet" href="<?= e(asset_v('/assets/css/base.css')) ?>">
 */
function asset_v(string $path): string
{
    $abs = BASE_PATH . '/public' . $path;   // caminho em disco, sem base path
    $v   = is_file($abs) ? filemtime($abs) : time();
    return BASE_URI . $path . '?v=' . $v;
}
