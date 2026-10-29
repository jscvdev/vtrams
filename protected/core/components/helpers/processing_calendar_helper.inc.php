<?php

declare(strict_types=1);

require_once __DIR__ . '/request_cache.inc.php';
require_once __DIR__ . '/schema_cache_helper.inc.php';

const PROCESSING_CALENDAR_CACHE_NS = 'processing_calendar';

function processing_calendar_office_start(): string
{
    return '07:00:00';
}

function processing_calendar_office_official_end(): string
{
    return '18:00:00';
}

/** Latest time that still counts toward processing (max 7:00 PM). */
function processing_calendar_office_max_end(): string
{
    return '19:00:00';
}

/**
 * @return array{start: string, official_end: string, max_end: string, work_days: string}
 */
function processing_calendar_office_hours_meta(): array
{
    return [
        'start' => '07:00',
        'official_end' => '18:00',
        'max_end' => '19:00',
        'work_days' => 'Monday–Thursday',
    ];
}

/**
 * @return array<string, string>
 */
function processing_calendar_event_types(): array
{
    return [
        'national_holiday' => 'National holiday',
        'holiday' => 'Local holiday',
        'special_order' => 'Special order (no work / office event)',
        'other' => 'Other non-working day',
    ];
}

/**
 * @return array<string, string>
 */
function processing_calendar_coverage_options(): array
{
    return [
        'whole_day' => 'Whole day (no work counted)',
        'half_day_am' => 'Half day — morning off (work 12:00 NN–7:00 PM)',
        'half_day_pm' => 'Half day — afternoon off (work 7:00 AM–12:00 NN)',
        'custom' => 'Custom remaining work hours',
    ];
}

function processing_calendar_ensure_schema(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS processing_calendar_days (
            id INT AUTO_INCREMENT PRIMARY KEY,
            event_date DATE NOT NULL,
            event_type VARCHAR(32) NOT NULL DEFAULT 'holiday',
            title VARCHAR(255) NOT NULL,
            coverage VARCHAR(16) NOT NULL DEFAULT 'whole_day',
            work_start TIME NULL DEFAULT NULL,
            work_end TIME NULL DEFAULT NULL,
            notes VARCHAR(500) NULL DEFAULT NULL,
            source VARCHAR(16) NOT NULL DEFAULT 'manual',
            created_by VARCHAR(64) NULL DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_processing_calendar_date (event_date),
            KEY idx_processing_calendar_type (event_type, event_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    processing_calendar_ensure_source_column($pdo);
    processing_calendar_seed_national_holidays($pdo);
}

function processing_calendar_ensure_source_column(PDO $pdo): void
{
    if (schema_table_has_column($pdo, 'processing_calendar_days', 'source')) {
        return;
    }

    $pdo->exec(
        "ALTER TABLE processing_calendar_days
         ADD COLUMN source VARCHAR(16) NOT NULL DEFAULT 'manual' AFTER notes"
    );
    RequestCache::forgetNamespace(SCHEMA_CACHE_NS);
}

/**
 * Gregorian Easter Sunday (Meeus/Jones/Butcher).
 */
function processing_calendar_easter_sunday(int $year): DateTimeImmutable
{
    $a = $year % 19;
    $b = intdiv($year, 100);
    $c = $year % 100;
    $d = intdiv($b, 4);
    $e = $b % 4;
    $f = intdiv($b + 8, 25);
    $g = intdiv($b - $f + 1, 3);
    $h = (19 * $a + $b - $d - $g + 15) % 30;
    $i = intdiv($c, 4);
    $k = $c % 4;
    $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
    $m = intdiv($a + 11 * $h + 22 * $l, 451);
    $month = intdiv($h + $l - 7 * $m + 114, 31);
    $day = (($h + $l - 7 * $m + 114) % 31) + 1;

    return new DateTimeImmutable(sprintf('%04d-%02d-%02d', $year, $month, $day));
}

function processing_calendar_last_monday_of_august(int $year): DateTimeImmutable
{
    $day = new DateTimeImmutable(sprintf('%04d-08-31', $year));
    while ((int) $day->format('N') !== 1) {
        $day = $day->modify('-1 day');
    }

    return $day;
}

/**
 * Official Eidul Fitr / Eidul Adha dates already proclaimed nationwide.
 * Later years are omitted until Malacañang/NCMF announces them.
 *
 * @return array<int, array{fitr?: string, adha?: string}>
 */
function processing_calendar_proclaimed_eid_dates(): array
{
    return [
        2023 => ['fitr' => '2023-04-21', 'adha' => '2023-06-29'],
        2024 => ['fitr' => '2024-04-10', 'adha' => '2024-06-17'],
        2025 => ['fitr' => '2025-03-31', 'adha' => '2025-06-06'],
    ];
}

/**
 * Statutory nationwide holidays only (RA 9492, RA 9177, RA 9849, RA 9256, RA 10966).
 * Excludes local holidays, EDSA (special working), and year-specific extras
 * such as Chinese New Year, Black Saturday, Christmas Eve, and All Souls' Day.
 *
 * @return list<array{event_date: string, title: string, notes: string}>
 */
function processing_calendar_national_holidays_for_year(int $year): array
{
    $easter = processing_calendar_easter_sunday($year);
    $heroes = processing_calendar_last_monday_of_august($year);
    $noteRegular = 'National regular holiday. Local holidays are not included.';
    $noteSpecial = 'Nationwide special non-working holiday. Local holidays are not included.';

    $days = [
        ['event_date' => sprintf('%04d-01-01', $year), 'title' => "New Year's Day", 'notes' => $noteRegular],
        ['event_date' => $easter->modify('-3 days')->format('Y-m-d'), 'title' => 'Maundy Thursday', 'notes' => $noteRegular],
        ['event_date' => $easter->modify('-2 days')->format('Y-m-d'), 'title' => 'Good Friday', 'notes' => $noteRegular],
        ['event_date' => sprintf('%04d-04-09', $year), 'title' => 'Araw ng Kagitingan', 'notes' => $noteRegular],
        ['event_date' => sprintf('%04d-05-01', $year), 'title' => 'Labor Day', 'notes' => $noteRegular],
        ['event_date' => sprintf('%04d-06-12', $year), 'title' => 'Independence Day', 'notes' => $noteRegular],
        ['event_date' => $heroes->format('Y-m-d'), 'title' => "National Heroes' Day", 'notes' => $noteRegular],
        ['event_date' => sprintf('%04d-08-21', $year), 'title' => 'Ninoy Aquino Day', 'notes' => $noteSpecial],
        ['event_date' => sprintf('%04d-11-01', $year), 'title' => "All Saints' Day", 'notes' => $noteSpecial],
        ['event_date' => sprintf('%04d-11-30', $year), 'title' => 'Bonifacio Day', 'notes' => $noteRegular],
        ['event_date' => sprintf('%04d-12-08', $year), 'title' => 'Feast of the Immaculate Conception', 'notes' => $noteSpecial],
        ['event_date' => sprintf('%04d-12-25', $year), 'title' => 'Christmas Day', 'notes' => $noteRegular],
        ['event_date' => sprintf('%04d-12-30', $year), 'title' => 'Rizal Day', 'notes' => $noteRegular],
        ['event_date' => sprintf('%04d-12-31', $year), 'title' => 'Last Day of the Year', 'notes' => $noteSpecial],
    ];

    $eid = processing_calendar_proclaimed_eid_dates()[$year] ?? [];
    if (!empty($eid['fitr'])) {
        $days[] = [
            'event_date' => $eid['fitr'],
            'title' => 'Eidul Fitr',
            'notes' => $noteRegular . ' Date as previously proclaimed; adjust if NCMF announces otherwise.',
        ];
    }
    if (!empty($eid['adha'])) {
        $days[] = [
            'event_date' => $eid['adha'],
            'title' => 'Eidul Adha',
            'notes' => $noteRegular . ' Date as previously proclaimed; adjust if NCMF announces otherwise.',
        ];
    }

    $unique = [];
    foreach ($days as $day) {
        $unique[$day['event_date']] = $day;
    }
    ksort($unique);

    return array_values($unique);
}

function processing_calendar_seed_national_holidays(PDO $pdo): void
{
    static $seeded = false;
    if ($seeded) {
        return;
    }
    $seeded = true;

    processing_calendar_ensure_source_column($pdo);

    $currentYear = (int) date('Y');
    $fromYear = 2020;
    $toYear = $currentYear + 3;

    try {
        $insert = $pdo->prepare(
            'INSERT IGNORE INTO processing_calendar_days
                (event_date, event_type, title, coverage, work_start, work_end, notes, created_by, source)
             VALUES
                (:event_date, :event_type, :title, :coverage, NULL, NULL, :notes, :created_by, :source)'
        );

        for ($year = $fromYear; $year <= $toYear; $year++) {
            foreach (processing_calendar_national_holidays_for_year($year) as $day) {
                $insert->execute([
                    ':event_date' => $day['event_date'],
                    ':event_type' => 'national_holiday',
                    ':title' => $day['title'],
                    ':coverage' => 'whole_day',
                    ':notes' => $day['notes'],
                    ':created_by' => 'system:national',
                    ':source' => 'national',
                ]);
            }
        }
    } catch (Throwable $e) {
        error_log('processing_calendar_seed_national_holidays: ' . $e->getMessage());
    }

    processing_calendar_invalidate_cache();
}

function processing_calendar_invalidate_cache(): void
{
    RequestCache::forgetNamespace(PROCESSING_CALENDAR_CACHE_NS);
}

/**
 * @return array<string, array{
 *   id: int,
 *   event_date: string,
 *   event_type: string,
 *   title: string,
 *   coverage: string,
 *   work_start: ?string,
 *   work_end: ?string,
 *   notes: string,
 *   source: string,
 *   is_national: bool
 * }>
 */
function processing_calendar_all_exceptions(PDO $pdo): array
{
    processing_calendar_ensure_schema($pdo);

    return RequestCache::remember(PROCESSING_CALENDAR_CACHE_NS, 'all', static function () use ($pdo): array {
        try {
            $sql = schema_table_has_column($pdo, 'processing_calendar_days', 'source')
                ? 'SELECT id, event_date, event_type, title, coverage, work_start, work_end, notes, source
                   FROM processing_calendar_days
                   ORDER BY event_date ASC'
                : 'SELECT id, event_date, event_type, title, coverage, work_start, work_end, notes
                   FROM processing_calendar_days
                   ORDER BY event_date ASC';
            $stmt = $pdo->query($sql);
            $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        } catch (Throwable) {
            return [];
        }

        $map = [];
        foreach ($rows as $row) {
            $date = trim((string) ($row['event_date'] ?? ''));
            if ($date === '') {
                continue;
            }
            $map[$date] = processing_calendar_normalize_row($row);
        }

        return $map;
    });
}

/**
 * @param array<string, mixed> $row
 * @return array{
 *   id: int,
 *   event_date: string,
 *   event_type: string,
 *   title: string,
 *   coverage: string,
 *   work_start: ?string,
 *   work_end: ?string,
 *   notes: string,
 *   source: string,
 *   is_national: bool
 * }
 */
function processing_calendar_normalize_row(array $row): array
{
    $types = processing_calendar_event_types();
    $coverages = processing_calendar_coverage_options();
    $type = strtolower(trim((string) ($row['event_type'] ?? 'holiday')));
    if (!isset($types[$type])) {
        $type = 'other';
    }
    $coverage = strtolower(trim((string) ($row['coverage'] ?? 'whole_day')));
    if (!isset($coverages[$coverage])) {
        $coverage = 'whole_day';
    }
    $source = strtolower(trim((string) ($row['source'] ?? 'manual')));
    if ($source !== 'national') {
        $source = 'manual';
    }
    $isNational = $source === 'national' || $type === 'national_holiday';

    return [
        'id' => (int) ($row['id'] ?? 0),
        'event_date' => trim((string) ($row['event_date'] ?? '')),
        'event_type' => $type,
        'title' => trim((string) ($row['title'] ?? '')),
        'coverage' => $coverage,
        'work_start' => processing_calendar_normalize_time($row['work_start'] ?? null),
        'work_end' => processing_calendar_normalize_time($row['work_end'] ?? null),
        'notes' => trim((string) ($row['notes'] ?? '')),
        'source' => $source,
        'is_national' => $isNational,
    ];
}

function processing_calendar_normalize_time(mixed $value): ?string
{
    $raw = trim((string) $value);
    if ($raw === '') {
        return null;
    }
    if (preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $raw, $m) !== 1) {
        return null;
    }
    $hour = (int) $m[1];
    $minute = (int) $m[2];
    $second = isset($m[3]) ? (int) $m[3] : 0;
    if ($hour > 23 || $minute > 59 || $second > 59) {
        return null;
    }

    return sprintf('%02d:%02d:%02d', $hour, $minute, $second);
}

function processing_calendar_time_to_seconds(string $time): int
{
    $parts = array_map('intval', explode(':', $time));

    return (($parts[0] ?? 0) * 3600) + (($parts[1] ?? 0) * 60) + ($parts[2] ?? 0);
}

/**
 * Remaining countable work window for a local calendar day, or null if closed.
 *
 * @param array{
 *   coverage?: string,
 *   work_start?: ?string,
 *   work_end?: ?string
 * }|null $exception
 * @return array{start: int, end: int}|null
 */
function processing_calendar_work_window_for_date(DateTimeInterface $dayStart, ?array $exception): ?array
{
    $dayOfWeek = (int) $dayStart->format('N');
    if ($dayOfWeek < 1 || $dayOfWeek > 4) {
        return null;
    }

    $officeStart = processing_calendar_time_to_seconds(processing_calendar_office_start());
    $officeEnd = processing_calendar_time_to_seconds(processing_calendar_office_max_end());
    $windowStart = $officeStart;
    $windowEnd = $officeEnd;

    if ($exception !== null) {
        $coverage = (string) ($exception['coverage'] ?? 'whole_day');
        if ($coverage === 'whole_day') {
            return null;
        }
        if ($coverage === 'half_day_am') {
            $windowStart = processing_calendar_time_to_seconds('12:00:00');
        } elseif ($coverage === 'half_day_pm') {
            $windowEnd = processing_calendar_time_to_seconds('12:00:00');
        } elseif ($coverage === 'custom') {
            $customStart = processing_calendar_normalize_time($exception['work_start'] ?? null);
            $customEnd = processing_calendar_normalize_time($exception['work_end'] ?? null);
            if ($customStart === null || $customEnd === null) {
                return null;
            }
            $windowStart = processing_calendar_time_to_seconds($customStart);
            $windowEnd = processing_calendar_time_to_seconds($customEnd);
        } else {
            return null;
        }
    }

    $windowStart = max($windowStart, $officeStart);
    $windowEnd = min($windowEnd, $officeEnd);
    if ($windowEnd <= $windowStart) {
        return null;
    }

    $midnight = $dayStart instanceof DateTimeImmutable
        ? $dayStart->setTime(0, 0, 0)
        : DateTimeImmutable::createFromInterface($dayStart)->setTime(0, 0, 0);

    return [
        'start' => $midnight->getTimestamp() + $windowStart,
        'end' => $midnight->getTimestamp() + $windowEnd,
    ];
}

function processing_calendar_work_label(?array $exception): string
{
    if ($exception === null) {
        return 'Regular office hours (7:00 AM–7:00 PM max)';
    }

    $coverage = (string) ($exception['coverage'] ?? 'whole_day');
    if ($coverage === 'whole_day') {
        return 'Closed all day';
    }
    if ($coverage === 'half_day_am') {
        return 'Morning off — work counted 12:00 NN–7:00 PM';
    }
    if ($coverage === 'half_day_pm') {
        return 'Afternoon off — work counted 7:00 AM–12:00 NN';
    }
    if ($coverage === 'custom') {
        $start = processing_calendar_normalize_time($exception['work_start'] ?? null);
        $end = processing_calendar_normalize_time($exception['work_end'] ?? null);
        if ($start === null || $end === null) {
            return 'Closed all day';
        }

        return 'Custom hours ' . substr($start, 0, 5) . '–' . substr($end, 0, 5);
    }

    return 'Closed all day';
}

/**
 * @return list<array<string, mixed>>
 */
function processing_calendar_fetch_month(PDO $pdo, int $year, int $month): array
{
    $all = processing_calendar_all_exceptions($pdo);
    $prefix = sprintf('%04d-%02d-', $year, $month);
    $out = [];
    foreach ($all as $date => $row) {
        if (!str_starts_with($date, $prefix)) {
            continue;
        }
        $row['type_label'] = processing_calendar_event_types()[$row['event_type']] ?? $row['event_type'];
        $row['coverage_label'] = processing_calendar_coverage_options()[$row['coverage']] ?? $row['coverage'];
        $row['work_label'] = processing_calendar_work_label($row);
        $row['is_national'] = !empty($row['is_national']);
        $out[] = $row;
    }

    return $out;
}

/**
 * @param array<string, mixed> $input
 * @return array{ok: true, id: int}|array{ok: false, error: string}
 */
function processing_calendar_save(PDO $pdo, array $input, string $createdBy): array
{
    processing_calendar_ensure_schema($pdo);

    $id = (int) ($input['id'] ?? 0);
    $date = trim((string) ($input['event_date'] ?? ''));
    $dt = DateTimeImmutable::createFromFormat('Y-m-d', $date);
    if ($dt === false || $dt->format('Y-m-d') !== $date) {
        return ['ok' => false, 'error' => 'Choose a valid date.'];
    }

    $title = trim((string) ($input['title'] ?? ''));
    if ($title === '') {
        return ['ok' => false, 'error' => 'Title is required.'];
    }
    if (mb_strlen($title) > 255) {
        $title = mb_substr($title, 0, 255);
    }

    $types = processing_calendar_event_types();
    $type = strtolower(trim((string) ($input['event_type'] ?? '')));
    if (!isset($types[$type])) {
        return ['ok' => false, 'error' => 'Choose a valid day type.'];
    }

    $coverages = processing_calendar_coverage_options();
    $coverage = strtolower(trim((string) ($input['coverage'] ?? '')));
    if (!isset($coverages[$coverage])) {
        return ['ok' => false, 'error' => 'Choose whole day, half day, or custom hours.'];
    }

    $workStart = processing_calendar_normalize_time($input['work_start'] ?? null);
    $workEnd = processing_calendar_normalize_time($input['work_end'] ?? null);
    if ($coverage !== 'custom') {
        $workStart = null;
        $workEnd = null;
    } elseif ($workStart === null || $workEnd === null) {
        return ['ok' => false, 'error' => 'Custom coverage requires a start and end time.'];
    } elseif (processing_calendar_time_to_seconds($workEnd) <= processing_calendar_time_to_seconds($workStart)) {
        return ['ok' => false, 'error' => 'Custom end time must be after the start time.'];
    }

    $notes = trim((string) ($input['notes'] ?? ''));
    if (mb_strlen($notes) > 500) {
        $notes = mb_substr($notes, 0, 500);
    }

    $dup = $pdo->prepare('SELECT id FROM processing_calendar_days WHERE event_date = :event_date LIMIT 1');
    $dup->execute([':event_date' => $date]);
    $existingId = (int) ($dup->fetchColumn() ?: 0);
    if ($existingId > 0 && $existingId !== $id) {
        return ['ok' => false, 'error' => 'That date already has a calendar entry. Edit the existing one instead.'];
    }

    $source = $type === 'national_holiday' ? 'national' : 'manual';
    if ($id > 0) {
        $existing = $pdo->prepare('SELECT source, event_type FROM processing_calendar_days WHERE id = :id LIMIT 1');
        $existing->bindValue(':id', $id, PDO::PARAM_INT);
        $existing->execute();
        $existingRow = $existing->fetch(PDO::FETCH_ASSOC) ?: [];
        if (strtolower(trim((string) ($existingRow['source'] ?? ''))) === 'national' && $type === 'national_holiday') {
            $source = 'national';
        }
    }

    try {
        if ($id > 0) {
            $stmt = $pdo->prepare(
                'UPDATE processing_calendar_days
                 SET event_date = :event_date, event_type = :event_type, title = :title,
                     coverage = :coverage, work_start = :work_start, work_end = :work_end, notes = :notes,
                     source = :source
                 WHERE id = :id'
            );
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO processing_calendar_days
                    (event_date, event_type, title, coverage, work_start, work_end, notes, created_by, source)
                 VALUES
                    (:event_date, :event_type, :title, :coverage, :work_start, :work_end, :notes, :created_by, :source)'
            );
            $stmt->bindValue(':created_by', $createdBy !== '' ? $createdBy : null, $createdBy !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
        }

        $stmt->bindValue(':event_date', $date);
        $stmt->bindValue(':event_type', $type);
        $stmt->bindValue(':title', $title);
        $stmt->bindValue(':coverage', $coverage);
        if ($workStart === null) {
            $stmt->bindValue(':work_start', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':work_start', $workStart);
        }
        if ($workEnd === null) {
            $stmt->bindValue(':work_end', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':work_end', $workEnd);
        }
        $stmt->bindValue(':notes', $notes !== '' ? $notes : null, $notes !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':source', $source);
        $stmt->execute();

        if ($id <= 0) {
            $id = (int) $pdo->lastInsertId();
        }
    } catch (PDOException $e) {
        error_log('processing_calendar_save: ' . $e->getMessage());

        return ['ok' => false, 'error' => 'Could not save the calendar day.'];
    }

    processing_calendar_invalidate_cache();

    return ['ok' => true, 'id' => $id];
}

function processing_calendar_delete(PDO $pdo, int $id): bool
{
    if ($id <= 0) {
        return false;
    }

    processing_calendar_ensure_schema($pdo);
    $stmt = $pdo->prepare('DELETE FROM processing_calendar_days WHERE id = :id');
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $ok = $stmt->execute();
    processing_calendar_invalidate_cache();

    return $ok && $stmt->rowCount() > 0;
}
