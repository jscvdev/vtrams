<?php

declare(strict_types=1);

require_once __DIR__ . '/request_cache.inc.php';

const SCHEMA_CACHE_NS = 'schema';

/**
 * @return non-empty-string|null
 */
function schema_sanitize_table_name(string $table): ?string
{
    $safe = preg_replace('/[^a-zA-Z0-9_]/', '', $table) ?? '';

    return ($safe !== '' && $safe === $table) ? $safe : null;
}

function schema_table_exists(PDO $pdo, string $table): bool
{
    $safe = schema_sanitize_table_name($table);
    if ($safe === null) {
        return false;
    }

    return (bool) RequestCache::remember(SCHEMA_CACHE_NS, 'exists:' . $safe, static function () use ($pdo, $safe): bool {
        try {
            $stmt = $pdo->query('SHOW TABLES LIKE ' . $pdo->quote($safe));

            return $stmt && $stmt->rowCount() > 0;
        } catch (Throwable) {
            return false;
        }
    });
}

/**
 * @return array<string, bool> column name => true
 */
function schema_table_column_map(PDO $pdo, string $table): array
{
    $safe = schema_sanitize_table_name($table);
    if ($safe === null) {
        return [];
    }

    return RequestCache::remember(SCHEMA_CACHE_NS, 'columns:' . $safe, static function () use ($pdo, $safe): array {
        $map = [];
        try {
            $stmt = $pdo->query('SHOW COLUMNS FROM `' . $safe . '`');
            $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
            foreach ($rows as $row) {
                if (isset($row['Field'])) {
                    $map[(string) $row['Field']] = true;
                }
            }
        } catch (Throwable) {
            $map = [];
        }

        return $map;
    });
}

function schema_table_has_column(PDO $pdo, string $table, string $column): bool
{
    return isset(schema_table_column_map($pdo, $table)[$column]);
}
