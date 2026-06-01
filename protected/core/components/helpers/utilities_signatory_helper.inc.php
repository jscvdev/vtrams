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
        $offices = [];
    }

    $defaultOffice = utilities_signatory_default_office();
    if ($defaultOffice !== '' && !in_array($defaultOffice, $offices, true)) {
        array_unshift($offices, $defaultOffice);
    }

    return array_values(array_unique($offices));
}

function utilities_signatory_resolve_office(PDO $pdo, ?string $requestedOffice = null): string
{
    $offices = utilities_signatory_fetch_offices($pdo);
    $requested = utilities_signatory_normalize_office((string) ($requestedOffice ?? ''));

    if ($requested !== '' && in_array($requested, $offices, true)) {
        return $requested;
    }

    $defaultOffice = utilities_signatory_default_office();
    if ($defaultOffice !== '' && in_array($defaultOffice, $offices, true)) {
        return $defaultOffice;
    }

    return $offices[0] ?? $defaultOffice;
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
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS ada_signatory_options (
            id INT AUTO_INCREMENT PRIMARY KEY,
            option_type VARCHAR(64) NOT NULL,
            office VARCHAR(255) NOT NULL DEFAULT '',
            option_value VARCHAR(255) NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
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
    if (!utilities_table_has_column($pdo, 'voucher_signatories', 'office')) {
        $pdo->exec("ALTER TABLE voucher_signatories ADD COLUMN office VARCHAR(255) NOT NULL DEFAULT '' AFTER signatory_key");
    }

    $backfillOffice = utilities_signatory_resolve_office($pdo, null);
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

function utilities_fetch_dv_signatory_map(PDO $pdo, string $office): array
{
    $office = utilities_signatory_normalize_office($office);
    $stmt = $pdo->prepare("
        SELECT signatory_key, display_name, position_line1, position_line2
        FROM voucher_signatories
        WHERE is_active = 1
          AND office = :office
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
        $out[$key] = [
            'key' => $key,
            'name' => (string) ($row['display_name'] ?? ''),
            'pos1' => (string) ($row['position_line1'] ?? ''),
            'pos2' => (string) ($row['position_line2'] ?? ''),
        ];
    }

    return $out;
}
