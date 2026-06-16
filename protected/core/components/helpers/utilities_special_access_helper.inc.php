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
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS voucher_special_access (
            id INT AUTO_INCREMENT PRIMARY KEY,
            voucher_type VARCHAR(150) NOT NULL,
            forward_designation VARCHAR(255) NOT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_voucher_type (voucher_type),
            KEY idx_active_sort (is_active, sort_order, voucher_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

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
 * Resolve direct-forward designation for a voucher type (empty when none configured).
 */
function utilities_special_access_resolve_target(PDO $pdo, string $voucher_type): string
{
    $voucher_type = utilities_special_access_normalize_value($voucher_type);
    if ($voucher_type === '') {
        return '';
    }

    return RequestCache::remember('special_access', 'target:' . $voucher_type, static function () use ($pdo, $voucher_type): string {
        $stmt = $pdo->prepare(
            'SELECT forward_designation FROM voucher_special_access
             WHERE voucher_type = :voucher_type AND is_active = 1
             LIMIT 1'
        );
        $stmt->execute([':voucher_type' => $voucher_type]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return utilities_special_access_normalize_value((string) ($row['forward_designation'] ?? ''));
    });
}

/**
 * Designations available when configuring special access rules.
 *
 * @return list<string>
 */
function utilities_special_access_forward_destinations(PDO $pdo): array
{
    static $preferred = [
        'Accounting Unit',
        'Budget Unit',
        'Planning Section',
        'Conservation & Development Section',
        'Cashiers Unit',
        'Office of the PENRO',
        'ICU',
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

function utilities_special_access_destination_is_allowed(PDO $pdo, string $designation): bool
{
    $designation = utilities_special_access_normalize_value($designation);
    if ($designation === '') {
        return false;
    }

    return in_array($designation, utilities_special_access_forward_destinations($pdo), true);
}
