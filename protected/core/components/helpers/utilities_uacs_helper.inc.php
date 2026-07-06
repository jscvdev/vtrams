<?php

declare(strict_types=1);

require_once __DIR__ . '/request_cache.inc.php';
require_once __DIR__ . '/utilities_emp_tag_helper.inc.php';
require_once __DIR__ . '/utilities_list_filter_helper.inc.php';

const UACS_CODE_CACHE_NS = 'uacs_code_options';

/** Voucher types that use employee-tag salary UACS rows on the DV. */
function utilities_uacs_salary_voucher_types(): array
{
    return [
        'Contractual Services or Job Order',
        'Contractual Services or Job Order Salary',
    ];
}

function utilities_uacs_is_salary_voucher_type(string $voucherType): bool
{
    return in_array(trim($voucherType), utilities_uacs_salary_voucher_types(), true);
}

function utilities_uacs_default_voucher_type(array $voucherTypes): string
{
    foreach (utilities_uacs_salary_voucher_types() as $preferred) {
        if (isset($voucherTypes[$preferred])) {
            return $preferred;
        }
    }

    foreach (array_keys($voucherTypes) as $typeKey) {
        if (trim((string) $typeKey) !== '') {
            return (string) $typeKey;
        }
    }

    return '';
}

/**
 * @param array<string, string> $voucherTypes
 */
function utilities_uacs_resolve_voucher_type(string $raw, array $voucherTypes): string
{
    $raw = trim($raw);
    if ($raw !== '' && isset($voucherTypes[$raw])) {
        return $raw;
    }

    return '';
}

/** @return list<array{voucher_type: string, account_title: string, uacs_code: string, is_indented: int, sort_order: int}> */
function utilities_uacs_builtin_defaults(): array
{
    $rows = [];
    $salaryType = 'Contractual Services or Job Order';

    foreach (utilities_emp_tag_builtin_defaults() as $tag) {
        $title = trim((string) ($tag['tag_value'] ?? ''));
        $uacs = trim((string) ($tag['uacs_code'] ?? ''));
        if ($title === '' || $uacs === '') {
            continue;
        }
        $rows[] = [
            'voucher_type' => $salaryType,
            'tag_name' => $title,
            'account_title' => $title,
            'uacs_code' => $uacs,
            'is_indented' => 0,
            'sort_order' => (int) ($tag['sort_order'] ?? 0),
        ];
    }
    foreach (utilities_emp_tag_builtin_sub_uacs() as $sub) {
        $title = trim((string) ($sub['account_title'] ?? ''));
        $uacs = trim((string) ($sub['uacs_code'] ?? ''));
        if ($title === '' || $uacs === '') {
            continue;
        }
        foreach (utilities_emp_tag_builtin_defaults() as $tag) {
            $tagName = trim((string) ($tag['tag_value'] ?? ''));
            if ($tagName === '') {
                continue;
            }
            $rows[] = [
                'voucher_type' => $salaryType,
                'tag_name' => $tagName,
                'account_title' => $title,
                'uacs_code' => $uacs,
                'is_indented' => 1,
                'sort_order' => 100 + (int) ($sub['sort_order'] ?? 0),
            ];
        }
    }

    return $rows;
}

function utilities_uacs_normalize_title(string $value): string
{
    return trim(preg_replace('/\s+/', ' ', $value));
}

function utilities_uacs_invalidate_cache(): void
{
    RequestCache::forgetNamespace(UACS_CODE_CACHE_NS);
}

function utilities_uacs_ensure_schema(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS uacs_code_options (
            id INT AUTO_INCREMENT PRIMARY KEY,
            voucher_type VARCHAR(150) NOT NULL DEFAULT '',
            tag_name VARCHAR(150) NOT NULL DEFAULT '',
            account_title VARCHAR(255) NOT NULL,
            parent_account_title VARCHAR(255) NOT NULL DEFAULT '',
            uacs_code VARCHAR(32) NOT NULL DEFAULT '',
            is_indented TINYINT(1) NOT NULL DEFAULT 0,
            sort_order INT NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_voucher_tag_uacs (voucher_type, tag_name, parent_account_title, account_title),
            KEY idx_type_active_sort (voucher_type, is_active, sort_order, account_title)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    utilities_uacs_migrate_schema($pdo);
    utilities_uacs_migrate_from_emp_tags($pdo);

    $count = (int) $pdo->query('SELECT COUNT(*) FROM uacs_code_options')->fetchColumn();
    if ($count === 0) {
        $insert = $pdo->prepare("
            INSERT INTO uacs_code_options (voucher_type, tag_name, account_title, parent_account_title, uacs_code, is_indented, sort_order, is_active)
            VALUES (:voucher_type, :tag_name, :title, :parent, :uacs, :indented, :sort, 1)
        ");
        foreach (utilities_uacs_builtin_defaults() as $row) {
            $tagName = (string) ($row['tag_name'] ?? '');
            $isIndented = (int) ($row['is_indented'] ?? 0) === 1;
            $insert->execute([
                ':voucher_type' => $row['voucher_type'],
                ':tag_name' => $tagName,
                ':title' => $row['account_title'],
                ':parent' => $isIndented ? utilities_uacs_primary_account_title($tagName) : '',
                ':uacs' => $row['uacs_code'],
                ':indented' => $isIndented ? 1 : 0,
                ':sort' => (int) $row['sort_order'],
            ]);
        }
    }
}

function utilities_uacs_migrate_schema(PDO $pdo): void
{
    $columns = [];
    $stmt = $pdo->query('SHOW COLUMNS FROM uacs_code_options');
    if ($stmt) {
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $columns[(string) ($row['Field'] ?? '')] = true;
        }
    }

    if (!isset($columns['voucher_type'])) {
        $pdo->exec("ALTER TABLE uacs_code_options ADD COLUMN voucher_type VARCHAR(150) NOT NULL DEFAULT '' AFTER id");
        $pdo->exec("UPDATE uacs_code_options SET voucher_type = 'Contractual Services or Job Order' WHERE voucher_type = ''");
    }

    if (!isset($columns['is_indented'])) {
        if (isset($columns['category'])) {
            $pdo->exec("ALTER TABLE uacs_code_options ADD COLUMN is_indented TINYINT(1) NOT NULL DEFAULT 0 AFTER uacs_code");
            $pdo->exec("
                UPDATE uacs_code_options
                SET is_indented = 1
                WHERE category LIKE '%liability%' OR category LIKE '%Salary%'
            ");
            try {
                $pdo->exec('ALTER TABLE uacs_code_options DROP COLUMN category');
            } catch (PDOException $e) {
                // ignore if already dropped
            }
        } else {
            $pdo->exec("ALTER TABLE uacs_code_options ADD COLUMN is_indented TINYINT(1) NOT NULL DEFAULT 0 AFTER uacs_code");
        }
    }

    if (!isset($columns['parent_account_title'])) {
        $pdo->exec("ALTER TABLE uacs_code_options ADD COLUMN parent_account_title VARCHAR(255) NOT NULL DEFAULT '' AFTER account_title");
        $pdo->exec("
            UPDATE uacs_code_options sub
            INNER JOIN (
                SELECT voucher_type, MIN(account_title) AS first_primary
                FROM uacs_code_options
                WHERE is_indented = 0 AND parent_account_title = ''
                GROUP BY voucher_type
            ) primary_rows ON primary_rows.voucher_type = sub.voucher_type
            SET sub.parent_account_title = primary_rows.first_primary
            WHERE sub.is_indented = 1 AND sub.parent_account_title = ''
        ");
    }

    if (!isset($columns['tag_name'])) {
        $pdo->exec("ALTER TABLE uacs_code_options ADD COLUMN tag_name VARCHAR(150) NOT NULL DEFAULT '' AFTER voucher_type");
        $pdo->exec("
            UPDATE uacs_code_options
            SET tag_name = account_title
            WHERE is_indented = 0
              AND parent_account_title = ''
              AND tag_name = ''
              AND account_title <> ''
        ");
        $pdo->exec("
            UPDATE uacs_code_options sub
            INNER JOIN uacs_code_options primary_row
                ON primary_row.voucher_type = sub.voucher_type
               AND primary_row.account_title = sub.parent_account_title
               AND primary_row.is_indented = 0
               AND primary_row.parent_account_title = ''
            SET sub.tag_name = primary_row.tag_name
            WHERE sub.is_indented = 1
              AND sub.tag_name = ''
        ");
    }

    try {
        $pdo->exec('ALTER TABLE uacs_code_options DROP INDEX uniq_uacs_account_title');
    } catch (PDOException $e) {
        // ignore
    }

    try {
        $pdo->exec('ALTER TABLE uacs_code_options DROP INDEX uniq_voucher_uacs_title');
    } catch (PDOException $e) {
        // ignore
    }

    try {
        $pdo->exec('ALTER TABLE uacs_code_options DROP INDEX uniq_voucher_uacs_parent_title');
    } catch (PDOException $e) {
        // ignore
    }

    try {
        $pdo->exec('ALTER TABLE uacs_code_options ADD UNIQUE KEY uniq_voucher_tag_uacs (voucher_type, tag_name, parent_account_title, account_title)');
    } catch (PDOException $e) {
        // ignore if exists
    }
}

/** @return list<string> */
function utilities_uacs_equivalent_voucher_types(string $voucherType): array
{
    $voucherType = trim($voucherType);
    if ($voucherType === '') {
        return [];
    }

    foreach (utilities_uacs_salary_voucher_types() as $salaryType) {
        if ($voucherType === $salaryType) {
            return utilities_uacs_salary_voucher_types();
        }
    }

    return [$voucherType];
}

function utilities_uacs_canonical_salary_voucher_type(PDO $pdo): string
{
    foreach (utilities_uacs_salary_voucher_types() as $type) {
        $stmt = $pdo->prepare('SELECT 1 FROM uacs_code_options WHERE voucher_type = :t LIMIT 1');
        $stmt->execute([':t' => $type]);
        if ($stmt->fetchColumn()) {
            return $type;
        }
    }

    return utilities_uacs_salary_voucher_types()[0] ?? 'Contractual Services or Job Order';
}

function utilities_uacs_migrate_from_emp_tags(PDO $pdo): void
{
    utilities_emp_tag_ensure_schema($pdo);
    $salaryType = utilities_uacs_canonical_salary_voucher_type($pdo);
    $stmt = $pdo->query("
        SELECT id, tag_value, uacs_code, sort_order
        FROM emp_tag_options
        WHERE TRIM(uacs_code) <> ''
        ORDER BY sort_order ASC, tag_value ASC
    ");
    $tags = $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
    if (!$tags) {
        return;
    }

    $insertPrimary = $pdo->prepare("
        INSERT INTO uacs_code_options (voucher_type, tag_name, account_title, parent_account_title, uacs_code, is_indented, sort_order, is_active)
        VALUES (:voucher_type, :tag_name, :title, '', :uacs, 0, :sort, 1)
    ");
    $insertSub = $pdo->prepare("
        INSERT INTO uacs_code_options (voucher_type, tag_name, account_title, parent_account_title, uacs_code, is_indented, sort_order, is_active)
        VALUES (:voucher_type, :tag_name, :title, :parent, :uacs, 1, :sort, 1)
    ");

    foreach ($tags as $tag) {
        $tagName = utilities_uacs_normalize_tag_name((string) ($tag['tag_value'] ?? ''));
        $uacs = trim((string) ($tag['uacs_code'] ?? ''));
        if ($tagName === '' || $uacs === '') {
            continue;
        }
        if (utilities_uacs_scope_exists($pdo, $salaryType, $tagName)) {
            continue;
        }

        $insertPrimary->execute([
            ':voucher_type' => $salaryType,
            ':tag_name' => $tagName,
            ':title' => utilities_uacs_primary_account_title($tagName),
            ':uacs' => $uacs,
            ':sort' => (int) ($tag['sort_order'] ?? 0),
        ]);

        $tagId = (int) ($tag['id'] ?? 0);
        $legacySubs = $tagId > 0 ? utilities_emp_tag_fetch_sub_uacs($pdo, $tagId, false) : [];
        $parentTitle = utilities_uacs_primary_account_title($tagName);
        if ($legacySubs) {
            foreach ($legacySubs as $sub) {
                $title = utilities_uacs_normalize_title((string) ($sub['account_title'] ?? ''));
                $subUacs = trim((string) ($sub['uacs_code'] ?? ''));
                if ($title === '' || $subUacs === '') {
                    continue;
                }
                $insertSub->execute([
                    ':voucher_type' => $salaryType,
                    ':tag_name' => $tagName,
                    ':title' => $title,
                    ':parent' => $parentTitle,
                    ':uacs' => $subUacs,
                    ':sort' => 100 + (int) ($sub['sort_order'] ?? 0),
                ]);
            }
        } else {
            utilities_uacs_seed_sub_for_scope($pdo, $salaryType, $tagName);
        }
    }
}

/**
 * @param array<string, array<string, array{primary: array<string, mixed>|null, subs: list<array<string, mixed>>}>> $grouped
 * @param list<string>|array<string, string> $knownTagNames
 * @return array<string, array<string, array{primary: array<string, mixed>|null, subs: list<array<string, mixed>>}>>
 */
function utilities_uacs_merge_emp_tag_scopes(PDO $pdo, array $grouped, array $knownTagNames, string $voucherType): array
{
    $voucherType = trim($voucherType);
    if ($voucherType === '' || !utilities_uacs_is_salary_voucher_type($voucherType)) {
        return $grouped;
    }

    $typeKey = isset($grouped[$voucherType]) ? $voucherType : utilities_uacs_canonical_salary_voucher_type($pdo);
    if (!isset($grouped[$typeKey])) {
        $grouped[$typeKey] = [];
    }

    foreach ($knownTagNames as $tagName) {
        $tagName = utilities_uacs_normalize_tag_name((string) $tagName);
        if ($tagName === '') {
            continue;
        }
        if (!isset($grouped[$typeKey][$tagName])) {
            $grouped[$typeKey][$tagName] = ['primary' => null, 'subs' => []];
        }
    }

    return $grouped;
}

/**
 * @param array<string, array<string, array{primary: array<string, mixed>|null, subs: list<array<string, mixed>>}>> $grouped
 */
function utilities_uacs_count_scopes(array $grouped): int
{
    $count = 0;
    foreach ($grouped as $tags) {
        $count += count($tags);
    }

    return $count;
}

function utilities_uacs_normalize_tag_name(string $value): string
{
    return utilities_emp_tag_normalize_value($value);
}

function utilities_uacs_primary_account_title(string $tagName): string
{
    return $tagName !== '' ? $tagName : 'Default';
}

function utilities_uacs_resolve_primary_account_title(string $tagName, string $submittedTitle): string
{
    $tagName = utilities_uacs_normalize_tag_name($tagName);
    if ($tagName !== '') {
        return utilities_uacs_primary_account_title($tagName);
    }

    $title = utilities_uacs_normalize_title($submittedTitle);

    return $title !== '' ? $title : 'Default';
}

function utilities_uacs_parent_title_for_scope(PDO $pdo, string $voucherType, string $tagName): string
{
    utilities_uacs_ensure_schema($pdo);
    $tagName = utilities_uacs_normalize_tag_name($tagName);
    $stmt = $pdo->prepare("
        SELECT account_title
        FROM uacs_code_options
        WHERE voucher_type = :vt
          AND tag_name = :tag
          AND is_indented = 0
          AND parent_account_title = ''
        LIMIT 1
    ");
    $stmt->execute([':vt' => trim($voucherType), ':tag' => $tagName]);
    $title = $stmt->fetchColumn();
    if (is_string($title) && trim($title) !== '') {
        return trim($title);
    }

    return utilities_uacs_primary_account_title($tagName);
}

function utilities_uacs_sync_sub_parent_titles(
    PDO $pdo,
    string $voucherType,
    string $tagName,
    string $oldParent,
    string $newParent
): void {
    $oldParent = utilities_uacs_normalize_title($oldParent);
    $newParent = utilities_uacs_normalize_title($newParent);
    if ($oldParent === '' || $newParent === '' || $oldParent === $newParent) {
        return;
    }

    $stmt = $pdo->prepare("
        UPDATE uacs_code_options
        SET parent_account_title = :new_parent
        WHERE voucher_type = :vt
          AND tag_name = :tag
          AND is_indented = 1
          AND parent_account_title = :old_parent
    ");
    $stmt->execute([
        ':new_parent' => $newParent,
        ':old_parent' => $oldParent,
        ':vt' => trim($voucherType),
        ':tag' => utilities_uacs_normalize_tag_name($tagName),
    ]);
}

/** @return list<array<string, mixed>> */
function utilities_uacs_fetch_all(PDO $pdo, ?string $voucherType = null): array
{
    utilities_uacs_ensure_schema($pdo);

    $sql = "
        SELECT id, voucher_type, tag_name, account_title, parent_account_title, uacs_code, is_indented, sort_order, is_active
        FROM uacs_code_options
    ";
    $params = [];
    if ($voucherType !== null && trim($voucherType) !== '') {
        $sql .= ' WHERE voucher_type = :voucher_type';
        $params[':voucher_type'] = trim($voucherType);
    }
    $sql .= ' ORDER BY voucher_type ASC, sort_order ASC, account_title ASC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/**
 * @param array<string, string> $voucherTypes
 * @return array{q: string, voucher_type: string, is_filtered: bool}
 */
function utilities_uacs_filter_params(array $voucherTypes): array
{
    $q = trim((string) ($_GET['q'] ?? ''));
    $voucherType = utilities_uacs_resolve_voucher_type((string) ($_GET['voucher_type'] ?? ''), $voucherTypes);

    return [
        'q' => $q,
        'voucher_type' => $voucherType,
        'is_filtered' => ($q !== '' || $voucherType !== ''),
    ];
}

/**
 * @param list<array<string, mixed>> $rows
 * @param list<string> $searchFields
 * @return list<array<string, mixed>>
 */
function utilities_uacs_filter_rows(array $rows, string $q, string $voucherType, array $searchFields): array
{
    $typeMatches = utilities_uacs_equivalent_voucher_types($voucherType);
    $filtered = [];
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        if ($voucherType !== '') {
            $rowType = (string) ($row['voucher_type'] ?? '');
            if (!in_array($rowType, $typeMatches, true)) {
                continue;
            }
        }
        if (!utilities_list_text_matches($row, $q, $searchFields)) {
            continue;
        }
        $filtered[] = $row;
    }

    return $filtered;
}

/**
 * @param list<array<string, mixed>> $rows
 * @return array<string, array<string, array{primary: array<string, mixed>|null, subs: list<array<string, mixed>>}>>
 */
function utilities_uacs_group_rows(array $rows): array
{
    $grouped = [];

    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $type = trim((string) ($row['voucher_type'] ?? ''));
        if ($type === '') {
            continue;
        }
        $tagName = utilities_uacs_normalize_tag_name((string) ($row['tag_name'] ?? ''));
        $title = trim((string) ($row['account_title'] ?? ''));
        $parentTitle = trim((string) ($row['parent_account_title'] ?? ''));
        $isSub = (int) ($row['is_indented'] ?? 0) === 1 || $parentTitle !== '';

        if (!isset($grouped[$type])) {
            $grouped[$type] = [];
        }
        if (!isset($grouped[$type][$tagName])) {
            $grouped[$type][$tagName] = ['primary' => null, 'subs' => []];
        }

        if (!$isSub) {
            $grouped[$type][$tagName]['primary'] = $row;
            continue;
        }

        $grouped[$type][$tagName]['subs'][] = $row;
    }

    return $grouped;
}

function utilities_uacs_scope_exists(PDO $pdo, string $voucherType, string $tagName): bool
{
    utilities_uacs_ensure_schema($pdo);
    $tagName = utilities_uacs_normalize_tag_name($tagName);
    $stmt = $pdo->prepare("
        SELECT 1
        FROM uacs_code_options
        WHERE voucher_type = :vt
          AND tag_name = :tag
          AND parent_account_title = ''
          AND is_indented = 0
        LIMIT 1
    ");
    $stmt->execute([':vt' => trim($voucherType), ':tag' => $tagName]);

    return (bool) $stmt->fetchColumn();
}

/**
 * @return list<array{title: string, uacs: string, indent: bool}>
 */
function utilities_uacs_build_scope_rows(PDO $pdo, string $voucherType, string $tagName = ''): array
{
    utilities_uacs_ensure_schema($pdo);
    $voucherType = trim($voucherType);
    $tagName = utilities_uacs_normalize_tag_name($tagName);
    if ($voucherType === '') {
        return [];
    }

    $stmt = $pdo->prepare("
        SELECT account_title, uacs_code, is_indented, parent_account_title, sort_order
        FROM uacs_code_options
        WHERE voucher_type = :vt
          AND tag_name = :tag
          AND is_active = 1
        ORDER BY is_indented ASC, sort_order ASC, account_title ASC
    ");
    $stmt->execute([':vt' => $voucherType, ':tag' => $tagName]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    if (!$rows) {
        return [];
    }

    $built = [];
    foreach ($rows as $row) {
        $title = trim((string) ($row['account_title'] ?? ''));
        if ($title === '') {
            continue;
        }
        $built[] = [
            'title' => $title,
            'uacs' => trim((string) ($row['uacs_code'] ?? '')),
            'indent' => (int) ($row['is_indented'] ?? 0) === 1,
        ];
    }

    return $built;
}

function utilities_uacs_primary_exists(PDO $pdo, string $voucherType, string $accountTitle, string $tagName = ''): bool
{
    return utilities_uacs_scope_exists($pdo, $voucherType, $tagName);
}

function utilities_uacs_type_options_from_rows(array $rows, array $voucherTypes): array
{
    $options = [];
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $type = trim((string) ($row['voucher_type'] ?? ''));
        if ($type !== '' && isset($voucherTypes[$type])) {
            $options[$type] = $voucherTypes[$type];
        }
    }

    return $options;
}

function utilities_uacs_seed_sub_for_scope(PDO $pdo, string $voucherType, string $tagName = ''): void
{
    $voucherType = trim($voucherType);
    $tagName = utilities_uacs_normalize_tag_name($tagName);
    if ($voucherType === '') {
        return;
    }

    $check = $pdo->prepare('SELECT COUNT(*) FROM uacs_code_options WHERE voucher_type = :vt AND tag_name = :tag AND is_indented = 1');
    $check->execute([':vt' => $voucherType, ':tag' => $tagName]);
    if ((int) $check->fetchColumn() > 0) {
        return;
    }

    $parentTitle = utilities_uacs_primary_account_title($tagName);
    $insert = $pdo->prepare("
        INSERT INTO uacs_code_options (voucher_type, tag_name, account_title, parent_account_title, uacs_code, is_indented, sort_order, is_active)
        VALUES (:voucher_type, :tag_name, :title, :parent, :uacs, 1, :sort, 1)
    ");
    foreach (utilities_emp_tag_builtin_sub_uacs() as $row) {
        $insert->execute([
            ':voucher_type' => $voucherType,
            ':tag_name' => $tagName,
            ':title' => $row['account_title'],
            ':parent' => $parentTitle,
            ':uacs' => $row['uacs_code'],
            ':sort' => 100 + (int) ($row['sort_order'] ?? 0),
        ]);
    }
}

/** @deprecated Use utilities_uacs_seed_sub_for_scope() */
function utilities_uacs_seed_sub_for_voucher_type(PDO $pdo, string $voucherType, string $parentAccountTitle = ''): void
{
    $tagName = utilities_uacs_normalize_tag_name($parentAccountTitle);
    if ($tagName === '' || $tagName === 'Default') {
        utilities_uacs_seed_sub_for_scope($pdo, $voucherType, '');
        return;
    }
    utilities_uacs_seed_sub_for_scope($pdo, $voucherType, $tagName);
}

function utilities_uacs_delete_scope(PDO $pdo, string $voucherType, string $tagName): int
{
    utilities_uacs_ensure_schema($pdo);
    $stmt = $pdo->prepare('DELETE FROM uacs_code_options WHERE voucher_type = :vt AND tag_name = :tag');
    $stmt->execute([
        ':vt' => trim($voucherType),
        ':tag' => utilities_uacs_normalize_tag_name($tagName),
    ]);

    return $stmt->rowCount();
}

/**
 * @return list<array{title: string, uacs: string, indent: bool}>
 */
function utilities_uacs_build_salary_rows_for_tag(PDO $pdo, string $tagValue): array
{
    $tagValue = utilities_uacs_normalize_tag_name($tagValue);
    if ($tagValue === '') {
        return [];
    }

    foreach (utilities_uacs_salary_voucher_types() as $salaryType) {
        $rows = utilities_uacs_build_scope_rows($pdo, $salaryType, $tagValue);
        if ($rows) {
            return $rows;
        }
    }

    return [];
}

/** @return array<string, string> account_title => uacs_code */
function utilities_uacs_build_map(PDO $pdo, bool $activeOnly = true, ?string $voucherType = null): array
{
    $cacheKey = ($activeOnly ? 'map_active' : 'map_all') . ':' . ($voucherType ?? '*');
    $cached = RequestCache::get(UACS_CODE_CACHE_NS, $cacheKey);
    if (is_array($cached)) {
        return $cached;
    }

    utilities_uacs_ensure_schema($pdo);
    $sql = 'SELECT voucher_type, tag_name, account_title, uacs_code FROM uacs_code_options WHERE 1=1';
    $params = [];
    if ($activeOnly) {
        $sql .= ' AND is_active = 1';
    }
    if ($voucherType !== null && trim($voucherType) !== '') {
        $sql .= ' AND voucher_type = :voucher_type';
        $params[':voucher_type'] = trim($voucherType);
    }
    $sql .= ' ORDER BY sort_order ASC, account_title ASC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $map = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $title = trim((string) ($row['account_title'] ?? ''));
        $uacs = trim((string) ($row['uacs_code'] ?? ''));
        if ($title !== '' && $uacs !== '') {
            $map[$title] = $uacs;
        }
    }

    RequestCache::set(UACS_CODE_CACHE_NS, $cacheKey, $map);

    return $map;
}
