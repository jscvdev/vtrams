<?php

declare(strict_types=1);

/**
 * Keep encoder-selected COA checklist JSON on the voucher through later hops and cashier payment.
 *
 * @return array{coa_options: ?string, coa_category: ?string, coa_subsection: ?string}
 */
function voucher_coa_empty_fields(): array
{
    return [
        'coa_options' => null,
        'coa_category' => null,
        'coa_subsection' => null,
    ];
}

function voucher_coa_normalize_text(mixed $value): ?string
{
    if ($value === null) {
        return null;
    }
    $text = trim((string) $value);

    return $text === '' ? null : $text;
}

function voucher_coa_has_selections(?string $json): bool
{
    $json = voucher_coa_normalize_text($json);
    if ($json === null) {
        return false;
    }
    $decoded = json_decode($json, true);
    if (!is_array($decoded) || $decoded === []) {
        return false;
    }

    return true;
}

/**
 * @param array<string, mixed>|null $row
 * @return array{coa_options: ?string, coa_category: ?string, coa_subsection: ?string}
 */
function voucher_coa_fields_from_row(?array $row): array
{
    if (!is_array($row)) {
        return voucher_coa_empty_fields();
    }

    return [
        'coa_options' => voucher_coa_normalize_text($row['coa_options'] ?? null),
        'coa_category' => voucher_coa_normalize_text($row['coa_category'] ?? null),
        'coa_subsection' => voucher_coa_normalize_text($row['coa_subsection'] ?? null),
    ];
}

/**
 * @param array{coa_options?: ?string, coa_category?: ?string, coa_subsection?: ?string} $preferred
 * @param array{coa_options?: ?string, coa_category?: ?string, coa_subsection?: ?string} $fallback
 * @return array{coa_options: ?string, coa_category: ?string, coa_subsection: ?string}
 */
function voucher_coa_merge_fields(array $preferred, array $fallback): array
{
    $out = voucher_coa_empty_fields();
    foreach (['coa_options', 'coa_category', 'coa_subsection'] as $key) {
        $preferredValue = voucher_coa_normalize_text($preferred[$key] ?? null);
        $fallbackValue = voucher_coa_normalize_text($fallback[$key] ?? null);
        if ($key === 'coa_options') {
            if (voucher_coa_has_selections($preferredValue)) {
                $out[$key] = $preferredValue;
            } elseif (voucher_coa_has_selections($fallbackValue)) {
                $out[$key] = $fallbackValue;
            } else {
                $out[$key] = $preferredValue ?? $fallbackValue;
            }
            continue;
        }
        $out[$key] = $preferredValue ?? $fallbackValue;
    }

    return $out;
}

/**
 * @return array{coa_options: ?string, coa_category: ?string, coa_subsection: ?string}
 */
function voucher_coa_load_for_processing_no(object $pdo, string $processing_no): array
{
    $processing_no = trim($processing_no);
    if ($processing_no === '') {
        return voucher_coa_empty_fields();
    }

    $lookups = [
        'SELECT coa_options, coa_category, coa_subsection FROM voucher_receiving WHERE processing_no = :processing_no LIMIT 1',
        'SELECT coa_options, coa_category, coa_subsection FROM voucher_incoming WHERE processing_no = :processing_no LIMIT 1',
        'SELECT coa_options, coa_category, coa_subsection FROM voucher_sent WHERE processing_no = :processing_no LIMIT 1',
        'SELECT coa_options, coa_category, coa_subsection FROM vouchers WHERE processing_no = :processing_no LIMIT 1',
        'SELECT coa_options, coa_category, coa_subsection FROM voucher_archives WHERE processing_no = :processing_no LIMIT 1',
        'SELECT coa_options, coa_category, coa_subsection FROM voucher_tracking WHERE processing_no = :processing_no LIMIT 1',
        "SELECT coa_options, coa_category, coa_subsection FROM voucher_action_logs
         WHERE processing_no = :processing_no
           AND coa_options IS NOT NULL
           AND TRIM(coa_options) <> ''
         ORDER BY datetime_action DESC
         LIMIT 1",
    ];

    $merged = voucher_coa_empty_fields();
    foreach ($lookups as $sql) {
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':processing_no', $processing_no, PDO::PARAM_STR);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!is_array($row)) {
                continue;
            }
            $merged = voucher_coa_merge_fields($merged, voucher_coa_fields_from_row($row));
            if (voucher_coa_has_selections($merged['coa_options'])) {
                return $merged;
            }
        } catch (Throwable) {
            // Column/table may be missing on older installs.
        }
    }

    return $merged;
}

/**
 * Prefer posted/current hop values, then stored encoder selections.
 *
 * @return array{coa_options: ?string, coa_category: ?string, coa_subsection: ?string}
 */
function voucher_coa_resolve(
    object $pdo,
    string $processing_no,
    mixed $coa_options = null,
    mixed $coa_category = null,
    mixed $coa_subsection = null
): array {
    $preferred = [
        'coa_options' => voucher_coa_normalize_text($coa_options),
        'coa_category' => voucher_coa_normalize_text($coa_category),
        'coa_subsection' => voucher_coa_normalize_text($coa_subsection),
    ];
    if (voucher_coa_has_selections($preferred['coa_options']) && $preferred['coa_category'] !== null && $preferred['coa_subsection'] !== null) {
        return $preferred;
    }

    return voucher_coa_merge_fields($preferred, voucher_coa_load_for_processing_no($pdo, $processing_no));
}

function voucher_coa_sync_tracking(
    object $pdo,
    string $processing_no,
    mixed $coa_options = null,
    mixed $coa_category = null,
    mixed $coa_subsection = null
): void {
    $fields = voucher_coa_resolve($pdo, $processing_no, $coa_options, $coa_category, $coa_subsection);
    if (!voucher_coa_has_selections($fields['coa_options'])) {
        return;
    }

    try {
        $stmt = $pdo->prepare(
            'UPDATE voucher_tracking
             SET coa_options = :coa_options,
                 coa_category = :coa_category,
                 coa_subsection = :coa_subsection
             WHERE processing_no = :processing_no'
        );
        $stmt->bindValue(':coa_options', $fields['coa_options'], PDO::PARAM_STR);
        $stmt->bindValue(':coa_category', $fields['coa_category'], $fields['coa_category'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':coa_subsection', $fields['coa_subsection'], $fields['coa_subsection'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':processing_no', $processing_no, PDO::PARAM_STR);
        $stmt->execute();
    } catch (Throwable) {
        // Best-effort: older tracking tables may lack COA columns.
    }
}
