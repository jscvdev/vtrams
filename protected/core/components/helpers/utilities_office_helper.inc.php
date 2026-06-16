<?php

declare(strict_types=1);

require_once __DIR__ . '/request_cache.inc.php';
require_once __DIR__ . '/utilities_signatory_helper.inc.php';

function utilities_office_invalidate_cache(): void
{
    RequestCache::forgetNamespace('system_offices');
}

function utilities_office_normalize_name(string $name): string
{
    return utilities_signatory_normalize_office($name);
}

function utilities_office_ensure_schema(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS system_offices (
            id INT AUTO_INCREMENT PRIMARY KEY,
            office_name VARCHAR(255) NOT NULL,
            parent_office_id INT NULL,
            is_processing_office TINYINT(1) NOT NULL DEFAULT 0,
            sort_order INT NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_office_name (office_name),
            KEY idx_parent_active_sort (parent_office_id, is_active, sort_order),
            KEY idx_processing (is_processing_office, is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $count = (int) $pdo->query('SELECT COUNT(*) FROM system_offices')->fetchColumn();
    if ($count === 0) {
        utilities_office_seed_from_user_group($pdo);
    }
}

function utilities_office_seed_from_user_group(PDO $pdo): void
{
    $penro = utilities_signatory_penro_office();
    $stmt = $pdo->query("
        SELECT DISTINCT TRIM(office) AS office
        FROM user_group
        WHERE TRIM(COALESCE(office, '')) <> ''
        ORDER BY office ASC
    ");
    $rows = $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];

    $insert = $pdo->prepare("
        INSERT INTO system_offices (office_name, parent_office_id, is_processing_office, sort_order, is_active)
        VALUES (:name, NULL, :processing, :sort, 1)
    ");

    $sort = 0;
    foreach ($rows as $row) {
        $name = utilities_office_normalize_name((string) ($row['office'] ?? ''));
        if ($name === '') {
            continue;
        }
        $isProcessing = utilities_signatory_offices_match($name, $penro) ? 1 : 0;
        $insert->execute([
            ':name' => $name,
            ':processing' => $isProcessing,
            ':sort' => $sort++,
        ]);
    }

    if ($penro !== '' && utilities_office_find_by_name($pdo, $penro) === null) {
        $insert->execute([
            ':name' => $penro,
            ':processing' => 1,
            ':sort' => 0,
        ]);
    }

    utilities_office_invalidate_cache();
}

/**
 * @return list<array<string, mixed>>
 */
function utilities_office_fetch_all(PDO $pdo, bool $activeOnly = false): array
{
    return RequestCache::remember('system_offices', 'all:' . ($activeOnly ? '1' : '0'), static function () use ($pdo, $activeOnly): array {
        utilities_office_ensure_schema($pdo);
        $sql = '
            SELECT o.id, o.office_name, o.parent_office_id, o.is_processing_office,
                   o.sort_order, o.is_active, p.office_name AS parent_office_name
            FROM system_offices o
            LEFT JOIN system_offices p ON p.id = o.parent_office_id
        ';
        if ($activeOnly) {
            $sql .= ' WHERE o.is_active = 1';
        }
        $sql .= ' ORDER BY o.sort_order ASC, o.office_name ASC';

        $stmt = $pdo->query($sql);

        return $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
    });
}

/**
 * @return array<string, mixed>|null
 */
function utilities_office_find_by_name(PDO $pdo, string $officeName): ?array
{
    $officeName = utilities_office_normalize_name($officeName);
    if ($officeName === '') {
        return null;
    }

    foreach (utilities_office_fetch_all($pdo) as $row) {
        if (utilities_signatory_offices_match((string) ($row['office_name'] ?? ''), $officeName)) {
            return $row;
        }
    }

    return null;
}

/**
 * @return array<string, mixed>|null
 */
function utilities_office_find_by_id(PDO $pdo, int $id): ?array
{
    if ($id <= 0) {
        return null;
    }

    foreach (utilities_office_fetch_all($pdo) as $row) {
        if ((int) ($row['id'] ?? 0) === $id) {
            return $row;
        }
    }

    return null;
}

/**
 * @return array<string, mixed>|null
 */
function utilities_office_get_processing(PDO $pdo): ?array
{
    utilities_office_ensure_schema($pdo);

    foreach (utilities_office_fetch_all($pdo, true) as $row) {
        if ((int) ($row['is_processing_office'] ?? 0) === 1) {
            return $row;
        }
    }

    $penro = utilities_signatory_penro_office();
    if ($penro !== '') {
        return utilities_office_find_by_name($pdo, $penro);
    }

    return null;
}

/**
 * @param list<array<string, mixed>> $offices
 * @return list<array<string, mixed>>
 */
function utilities_office_build_tree(array $offices): array
{
    $byParent = [];
    foreach ($offices as $office) {
        $parentId = $office['parent_office_id'] ?? null;
        $key = $parentId === null || $parentId === '' ? 0 : (int) $parentId;
        $byParent[$key][] = $office;
    }

    $build = static function (int $parentId) use (&$build, $byParent): array {
        $nodes = [];
        foreach ($byParent[$parentId] ?? [] as $office) {
            $id = (int) ($office['id'] ?? 0);
            $office['children'] = $build($id);
            $nodes[] = $office;
        }

        return $nodes;
    };

    return $build(0);
}

/**
 * @return list<int>
 */
function utilities_office_descendant_ids(PDO $pdo, int $officeId): array
{
    $all = utilities_office_fetch_all($pdo);
    $childrenByParent = [];
    foreach ($all as $row) {
        $parentId = $row['parent_office_id'] ?? null;
        if ($parentId === null || $parentId === '') {
            continue;
        }
        $childrenByParent[(int) $parentId][] = (int) ($row['id'] ?? 0);
    }

    $descendants = [];
    $stack = $childrenByParent[$officeId] ?? [];
    while ($stack !== []) {
        $current = array_pop($stack);
        if ($current <= 0 || in_array($current, $descendants, true)) {
            continue;
        }
        $descendants[] = $current;
        foreach ($childrenByParent[$current] ?? [] as $childId) {
            $stack[] = $childId;
        }
    }

    return $descendants;
}

function utilities_office_user_count(PDO $pdo, string $officeName): int
{
    $officeName = utilities_office_normalize_name($officeName);
    if ($officeName === '') {
        return 0;
    }

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM user_group WHERE LOWER(TRIM(office)) = LOWER(:office)');
    $stmt->execute([':office' => $officeName]);

    return (int) $stmt->fetchColumn();
}

function utilities_office_register_for_liaison(PDO $pdo, string $officeName): void
{
    $officeName = utilities_office_normalize_name($officeName);
    if ($officeName === '') {
        return;
    }

    $stmt = $pdo->prepare(
        'SELECT id, designated_office FROM designation_limit
         WHERE LOWER(TRIM(designation)) = LOWER(TRIM(:designation))
         LIMIT 1'
    );
    $stmt->execute([':designation' => 'Liaison Officer']);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return;
    }

    $offices = array_values(array_filter(
        array_map('trim', explode(',', (string) ($row['designated_office'] ?? ''))),
        static fn(string $office): bool => $office !== ''
    ));

    foreach ($offices as $listed) {
        if (utilities_signatory_offices_match($listed, $officeName)) {
            return;
        }
    }

    $offices[] = $officeName;
    $update = $pdo->prepare('UPDATE designation_limit SET designated_office = :offices WHERE id = :id');
    $update->execute([
        ':offices' => implode(',', $offices),
        ':id' => (int) ($row['id'] ?? 0),
    ]);
}

function utilities_office_clear_processing_flag(PDO $pdo, int $exceptId = 0): void
{
    if ($exceptId > 0) {
        $stmt = $pdo->prepare('UPDATE system_offices SET is_processing_office = 0 WHERE id <> :id');
        $stmt->execute([':id' => $exceptId]);
    } else {
        $pdo->exec('UPDATE system_offices SET is_processing_office = 0');
    }
}

/**
 * Encoders at sub-offices (any office with a parent) must forward to a Liaison Officer first.
 */
function utilities_office_encoder_requires_liaison_first(PDO $pdo, string $encoderOffice): bool
{
    $record = utilities_office_find_by_name($pdo, $encoderOffice);
    if ($record === null) {
        return false;
    }

    if ((int) ($record['is_processing_office'] ?? 0) === 1) {
        return false;
    }

    $parentId = $record['parent_office_id'] ?? null;

    return $parentId !== null && $parentId !== '' && (int) $parentId > 0;
}

/**
 * Office where the Liaison Officer should receive vouchers from an encoder.
 */
function utilities_office_encoder_liaison_office(PDO $pdo, string $encoderOffice): string
{
    $encoderOffice = utilities_office_normalize_name($encoderOffice);
    if ($encoderOffice === '') {
        return '';
    }

    $record = utilities_office_find_by_name($pdo, $encoderOffice);
    if ($record === null) {
        return $encoderOffice;
    }

    $parentId = $record['parent_office_id'] ?? null;
    if ($parentId === null || $parentId === '' || (int) $parentId <= 0) {
        return $encoderOffice;
    }

    $parent = utilities_office_find_by_id($pdo, (int) $parentId);
    if ($parent === null) {
        return $encoderOffice;
    }

    if ((int) ($parent['is_processing_office'] ?? 0) === 1) {
        return $encoderOffice;
    }

    return utilities_office_normalize_name((string) ($parent['office_name'] ?? '')) ?: $encoderOffice;
}

/**
 * Processing office used when a Liaison Officer forwards vouchers upstream.
 */
function utilities_office_liaison_forward_processing_office(PDO $pdo, string $liaisonOffice): string
{
    $processing = utilities_office_get_processing($pdo);
    if ($processing !== null) {
        $name = utilities_office_normalize_name((string) ($processing['office_name'] ?? ''));
        if ($name !== '') {
            return $name;
        }
    }

    return utilities_signatory_penro_office();
}

/**
 * @return list<string>
 */
function utilities_office_registered_names(PDO $pdo, bool $activeOnly = true): array
{
    $names = [];
    foreach (utilities_office_fetch_all($pdo, $activeOnly) as $row) {
        $name = utilities_office_normalize_name((string) ($row['office_name'] ?? ''));
        if ($name !== '') {
            $names[] = $name;
        }
    }

    return array_values(array_unique($names));
}
