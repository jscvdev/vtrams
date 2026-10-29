<?php

declare(strict_types=1);

/**
 * Sequential table filters: Year → Month → Day, then Ascending / Descending by processing_no.
 *
 * @return array{
 *   year: ?int,
 *   month: ?int,
 *   day: ?int,
 *   sort: 'asc'|'desc',
 *   year_value: string,
 *   month_value: string,
 *   day_value: string,
 *   sort_value: string,
 *   date_from: ?string,
 *   date_to: ?string,
 *   is_date_filtered: bool
 * }
 */
function voucher_table_filter_parse(?array $src = null): array
{
    $src = $src ?? $_GET;
    $yearRaw = trim((string) ($src['year'] ?? 'all'));
    $monthRaw = trim((string) ($src['month'] ?? 'all'));
    $dayRaw = trim((string) ($src['day'] ?? 'all'));
    $sort = voucher_table_filter_normalize_sort((string) ($src['sort'] ?? 'asc'));

    $year = ctype_digit($yearRaw) ? (int) $yearRaw : null;
    if ($year === null || $year < 2000 || $year > 2100) {
        $year = null;
    }

    $month = null;
    if ($year !== null && preg_match('/^(0?[1-9]|1[0-2])$/', $monthRaw)) {
        $month = (int) $monthRaw;
    }

    $day = null;
    if ($year !== null && $month !== null) {
        $dayNum = (int) ltrim($dayRaw, '0');
        if ($dayRaw !== 'all' && $dayNum >= 1 && $dayNum <= 31) {
            $maxDay = (int) date('t', mktime(0, 0, 0, $month, 1, $year));
            if ($dayNum <= $maxDay) {
                $day = $dayNum;
            }
        }
    }

    $dateFrom = null;
    $dateTo = null;
    if ($year !== null && $month !== null && $day !== null) {
        $dateFrom = sprintf('%04d-%02d-%02d', $year, $month, $day);
        $dateTo = $dateFrom;
    } elseif ($year !== null && $month !== null) {
        $dateFrom = sprintf('%04d-%02d-01', $year, $month);
        $dateTo = date('Y-m-t', strtotime($dateFrom) ?: time());
    } elseif ($year !== null) {
        $dateFrom = sprintf('%04d-01-01', $year);
        $dateTo = sprintf('%04d-12-31', $year);
    }

    return [
        'year' => $year,
        'month' => $month,
        'day' => $day,
        'sort' => $sort,
        'year_value' => $year !== null ? (string) $year : 'all',
        'month_value' => $month !== null ? sprintf('%02d', $month) : 'all',
        'day_value' => $day !== null ? sprintf('%02d', $day) : 'all',
        'sort_value' => $sort,
        'date_from' => $dateFrom,
        'date_to' => $dateTo,
        'is_date_filtered' => $dateFrom !== null,
    ];
}

/**
 * @param array{year:?int,month:?int,day:?int,sort:string} $parsed
 */
function voucher_table_filter_query_suffix(array $parsed): string
{
    $parts = [];
    if (!empty($parsed['year'])) {
        $parts[] = 'year=' . (int) $parsed['year'];
    }
    if (!empty($parsed['month'])) {
        $parts[] = 'month=' . sprintf('%02d', (int) $parsed['month']);
    }
    if (!empty($parsed['day'])) {
        $parts[] = 'day=' . sprintf('%02d', (int) $parsed['day']);
    }
    $sort = voucher_table_filter_normalize_sort((string) ($parsed['sort'] ?? 'asc'));
    if ($sort !== 'asc') {
        $parts[] = 'sort=' . rawurlencode($sort);
    }

    return $parts === [] ? '' : ('&' . implode('&', $parts));
}

/**
 * @return array{0: string, 1: array<string, int>}
 */
function voucher_table_filter_date_sql(string $expr, array $parsed, string $prefix = 'vtfd'): array
{
    $sql = '';
    $params = [];
    if (!empty($parsed['year'])) {
        $sql .= " AND YEAR({$expr}) = :{$prefix}_year";
        $params[':' . $prefix . '_year'] = (int) $parsed['year'];
    }
    if (!empty($parsed['month'])) {
        $sql .= " AND MONTH({$expr}) = :{$prefix}_month";
        $params[':' . $prefix . '_month'] = (int) $parsed['month'];
    }
    if (!empty($parsed['day'])) {
        $sql .= " AND DAY({$expr}) = :{$prefix}_day";
        $params[':' . $prefix . '_day'] = (int) $parsed['day'];
    }

    return [$sql, $params];
}

function voucher_table_filter_normalize_sort(string $sort): string
{
    $sort = strtolower(trim($sort));
    if ($sort === 'recent') {
        return 'asc';
    }

    return $sort === 'desc' ? 'desc' : 'asc';
}

function voucher_table_filter_order_sql(string $recentExpr, string $keyExpr, array $parsed): string
{
    $dir = voucher_table_filter_normalize_sort((string) ($parsed['sort'] ?? 'asc')) === 'desc' ? 'DESC' : 'ASC';

    return implode(', ', [
        'CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(' . $keyExpr . ', \'-\', 2), \'-\', -1) AS UNSIGNED) ' . $dir,
        'CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(' . $keyExpr . ', \'-\', 3), \'-\', -1) AS UNSIGNED) ' . $dir,
        'CAST(SUBSTRING_INDEX(' . $keyExpr . ', \'-\', -1) AS UNSIGNED) ' . $dir,
        $keyExpr . ' ' . $dir,
        $recentExpr . ' ' . $dir,
    ]);
}

/**
 * @param array<string, mixed> $opts
 */
function voucher_table_filter_render_controls(array $parsed, array $opts = []): void
{
    $yearId = (string) ($opts['year_id'] ?? 'tableYearFilter');
    $monthId = (string) ($opts['month_id'] ?? 'tableMonthFilter');
    $dayId = (string) ($opts['day_id'] ?? 'tableDayFilter');
    $sortId = (string) ($opts['sort_id'] ?? 'tableSortFilter');
    $fieldClass = (string) ($opts['field_class'] ?? 'filter-ymd-select');
    $includeSort = ($opts['include_sort'] ?? true) !== false;
    $visibleLabels = !empty($opts['visible_labels']);
    $labelClass = $visibleLabels ? '' : 'hidden';
    $currentYear = (int) date('Y');
    $startYear = (int) ($opts['start_year'] ?? 2010);
    $monthEnabled = $parsed['year'] !== null;
    $dayEnabled = $parsed['year'] !== null && $parsed['month'] !== null;
    $maxDay = $dayEnabled
        ? (int) date('t', mktime(0, 0, 0, (int) $parsed['month'], 1, (int) $parsed['year']))
        : 31;
    ?>
    <div class="<?= htmlspecialchars($fieldClass, ENT_QUOTES, 'UTF-8') ?>">
        <label class="<?= htmlspecialchars($labelClass, ENT_QUOTES, 'UTF-8') ?>" for="<?= htmlspecialchars($yearId, ENT_QUOTES, 'UTF-8') ?>">Year</label>
        <select id="<?= htmlspecialchars($yearId, ENT_QUOTES, 'UTF-8') ?>" name="year" aria-label="Filter year">
            <option value="all"<?= $parsed['year'] === null ? ' selected' : '' ?>>Year</option>
            <?php for ($y = $currentYear; $y >= $startYear; $y--): ?>
                <option value="<?= $y ?>"<?= $parsed['year'] === $y ? ' selected' : '' ?>><?= $y ?></option>
            <?php endfor; ?>
        </select>
    </div>
    <div class="<?= htmlspecialchars($fieldClass, ENT_QUOTES, 'UTF-8') ?>">
        <label class="<?= htmlspecialchars($labelClass, ENT_QUOTES, 'UTF-8') ?>" for="<?= htmlspecialchars($monthId, ENT_QUOTES, 'UTF-8') ?>">Month</label>
        <select id="<?= htmlspecialchars($monthId, ENT_QUOTES, 'UTF-8') ?>" name="month" aria-label="Filter month"<?= $monthEnabled ? '' : ' disabled' ?>>
            <option value="all"<?= $parsed['month'] === null ? ' selected' : '' ?>>Month</option>
            <?php for ($m = 1; $m <= 12; $m++):
                $mv = sprintf('%02d', $m);
                $label = date('M', mktime(0, 0, 0, $m, 1));
                ?>
                <option value="<?= $mv ?>"<?= $parsed['month'] === $m ? ' selected' : '' ?>><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></option>
            <?php endfor; ?>
        </select>
    </div>
    <div class="<?= htmlspecialchars($fieldClass, ENT_QUOTES, 'UTF-8') ?>">
        <label class="<?= htmlspecialchars($labelClass, ENT_QUOTES, 'UTF-8') ?>" for="<?= htmlspecialchars($dayId, ENT_QUOTES, 'UTF-8') ?>">Day</label>
        <select id="<?= htmlspecialchars($dayId, ENT_QUOTES, 'UTF-8') ?>" name="day" aria-label="Filter day"<?= $dayEnabled ? '' : ' disabled' ?>>
            <option value="all"<?= $parsed['day'] === null ? ' selected' : '' ?>>Day</option>
            <?php for ($d = 1; $d <= $maxDay; $d++):
                $dv = sprintf('%02d', $d);
                ?>
                <option value="<?= $dv ?>"<?= $parsed['day'] === $d ? ' selected' : '' ?>><?= $dv ?></option>
            <?php endfor; ?>
        </select>
    </div>
    <?php if ($includeSort): ?>
    <div class="<?= htmlspecialchars($fieldClass, ENT_QUOTES, 'UTF-8') ?> filter-ymd-select--sort">
        <label class="<?= htmlspecialchars($labelClass, ENT_QUOTES, 'UTF-8') ?>" for="<?= htmlspecialchars($sortId, ENT_QUOTES, 'UTF-8') ?>">Sort</label>
        <select id="<?= htmlspecialchars($sortId, ENT_QUOTES, 'UTF-8') ?>" name="sort" aria-label="Sort order">
            <option value="asc"<?= $parsed['sort'] !== 'desc' ? ' selected' : '' ?>>Ascending</option>
            <option value="desc"<?= $parsed['sort'] === 'desc' ? ' selected' : '' ?>>Descending</option>
        </select>
    </div>
    <?php endif;
}

function voucher_table_filter_script_url(): string
{
    $path = dirname(__DIR__, 3) . '/js/voucher_table_filters.js';
    if (function_exists('asset_url')) {
        return asset_url('../../protected/js/voucher_table_filters.js', $path);
    }

    return '../../protected/js/voucher_table_filters.js';
}
