<?php

/**
 * Shared paths for Apache ErrorDocument pages.
 * Relative hrefs break because the browser URL is still the forbidden directory.
 */
function error_page_web_base(): string
{
    if (defined('REDIRECT_WEB_BASE')) {
        return rtrim((string) REDIRECT_WEB_BASE, '/');
    }

    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    $marker = '/protected/routes/';
    $pos = strpos($script, $marker);
    if ($pos !== false) {
        return substr($script, 0, $pos) ?: '/vtrams';
    }

    return '/vtrams';
}

function error_page_asset_url(string $relativeFromAppRoot): string
{
    return error_page_web_base() . '/' . ltrim($relativeFromAppRoot, '/');
}
