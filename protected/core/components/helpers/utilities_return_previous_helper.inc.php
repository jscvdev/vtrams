<?php

declare(strict_types=1);

require_once __DIR__ . '/request_cache.inc.php';

function utilities_return_previous_invalidate_cache(): void
{
    RequestCache::forgetNamespace('return_previous');
}

function utilities_return_previous_normalize_value(string $value): string
{
    return trim(preg_replace('/\s+/', ' ', $value));
}

function utilities_return_previous_ensure_schema(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS voucher_return_previous_units (
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

    $count = (int) $pdo->query('SELECT COUNT(*) FROM voucher_return_previous_units')->fetchColumn();
    if ($count === 0) {
        $seed = [
            ['Planning Section', 0],
            ['ICU', 1],
            ['Budget Unit', 2],
            ['Accounting Unit', 3],
            ['Accountant III', 4],
            ['Office of the PENRO', 5],
            ['Cashiers Unit', 6],
        ];
        $stmt = $pdo->prepare("
            INSERT INTO voucher_return_previous_units (designation, sort_order, is_active)
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
function utilities_return_previous_fetch_all(PDO $pdo): array
{
    utilities_return_previous_ensure_schema($pdo);
    $stmt = $pdo->query(
        'SELECT id, designation, sort_order, is_active
         FROM voucher_return_previous_units
         ORDER BY sort_order ASC, designation ASC'
    );

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/**
 * Active designations that may appear in "Return to previous process".
 *
 * @return list<string>
 */
function utilities_return_previous_active_designations(PDO $pdo): array
{
    return RequestCache::remember('return_previous', 'active_designations', static function () use ($pdo): array {
        utilities_return_previous_ensure_schema($pdo);
        $stmt = $pdo->query(
            'SELECT designation FROM voucher_return_previous_units
             WHERE is_active = 1
             ORDER BY sort_order ASC, designation ASC'
        );
        $found = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $name = utilities_return_previous_normalize_value((string) ($row['designation'] ?? ''));
            if ($name !== '') {
                $found[] = $name;
            }
        }

        return $found;
    });
}

/**
 * Designations available when configuring return-to-previous rules.
 *
 * @return list<string>
 */
function utilities_return_previous_configurable_destinations(PDO $pdo): array
{
    static $preferred = [
        'Planning Section',
        'ICU',
        'Budget Unit',
        'Accounting Unit',
        'Accountant III',
        'Office of the PENRO',
        'Cashiers Unit',
        'Conservation & Development Section',
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

        if ($found !== []) {
            return $found;
        }
    } catch (Throwable $e) {
        // Fall back when designation_limit is unavailable.
    }

    return $preferred;
}

function utilities_return_previous_destination_is_allowed(PDO $pdo, string $destination): bool
{
    $destination = utilities_return_previous_normalize_value($destination);
    if ($destination === '') {
        return false;
    }

    $allowed = utilities_return_previous_active_designations($pdo);
    if ($allowed === []) {
        return false;
    }

    $normalized = utilities_return_previous_normalize_destination($destination);
    foreach ($allowed as $designation) {
        if (strcasecmp($destination, $designation) === 0) {
            return true;
        }
        if ($normalized !== '' && strcasecmp($normalized, $designation) === 0) {
            return true;
        }
    }

    return false;
}

function utilities_return_previous_normalize_destination(string $destination): string
{
    $destination = utilities_return_previous_normalize_value($destination);
    if ($destination === '') {
        return '';
    }

    if (strcasecmp($destination, 'Accountant III') === 0) {
        return 'Accountant III';
    }

    require_once __DIR__ . '/voucher_tracking_helper.inc.php';

    return voucher_tracking_normalize_section_label($destination);
}
