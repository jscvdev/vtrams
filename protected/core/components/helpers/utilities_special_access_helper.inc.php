<?php

declare(strict_types=1);

require_once __DIR__ . '/request_cache.inc.php';

function utilities_special_access_invalidate_cache(): void
{
    RequestCache::forgetNamespace('special_access');
}

function utilities_special_access_normalize_value(string $value): string
{
    return trim(preg_replace('/\s+/', ' ', $value));
}

function utilities_special_access_ensure_schema(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS voucher_special_access (
            id INT AUTO_INCREMENT PRIMARY KEY,
            voucher_type VARCHAR(150) NOT NULL,
            forward_designation VARCHAR(255) NOT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_voucher_type_active_sort (voucher_type, is_active, sort_order, id),
            KEY idx_active_sort (is_active, sort_order, voucher_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    utilities_special_access_upgrade_schema_for_multiple_rules($pdo);

    $count = (int) $pdo->query('SELECT COUNT(*) FROM voucher_special_access')->fetchColumn();
    if ($count === 0) {
        $seed = [
            ['e-NGP Retention', 'Accounting Unit', 0],
            ['e-NGP Seedling Production & MP', 'Accounting Unit', 1],
        ];
        $stmt = $pdo->prepare("
            INSERT INTO voucher_special_access (voucher_type, forward_designation, sort_order, is_active)
            VALUES (:voucher_type, :forward_designation, :sort_order, 1)
        ");
        foreach ($seed as [$type, $designation, $sort]) {
            $stmt->execute([
                ':voucher_type' => $type,
                ':forward_designation' => $designation,
                ':sort_order' => $sort,
            ]);
        }
    }

    utilities_special_access_ensure_forward_destinations_schema($pdo);
}

function utilities_special_access_upgrade_schema_for_multiple_rules(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    try {
        $stmt = $pdo->query("SHOW INDEX FROM voucher_special_access WHERE Key_name = 'uniq_voucher_type'");
        if ($stmt && $stmt->fetch(PDO::FETCH_ASSOC)) {
            $pdo->exec('ALTER TABLE voucher_special_access DROP INDEX uniq_voucher_type');
        }
    } catch (Throwable) {
        // Index may already be removed.
    }

    try {
        $stmt = $pdo->query("SHOW INDEX FROM voucher_special_access WHERE Key_name = 'uniq_voucher_type_destination'");
        if (!$stmt || !$stmt->fetch(PDO::FETCH_ASSOC)) {
            $pdo->exec(
                'ALTER TABLE voucher_special_access
                 ADD UNIQUE KEY uniq_voucher_type_destination (voucher_type, forward_designation)'
            );
        }
    } catch (Throwable) {
        // Composite unique may already exist or duplicates need manual cleanup.
    }
}

function utilities_special_access_ensure_forward_destinations_schema(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS voucher_forward_destinations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            designation VARCHAR(255) NOT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_designation (designation),
            KEY idx_active_sort (is_active, sort_order, designation)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $count = (int) $pdo->query('SELECT COUNT(*) FROM voucher_forward_destinations')->fetchColumn();
    if ($count === 0) {
        $seed = [
            ['Accounting Unit', 0],
            ['Budget Unit', 1],
            ['Planning Section', 2],
            ['Conservation & Development Section', 3],
            ['Cashiers Unit', 4],
            ['Office of the PENRO', 5],
            ['ICU', 6],
            ['TSD-ENGP', 7],
        ];
        $stmt = $pdo->prepare("
            INSERT INTO voucher_forward_destinations (designation, sort_order, is_active)
            VALUES (:designation, :sort_order, 1)
        ");
        foreach ($seed as [$designation, $sort]) {
            $stmt->execute([
                ':designation' => $designation,
                ':sort_order' => $sort,
            ]);
        }
    }
}

/**
 * @return list<array<string, mixed>>
 */
function utilities_special_access_forward_destinations_fetch_all(PDO $pdo): array
{
    utilities_special_access_ensure_forward_destinations_schema($pdo);
    $stmt = $pdo->query(
        'SELECT id, designation, sort_order, is_active
         FROM voucher_forward_destinations
         ORDER BY sort_order ASC, designation ASC'
    );

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/**
 * Active designations shown in routing rule "Forward to" dropdowns.
 *
 * @return list<string>
 */
function utilities_special_access_forward_destinations_active(PDO $pdo): array
{
    return RequestCache::remember('special_access', 'forward_destinations_active', static function () use ($pdo): array {
        utilities_special_access_ensure_forward_destinations_schema($pdo);
        $stmt = $pdo->query(
            'SELECT designation FROM voucher_forward_destinations
             WHERE is_active = 1
             ORDER BY sort_order ASC, designation ASC'
        );
        $found = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $name = utilities_special_access_normalize_value((string) ($row['designation'] ?? ''));
            if ($name !== '') {
                $found[] = $name;
            }
        }

        return $found;
    });
}

/**
 * Designations available when adding a forward destination entry.
 *
 * @return list<string>
 */
function utilities_special_access_forward_destinations_configurable(PDO $pdo): array
{
    static $preferred = [
        'Accounting Unit',
        'Accountant III',
        'Budget Unit',
        'Planning Section',
        'Conservation & Development Section',
        'Cashiers Unit',
        'Office of the PENRO',
        'ICU',
        'TSD-ENGP',
    ];

    $found = [];
    try {
        $stmt = $pdo->query(
            'SELECT designation, designated_udc FROM designation_limit
             WHERE designation IS NOT NULL AND TRIM(designation) <> \'\'
             ORDER BY id ASC'
        );
        $registered = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $name = trim((string) ($row['designation'] ?? ''));
            $udc = trim((string) ($row['designated_udc'] ?? ''));
            if ($name === '' || $udc === '') {
                continue;
            }
            $registered[$name] = true;
        }

        foreach ($preferred as $designation) {
            if (isset($registered[$designation])) {
                $found[] = $designation;
            }
        }

        static $alwaysInclude = ['TSD-ENGP'];
        foreach ($alwaysInclude as $designation) {
            if (!in_array($designation, $found, true)) {
                $found[] = $designation;
            }
        }

        if ($found !== []) {
            return $found;
        }
    } catch (Throwable $e) {
        // Fall back when designation_limit is unavailable.
    }

    return $preferred;
}

/**
 * @return list<array<string, mixed>>
 */
function utilities_special_access_fetch_all(PDO $pdo): array
{
    $stmt = $pdo->query(
        'SELECT id, voucher_type, forward_designation, sort_order, is_active
         FROM voucher_special_access
         ORDER BY sort_order ASC, voucher_type ASC'
    );

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/**
 * Active direct-forward destinations for a voucher type (empty when none configured).
 *
 * @return list<string>
 */
function utilities_special_access_resolve_targets(PDO $pdo, string $voucher_type): array
{
    $voucher_type = utilities_special_access_normalize_value($voucher_type);
    if ($voucher_type === '') {
        return [];
    }

    return RequestCache::remember('special_access', 'targets:' . $voucher_type, static function () use ($pdo, $voucher_type): array {
        $stmt = $pdo->prepare(
            'SELECT forward_designation FROM voucher_special_access
             WHERE voucher_type = :voucher_type AND is_active = 1
             ORDER BY sort_order ASC, id ASC'
        );
        $stmt->execute([':voucher_type' => $voucher_type]);

        $targets = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $designation = utilities_special_access_normalize_value((string) ($row['forward_designation'] ?? ''));
            if ($designation !== '' && !in_array($designation, $targets, true)) {
                $targets[] = $designation;
            }
        }

        return $targets;
    });
}

/**
 * Resolve direct-forward designation for a voucher type (empty when none configured).
 */
function utilities_special_access_resolve_target(PDO $pdo, string $voucher_type): string
{
    $targets = utilities_special_access_resolve_targets($pdo, $voucher_type);

    return $targets[0] ?? '';
}

/**
 * Designations available when configuring special access rules.
 *
 * @return list<string>
 */
function utilities_special_access_forward_destinations(PDO $pdo): array
{
    $active = utilities_special_access_forward_destinations_active($pdo);
    if ($active !== []) {
        return $active;
    }

    return utilities_special_access_forward_destinations_configurable($pdo);
}

function utilities_special_access_destination_is_allowed(PDO $pdo, string $designation): bool
{
    $designation = utilities_special_access_normalize_value($designation);
    if ($designation === '') {
        return false;
    }

    $allowed = utilities_special_access_forward_destinations_active($pdo);
    if ($allowed === []) {
        $allowed = utilities_special_access_forward_destinations_configurable($pdo);
    }

    if (in_array($designation, $allowed, true)) {
        return true;
    }

    utilities_special_access_ensure_forward_destinations_schema($pdo);
    $stmt = $pdo->prepare(
        'SELECT 1 FROM voucher_forward_destinations WHERE designation = :designation LIMIT 1'
    );
    $stmt->execute([':designation' => $designation]);

    return (bool) $stmt->fetchColumn();
}
