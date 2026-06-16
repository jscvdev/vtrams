<?php
declare(strict_types=1);

function normalize_user_name_fields(string &$emp_fn, string &$emp_mi, string &$emp_ln): void
{
    $emp_fn = mb_strtoupper(trim($emp_fn), 'UTF-8');
    $emp_ln = mb_strtoupper(trim($emp_ln), 'UTF-8');
    $emp_mi = trim($emp_mi);
    if ($emp_mi !== '') {
        $emp_mi = mb_strtoupper($emp_mi, 'UTF-8');
        if (!str_ends_with($emp_mi, '.')) {
            $emp_mi .= '.';
        }
    }
}

function build_user_full_name(string $emp_fn, string $emp_mi, string $emp_ln): string
{
    return trim(preg_replace('/\s+/u', ' ', trim($emp_fn) . ' ' . trim($emp_mi) . ' ' . trim($emp_ln)));
}

function normalize_user_full_name(string $fullName): string
{
    return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $fullName)), 'UTF-8');
}

function user_emp_id_exists(object $pdo, string $emp_id, ?string $exclude_emp_id = null): bool
{
    $sql = 'SELECT 1 FROM user_group WHERE emp_id = :emp_id';
    if ($exclude_emp_id !== null && $exclude_emp_id !== '') {
        $sql .= ' AND emp_id != :exclude_emp_id';
    }
    $sql .= ' LIMIT 1';

    $statement = $pdo->prepare($sql);
    $statement->bindValue(':emp_id', $emp_id);
    if ($exclude_emp_id !== null && $exclude_emp_id !== '') {
        $statement->bindValue(':exclude_emp_id', $exclude_emp_id);
    }
    $statement->execute();

    return (bool) $statement->fetchColumn();
}

function user_full_name_exists(object $pdo, string $fullName, ?string $exclude_emp_id = null): bool
{
    $target = normalize_user_full_name($fullName);
    if ($target === '') {
        return false;
    }

    $sql = 'SELECT emp_fn, emp_mi, emp_ln FROM user_group';
    if ($exclude_emp_id !== null && $exclude_emp_id !== '') {
        $sql .= ' WHERE emp_id != :exclude_emp_id';
    }

    $statement = $pdo->prepare($sql);
    if ($exclude_emp_id !== null && $exclude_emp_id !== '') {
        $statement->bindValue(':exclude_emp_id', $exclude_emp_id);
    }
    $statement->execute();

    while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
        $existing = build_user_full_name(
            (string) ($row['emp_fn'] ?? ''),
            (string) ($row['emp_mi'] ?? ''),
            (string) ($row['emp_ln'] ?? '')
        );
        if (normalize_user_full_name($existing) === $target) {
            return true;
        }
    }

    return false;
}

function get_user_duplicate_errors(
    object $pdo,
    string $emp_id,
    string $emp_fn,
    string $emp_mi,
    string $emp_ln,
    ?string $exclude_emp_id = null
): array {
    $errors = [];

    if (user_emp_id_exists($pdo, $emp_id, $exclude_emp_id)) {
        $errors['duplicate_emp_id'] = 'Duplicate employee ID is not allowed.';
    }

    $fullName = build_user_full_name($emp_fn, $emp_mi, $emp_ln);
    if ($fullName !== '' && user_full_name_exists($pdo, $fullName, $exclude_emp_id)) {
        $errors['duplicate_name'] = 'Duplicate User is not allowed.';
    }

    return $errors;
}
