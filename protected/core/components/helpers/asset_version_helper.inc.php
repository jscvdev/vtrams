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
