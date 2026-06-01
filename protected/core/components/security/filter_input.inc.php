<?php
require_once __DIR__ . '/../../utf8_helper.inc.php';

/**
 * Filter a user-provided free-text query before using it in DB searches.
 *
 * - NOT a replacement for prepared statements (still required).
 * - Designed for "search box" input (LIKE queries), not arbitrary SQL.
 */
function filterInput($raw, int $maxLen = 120): string
{
    $value = utf8_clean((string) $raw);

    // Drop control chars and normalize whitespace.
    $value = preg_replace('/[\x00-\x1F\x7F]/u', '', $value) ?? '';
    $value = trim((string) preg_replace('/\s+/u', ' ', $value));

    if ($value === '') {
        return '';
    }

    // Hard limit length to avoid abusive queries/perf issues.
    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        if (mb_strlen($value, 'UTF-8') > $maxLen) {
            $value = mb_substr($value, 0, $maxLen, 'UTF-8');
        }
    } elseif (strlen($value) > $maxLen) {
        $value = substr($value, 0, $maxLen);
    }

    // Reject common SQLi / XSS payload indicators for search inputs.
    $dangerTokens = '/(;|--|\/\*|\*\/|#|\bunion\b|\bselect\b|\binsert\b|\bupdate\b|\bdelete\b|\bdrop\b|\balter\b|\bcreate\b|\btruncate\b|\bsleep\b|\bbenchmark\b|\bload_file\b|\boutfile\b|\binformation_schema\b|\bxp_cmdshell\b|<\s*script\b|<\s*\/\s*script\s*>)/i';
    if (preg_match($dangerTokens, $value)) {
        return '';
    }

    return $value;
}
