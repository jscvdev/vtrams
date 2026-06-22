<?php

declare(strict_types=1);

const UTILITIES_SIGNATORY_PENRO_OFFICE = 'DENR-PENRO EASTERN SAMAR';

function utilities_signatory_penro_office(): string
{
    return UTILITIES_SIGNATORY_PENRO_OFFICE;
}

function utilities_signatory_can_select_office(): bool
{
    return utilities_signatory_normalize_office(utilities_signatory_default_office())
        === utilities_signatory_normalize_office(utilities_signatory_penro_office());
}

function utilities_signatory_default_office(): string
{
    return trim((string) ($_SESSION['logged_user_office'] ?? ''));
}

function utilities_signatory_normalize_office(string $office): string
{
    return trim(preg_replace('/\s+/', ' ', $office));
}

function utilities_signatory_offices_match(string $a, string $b): bool
{
    return strcasecmp(
        utilities_signatory_normalize_office($a),
        utilities_signatory_normalize_office($b)
    ) === 0;
}

/**
 * Find the canonical office string as stored in user_group (case/spacing tolerant).
 */
function utilities_signatory_find_in_office_list(string $needle, array $offices): ?string
{
    $needle = utilities_signatory_normalize_office($needle);
    if ($needle === '') {
        return null;
    }

    foreach ($offices as $office) {
        if (utilities_signatory_offices_match($needle, (string) $office)) {
            return utilities_signatory_normalize_office((string) $office);
        }
    }

    return null;
}

/**
 * Match an office label to how it is stored on voucher_signatories rows.
 */
function utilities_signatory_match_office_in_signatories(PDO $pdo, string $office): ?string
{
    $office = utilities_signatory_normalize_office($office);

    if ($office === '') {
        $stmt = $pdo->query("
            SELECT 1
            FROM voucher_signatories
            WHERE TRIM(COALESCE(office, '')) = ''
            LIMIT 1
        ");
        return $stmt && $stmt->fetchColumn() ? '' : null;
    }

    $stmt = $pdo->prepare("
        SELECT DISTINCT TRIM(office) AS office
        FROM voucher_signatories
        WHERE LOWER(TRIM(office)) = LOWER(:office)
        LIMIT 1
    ");
    $stmt->execute([':office' => $office]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row
        ? utilities_signatory_normalize_office((string) ($row['office'] ?? ''))
        : null;
}

/**
 * Offices to query for DV signatories: user office, then PENRO defaults, then legacy blank office.
 *
 * @return list<string>
 */
function utilities_signatory_fallback_office_candidates(PDO $pdo, string $office): array
{
    $office = utilities_signatory_normalize_office($office);
    $candidates = [];

    if ($office !== '') {
        $candidates[] = utilities_signatory_match_office_in_signatories($pdo, $office) ?? $office;
    }

    $penro = utilities_signatory_penro_office();
    $storedPenro = utilities_signatory_match_office_in_signatories($pdo, $penro) ?? $penro;
    if ($storedPenro !== '' && !utilities_signatory_offices_match($storedPenro, $office)) {
        $candidates[] = $storedPenro;
    }

    if (!in_array('', $candidates, true)) {
        $candidates[] = '';
    }

    $unique = [];
    foreach ($candidates as $candidate) {
        $normalized = utilities_signatory_normalize_office((string) $candidate);
        $exists = false;
        foreach ($unique as $existing) {
            if (utilities_signatory_offices_match($normalized, $existing)) {
                $exists = true;
                break;
            }
        }
        if (!$exists) {
            $unique[] = $normalized;
        }
    }

    return $unique;
}

function utilities_table_has_column(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = :table
          AND COLUMN_NAME = :column
    ");
    $stmt->execute([':table' => $table, ':column' => $column]);

    return ((int) $stmt->fetchColumn()) > 0;
}

function utilities_table_has_index(PDO $pdo, string $table, string $index): bool
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = :table
          AND INDEX_NAME = :index
    ");
    $stmt->execute([':table' => $table, ':index' => $index]);

    return ((int) $stmt->fetchColumn()) > 0;
}

function utilities_signatory_fetch_offices(PDO $pdo): array
{
    $offices = [];
    try {
        require_once __DIR__ . '/utilities_office_helper.inc.php';
        utilities_office_ensure_schema($pdo);
        foreach (utilities_office_registered_names($pdo, true) as $office) {
            $offices[] = $office;
        }
    } catch (Throwable $e) {
        $offices = [];
    }

    try {
        $stmt = $pdo->query("
            SELECT DISTINCT TRIM(office) AS office
            FROM user_group
            WHERE TRIM(COALESCE(office, '')) <> ''
            ORDER BY office ASC
        ");
        $rows = $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
        foreach ($rows as $row) {
            $office = utilities_signatory_normalize_office((string) ($row['office'] ?? ''));
            if ($office !== '') {
                $offices[] = $office;
            }
        }
    } catch (Throwable $e) {
        // keep registry offices when user_group lookup fails
    }

    $defaultOffice = utilities_signatory_default_office();
    if ($defaultOffice !== '' && !in_array($defaultOffice, $offices, true)) {
        array_unshift($offices, $defaultOffice);
    }

    $unique = [];
    foreach ($offices as $office) {
        $exists = false;
        foreach ($unique as $existing) {
            if (utilities_signatory_offices_match($office, $existing)) {
                $exists = true;
                break;
            }
        }
        if (!$exists) {
            $unique[] = $office;
        }
    }

    return $unique;
}

function utilities_signatory_resolve_office(PDO $pdo, ?string $requestedOffice = null): string
{
    $offices = utilities_signatory_fetch_offices($pdo);
    $requested = utilities_signatory_normalize_office((string) ($requestedOffice ?? ''));

    $canonical = utilities_signatory_find_in_office_list($requested, $offices);
    if ($canonical !== null) {
        return $canonical;
    }

    $defaultOffice = utilities_signatory_default_office();
    $canonical = utilities_signatory_find_in_office_list($defaultOffice, $offices);
    if ($canonical !== null) {
        return $canonical;
    }

    if ($defaultOffice !== '') {
        return $defaultOffice;
    }

    return $offices[0] ?? '';
}

function utilities_signatory_backfill_office(PDO $pdo, string $office): void
{
    $office = utilities_signatory_normalize_office($office);
    if ($office === '') {
        return;
    }

    $pdo->prepare("UPDATE ada_signatory_options SET office = :office WHERE TRIM(COALESCE(office, '')) = ''")
        ->execute([':office' => $office]);
    $pdo->prepare("UPDATE voucher_signatories SET office = :office WHERE TRIM(COALESCE(office, '')) = ''")
        ->execute([':office' => $office]);
}

function utilities_signatory_ensure_schema(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS ada_signatory_options (
            id INT AUTO_INCREMENT PRIMARY KEY,
            option_type VARCHAR(64) NOT NULL,
            office VARCHAR(255) NOT NULL DEFAULT '',
            option_value VARCHAR(255) NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            is_default TINYINT(1) NOT NULL DEFAULT 0,
            sort_order INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_type_value_office (option_type, option_value, office),
            KEY idx_office_type_active_sort (office, option_type, is_active, sort_order, option_value)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS voucher_signatories (
            id INT AUTO_INCREMENT PRIMARY KEY,
            signatory_key VARCHAR(64) NOT NULL,
            office VARCHAR(255) NOT NULL DEFAULT '',
            display_name VARCHAR(255) NOT NULL,
            position_line1 VARCHAR(255) NOT NULL DEFAULT '',
            position_line2 VARCHAR(255) NOT NULL DEFAULT '',
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_key_office (signatory_key, office),
            KEY idx_office_active (office, is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    if (!utilities_table_has_column($pdo, 'ada_signatory_options', 'office')) {
        $pdo->exec("ALTER TABLE ada_signatory_options ADD COLUMN office VARCHAR(255) NOT NULL DEFAULT '' AFTER option_type");
    }
    if (!utilities_table_has_column($pdo, 'ada_signatory_options', 'is_default')) {
        $pdo->exec("ALTER TABLE ada_signatory_options ADD COLUMN is_default TINYINT(1) NOT NULL DEFAULT 0 AFTER is_active");
    }
    if (!utilities_table_has_column($pdo, 'voucher_signatories', 'office')) {
        $pdo->exec("ALTER TABLE voucher_signatories ADD COLUMN office VARCHAR(255) NOT NULL DEFAULT '' AFTER signatory_key");
    }

    $backfillOffice = utilities_signatory_penro_office();
    if ($backfillOffice === '') {
        $backfillOffice = utilities_signatory_resolve_office($pdo, null);
    }
    utilities_signatory_backfill_office($pdo, $backfillOffice);

    if (utilities_table_has_index($pdo, 'ada_signatory_options', 'uniq_type_value')
        && !utilities_table_has_index($pdo, 'ada_signatory_options', 'uniq_type_value_office')) {
        $pdo->exec("ALTER TABLE ada_signatory_options DROP INDEX uniq_type_value");
    }
    if (!utilities_table_has_index($pdo, 'ada_signatory_options', 'uniq_type_value_office')) {
        $pdo->exec("ALTER TABLE ada_signatory_options ADD UNIQUE KEY uniq_type_value_office (option_type, option_value, office)");
    }

    if (utilities_table_has_index($pdo, 'voucher_signatories', 'uniq_key')
        && !utilities_table_has_index($pdo, 'voucher_signatories', 'uniq_key_office')) {
        $pdo->exec("ALTER TABLE voucher_signatories DROP INDEX uniq_key");
    }
    if (!utilities_table_has_index($pdo, 'voucher_signatories', 'uniq_key_office')) {
        $pdo->exec("ALTER TABLE voucher_signatories ADD UNIQUE KEY uniq_key_office (signatory_key, office)");
    }
}

function utilities_fetch_ada_options(PDO $pdo, string $office): array
{
    $office = utilities_signatory_normalize_office($office);
    $stmt = $pdo->prepare("
        SELECT option_type, option_value
        FROM ada_signatory_options
        WHERE is_active = 1
          AND office = :office
          AND option_type IN ('certified_correct', 'approved_by', 'agency_authorized_signatory')
        ORDER BY option_type ASC, sort_order ASC, option_value ASC
    ");
    $stmt->execute([':office' => $office]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $options = [];
    foreach ($rows as $row) {
        $type = (string) ($row['option_type'] ?? '');
        $value = (string) ($row['option_value'] ?? '');
        if ($type !== '' && $value !== '') {
            $options[$type][] = $value;
        }
    }

    return $options;
}

/** @return array<string, string> option_type => default option_value */
function utilities_fetch_ada_option_defaults(PDO $pdo, string $office): array
{
    $office = utilities_signatory_normalize_office($office);
    $stmt = $pdo->prepare("
        SELECT option_type, option_value
        FROM ada_signatory_options
        WHERE is_active = 1
          AND is_default = 1
          AND office = :office
          AND option_type IN ('certified_correct', 'approved_by', 'agency_authorized_signatory')
        ORDER BY option_type ASC, sort_order ASC, id ASC
    ");
    $stmt->execute([':office' => $office]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $defaults = [];
    foreach ($rows as $row) {
        $type = (string) ($row['option_type'] ?? '');
        $value = trim((string) ($row['option_value'] ?? ''));
        if ($type !== '' && $value !== '' && !isset($defaults[$type])) {
            $defaults[$type] = $value;
        }
    }

    return $defaults;
}

/**
 * Resolve ADA signatory options/defaults for an office, falling back to PENRO then legacy blank office.
 *
 * @return array{office: string, options: array<string, list<string>>, defaults: array<string, string>}
 */
function utilities_fetch_ada_signatory_bundle(PDO $pdo, string $office): array
{
    $office = utilities_signatory_normalize_office($office);
    $candidates = [];

    if ($office !== '') {
        $candidates[] = $office;
    }

    $penro = utilities_signatory_penro_office();
    $storedPenro = utilities_signatory_match_office_in_signatories($pdo, $penro) ?? $penro;
    if ($storedPenro !== '' && !utilities_signatory_offices_match($storedPenro, $office)) {
        $candidates[] = $storedPenro;
    }

    if (!in_array('', $candidates, true)) {
        $candidates[] = '';
    }

    $unique = [];
    foreach ($candidates as $candidate) {
        $normalized = utilities_signatory_normalize_office((string) $candidate);
        $exists = false;
        foreach ($unique as $existing) {
            if (utilities_signatory_offices_match($normalized, $existing)) {
                $exists = true;
                break;
            }
        }
        if (!$exists) {
            $unique[] = $normalized;
        }
    }

    foreach ($unique as $candidateOffice) {
        $options = utilities_fetch_ada_options($pdo, $candidateOffice);
        if ($options !== []) {
            return [
                'office' => $candidateOffice,
                'options' => $options,
                'defaults' => utilities_fetch_ada_option_defaults($pdo, $candidateOffice),
            ];
        }
    }

    return [
        'office' => $office,
        'options' => [],
        'defaults' => [],
    ];
}

function utilities_ada_signatory_set_default(PDO $pdo, int $id, string $optionType, string $office): void
{
    $optionType = trim($optionType);
    $office = utilities_signatory_normalize_office($office);
    $stmt = $pdo->prepare("
        UPDATE ada_signatory_options
        SET is_default = 0
        WHERE option_type = :t AND office = :office
    ");
    $stmt->execute([':t' => $optionType, ':office' => $office]);
    $stmt = $pdo->prepare('UPDATE ada_signatory_options SET is_default = 1 WHERE id = :id');
    $stmt->execute([':id' => $id]);
}

function utilities_fetch_dv_signatories(PDO $pdo, string $office): array
{
    $office = utilities_signatory_normalize_office($office);
    $stmt = $pdo->prepare("
        SELECT signatory_key, display_name, position_line1, position_line2, is_active
        FROM voucher_signatories
        WHERE office = :office
        ORDER BY signatory_key ASC
    ");
    $stmt->execute([':office' => $office]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $out = [];
    foreach ($rows as $row) {
        $key = (string) ($row['signatory_key'] ?? '');
        if ($key === '') {
            continue;
        }
        $out[$key] = $row;
    }

    return $out;
}

function utilities_fetch_dv_signatory_map_for_office(PDO $pdo, string $office): array
{
    $office = utilities_signatory_normalize_office($office);
    if ($office === '') {
        $stmt = $pdo->query("
            SELECT signatory_key, display_name, position_line1, position_line2
            FROM voucher_signatories
            WHERE is_active = 1
              AND TRIM(COALESCE(office, '')) = ''
            ORDER BY signatory_key ASC
        ");
        $rows = $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
    } else {
        $stmt = $pdo->prepare("
            SELECT signatory_key, display_name, position_line1, position_line2
            FROM voucher_signatories
            WHERE is_active = 1
              AND LOWER(TRIM(office)) = LOWER(:office)
            ORDER BY signatory_key ASC
        ");
        $stmt->execute([':office' => $office]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    $out = [];
    foreach ($rows as $row) {
        $key = (string) ($row['signatory_key'] ?? '');
        $name = trim((string) ($row['display_name'] ?? ''));
        if ($key === '' || $name === '') {
            continue;
        }
        $out[$key] = [
            'key' => $key,
            'name' => $name,
            'pos1' => (string) ($row['position_line1'] ?? ''),
            'pos2' => (string) ($row['position_line2'] ?? ''),
        ];
    }

    return $out;
}

/**
 * Active DV signatories for an office, merging PENRO/legacy defaults per signatory key.
 */
function utilities_fetch_dv_signatory_map(PDO $pdo, string $office): array
{
    $office = utilities_signatory_normalize_office($office);
    $candidates = utilities_signatory_fallback_office_candidates($pdo, $office);

    $merged = [];
    foreach ($candidates as $candidateOffice) {
        $map = utilities_fetch_dv_signatory_map_for_office($pdo, $candidateOffice);
        foreach ($map as $key => $data) {
            if (!isset($merged[$key])) {
                $merged[$key] = $data;
            }
        }
    }

    return $merged;
}

/** Required DV keys for the print modal. */
function utilities_dv_signatory_required_keys(): array
{
    return [
        'dv_certified_msd',
        'dv_certified_tsd',
        'dv_accounting_certified',
        'dv_approved_for_payment',
    ];
}

/**
 * True when at least one cert option and accounting + approved signatories exist.
 */
function utilities_dv_signatory_map_is_printable(array $map): bool
{
    $hasCert = !empty($map['dv_certified_msd']) || !empty($map['dv_certified_tsd']);
    $hasAccounting = !empty($map['dv_accounting_certified']);
    $hasApproved = !empty($map['dv_approved_for_payment']);

    return $hasCert && $hasAccounting && $hasApproved;
}
