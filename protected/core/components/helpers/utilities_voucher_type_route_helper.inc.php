<?php

declare(strict_types=1);

require_once __DIR__ . '/request_cache.inc.php';

function utilities_voucher_type_route_invalidate_cache(): void
{
    RequestCache::forgetNamespace('voucher_type_route');
}

function utilities_voucher_type_route_normalize_value(string $value): string
{
    return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
}

function utilities_voucher_type_route_ensure_schema(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS voucher_type_route (
            id INT AUTO_INCREMENT PRIMARY KEY,
            voucher_type VARCHAR(150) NOT NULL,
            designation VARCHAR(255) NOT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_type_active_sort (voucher_type, is_active, sort_order, id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    utilities_voucher_type_route_invalidate_cache();
}

/**
 * @return list<array<string, mixed>>
 */
function utilities_voucher_type_route_fetch_all(PDO $pdo): array
{
    utilities_voucher_type_route_ensure_schema($pdo);
    $stmt = $pdo->query(
        'SELECT id, voucher_type, designation, sort_order, is_active
         FROM voucher_type_route
         ORDER BY voucher_type ASC, sort_order ASC, id ASC'
    );

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/**
 * Active hops after Encoder for one voucher type. Empty when the type uses the default processing-office flow.
 *
 * @return list<string>
 */
function utilities_voucher_type_route_active_steps(PDO $pdo, string $voucher_type): array
{
    $voucher_type = utilities_voucher_type_route_normalize_value($voucher_type);
    if ($voucher_type === '') {
        return [];
    }

    return RequestCache::remember(
        'voucher_type_route',
        'active_steps:' . $voucher_type,
        static function () use ($pdo, $voucher_type): array {
            utilities_voucher_type_route_ensure_schema($pdo);
            $stmt = $pdo->prepare(
                'SELECT designation FROM voucher_type_route
                 WHERE voucher_type = :voucher_type AND is_active = 1
                 ORDER BY sort_order ASC, id ASC'
            );
            $stmt->execute([':voucher_type' => $voucher_type]);
            $found = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $name = utilities_voucher_type_route_normalize_value((string) ($row['designation'] ?? ''));
                if ($name !== '') {
                    $found[] = $name;
                }
            }

            return $found;
        }
    );
}

function utilities_voucher_type_route_has_steps(PDO $pdo, string $voucher_type): bool
{
    return utilities_voucher_type_route_active_steps($pdo, $voucher_type) !== [];
}

function utilities_voucher_type_route_encoder_target(PDO $pdo, string $voucher_type): string
{
    $steps = utilities_voucher_type_route_active_steps($pdo, $voucher_type);

    return $steps[0] ?? '';
}

function utilities_voucher_type_route_flow_label(PDO $pdo, string $voucher_type): string
{
    $steps = utilities_voucher_type_route_active_steps($pdo, $voucher_type);
    if ($steps === []) {
        return '';
    }

    return implode(' → ', array_merge(['Encoder'], $steps));
}

/**
 * @return array<string, list<array<string, mixed>>>
 */
function utilities_voucher_type_route_group_by_type(array $rows): array
{
    $grouped = [];
    foreach ($rows as $row) {
        $type = utilities_voucher_type_route_normalize_value((string) ($row['voucher_type'] ?? ''));
        if ($type === '') {
            continue;
        }
        $grouped[$type][] = $row;
    }

    return $grouped;
}

/**
 * @return list<string>
 */
function utilities_voucher_type_route_configurable_destinations(PDO $pdo): array
{
    require_once __DIR__ . '/utilities_processing_office_route_helper.inc.php';

    return utilities_processing_office_route_configurable_destinations($pdo);
}

function utilities_voucher_type_route_destination_is_allowed(PDO $pdo, string $destination): bool
{
    require_once __DIR__ . '/utilities_processing_office_route_helper.inc.php';

    return utilities_processing_office_route_destination_is_allowed($pdo, $destination);
}
