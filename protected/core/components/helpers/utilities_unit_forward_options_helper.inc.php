<?php

declare(strict_types=1);

require_once __DIR__ . '/request_cache.inc.php';

function utilities_unit_forward_options_invalidate_cache(): void
{
    RequestCache::forgetNamespace('unit_forward_options');
}

function utilities_unit_forward_options_normalize_value(string $value): string
{
    return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
}

/**
 * @return list<array{0: string, 1: string, 2: int}>
 */
function utilities_unit_forward_options_default_rows(): array
{
    return [
        ['Liaison Officer', 'ICU', 0],
        ['ICU', 'Planning Section', 0],
        ['ICU', 'Budget Unit', 1],
        ['ICU', 'Accounting Unit', 2],
        ['ICU', 'Accountant III', 3],
        ['ICU', '4HyLy', 4],
        ['ICU', 'YS9M3', 5],
        ['ICU', 's1JxV', 6],
        ['ICU', '5Cw9e', 7],
        ['Planning Section', 'Budget Unit', 0],
        ['Planning Section', 'Planning Section Chief', 1],
        ['Conservation & Development Section', 'Accounting Unit', 0],
        ['Budget Unit', 'Accounting Unit', 0],
        ['Budget Unit', 'Accountant III', 1],
        ['Budget Unit', 'Budget Officer', 2],
        ['Accounting Unit', 'Accountant III', 0],
        ['Accounting Unit', 'Office of the PENRO', 1],
        ['Accounting Unit', 'Cashiers Unit', 2],
        ['Processor', 'Accountant III', 0],
        ['Processor', 'Office of the PENRO', 1],
        ['Processor', 'Cashiers Unit', 2],
        ['Processor', '4HyLy', 3],
        ['Processor', 'YS9M3', 4],
        ['Processor', 's1JxV', 5],
        ['Office of the PENRO', 'Budget Unit', 0],
        ['Office of the PENRO', 'Cashiers Unit', 1],
        ['Cashiers Unit', 'Accountant III', 0],
        ['Cashiers Unit', 'Office of the PENRO', 1],
        ['Cashiers Unit', 'Cashier', 2],
    ];
}

/** @return array<string, string> */
function utilities_unit_forward_options_default_labels(): array
{
    return [
        'Accountant III' => 'Chief Accountant',
        '4HyLy' => '1. Marife C. Briton',
        'YS9M3' => '2. Diana E. Costuna',
        's1JxV' => '3. Gracile B. Palce',
        '5Cw9e' => 'Eda Buen',
    ];
}

function utilities_unit_forward_options_option_class(string $destination): string
{
    return match ($destination) {
        'Accountant III' => 'processed',
        'Planning Section Chief' => 'Planning_Officer',
        'Budget Officer' => 'Budget_Officer',
        'Cashier' => 'Cashier_Officer',
        default => '',
    };
}

function utilities_unit_forward_options_label(string $destination, string $storedLabel = ''): string
{
    $storedLabel = utilities_unit_forward_options_normalize_value($storedLabel);
    if ($storedLabel !== '') {
        return $storedLabel;
    }

    $defaults = utilities_unit_forward_options_default_labels();

    return $defaults[$destination] ?? $destination;
}

function utilities_unit_forward_options_ensure_schema(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS voucher_unit_forward_options (
            id INT AUTO_INCREMENT PRIMARY KEY,
            from_designation VARCHAR(255) NOT NULL,
            to_designation VARCHAR(255) NOT NULL,
            label VARCHAR(255) NULL,
            sort_order INT NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_from_to (from_designation, to_designation),
            KEY idx_from_active_sort (from_designation, is_active, sort_order, id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $count = (int) $pdo->query('SELECT COUNT(*) FROM voucher_unit_forward_options')->fetchColumn();
    if ($count === 0) {
        $stmt = $pdo->prepare("
            INSERT INTO voucher_unit_forward_options (from_designation, to_designation, label, sort_order, is_active)
            VALUES (:from_designation, :to_designation, :label, :sort_order, 1)
        ");
        $labels = utilities_unit_forward_options_default_labels();
        foreach (utilities_unit_forward_options_default_rows() as [$from, $to, $sort]) {
            $stmt->execute([
                ':from_designation' => $from,
                ':to_designation' => $to,
                ':label' => $labels[$to] ?? null,
                ':sort_order' => $sort,
            ]);
        }
    }

    utilities_unit_forward_options_invalidate_cache();
}

/**
 * @return list<array<string, mixed>>
 */
function utilities_unit_forward_options_fetch_all(PDO $pdo): array
{
    utilities_unit_forward_options_ensure_schema($pdo);
    $stmt = $pdo->query(
        'SELECT id, from_designation, to_designation, label, sort_order, is_active
         FROM voucher_unit_forward_options
         ORDER BY from_designation ASC, sort_order ASC, id ASC'
    );

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/**
 * @param list<array<string, mixed>> $rows
 * @return array<string, list<array<string, mixed>>>
 */
function utilities_unit_forward_options_group_by_from(array $rows): array
{
    $grouped = [];
    foreach ($rows as $row) {
        $from = utilities_unit_forward_options_normalize_value((string) ($row['from_designation'] ?? ''));
        if ($from === '') {
            continue;
        }
        $grouped[$from][] = $row;
    }

    return $grouped;
}

/**
 * Units that can have Forward To lists.
 *
 * @return list<string>
 */
function utilities_unit_forward_options_source_units(PDO $pdo): array
{
    require_once __DIR__ . '/utilities_special_access_helper.inc.php';
    $units = utilities_special_access_forward_destinations_configurable($pdo);
    foreach ([
        'Liaison Officer',
        'Planning Section Chief',
        'Budget Officer',
        'Processor',
        'Accountant III',
        'Cashier',
        'PENR Officer',
    ] as $extra) {
        if (!in_array($extra, $units, true)) {
            $units[] = $extra;
        }
    }

    return $units;
}

/**
 * Destinations that can be added to a unit's Forward To list.
 *
 * @return list<string>
 */
function utilities_unit_forward_options_target_units(PDO $pdo): array
{
    $units = utilities_unit_forward_options_source_units($pdo);
    foreach (array_keys(utilities_unit_forward_options_default_labels()) as $code) {
        if (!in_array($code, $units, true)) {
            $units[] = $code;
        }
    }

    return $units;
}

function utilities_unit_forward_options_destination_is_allowed(PDO $pdo, string $destination): bool
{
    $destination = utilities_unit_forward_options_normalize_value($destination);
    if ($destination === '') {
        return false;
    }

    return in_array($destination, utilities_unit_forward_options_target_units($pdo), true);
}

/**
 * Active Forward To values for one source unit.
 *
 * @return list<array{value: string, label: string, class: string}>
 */
function utilities_unit_forward_options_active_for_unit(PDO $pdo, string $from_designation): array
{
    $from_designation = utilities_unit_forward_options_normalize_value($from_designation);
    if ($from_designation === '') {
        return [];
    }

    return RequestCache::remember(
        'unit_forward_options',
        'active:' . $from_designation,
        static function () use ($pdo, $from_designation): array {
            utilities_unit_forward_options_ensure_schema($pdo);
            $stmt = $pdo->prepare(
                'SELECT to_designation, label, sort_order
                 FROM voucher_unit_forward_options
                 WHERE from_designation = :from_designation AND is_active = 1
                 ORDER BY sort_order ASC, id ASC'
            );
            $stmt->execute([':from_designation' => $from_designation]);
            $found = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $value = utilities_unit_forward_options_normalize_value((string) ($row['to_designation'] ?? ''));
                if ($value === '') {
                    continue;
                }
                $found[] = [
                    'value' => $value,
                    'label' => utilities_unit_forward_options_label($value, (string) ($row['label'] ?? '')),
                    'class' => utilities_unit_forward_options_option_class($value),
                ];
            }

            return $found;
        }
    );
}

/**
 * Union of Forward To options for the logged-in processing units.
 *
 * @param list<string> $user_designations
 * @return list<array{value: string, label: string, class: string}>
 */
function utilities_unit_forward_options_for_user(
    PDO $pdo,
    array $user_designations,
    string $logged_udc = ''
): array {
    $aliases = [
        'Planning Section Chief' => 'Planning Section',
        'Budget Officer' => 'Budget Unit',
        'Accountant III' => 'Accounting Unit',
        'Cashier' => 'Cashiers Unit',
        'PENR Officer' => 'Office of the PENRO',
    ];

    $sources = [];
    foreach ($user_designations as $designation) {
        $designation = utilities_unit_forward_options_normalize_value((string) $designation);
        if ($designation === '') {
            continue;
        }
        $sources[$designation] = true;
        $mapped = $aliases[$designation] ?? '';
        if ($mapped !== '') {
            $sources[$mapped] = true;
        }
    }

    $out = [];
    $seen = [];
    $logged_udc = trim($logged_udc);
    $own = [];
    foreach ($user_designations as $designation) {
        $name = utilities_unit_forward_options_normalize_value((string) $designation);
        if ($name !== '') {
            $own[strtolower($name)] = true;
        }
    }
    if ($logged_udc !== '') {
        $own[strtolower($logged_udc)] = true;
    }

    foreach (array_keys($sources) as $from) {
        foreach (utilities_unit_forward_options_active_for_unit($pdo, $from) as $option) {
            $value = $option['value'];
            if (isset($seen[$value]) || isset($own[strtolower($value)])) {
                continue;
            }
            $seen[$value] = true;
            $out[] = $option;
        }
    }

    return $out;
}

function utilities_unit_forward_option_is_allowed(
    PDO $pdo,
    array $user_designations,
    string $document_to,
    string $logged_udc = ''
): bool {
    $document_to = utilities_unit_forward_options_normalize_value($document_to);
    if ($document_to === '') {
        return false;
    }

    $options = utilities_unit_forward_options_for_user($pdo, $user_designations, $logged_udc);
    if ($options === []) {
        return true;
    }

    foreach ($options as $option) {
        if (strcasecmp($option['value'], $document_to) === 0) {
            return true;
        }
    }

    return false;
}

/**
 * @param list<array{value: string, label: string, class: string}> $options
 */
function utilities_unit_forward_options_select_html(array $options, bool $liaisonOnly = false): string
{
    if ($liaisonOnly) {
        return "<option value='ICU' selected>ICU</option>";
    }

    $html = '<option value="" disabled selected>Please Select</option>';
    foreach ($options as $option) {
        $value = htmlspecialchars($option['value'], ENT_QUOTES, 'UTF-8');
        $label = htmlspecialchars($option['label'], ENT_QUOTES, 'UTF-8');
        $class = trim($option['class']);
        $classAttr = $class !== '' ? ' class="' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . '"' : '';
        $html .= "<option value='{$value}'{$classAttr}>{$label}</option>";
    }

    return $html;
}
