<?php

declare(strict_types=1);

require_once __DIR__ . '/request_cache.inc.php';

function utilities_processing_office_route_invalidate_cache(): void
{
    RequestCache::forgetNamespace('processing_office_route');
}

function utilities_processing_office_route_normalize_value(string $value): string
{
    return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
}

/** @return list<array{0: string, 1: int}> */
function utilities_processing_office_route_default_steps(): array
{
    return [
        ['ICU', 0],
        ['Planning Section', 1],
        ['Office of the PENRO', 2],
        ['Budget Unit', 3],
        ['Accounting Unit', 4],
        ['Office of the PENRO', 5],
        ['Cashiers Unit', 6],
    ];
}

function utilities_processing_office_route_ensure_schema(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS voucher_processing_office_route (
            id INT AUTO_INCREMENT PRIMARY KEY,
            designation VARCHAR(255) NOT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_active_sort (is_active, sort_order, id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $count = (int) $pdo->query('SELECT COUNT(*) FROM voucher_processing_office_route')->fetchColumn();
    if ($count === 0) {
        $stmt = $pdo->prepare("
            INSERT INTO voucher_processing_office_route (designation, sort_order, is_active)
            VALUES (:designation, :sort_order, 1)
        ");
        foreach (utilities_processing_office_route_default_steps() as [$designation, $sort]) {
            $stmt->execute([
                ':designation' => $designation,
                ':sort_order' => $sort,
            ]);
        }
    }

    utilities_processing_office_route_invalidate_cache();
}

/**
 * @return list<array<string, mixed>>
 */
function utilities_processing_office_route_fetch_all(PDO $pdo): array
{
    utilities_processing_office_route_ensure_schema($pdo);
    $stmt = $pdo->query(
        'SELECT id, designation, sort_order, is_active
         FROM voucher_processing_office_route
         ORDER BY sort_order ASC, id ASC'
    );

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/**
 * Active processing-office hops after Encoder, in order. Repeats are allowed.
 *
 * @return list<string>
 */
function utilities_processing_office_route_active_steps(PDO $pdo): array
{
    return RequestCache::remember('processing_office_route', 'active_steps', static function () use ($pdo): array {
        utilities_processing_office_route_ensure_schema($pdo);
        $stmt = $pdo->query(
            'SELECT designation FROM voucher_processing_office_route
             WHERE is_active = 1
             ORDER BY sort_order ASC, id ASC'
        );
        $found = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $name = utilities_processing_office_route_normalize_value((string) ($row['designation'] ?? ''));
            if ($name !== '') {
                $found[] = $name;
            }
        }

        if ($found !== []) {
            return $found;
        }

        return array_map(
            static fn(array $step): string => $step[0],
            utilities_processing_office_route_default_steps()
        );
    });
}

function utilities_processing_office_route_encoder_target(PDO $pdo): string
{
    $steps = utilities_processing_office_route_active_steps($pdo);

    return $steps[0] ?? 'ICU';
}

function utilities_processing_office_route_flow_label(PDO $pdo): string
{
    $steps = utilities_processing_office_route_active_steps($pdo);
    $parts = array_merge(['Encoder'], $steps);

    return implode(' → ', $parts);
}

/**
 * @return list<string>
 */
function utilities_processing_office_route_configurable_destinations(PDO $pdo): array
{
    require_once __DIR__ . '/utilities_special_access_helper.inc.php';

    return utilities_special_access_forward_destinations_configurable($pdo);
}

function utilities_processing_office_route_destination_is_allowed(PDO $pdo, string $destination): bool
{
    $destination = utilities_processing_office_route_normalize_value($destination);
    if ($destination === '') {
        return false;
    }

    return in_array($destination, utilities_processing_office_route_configurable_destinations($pdo), true);
}

function utilities_processing_office_route_step_label(string $designation): string
{
    $labels = [
        'ICU' => 'Internal Control Unit',
        'Planning Section' => 'Planning Section',
        'Office of the PENRO' => 'Office of the PENRO',
        'Budget Unit' => 'Budget Unit',
        'Accounting Unit' => 'Accounting Unit',
        'Cashiers Unit' => 'Cashiers Unit',
        'TSD-ENGP' => 'TSD-ENGP',
        'Conservation & Development Section' => 'Conservation & Development Section',
        'Accountant III' => 'Chief Accountant',
    ];

    return $labels[$designation] ?? $designation;
}

function utilities_processing_office_route_step_icon(string $designation): string
{
    $icons = [
        'ICU' => 'ri-shield-check-line',
        'Planning Section' => 'ri-map-pin-line',
        'Office of the PENRO' => 'ri-briefcase-4-line',
        'Budget Unit' => 'ri-funds-line',
        'Accounting Unit' => 'ri-file-chart-line',
        'Cashiers Unit' => 'ri-safe-line',
        'TSD-ENGP' => 'ri-plant-line',
        'Conservation & Development Section' => 'ri-leaf-line',
        'Accountant III' => 'ri-file-chart-line',
        'Paid' => 'ri-money-dollar-circle-line',
    ];

    return $icons[$designation] ?? 'ri-building-line';
}

/**
 * Kiosk stepper entries: configured hops plus Paid.
 *
 * @return list<array{designation: string, label: string, icon: string}>
 */
function utilities_processing_office_route_kiosk_steps(PDO $pdo): array
{
    $steps = [];
    foreach (utilities_processing_office_route_active_steps($pdo) as $designation) {
        $steps[] = [
            'designation' => $designation,
            'label' => utilities_processing_office_route_step_label($designation),
            'icon' => utilities_processing_office_route_step_icon($designation),
        ];
    }
    $steps[] = [
        'designation' => 'Paid',
        'label' => 'Paid',
        'icon' => utilities_processing_office_route_step_icon('Paid'),
    ];

    return $steps;
}
