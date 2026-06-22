<?php

declare(strict_types=1);

require_once __DIR__ . '/request_cache.inc.php';

function utilities_checklist_normalize_value(string $value): string
{
    return trim(preg_replace('/\s+/', ' ', $value));
}

function utilities_checklist_invalidate_cache(): void
{
    RequestCache::forgetNamespace('checklist');
}

function utilities_checklist_ensure_schema(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS checklist_type_options (
            id INT AUTO_INCREMENT PRIMARY KEY,
            type_key VARCHAR(150) NOT NULL,
            title VARCHAR(500) NOT NULL DEFAULT '',
            display_label VARCHAR(150) NULL DEFAULT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_type_key (type_key),
            KEY idx_active_sort (is_active, sort_order, type_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS checklist_type_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            checklist_type_id INT NOT NULL,
            item_label VARCHAR(500) NOT NULL,
            subitems_json TEXT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_type_item (checklist_type_id, item_label(191)),
            KEY idx_type_active_sort (checklist_type_id, is_active, sort_order, item_label),
            CONSTRAINT fk_checklist_type_items_type
                FOREIGN KEY (checklist_type_id) REFERENCES checklist_type_options(id)
                ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $count = (int) $pdo->query('SELECT COUNT(*) FROM checklist_type_options')->fetchColumn();
    if ($count === 0) {
        utilities_checklist_seed_from_legacy($pdo);
    }
}

/**
 * Import legacy file/built-in checklist definitions on first run.
 */
function utilities_checklist_seed_from_legacy(PDO $pdo): void
{
    $legacyPath = dirname(__DIR__, 4) . '/public/vouchers/checklist_config.php';
    if (!is_file($legacyPath)) {
        return;
    }

    require_once $legacyPath;

    $templates = checklist_get_folder_templates();
    if ($templates === []) {
        $templates = checklist_get_builtin_templates();
        foreach (checklist_type_specific_items() as $type => $def) {
            if (!isset($templates[$type])) {
                $templates[$type] = $def;
            }
        }
    }

    if ($templates === []) {
        return;
    }

    $labelFixes = [
        'Diesel or Fuel Expnese' => 'Diesel Fuel Expense',
        'Contractual Services or Job Order' => 'Contractual Services or Job Order Salary',
    ];

    $insertType = $pdo->prepare("
        INSERT INTO checklist_type_options (type_key, title, display_label, sort_order, is_active)
        VALUES (:type_key, :title, :display_label, :sort, 1)
    ");
    $insertItem = $pdo->prepare("
        INSERT INTO checklist_type_items (checklist_type_id, item_label, subitems_json, sort_order, is_active)
        VALUES (:type_id, :label, :subitems, :sort, 1)
    ");

    $sortType = 0;
    foreach ($templates as $typeKey => $def) {
        $typeKey = utilities_checklist_normalize_value((string) $typeKey);
        if ($typeKey === '') {
            continue;
        }

        $title = trim((string) ($def['title'] ?? strtoupper($typeKey)));
        $displayLabel = $labelFixes[$typeKey] ?? null;

        $insertType->execute([
            ':type_key' => $typeKey,
            ':title' => $title,
            ':display_label' => $displayLabel,
            ':sort' => $sortType++,
        ]);
        $typeId = (int) $pdo->lastInsertId();

        $items = isset($def['items']) && is_array($def['items']) ? $def['items'] : [];
        $sortItem = 0;
        foreach ($items as $item) {
            $parsed = checklist_parse_item($item);
            if ($parsed['label'] === '') {
                continue;
            }
            $subJson = $parsed['subitems'] !== [] ? json_encode($parsed['subitems'], JSON_UNESCAPED_UNICODE) : null;
            $insertItem->execute([
                ':type_id' => $typeId,
                ':label' => $parsed['label'],
                ':subitems' => $subJson,
                ':sort' => $sortItem++,
            ]);
        }
    }
}

function utilities_checklist_type_exists(PDO $pdo, int $typeId): bool
{
    if ($typeId <= 0) {
        return false;
    }
    $stmt = $pdo->prepare('SELECT 1 FROM checklist_type_options WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $typeId]);

    return (bool) $stmt->fetchColumn();
}

/** @return list<array<string, mixed>> */
function utilities_checklist_fetch_all(PDO $pdo): array
{
    utilities_checklist_ensure_schema($pdo);
    $stmt = $pdo->query("
        SELECT id, type_key, title, display_label, sort_order, is_active
        FROM checklist_type_options
        ORDER BY sort_order ASC, type_key ASC
    ");
    $types = $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];

    foreach ($types as &$type) {
        $type['items'] = utilities_checklist_fetch_items($pdo, (int) ($type['id'] ?? 0));
    }
    unset($type);

    return $types;
}

/** @return list<array<string, mixed>> */
function utilities_checklist_fetch_items(PDO $pdo, int $typeId, bool $activeOnly = false): array
{
    if ($typeId <= 0) {
        return [];
    }

    $sql = "
        SELECT id, item_label, subitems_json, sort_order, is_active
        FROM checklist_type_items
        WHERE checklist_type_id = :id
    ";
    if ($activeOnly) {
        $sql .= ' AND is_active = 1';
    }
    $sql .= ' ORDER BY sort_order ASC, item_label ASC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $typeId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    foreach ($rows as &$row) {
        $row['subitems'] = utilities_checklist_decode_subitems((string) ($row['subitems_json'] ?? ''));
    }
    unset($row);

    return $rows;
}

/** @return string[] */
function utilities_checklist_decode_subitems(string $json): array
{
    if (trim($json) === '') {
        return [];
    }
    $decoded = json_decode($json, true);
    if (!is_array($decoded)) {
        return [];
    }
    $out = [];
    foreach ($decoded as $s) {
        if (is_string($s)) {
            $t = trim($s);
            if ($t !== '') {
                $out[] = $t;
            }
        }
    }

    return $out;
}

/** @param string[] $subitems */
function utilities_checklist_encode_subitems(array $subitems): ?string
{
    $clean = [];
    foreach ($subitems as $s) {
        $t = utilities_checklist_normalize_value((string) $s);
        if ($t !== '') {
            $clean[] = $t;
        }
    }

    return $clean === [] ? null : json_encode($clean, JSON_UNESCAPED_UNICODE);
}

/** @return string[] */
function utilities_checklist_parse_subitems_text(string $raw): array
{
    $lines = preg_split('/\r\n|\r|\n/', $raw) ?: [];
    $out = [];
    foreach ($lines as $line) {
        $t = utilities_checklist_normalize_value($line);
        if ($t !== '') {
            $out[] = $t;
        }
    }

    return $out;
}

/**
 * Build checklist templates in the shape expected by checklist_config.php.
 *
 * @return array<string, array{title: string, items: array<int, string|array<string, mixed>>}>
 */
function utilities_checklist_build_templates(PDO $pdo, bool $activeOnly = true): array
{
    utilities_checklist_ensure_schema($pdo);
    $sql = "
        SELECT id, type_key, title, display_label, sort_order, is_active
        FROM checklist_type_options
    ";
    if ($activeOnly) {
        $sql .= ' WHERE is_active = 1';
    }
    $sql .= ' ORDER BY sort_order ASC, type_key ASC';

    $types = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $templates = [];

    foreach ($types as $type) {
        $typeKey = (string) ($type['type_key'] ?? '');
        if ($typeKey === '') {
            continue;
        }
        $items = [];
        foreach (utilities_checklist_fetch_items($pdo, (int) $type['id'], $activeOnly) as $row) {
            $label = (string) ($row['item_label'] ?? '');
            $subs = $row['subitems'] ?? [];
            if ($label === '') {
                continue;
            }
            if ($subs !== []) {
                $items[] = ['label' => $label, 'subitems' => $subs];
            } else {
                $items[] = $label;
            }
        }
        if ($items === []) {
            $items = checklist_default_items();
        }
        $templates[$typeKey] = [
            'title' => (string) ($type['title'] ?? strtoupper($typeKey)),
            'items' => $items,
        ];
    }

    return $templates;
}

/**
 * @return array<string, string>
 */
function utilities_checklist_types_with_labels(PDO $pdo): array
{
    utilities_checklist_ensure_schema($pdo);
    $stmt = $pdo->query("
        SELECT type_key, display_label
        FROM checklist_type_options
        WHERE is_active = 1
        ORDER BY sort_order ASC, type_key ASC
    ");
    $rows = $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
    $out = [];
    foreach ($rows as $row) {
        $key = (string) ($row['type_key'] ?? '');
        if ($key === '') {
            continue;
        }
        $label = trim((string) ($row['display_label'] ?? ''));
        $out[$key] = $label !== '' ? $label : $key;
    }

    return $out;
}

function utilities_checklist_has_types(PDO $pdo): bool
{
    utilities_checklist_ensure_schema($pdo);
    $count = (int) $pdo->query('SELECT COUNT(*) FROM checklist_type_options WHERE is_active = 1')->fetchColumn();

    return $count > 0;
}
