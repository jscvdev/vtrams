<?php
declare(strict_types=1);

require_once __DIR__ . '/../../protected/core/components/helpers/utilities_emp_tag_helper.inc.php';

/** Valid user_group.emp_tag / form "tag" values for salary UACS mapping. */
function dv_known_emp_tags(?PDO $pdo = null): array
{
    if ($pdo instanceof PDO) {
        try {
            utilities_emp_tag_ensure_schema($pdo);
            $rows = utilities_emp_tag_fetch_active($pdo);
            $tags = [];
            foreach ($rows as $row) {
                $tag = trim((string) ($row['tag_value'] ?? ''));
                if ($tag !== '') {
                    $tags[] = $tag;
                }
            }
            if ($tags) {
                return $tags;
            }
        } catch (Throwable $e) {
            // fall through to built-in defaults
        }
    }

    return array_column(utilities_emp_tag_builtin_defaults(), 'tag_value');
}

function dv_default_emp_tag(?PDO $pdo = null): string
{
    if ($pdo instanceof PDO) {
        try {
            return utilities_emp_tag_default_value($pdo);
        } catch (Throwable $e) {
            // fall through
        }
    }

    return 'Other Professional Services';
}

function dv_normalize_emp_tag(?string $tag, ?PDO $pdo = null): string
{
    $t = trim((string) $tag);
    if ($t !== '' && in_array($t, dv_known_emp_tags($pdo), true)) {
        return $t;
    }

    return dv_default_emp_tag($pdo);
}

function dv_format_employee_name(string $fn, string $mi, string $ln): string
{
    $parts = explode(' ', trim($fn . ' ' . $mi . ' ' . $ln));
    return implode(' ', array_filter($parts, static fn($p) => $p !== ''));
}

function dv_normalize_name_key(string $name): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    return implode(' ', array_filter($parts, static fn($p) => $p !== ''));
}

/** Account title => UACS code (salary accomplishments reference). */
function dv_uacs_code_map(?PDO $pdo = null): array
{
    $map = [];

    if ($pdo instanceof PDO) {
        try {
            utilities_emp_tag_ensure_schema($pdo);
            foreach (utilities_emp_tag_build_salary_maps($pdo) as $rows) {
                foreach ($rows as $row) {
                    $title = trim((string) ($row['title'] ?? ''));
                    $uacs = trim((string) ($row['uacs'] ?? ''));
                    if ($title !== '' && $uacs !== '') {
                        $map[$title] = $uacs;
                    }
                }
            }
        } catch (Throwable $e) {
            // fall through
        }
    }

    if (!$map) {
        foreach (utilities_emp_tag_builtin_defaults() as $row) {
            $tag = (string) ($row['tag_value'] ?? '');
            $uacs = (string) ($row['uacs_code'] ?? '');
            if ($tag !== '' && $uacs !== '') {
                $map[$tag] = $uacs;
            }
        }
        foreach (utilities_emp_tag_builtin_sub_uacs() as $sub) {
            $title = (string) ($sub['account_title'] ?? '');
            $uacs = (string) ($sub['uacs_code'] ?? '');
            if ($title !== '' && $uacs !== '') {
                $map[$title] = $uacs;
            }
        }
    }

    return $map;
}

/** Shared liability/cash lines after the service expense row (indented on print). */
function dv_salary_common_account_titles(?PDO $pdo = null, ?string $empTag = null): array
{
    if ($pdo instanceof PDO && $empTag !== null && trim($empTag) !== '') {
        try {
            $tagId = utilities_emp_tag_find_id_by_value($pdo, $empTag);
            if ($tagId !== null) {
                $titles = [];
                foreach (utilities_emp_tag_fetch_sub_uacs($pdo, $tagId, true) as $sub) {
                    $title = trim((string) ($sub['account_title'] ?? ''));
                    if ($title !== '') {
                        $titles[] = $title;
                    }
                }
                if ($titles) {
                    return $titles;
                }
            }
        } catch (Throwable $e) {
            // fall through
        }
    }

    return array_column(utilities_emp_tag_builtin_sub_uacs(), 'account_title');
}

/**
 * @return list<array{title: string, uacs: string, indent: bool}>
 */
function dv_build_salary_accounting_rows(string $empTag, ?PDO $pdo = null): array
{
    if ($pdo instanceof PDO) {
        try {
            return utilities_emp_tag_build_salary_rows($pdo, $empTag);
        } catch (Throwable $e) {
            // fall through
        }
    }

    $normalized = dv_normalize_emp_tag($empTag, $pdo);
    return utilities_emp_tag_build_salary_rows_fallback($normalized);
}

function dv_resolve_service_account_title(string $empTag, ?PDO $pdo = null): string
{
    return dv_normalize_emp_tag($empTag, $pdo);
}

/**
 * Resolve emp_tag for a voucher payee from user_group (by emp_id, then full name).
 *
 * @return array{
 *   defaultEmpTag: string,
 *   loggedUserName: string,
 *   payeeEmpTags: array<string, string>,
 *   payeeEmpTagsLower: array<string, string>,
 *   payeeEmpTagsByEmpId: array<string, string>
 * }
 */
function dv_build_emp_tag_lookup(PDO $pdo, ?string $loggedEmpId = null, ?string $loggedUserName = null): array
{
    $defaultEmpTag = dv_default_emp_tag($pdo);
    $payeeEmpTags = [];
    $payeeEmpTagsLower = [];
    $payeeEmpTagsByEmpId = [];

    if ($loggedEmpId !== null && $loggedEmpId !== '') {
        $tagStmt = $pdo->prepare('SELECT emp_tag FROM user_group WHERE emp_id = :id LIMIT 1');
        $tagStmt->execute([':id' => $loggedEmpId]);
        $tagRow = $tagStmt->fetch(PDO::FETCH_ASSOC);
        $loggedTag = trim((string) ($tagRow['emp_tag'] ?? ''));
        if ($loggedTag !== '') {
            $defaultEmpTag = dv_normalize_emp_tag($loggedTag, $pdo);
        }
    }

    $nameStmt = $pdo->query('SELECT emp_id, emp_fn, emp_mi, emp_ln, emp_tag FROM user_group');
    while ($u = $nameStmt->fetch(PDO::FETCH_ASSOC)) {
        $empId = trim((string) ($u['emp_id'] ?? ''));
        $fullName = dv_format_employee_name(
            (string) ($u['emp_fn'] ?? ''),
            (string) ($u['emp_mi'] ?? ''),
            (string) ($u['emp_ln'] ?? '')
        );
        $tag = dv_normalize_emp_tag((string) ($u['emp_tag'] ?? ''), $pdo);

        if ($empId !== '') {
            $payeeEmpTagsByEmpId[$empId] = $tag;
        }
        if ($fullName !== '') {
            $payeeEmpTags[$fullName] = $tag;
            $payeeEmpTagsLower[strtolower($fullName)] = $tag;
        }
    }

    return [
        'defaultEmpTag' => $defaultEmpTag,
        'loggedUserName' => trim((string) $loggedUserName),
        'payeeEmpTags' => $payeeEmpTags,
        'payeeEmpTagsLower' => $payeeEmpTagsLower,
        'payeeEmpTagsByEmpId' => $payeeEmpTagsByEmpId,
    ];
}

function dv_resolve_emp_tag_for_payee(
    array $lookup,
    ?string $payee,
    ?string $explicitTag = null,
    ?string $empId = null,
    ?PDO $pdo = null
): string {
    $explicit = trim((string) $explicitTag);
    if ($explicit !== '' && in_array($explicit, dv_known_emp_tags($pdo), true)) {
        return dv_normalize_emp_tag($explicit, $pdo);
    }

    $id = trim((string) $empId);
    if ($id !== '' && !empty($lookup['payeeEmpTagsByEmpId'][$id])) {
        return dv_normalize_emp_tag($lookup['payeeEmpTagsByEmpId'][$id], $pdo);
    }

    $payeeKey = dv_normalize_name_key((string) $payee);
    if ($payeeKey !== '') {
        if (!empty($lookup['payeeEmpTags'][$payeeKey])) {
            return dv_normalize_emp_tag($lookup['payeeEmpTags'][$payeeKey], $pdo);
        }
        $lower = strtolower($payeeKey);
        if (!empty($lookup['payeeEmpTagsLower'][$lower])) {
            return dv_normalize_emp_tag($lookup['payeeEmpTagsLower'][$lower], $pdo);
        }
    }

    $loggedName = dv_normalize_name_key((string) ($lookup['loggedUserName'] ?? ''));
    if ($loggedName !== '' && $payeeKey !== '' && strcasecmp($loggedName, $payeeKey) === 0) {
        return dv_normalize_emp_tag($lookup['defaultEmpTag'] ?? dv_default_emp_tag($pdo), $pdo);
    }

    return dv_default_emp_tag($pdo);
}
