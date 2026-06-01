<?php

declare(strict_types=1);

/** @return list<array{tag_value: string, uacs_code: string, is_default: int, sort_order: int}> */
function utilities_emp_tag_builtin_defaults(): array
{
    return [
        ['tag_value' => 'Other Professional Services', 'uacs_code' => '5021199000', 'is_default' => 1, 'sort_order' => 0],
        ['tag_value' => 'Janitorial Services', 'uacs_code' => '5021202000', 'is_default' => 0, 'sort_order' => 1],
        ['tag_value' => 'Security Services', 'uacs_code' => '5021203000', 'is_default' => 0, 'sort_order' => 2],
    ];
}

/** Shared liability/cash sub-UACS rows (indented on DV salary print). */
function utilities_emp_tag_builtin_sub_uacs(): array
{
    return [
        ['account_title' => 'Due to Pag-ibig Premium', 'uacs_code' => '2020103001', 'sort_order' => 0],
        ['account_title' => 'Due to Pag-ibig MPL', 'uacs_code' => '2020103002', 'sort_order' => 1],
        ['account_title' => 'Due to Pag-ibig CAL', 'uacs_code' => '2020103002', 'sort_order' => 2],
        ['account_title' => 'Due to PhilHealth', 'uacs_code' => '2020104000', 'sort_order' => 3],
        ['account_title' => 'Due to GOCCs', 'uacs_code' => '2020106000', 'sort_order' => 4],
        ['account_title' => 'Cash-MDS, Regular', 'uacs_code' => '1010404000', 'sort_order' => 5],
    ];
}

function utilities_emp_tag_normalize_value(string $value): string
{
    return trim(preg_replace('/\s+/', ' ', $value));
}

function utilities_emp_tag_normalize_uacs(string $value): string
{
    return preg_replace('/\D+/', '', trim($value)) ?? '';
}

/** @return array{ok: bool, uacs: string, error: string} */
function utilities_emp_tag_validate_uacs(string $rawUacs, bool $required = false): array
{
    $uacs = utilities_emp_tag_normalize_uacs($rawUacs);
    if ($uacs === '') {
        if ($required) {
            return ['ok' => false, 'uacs' => '', 'error' => 'Associated UACS code is required.'];
        }
        return ['ok' => true, 'uacs' => '', 'error' => ''];
    }
    if (strlen($uacs) !== 10) {
        return ['ok' => false, 'uacs' => $uacs, 'error' => 'UACS code must be exactly 10 digits.'];
    }

    return ['ok' => true, 'uacs' => $uacs, 'error' => ''];
}

function utilities_emp_tag_ensure_schema(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS emp_tag_options (
            id INT AUTO_INCREMENT PRIMARY KEY,
            tag_value VARCHAR(150) NOT NULL,
            uacs_code VARCHAR(32) NOT NULL DEFAULT '',
            sort_order INT NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            is_default TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_tag_value (tag_value),
            KEY idx_active_sort (is_active, sort_order, tag_value)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS emp_tag_sub_uacs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            emp_tag_id INT NOT NULL,
            account_title VARCHAR(255) NOT NULL,
            uacs_code VARCHAR(32) NOT NULL DEFAULT '',
            sort_order INT NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_tag_title (emp_tag_id, account_title),
            KEY idx_tag_active_sort (emp_tag_id, is_active, sort_order, account_title),
            CONSTRAINT fk_emp_tag_sub_uacs_tag
                FOREIGN KEY (emp_tag_id) REFERENCES emp_tag_options(id)
                ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $count = (int) $pdo->query('SELECT COUNT(*) FROM emp_tag_options')->fetchColumn();
    if ($count === 0) {
        $insert = $pdo->prepare("
            INSERT INTO emp_tag_options (tag_value, uacs_code, sort_order, is_active, is_default)
            VALUES (:tag, :uacs, :sort, 1, :is_default)
        ");
        foreach (utilities_emp_tag_builtin_defaults() as $row) {
            $insert->execute([
                ':tag' => $row['tag_value'],
                ':uacs' => $row['uacs_code'],
                ':sort' => (int) $row['sort_order'],
                ':is_default' => (int) $row['is_default'],
            ]);
            utilities_emp_tag_seed_sub_uacs($pdo, (int) $pdo->lastInsertId());
        }
        return;
    }

    utilities_emp_tag_backfill_sub_uacs($pdo);
}

function utilities_emp_tag_backfill_sub_uacs(PDO $pdo): void
{
    $tagIds = $pdo->query('SELECT id FROM emp_tag_options ORDER BY id ASC')->fetchAll(PDO::FETCH_COLUMN) ?: [];
    foreach ($tagIds as $tagId) {
        utilities_emp_tag_seed_sub_uacs($pdo, (int) $tagId);
    }
}

function utilities_emp_tag_seed_sub_uacs(PDO $pdo, int $tagId): void
{
    if ($tagId <= 0) {
        return;
    }

    $check = $pdo->prepare('SELECT COUNT(*) FROM emp_tag_sub_uacs WHERE emp_tag_id = :id');
    $check->execute([':id' => $tagId]);
    if ((int) $check->fetchColumn() > 0) {
        return;
    }

    $insert = $pdo->prepare("
        INSERT INTO emp_tag_sub_uacs (emp_tag_id, account_title, uacs_code, sort_order, is_active)
        VALUES (:tag_id, :title, :uacs, :sort, 1)
    ");
    foreach (utilities_emp_tag_builtin_sub_uacs() as $row) {
        $insert->execute([
            ':tag_id' => $tagId,
            ':title' => $row['account_title'],
            ':uacs' => $row['uacs_code'],
            ':sort' => (int) $row['sort_order'],
        ]);
    }
}

function utilities_emp_tag_fetch_all(PDO $pdo): array
{
    utilities_emp_tag_ensure_schema($pdo);
    $stmt = $pdo->query("
        SELECT id, tag_value, uacs_code, sort_order, is_active, is_default
        FROM emp_tag_options
        ORDER BY sort_order ASC, tag_value ASC
    ");
    $tags = $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];

    foreach ($tags as &$tag) {
        $tag['sub_uacs'] = utilities_emp_tag_fetch_sub_uacs($pdo, (int) ($tag['id'] ?? 0));
    }
    unset($tag);

    return $tags;
}

function utilities_emp_tag_fetch_active(PDO $pdo): array
{
    utilities_emp_tag_ensure_schema($pdo);
    $stmt = $pdo->query("
        SELECT id, tag_value, uacs_code, is_default
        FROM emp_tag_options
        WHERE is_active = 1
        ORDER BY sort_order ASC, tag_value ASC
    ");

    return $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
}

/** @return list<array{id: int, account_title: string, uacs_code: string, sort_order: int, is_active: int}> */
function utilities_emp_tag_fetch_sub_uacs(PDO $pdo, int $tagId, bool $activeOnly = false): array
{
    if ($tagId <= 0) {
        return [];
    }

    $sql = "
        SELECT id, account_title, uacs_code, sort_order, is_active
        FROM emp_tag_sub_uacs
        WHERE emp_tag_id = :id
    ";
    if ($activeOnly) {
        $sql .= ' AND is_active = 1';
    }
    $sql .= ' ORDER BY sort_order ASC, account_title ASC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $tagId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function utilities_emp_tag_find_id_by_value(PDO $pdo, string $tagValue): ?int
{
    $tagValue = utilities_emp_tag_normalize_value($tagValue);
    if ($tagValue === '') {
        return null;
    }

    $stmt = $pdo->prepare('SELECT id FROM emp_tag_options WHERE tag_value = :v LIMIT 1');
    $stmt->execute([':v' => $tagValue]);
    $id = $stmt->fetchColumn();

    return $id !== false ? (int) $id : null;
}

/**
 * @return list<array{title: string, uacs: string, indent: bool}>
 */
function utilities_emp_tag_build_salary_rows(PDO $pdo, string $tagValue): array
{
    utilities_emp_tag_ensure_schema($pdo);
    $tagValue = utilities_emp_tag_normalize_value($tagValue);

    $stmt = $pdo->prepare("
        SELECT id, tag_value, uacs_code
        FROM emp_tag_options
        WHERE tag_value = :v AND is_active = 1
        LIMIT 1
    ");
    $stmt->execute([':v' => $tagValue]);
    $tag = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$tag) {
        return utilities_emp_tag_build_salary_rows_fallback($tagValue);
    }

    $serviceTitle = (string) ($tag['tag_value'] ?? $tagValue);
    $rows = [[
        'title' => $serviceTitle,
        'uacs' => trim((string) ($tag['uacs_code'] ?? '')),
        'indent' => false,
    ]];

    foreach (utilities_emp_tag_fetch_sub_uacs($pdo, (int) $tag['id'], true) as $sub) {
        $rows[] = [
            'title' => (string) ($sub['account_title'] ?? ''),
            'uacs' => trim((string) ($sub['uacs_code'] ?? '')),
            'indent' => true,
        ];
    }

    return $rows;
}

/** @return list<array{title: string, uacs: string, indent: bool}> */
function utilities_emp_tag_build_salary_rows_fallback(string $tagValue): array
{
    $primaryUacs = '';
    foreach (utilities_emp_tag_builtin_defaults() as $row) {
        if (($row['tag_value'] ?? '') === $tagValue) {
            $primaryUacs = (string) ($row['uacs_code'] ?? '');
            break;
        }
    }
    if ($primaryUacs === '') {
        $primaryUacs = '5021199000';
        $tagValue = 'Other Professional Services';
    }

    $rows = [[
        'title' => $tagValue,
        'uacs' => $primaryUacs,
        'indent' => false,
    ]];
    foreach (utilities_emp_tag_builtin_sub_uacs() as $sub) {
        $rows[] = [
            'title' => (string) $sub['account_title'],
            'uacs' => (string) $sub['uacs_code'],
            'indent' => true,
        ];
    }

    return $rows;
}

/** @return array<string, list<array{title: string, uacs: string, indent: bool}>> */
function utilities_emp_tag_build_salary_maps(PDO $pdo): array
{
    utilities_emp_tag_ensure_schema($pdo);
    $maps = [];

    foreach (utilities_emp_tag_fetch_active($pdo) as $tag) {
        $tagValue = utilities_emp_tag_normalize_value((string) ($tag['tag_value'] ?? ''));
        if ($tagValue === '') {
            continue;
        }
        $maps[$tagValue] = utilities_emp_tag_build_salary_rows($pdo, $tagValue);
    }

    if (!$maps) {
        foreach (utilities_emp_tag_builtin_defaults() as $row) {
            $tagValue = (string) $row['tag_value'];
            $maps[$tagValue] = utilities_emp_tag_build_salary_rows_fallback($tagValue);
        }
    }

    return $maps;
}

function utilities_emp_tag_default_value(PDO $pdo): string
{
    utilities_emp_tag_ensure_schema($pdo);
    $stmt = $pdo->query("
        SELECT tag_value
        FROM emp_tag_options
        WHERE is_active = 1 AND is_default = 1
        ORDER BY sort_order ASC, id ASC
        LIMIT 1
    ");
    $value = $stmt ? $stmt->fetchColumn() : false;

    if (is_string($value) && trim($value) !== '') {
        return utilities_emp_tag_normalize_value($value);
    }

    foreach (utilities_emp_tag_builtin_defaults() as $row) {
        if ((int) ($row['is_default'] ?? 0) === 1) {
            return $row['tag_value'];
        }
    }

    return 'Other Professional Services';
}

function utilities_emp_tag_set_default(PDO $pdo, int $id): void
{
    $pdo->exec('UPDATE emp_tag_options SET is_default = 0');
    $stmt = $pdo->prepare('UPDATE emp_tag_options SET is_default = 1 WHERE id = :id');
    $stmt->execute([':id' => $id]);
}

function utilities_emp_tag_fill_empty(PDO $pdo): int
{
    utilities_emp_tag_ensure_schema($pdo);
    $defaultTag = utilities_emp_tag_default_value($pdo);
    $stmt = $pdo->prepare("UPDATE user_group SET emp_tag = :tag WHERE emp_tag IS NULL OR emp_tag = ''");
    $stmt->execute([':tag' => $defaultTag]);

    return $stmt->rowCount();
}

function utilities_emp_tag_sub_uacs_tag_exists(PDO $pdo, int $tagId): bool
{
    if ($tagId <= 0) {
        return false;
    }
    $stmt = $pdo->prepare('SELECT 1 FROM emp_tag_options WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $tagId]);

    return (bool) $stmt->fetchColumn();
}
