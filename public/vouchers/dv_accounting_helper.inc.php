<?php
declare(strict_types=1);

/** Valid user_group.emp_tag / form "tag" values for salary UACS mapping. */
function dv_known_emp_tags(): array
{
    return [
        'Other Professional Services',
        'Janitorial Services',
        'Security Services',
    ];
}

function dv_normalize_emp_tag(?string $tag): string
{
    $t = trim((string) $tag);
    if (in_array($t, ['Janitorial Services', 'Security Services'], true)) {
        return $t;
    }
    return 'Other Professional Services';
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
function dv_uacs_code_map(): array
{
    return [
        'Other Professional Services' => '5021199000',
        'Janitorial Services' => '5021202000',
        'Security Services' => '5021203000',
        'Due to Pag-ibig Premium' => '2020103001',
        'Due to Pag-ibig MPL' => '2020103002',
        'Due to Pag-ibig CAL' => '2020103002',
        'Due to PhilHealth' => '2020104000',
        'Due to GOCCs' => '2020106000',
        'Cash-MDS, Regular' => '1010404000',
    ];
}

/** Shared liability/cash lines after the service expense row (indented on print). */
function dv_salary_common_account_titles(): array
{
    return [
        'Due to Pag-ibig Premium',
        'Due to Pag-ibig MPL',
        'Due to Pag-ibig CAL',
        'Due to PhilHealth',
        'Due to GOCCs',
        'Cash-MDS, Regular',
    ];
}

/**
 * @return list<array{title: string, uacs: string, indent: bool}>
 */
function dv_build_salary_accounting_rows(string $empTag): array
{
    $map = dv_uacs_code_map();
    $serviceTitle = dv_resolve_service_account_title($empTag);
    $rows = [
        [
            'title' => $serviceTitle,
            'uacs' => $map[$serviceTitle] ?? '',
            'indent' => false,
        ],
    ];
    foreach (dv_salary_common_account_titles() as $title) {
        $rows[] = [
            'title' => $title,
            'uacs' => $map[$title] ?? '',
            'indent' => true,
        ];
    }
    return $rows;
}

function dv_resolve_service_account_title(string $empTag): string
{
    return dv_normalize_emp_tag($empTag) === 'Janitorial Services'
        ? 'Janitorial Services'
        : (dv_normalize_emp_tag($empTag) === 'Security Services'
            ? 'Security Services'
            : 'Other Professional Services');
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
    $defaultEmpTag = 'Other Professional Services';
    $payeeEmpTags = [];
    $payeeEmpTagsLower = [];
    $payeeEmpTagsByEmpId = [];

    if ($loggedEmpId !== null && $loggedEmpId !== '') {
        $tagStmt = $pdo->prepare('SELECT emp_tag FROM user_group WHERE emp_id = :id LIMIT 1');
        $tagStmt->execute([':id' => $loggedEmpId]);
        $tagRow = $tagStmt->fetch(PDO::FETCH_ASSOC);
        $loggedTag = trim((string) ($tagRow['emp_tag'] ?? ''));
        if ($loggedTag !== '') {
            $defaultEmpTag = dv_normalize_emp_tag($loggedTag);
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
        $tag = dv_normalize_emp_tag((string) ($u['emp_tag'] ?? ''));

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
    ?string $empId = null
): string {
    $explicit = trim((string) $explicitTag);
    if ($explicit !== '' && in_array($explicit, dv_known_emp_tags(), true)) {
        return dv_normalize_emp_tag($explicit);
    }

    $id = trim((string) $empId);
    if ($id !== '' && !empty($lookup['payeeEmpTagsByEmpId'][$id])) {
        return dv_normalize_emp_tag($lookup['payeeEmpTagsByEmpId'][$id]);
    }

    $payeeKey = dv_normalize_name_key((string) $payee);
    if ($payeeKey !== '') {
        if (!empty($lookup['payeeEmpTags'][$payeeKey])) {
            return dv_normalize_emp_tag($lookup['payeeEmpTags'][$payeeKey]);
        }
        $lower = strtolower($payeeKey);
        if (!empty($lookup['payeeEmpTagsLower'][$lower])) {
            return dv_normalize_emp_tag($lookup['payeeEmpTagsLower'][$lower]);
        }
    }

    $loggedName = dv_normalize_name_key((string) ($lookup['loggedUserName'] ?? ''));
    if ($loggedName !== '' && $payeeKey !== '' && strcasecmp($loggedName, $payeeKey) === 0) {
        return dv_normalize_emp_tag($lookup['defaultEmpTag'] ?? 'Other Professional Services');
    }

    return 'Other Professional Services';
}
