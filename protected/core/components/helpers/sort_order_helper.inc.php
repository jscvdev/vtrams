<?php

declare(strict_types=1);

function sort_order_safe_identifier(string $name): string
{
    return preg_replace('/[^a-zA-Z0-9_]/', '', $name) ?? '';
}

/**
 * @param array<string, scalar> $scopeColumns
 * @return array{0: string, 1: array<string, scalar>}
 */
function sort_order_build_scope_clause(array $scopeColumns, string $paramPrefix = 'scope'): array
{
    $parts = [];
    $params = [];
    $i = 0;

    foreach ($scopeColumns as $col => $val) {
        $colSafe = sort_order_safe_identifier((string) $col);
        $param = ':' . $paramPrefix . $i;
        $parts[] = "`{$colSafe}` = {$param}";
        $params[$param] = $val;
        $i++;
    }

    return [$parts === [] ? '' : ' AND ' . implode(' AND ', $parts), $params];
}

/**
 * Ordered row ids in the current list (sort_order ASC, then id ASC).
 *
 * @param array<string, scalar> $scopeColumns
 * @return list<int>
 */
function sort_order_fetch_ordered_ids(
    PDO $pdo,
    string $table,
    array $scopeColumns = [],
    string $idColumn = 'id',
    string $sortColumn = 'sort_order'
): array {
    $tableSafe = sort_order_safe_identifier($table);
    $idColSafe = sort_order_safe_identifier($idColumn);
    $sortColSafe = sort_order_safe_identifier($sortColumn);

    [$scopeSql, $scopeParams] = sort_order_build_scope_clause($scopeColumns);

    $sql = sprintf(
        'SELECT `%s` FROM `%s` WHERE 1=1%s ORDER BY `%s` ASC, `%s` ASC',
        $idColSafe,
        $tableSafe,
        $scopeSql,
        $sortColSafe,
        $idColSafe
    );

    $stmt = $pdo->prepare($sql);
    $stmt->execute($scopeParams);
    $ids = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

    return array_values(array_map('intval', $ids));
}

/**
 * Write sequential sort_order (0, 1, 2, …) following the given id order.
 *
 * @param list<int> $orderedIds
 * @param array<string, scalar> $scopeColumns
 */
function sort_order_write_sequence(
    PDO $pdo,
    string $table,
    array $orderedIds,
    array $scopeColumns = [],
    string $idColumn = 'id',
    string $sortColumn = 'sort_order'
): void {
    if ($orderedIds === []) {
        return;
    }

    $tableSafe = sort_order_safe_identifier($table);
    $idColSafe = sort_order_safe_identifier($idColumn);
    $sortColSafe = sort_order_safe_identifier($sortColumn);

    [$scopeSql, $scopeParams] = sort_order_build_scope_clause($scopeColumns);

    $update = $pdo->prepare(sprintf(
        'UPDATE `%s` SET `%s` = :sort_value WHERE `%s` = :item_id%s',
        $tableSafe,
        $sortColSafe,
        $idColSafe,
        $scopeSql
    ));

    foreach ($orderedIds as $index => $itemId) {
        $itemId = (int) $itemId;
        if ($itemId <= 0) {
            continue;
        }
        $update->execute(array_merge([
            ':sort_value' => $index,
            ':item_id' => $itemId,
        ], $scopeParams));
    }
}

/**
 * Place a row at list position $targetPosition (0-based) and renumber the whole list
 * sequentially so each sort_order follows the previous item (0, 1, 2, 3, …).
 *
 * @param array<string, scalar> $scopeColumns
 */
function sort_order_place_at_position(
    PDO $pdo,
    string $table,
    int $itemId,
    int $targetPosition,
    array $scopeColumns = [],
    string $idColumn = 'id',
    string $sortColumn = 'sort_order'
): void {
    if ($itemId <= 0) {
        return;
    }

    if ($targetPosition < 0) {
        $targetPosition = 0;
    }

    $orderedIds = sort_order_fetch_ordered_ids($pdo, $table, $scopeColumns, $idColumn, $sortColumn);

    $orderedIds = array_values(array_filter(
        $orderedIds,
        static fn(int $id): bool => $id !== $itemId
    ));

    $maxPosition = count($orderedIds);
    if ($targetPosition > $maxPosition) {
        $targetPosition = $maxPosition;
    }

    array_splice($orderedIds, $targetPosition, 0, [$itemId]);

    sort_order_write_sequence($pdo, $table, $orderedIds, $scopeColumns, $idColumn, $sortColumn);
}

/**
 * Re-read list order and assign 0, 1, 2, … (fixes legacy gaps/duplicates).
 *
 * @param array<string, scalar> $scopeColumns
 */
function sort_order_reindex_sequential(
    PDO $pdo,
    string $table,
    array $scopeColumns = [],
    string $idColumn = 'id',
    string $sortColumn = 'sort_order'
): void {
    $orderedIds = sort_order_fetch_ordered_ids($pdo, $table, $scopeColumns, $idColumn, $sortColumn);
    sort_order_write_sequence($pdo, $table, $orderedIds, $scopeColumns, $idColumn, $sortColumn);
}

/**
 * @deprecated Use sort_order_place_at_position() after INSERT when the new id is known.
 *
 * @param array<string, scalar> $scopeColumns
 */
function sort_order_prepare_insert(
    PDO $pdo,
    string $table,
    int $newSort,
    array $scopeColumns = [],
    string $sortColumn = 'sort_order'
): void {
    // No-op: sorting is applied via sort_order_place_at_position() after insert.
}

/**
 * @param array<string, scalar> $scopeColumns
 */
function sort_order_handle_update(
    PDO $pdo,
    string $table,
    int $itemId,
    int $newSort,
    array $scopeColumns = [],
    string $idColumn = 'id',
    string $sortColumn = 'sort_order'
): void {
    sort_order_place_at_position($pdo, $table, $itemId, $newSort, $scopeColumns, $idColumn, $sortColumn);
}

/**
 * @param array<string, scalar> $scopeColumns
 */
function sort_order_after_save(
    PDO $pdo,
    string $table,
    array $scopeColumns = [],
    string $idColumn = 'id',
    string $sortColumn = 'sort_order'
): void {
    sort_order_reindex_sequential($pdo, $table, $scopeColumns, $idColumn, $sortColumn);
}
