<?php

declare(strict_types=1);

require_once __DIR__ . '/request_cache.inc.php';

const VOUCHER_TYPE_CACHE_NS = 'voucher_type_settings';

function utilities_voucher_type_normalize_value(string $value): string
{
    return trim(preg_replace('/\s+/', ' ', $value));
}

function utilities_voucher_type_invalidate_cache(): void
{
    RequestCache::forgetNamespace(VOUCHER_TYPE_CACHE_NS);
}

/**
 * Fields that can be locked on the new-voucher form.
 *
 * @return array<string, string> field_key => label
 */
function utilities_voucher_type_lockable_fields(): array
{
    return [
        'payee' => 'Payee',
        'tin_employee_no' => 'TIN / Employee No.',
        'address' => 'Address',
        'voucher_date' => 'Voucher Date',
        'particulars' => 'Particulars',
        'amount' => 'Amount',
        'month_year' => 'Month / Year (TEV)',
        'engp_quarter' => 'eNGP Quarter',
        'engp_year' => 'eNGP Year',
        'engp_area' => 'eNGP Area',
        'engp_commodity' => 'eNGP Commodity',
        'engp_location' => 'eNGP Location',
    ];
}

function utilities_voucher_type_default_particulars(): string
{
    return 'For payment as per supporting documents hereto attached in the total amount of ......';
}

function utilities_voucher_type_ensure_schema(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS voucher_type_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            type_key VARCHAR(150) NOT NULL,
            display_label VARCHAR(150) NULL DEFAULT NULL,
            default_particulars TEXT NULL,
            locked_fields_json TEXT NOT NULL,
            require_particulars_edit TINYINT(1) NOT NULL DEFAULT 1,
            sort_order INT NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_voucher_type_key (type_key),
            KEY idx_active_sort (is_active, sort_order, type_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $count = (int) $pdo->query('SELECT COUNT(*) FROM voucher_type_settings')->fetchColumn();
    if ($count === 0) {
        utilities_voucher_type_seed_defaults($pdo);
    }
}

function utilities_voucher_type_encode_locked_fields(array $fields): string
{
    $allowed = array_keys(utilities_voucher_type_lockable_fields());
    $out = [];
    foreach ($fields as $field) {
        $field = (string) $field;
        if (in_array($field, $allowed, true) && !in_array($field, $out, true)) {
            $out[] = $field;
        }
    }

    return json_encode($out, JSON_UNESCAPED_UNICODE) ?: '[]';
}

function utilities_voucher_type_decode_locked_fields(?string $json): array
{
    if ($json === null || trim($json) === '') {
        return [];
    }
    $decoded = json_decode($json, true);
    if (!is_array($decoded)) {
        return [];
    }
    $allowed = array_keys(utilities_voucher_type_lockable_fields());
    $out = [];
    foreach ($decoded as $field) {
        $field = (string) $field;
        if (in_array($field, $allowed, true) && !in_array($field, $out, true)) {
            $out[] = $field;
        }
    }

    return $out;
}

function utilities_voucher_type_parse_locked_fields_from_post(array $post): array
{
    $raw = $post['locked_fields'] ?? [];
    if (!is_array($raw)) {
        return [];
    }

    return utilities_voucher_type_decode_locked_fields(
        utilities_voucher_type_encode_locked_fields($raw)
    );
}

function utilities_voucher_type_row_to_config(array $row): array
{
    return [
        'id' => (int) ($row['id'] ?? 0),
        'type_key' => (string) ($row['type_key'] ?? ''),
        'display_label' => $row['display_label'] !== null ? (string) $row['display_label'] : '',
        'default_particulars' => (string) ($row['default_particulars'] ?? utilities_voucher_type_default_particulars()),
        'locked_fields' => utilities_voucher_type_decode_locked_fields($row['locked_fields_json'] ?? '[]'),
        'require_particulars_edit' => (int) ($row['require_particulars_edit'] ?? 1) === 1,
        'sort_order' => (int) ($row['sort_order'] ?? 0),
        'is_active' => (int) ($row['is_active'] ?? 1) === 1,
    ];
}

function utilities_voucher_type_fetch_all(PDO $pdo, bool $activeOnly = false): array
{
    utilities_voucher_type_ensure_schema($pdo);

    $sql = '
        SELECT id, type_key, display_label, default_particulars, locked_fields_json,
               require_particulars_edit, sort_order, is_active
        FROM voucher_type_settings
    ';
    if ($activeOnly) {
        $sql .= ' WHERE is_active = 1';
    }
    $sql .= ' ORDER BY sort_order ASC, type_key ASC';

    $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];

    return array_map(static fn(array $row): array => utilities_voucher_type_row_to_config($row), $rows);
}

function utilities_voucher_type_fetch_map(PDO $pdo, bool $activeOnly = true): array
{
    return RequestCache::remember(
        VOUCHER_TYPE_CACHE_NS,
        'map:' . ($activeOnly ? 'active' : 'all'),
        static function () use ($pdo, $activeOnly): array {
            $map = [];
            foreach (utilities_voucher_type_fetch_all($pdo, $activeOnly) as $row) {
                $key = (string) ($row['type_key'] ?? '');
                if ($key !== '') {
                    $map[$key] = $row;
                }
            }

            return $map;
        }
    );
}

function utilities_voucher_type_find_by_id(PDO $pdo, int $id): ?array
{
    if ($id <= 0) {
        return null;
    }
    utilities_voucher_type_ensure_schema($pdo);
    $stmt = $pdo->prepare('
        SELECT id, type_key, display_label, default_particulars, locked_fields_json,
               require_particulars_edit, sort_order, is_active
        FROM voucher_type_settings
        WHERE id = :id
        LIMIT 1
    ');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ? utilities_voucher_type_row_to_config($row) : null;
}

function utilities_voucher_type_known_type_keys(PDO $pdo): array
{
    $keys = [];
    foreach (utilities_voucher_type_fetch_all($pdo) as $row) {
        $key = (string) ($row['type_key'] ?? '');
        if ($key !== '') {
            $keys[$key] = true;
        }
    }

    $checklistPath = dirname(__DIR__, 4) . '/public/vouchers/checklist_config.php';
    if (is_file($checklistPath)) {
        require_once $checklistPath;
        foreach (checklist_types_with_labels() as $typeKey => $label) {
            $typeKey = utilities_voucher_type_normalize_value((string) $typeKey);
            if ($typeKey !== '') {
                $keys[$typeKey] = true;
            }
        }
    }

    $sorted = array_keys($keys);
    sort($sorted, SORT_NATURAL | SORT_FLAG_CASE);

    return $sorted;
}

function utilities_voucher_type_seed_defaults(PDO $pdo): void
{
    $types = [];
    $checklistPath = dirname(__DIR__, 4) . '/public/vouchers/checklist_config.php';
    if (is_file($checklistPath)) {
        require_once $checklistPath;
        $types = checklist_types_with_labels();
    }

    if ($types === []) {
        return;
    }

    $payeeLockedTypes = [
        'Traveling Expenses',
        'PRE-Traveling Expenses',
        'Contractual Services or Job Order',
        'Contractual Services or Job Order Salary',
        'TEV',
    ];

    $knownParticulars = [
        'TEV' => 'For payment of Traveling Expenses for the month of <MONTH YEAR> as per supporting documents hereto attached in the total amount of ......',
        'Traveling Expenses' => 'For payment of Traveling Expenses for the month of <MONTH YEAR> as per supporting documents hereto attached in the total amount of ......',
        'eNGP' => 'For payment of eNGP / Reforestation for <QUARTER> quarter CY <YEAR> covering <AREA> hectares of <COMMODITY> at <LOCATION> as per supporting documents hereto attached in the total amount of ......',
    ];

    $insert = $pdo->prepare("
        INSERT INTO voucher_type_settings
            (type_key, display_label, default_particulars, locked_fields_json, require_particulars_edit, sort_order, is_active)
        VALUES
            (:type_key, :display_label, :default_particulars, :locked_fields_json, :require_particulars_edit, :sort_order, 1)
    ");

    $sort = 0;
    foreach ($types as $typeKey => $label) {
        $typeKey = utilities_voucher_type_normalize_value((string) $typeKey);
        if ($typeKey === '') {
            continue;
        }

        $locked = in_array($typeKey, $payeeLockedTypes, true) ? ['payee'] : [];
        $particulars = $knownParticulars[$typeKey] ?? utilities_voucher_type_default_particulars();

        $insert->execute([
            ':type_key' => $typeKey,
            ':display_label' => utilities_voucher_type_normalize_value((string) $label) ?: null,
            ':default_particulars' => $particulars,
            ':locked_fields_json' => utilities_voucher_type_encode_locked_fields($locked),
            ':require_particulars_edit' => in_array($typeKey, $payeeLockedTypes, true) ? 0 : 1,
            ':sort_order' => $sort++,
        ]);
    }

    utilities_voucher_type_invalidate_cache();
}

function utilities_voucher_type_client_payload(PDO $pdo): array
{
    $map = utilities_voucher_type_fetch_map($pdo, true);
    $particularsMap = [];
    $settingsMap = [];

    foreach ($map as $typeKey => $row) {
        $particularsMap[$typeKey] = (string) ($row['default_particulars'] ?? utilities_voucher_type_default_particulars());
        $settingsMap[$typeKey] = [
            'lockedFields' => $row['locked_fields'] ?? [],
            'requireParticularsEdit' => !empty($row['require_particulars_edit']),
        ];
    }

    return [
        'particularsMap' => $particularsMap,
        'settingsMap' => $settingsMap,
        'defaultParticulars' => utilities_voucher_type_default_particulars(),
    ];
}
