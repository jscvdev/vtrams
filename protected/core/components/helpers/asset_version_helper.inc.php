<?php

declare(strict_types=1);

/**
 * Cache-busting version from a static asset's last-modified time.
 */
function asset_version(string $absolutePath): int
{
    return is_file($absolutePath) ? (int) filemtime($absolutePath) : time();
}

/**
 * Build a cache-busted asset URL (?v=filemtime).
 */
function asset_url(string $href, string $absolutePath): string
{
    $separator = str_contains($href, '?') ? '&' : '?';

    return $href . $separator . 'v=' . asset_version($absolutePath);
}

/**
 * Stylesheets bundled by base.css (@import does not bust cache for imports).
 *
 * @return list<string>
 */
function asset_base_stylesheet_files(): array
{
    return [
        'hstyle.css',
        'ppop.css',
        'gen_slip.css',
        'custom_buttons.css',
    ];
}

/**
 * Echo cache-busted links for base layout CSS (includes hstyle / sidebar badge rules).
 */
function asset_base_stylesheets(string $hrefPrefix, string $absoluteDir): void
{
    foreach (asset_base_stylesheet_files() as $styleFile) {
        asset_stylesheet($hrefPrefix . $styleFile, $absoluteDir . '/' . $styleFile);
    }
}

/**
 * Stylesheets used by public/documents/index.php (login).
 * Loaded as separate cache-busted files (not via @import).
 *
 * @return list<string>
 */
function asset_login_stylesheet_files(): array
{
    return [
        'hstyle.css',
        'custom_buttons.css',
        'base2.css',
    ];
}

/**
 * Echo cache-busted links for the login page CSS.
 */
function asset_login_stylesheets(string $hrefPrefix, string $absoluteDir): void
{
    foreach (asset_login_stylesheet_files() as $styleFile) {
        asset_stylesheet($hrefPrefix . $styleFile, $absoluteDir . '/' . $styleFile);
    }
}

/**
 * Echo a cache-busted stylesheet link tag.
 */
function asset_stylesheet(string $href, string $absolutePath): void
{
    echo '<link rel="stylesheet" href="' . htmlspecialchars(asset_url($href, $absolutePath), ENT_QUOTES, 'UTF-8') . '">' . "\n";
}

/**
 * Echo a cache-busted script tag.
 */
function asset_script(string $src, string $absolutePath): void
{
    echo '<script src="' . htmlspecialchars(asset_url($src, $absolutePath), ENT_QUOTES, 'UTF-8') . '"></script>' . "\n";
}
