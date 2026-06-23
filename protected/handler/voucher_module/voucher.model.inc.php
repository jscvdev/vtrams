<?php

declare(strict_types=1);

require_once __DIR__ . '/../../core/components/helpers/amount_helper.inc.php';

/**
 * Best-effort DDL helper (legacy installs may already have dv_entries with a different shape).
 */
function dv_entries_try_exec(object $pdo, string $sql): void
{
    if ($pdo->inTransaction() && sql_is_implicit_commit_statement($sql)) {
        throw new TransactionImplicitCommitException(
            'DDL cannot run inside a transaction (MySQL implicit commit). '
            . 'Call vouchers_bootstrap_schema() before handler_execute_writes(). SQL: ' . $sql
        );
    }

    try {
        $pdo->exec($sql);
    } catch (Throwable) {
        // Duplicate column / duplicate key name / incompatible state — safe to ignore for idempotent migration.
    }
}

/**
 * Align an existing legacy dv_entries table so application INSERTs succeed.
 */
function dv_entries_migrate_legacy_shape(object $pdo): void
{
    if ($pdo->inTransaction()) {
        return;
    }

    $pk = $pdo->query("SHOW KEYS FROM dv_entries WHERE Key_name = 'PRIMARY'")->fetch(PDO::FETCH_ASSOC);
    if ($pk === false) {
        dv_entries_try_exec($pdo, 'ALTER TABLE dv_entries ADD PRIMARY KEY (id)');
    }

    $idCol = $pdo->query("SHOW COLUMNS FROM dv_entries WHERE Field = 'id'")->fetch(PDO::FETCH_ASSOC);
    if (is_array($idCol) && stripos((string)($idCol['Extra'] ?? ''), 'auto_increment') === false) {
        dv_entries_try_exec($pdo, 'ALTER TABLE dv_entries MODIFY COLUMN id INT UNSIGNED NOT NULL AUTO_INCREMENT');
    }

    dv_entries_try_exec($pdo, "ALTER TABLE dv_entries ADD COLUMN ors_no VARCHAR(64) NOT NULL DEFAULT '' AFTER ada_check_no");
    dv_entries_try_exec($pdo, "ALTER TABLE dv_entries ADD COLUMN office_from VARCHAR(255) NOT NULL DEFAULT '' AFTER encoded_by");

    dv_entries_try_exec($pdo, 'ALTER TABLE dv_entries MODIFY payee VARCHAR(512) NOT NULL');
    dv_entries_try_exec($pdo, 'ALTER TABLE dv_entries MODIFY encoded_from VARCHAR(255) NOT NULL');
    dv_entries_try_exec($pdo, 'ALTER TABLE dv_entries MODIFY encoded_by VARCHAR(255) NOT NULL');
    dv_entries_try_exec($pdo, "ALTER TABLE dv_entries MODIFY address VARCHAR(512) NOT NULL DEFAULT ''");
    dv_entries_try_exec($pdo, "ALTER TABLE dv_entries MODIFY tin_employee_no VARCHAR(255) NOT NULL DEFAULT ''");
    dv_entries_try_exec($pdo, "ALTER TABLE dv_entries MODIFY amount VARCHAR(64) NOT NULL DEFAULT ''");
    dv_entries_try_exec($pdo, 'ALTER TABLE dv_entries MODIFY particulars TEXT NULL');
    dv_entries_try_exec($pdo, "ALTER TABLE dv_entries MODIFY datetime_encoded VARCHAR(64) NOT NULL DEFAULT ''");

    dv_entries_try_exec($pdo, 'ALTER TABLE dv_entries MODIFY coa_options TEXT NULL');
    dv_entries_try_exec($pdo, 'ALTER TABLE dv_entries MODIFY coa_category VARCHAR(255) NULL');
    dv_entries_try_exec($pdo, 'ALTER TABLE dv_entries MODIFY coa_subsection VARCHAR(255) NULL');
    dv_entries_try_exec($pdo, 'ALTER TABLE dv_entries MODIFY return_remarks TEXT NULL');
    dv_entries_try_exec($pdo, 'ALTER TABLE dv_entries MODIFY process_history TEXT NULL');

    dv_entries_try_exec($pdo, 'ALTER TABLE dv_entries ADD UNIQUE KEY uq_dv_entries_processing_no (processing_no)');
    dv_entries_try_exec($pdo, 'ALTER TABLE dv_entries ADD COLUMN created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP');
}

/**
 * Ensure dv_entries exists and is writable (fresh CREATE or migrate legacy table in place).
 */
function ensure_dv_entries_table(object $pdo): void
{
    static $done = false;
    if ($done || $pdo->inTransaction()) {
        return;
    }

    $dbName = $pdo->query('SELECT DATABASE()')->fetchColumn();
    if (!is_string($dbName) || $dbName === '') {
        return;
    }

    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ? AND table_name = ?'
    );
    $stmt->execute([$dbName, 'dv_entries']);
    if ((int) $stmt->fetchColumn() === 0) {
        try {
            $pdo->exec("
            CREATE TABLE dv_entries (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                processing_no VARCHAR(64) NOT NULL,
                dv_no VARCHAR(64) NOT NULL DEFAULT '',
                ada_check_no VARCHAR(64) NOT NULL DEFAULT '',
                ors_no VARCHAR(64) NOT NULL DEFAULT '',
                payee VARCHAR(512) NOT NULL DEFAULT '',
                tin_employee_no VARCHAR(255) NOT NULL DEFAULT '',
                address VARCHAR(512) NOT NULL DEFAULT '',
                amount VARCHAR(64) NOT NULL DEFAULT '',
                voucher_type VARCHAR(255) NOT NULL DEFAULT '',
                voucher_date VARCHAR(32) NOT NULL DEFAULT '',
                particulars TEXT NULL,
                datetime_encoded VARCHAR(64) NOT NULL DEFAULT '',
                encoded_from VARCHAR(255) NOT NULL DEFAULT '',
                encoded_by VARCHAR(255) NOT NULL DEFAULT '',
                office_from VARCHAR(255) NOT NULL DEFAULT '',
                coa_options TEXT NULL,
                coa_category VARCHAR(255) NULL,
                coa_subsection VARCHAR(255) NULL,
                return_remarks TEXT NULL,
                process_history TEXT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_dv_entries_processing_no (processing_no),
                KEY idx_dv_entries_encoded_by (encoded_by),
                KEY idx_dv_entries_voucher_date (voucher_date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        } catch (Throwable) {
            // Concurrent first-time create or rare DDL race; migrate will no-op on a fresh catalog table.
        }

        $done = true;

        return;
    }

    dv_entries_migrate_legacy_shape($pdo);
    $done = true;
}

/**
 * Run legacy DDL once per request before any handler transaction (MySQL implicit-commit safe).
 */
function vouchers_bootstrap_schema(object $pdo): void
{
    static $bootstrapped = false;
    if ($bootstrapped) {
        return;
    }
    if ($pdo->inTransaction()) {
        throw new RuntimeException('vouchers_bootstrap_schema() must run before beginTransaction().');
    }

    $bootstrapped = true;
    ensure_dv_entries_table($pdo);
    vouchers_amount_ensure_string_column($pdo);
    vouchers_ensure_id_auto_increment($pdo);
    vouchers_ensure_ors_no_column($pdo);
}

/**
 * Insert a row into dv_entries when a voucher is saved (encoded).
 */
function insert_dv_entry(
    object $pdo,
    string $processing_no,
    string $dv_no,
    string $ada_check_no,
    string $ors_no,
    string $payee,
    string $address,
    string $tin_employee_no,
    string $voucher_date,
    string $amount,
    string $voucher_type,
    string $particulars,
    string $datetime_encoded,
    string $encoded_from,
    string $encoded_by,
    string $office_from
): bool {
    ensure_dv_entries_table($pdo);
    $amount = ensure_amount_two_decimals($amount);

    // Legacy table requires coa_* / return_remarks / process_history columns; omit them from ON DUPLICATE so forwarded data is not cleared.
    $sql = "INSERT INTO dv_entries (
        processing_no, dv_no, ada_check_no, ors_no, payee, tin_employee_no, address, amount, voucher_type, voucher_date, particulars,
        datetime_encoded, encoded_from, encoded_by, office_from,
        coa_options, coa_category, coa_subsection, return_remarks, process_history
    ) VALUES (
        :processing_no, :dv_no, :ada_check_no, :ors_no, :payee, :tin_employee_no, :address, :amount, :voucher_type, :voucher_date, :particulars,
        :datetime_encoded, :encoded_from, :encoded_by, :office_from,
        :coa_options, :coa_category, :coa_subsection, :return_remarks, :process_history
    ) ON DUPLICATE KEY UPDATE
        dv_no = VALUES(dv_no),
        ada_check_no = VALUES(ada_check_no),
        ors_no = VALUES(ors_no),
        payee = VALUES(payee),
        tin_employee_no = VALUES(tin_employee_no),
        address = VALUES(address),
        amount = VALUES(amount),
        voucher_type = VALUES(voucher_type),
        voucher_date = VALUES(voucher_date),
        particulars = VALUES(particulars),
        datetime_encoded = VALUES(datetime_encoded),
        encoded_from = VALUES(encoded_from),
        encoded_by = VALUES(encoded_by),
        office_from = VALUES(office_from)";

    $statement = $pdo->prepare($sql);
    // bindValue (not bindParam): native prepares + bindParam-by-reference can bind wrong values at execute().
    $statement->bindValue(':processing_no', $processing_no, PDO::PARAM_STR);
    $statement->bindValue(':dv_no', $dv_no, PDO::PARAM_STR);
    $statement->bindValue(':ada_check_no', $ada_check_no, PDO::PARAM_STR);
    $statement->bindValue(':ors_no', $ors_no, PDO::PARAM_STR);
    $statement->bindValue(':payee', $payee, PDO::PARAM_STR);
    $statement->bindValue(':tin_employee_no', $tin_employee_no, PDO::PARAM_STR);
    $statement->bindValue(':address', $address, PDO::PARAM_STR);
    $statement->bindValue(':amount', $amount, PDO::PARAM_STR);
    $statement->bindValue(':voucher_type', $voucher_type, PDO::PARAM_STR);
    $statement->bindValue(':voucher_date', $voucher_date, PDO::PARAM_STR);
    $statement->bindValue(':particulars', $particulars, PDO::PARAM_STR);
    $statement->bindValue(':datetime_encoded', $datetime_encoded, PDO::PARAM_STR);
    $statement->bindValue(':encoded_from', $encoded_from, PDO::PARAM_STR);
    $statement->bindValue(':encoded_by', $encoded_by, PDO::PARAM_STR);
    $statement->bindValue(':office_from', $office_from, PDO::PARAM_STR);
    $statement->bindValue(':coa_options', null, PDO::PARAM_NULL);
    $statement->bindValue(':coa_category', null, PDO::PARAM_NULL);
    $statement->bindValue(':coa_subsection', null, PDO::PARAM_NULL);
    $statement->bindValue(':return_remarks', null, PDO::PARAM_NULL);
    $statement->bindValue(':process_history', null, PDO::PARAM_NULL);

    $statement->execute();

    return true;
}

/** Store amount as exact string (legacy installs may use DECIMAL which rounds). */
function vouchers_amount_ensure_string_column(object $pdo): void
{
    static $done = false;
    if ($done || $pdo->inTransaction()) {
        return;
    }
    $done = true;

    $tables = [
        'vouchers',
        'voucher_incoming',
        'voucher_receiving',
        'voucher_sent',
        'voucher_temp',
        'voucher_archives',
        'voucher_action_logs',
        'voucher_tracking',
    ];

    foreach ($tables as $table) {
        dv_entries_try_exec($pdo, "ALTER TABLE `{$table}` MODIFY amount VARCHAR(64) NOT NULL DEFAULT ''");
        dv_entries_try_exec($pdo, "ALTER TABLE `{$table}` MODIFY charged_amount VARCHAR(64) NULL DEFAULT NULL");
    }
}

/** Legacy installs: pending vouchers table may lack ors_no until first return-to-encoder. */
function vouchers_ensure_ors_no_column(object $pdo): void
{
    static $done = false;
    if ($done || $pdo->inTransaction()) {
        return;
    }
    $done = true;

    dv_entries_try_exec($pdo, "ALTER TABLE `vouchers` ADD COLUMN `ors_no` VARCHAR(64) NOT NULL DEFAULT '' AFTER `ada_check_no`");
}

/** Legacy installs: id may be NOT NULL without AUTO_INCREMENT (breaks return-to-encoder INSERT). */
function vouchers_ensure_id_auto_increment(object $pdo): void
{
    static $done = false;
    if ($done || $pdo->inTransaction()) {
        return;
    }
    $done = true;

    try {
        $idCol = $pdo->query("SHOW COLUMNS FROM vouchers WHERE Field = 'id'")->fetch(PDO::FETCH_ASSOC);
        if (!is_array($idCol) || stripos((string) ($idCol['Extra'] ?? ''), 'auto_increment') !== false) {
            return;
        }

        $pk = $pdo->query("SHOW KEYS FROM vouchers WHERE Key_name = 'PRIMARY'")->fetch(PDO::FETCH_ASSOC);
        if ($pk === false) {
            dv_entries_try_exec($pdo, 'ALTER TABLE vouchers ADD PRIMARY KEY (id)');
        }

        $nextId = 1;
        $maxStmt = $pdo->query('SELECT COALESCE(MAX(id), 0) + 1 AS next_id FROM vouchers');
        $maxRow = $maxStmt ? $maxStmt->fetch(PDO::FETCH_ASSOC) : false;
        if (is_array($maxRow) && isset($maxRow['next_id'])) {
            $nextId = max(1, (int) $maxRow['next_id']);
        }

        dv_entries_try_exec(
            $pdo,
            'ALTER TABLE vouchers MODIFY id INT(10) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=' . $nextId
        );
    } catch (Throwable) {
        // Table may be absent on partial installs.
    }
}

function insert_to_vouchers(object $pdo, string $processing_no, string $dv_no, string $ada_check_no, string $payee_name, string $address, string $tin_employee_no, string $voucher_date, string $amount, string $voucher_type, string $particulars, string $datetime_action, string $encoded_from, string $encoded_by) {
    vouchers_amount_ensure_string_column($pdo);
    vouchers_ensure_id_auto_increment($pdo);
    $amount = ensure_amount_two_decimals($amount);
    $query = "INSERT INTO vouchers (processing_no, dv_no, ada_check_no, payee, address, amount, voucher_type, particulars, datetime_encoded, encoded_from, encoded_by, tin_employee_no, voucher_date) 
                        VALUES (:processing_no, :dv_no, :ada_check_no, :payee, :address, :amount, :voucher_type, :particulars, :datetime_encoded, :encoded_from, :encoded_by, :tin_employee_no, :voucher_date)";

    $statement = $pdo->prepare($query);

    $statement->bindParam(":processing_no",$processing_no);
    $statement->bindParam(":dv_no",$dv_no);
    $statement->bindParam(":ada_check_no",$ada_check_no);
    $statement->bindParam(":payee",$payee_name);
    $statement->bindParam(":address",$address);
    $statement->bindValue(":amount", $amount, PDO::PARAM_STR);
    $statement->bindParam(":voucher_type",$voucher_type);
    $statement->bindParam(":tin_employee_no",$tin_employee_no);
    $statement->bindParam(":voucher_date",$voucher_date);
    $statement->bindParam(":particulars",$particulars);
    $statement->bindParam(":datetime_encoded",$datetime_action);
    $statement->bindParam(":encoded_from",$encoded_from);
    $statement->bindParam(":encoded_by",$encoded_by);

    $statement->execute();

    return $statement->rowCount() > 0;
}

function insert_dv_no(object $pdo, string $dv_no) {
    $query = "INSERT into encoded_voucher_no (dv_no) VALUES (:dv_no)";

    $statement = $pdo->prepare($query);

    $statement->bindParam(":dv_no",$dv_no);

    $statement->execute();

    return $statement->rowCount() > 0;
}

function get_dv_no(object $pdo, string $dv_no) {
    $query = "SELECT * FROM encoded_voucher_no WHERE dv_no = :dv_no";

    $statement = $pdo->prepare($query);

    $statement->bindParam(":dv_no",$dv_no);

    $statement->execute();

    return $statement->rowCount() > 0;
}

function voucher_log_to_document_tracking(object $pdo, string $processing_no, string $ors_no, string $ada_check_no, string $dv_no, string $payee, string $address, string $particulars, string $amount, string $voucher_type, string $voucher_date, string $datetime_encoded, string $action, string $datetime_action, string $encoded_by, string $office_to, string $office_from, string $combined_remarks, string $coa_options = null, string $coa_category = null, string $coa_subsection = null) {
    vouchers_amount_ensure_string_column($pdo);
    $amount = ensure_amount_two_decimals($amount);
    $ada_check_date = 'TBD';
    $status = 'TBD';
    $query = "INSERT INTO voucher_tracking (processing_no, ors_no, ada_check_no, ada_check_date, dv_no, payee, address, particulars, amount, voucher_type, voucher_date, datetime_encoded, voucher_status, status, datetime_status, encoded_by, office_to, office_from, remarks, coa_options, coa_category, coa_subsection, active_status) 
                        VALUES (:processing_no, :ors_no, :ada_check_no, :ada_check_date, :dv_no, :payee, :address, :particulars, :amount, :voucher_type, :voucher_date, :datetime_encoded, :voucher_status, :status, :datetime_status, :encoded_by, :office_to, :office_from, :remarks, :coa_options, :coa_category, :coa_subsection, 'no')";

    $statement = $pdo->prepare($query);

    $statement->bindParam(":processing_no",$processing_no);
    $statement->bindParam(":ors_no",$ors_no);
    $statement->bindParam(":ada_check_no",$ada_check_no);
    $statement->bindParam(":ada_check_date",$ada_check_date);
    $statement->bindParam(":dv_no",$dv_no);
    $statement->bindParam(":payee",$payee);
    $statement->bindParam(":address",$address);
    $statement->bindParam(":particulars",$particulars);
    $statement->bindValue(":amount", $amount, PDO::PARAM_STR);
    $statement->bindParam(":voucher_type",$voucher_type);
    $statement->bindParam(":voucher_date",$voucher_date);
    $statement->bindParam(":datetime_encoded",$datetime_encoded);
    $statement->bindParam(":voucher_status",$action);
    $statement->bindParam(":status",$status);
    $statement->bindParam(":datetime_status",$datetime_action);
    $statement->bindParam(":encoded_by",$encoded_by);
    $statement->bindParam(":office_to",$office_to);
    $statement->bindParam(":office_from",$office_from);
    $statement->bindParam(":remarks",$combined_remarks);
    $statement->bindParam(":coa_options",$coa_options);
    $statement->bindParam(":coa_category",$coa_category);
    $statement->bindParam(":coa_subsection",$coa_subsection);

    $statement->execute();

    return $statement->rowCount() > 0;
}

/** Preserve dv_no on edit when the form does not submit it (pending vouchers stay TBD). */
function voucher_resolve_existing_dv_no(object $pdo, string $processing_no, string $posted_dv_no = ''): string
{
    $dv_no = trim($posted_dv_no);
    if ($dv_no !== '') {
        return $dv_no;
    }

    $stmt = $pdo->prepare('SELECT dv_no FROM vouchers WHERE processing_no = :processing_no LIMIT 1');
    $stmt->bindValue(':processing_no', $processing_no, PDO::PARAM_STR);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $existing = trim((string) ($row['dv_no'] ?? ''));

    return $existing !== '' ? $existing : 'TBD';
}

/**
 * Keep voucher_tracking (Voucher Status page) in sync after a pending-voucher edit.
 */
function sync_voucher_tracking_after_edit(
    object $pdo,
    string $processing_no,
    string $payee,
    string $address,
    string $particulars,
    string $amount,
    string $voucher_type,
    string $voucher_date,
    string $voucher_status,
    string $datetime_status
): bool {
    vouchers_amount_ensure_string_column($pdo);
    $amount = ensure_amount_two_decimals($amount);

    $query = 'UPDATE voucher_tracking SET
        payee = :payee,
        address = :address,
        particulars = :particulars,
        amount = :amount,
        voucher_type = :voucher_type,
        voucher_date = :voucher_date,
        voucher_status = :voucher_status,
        datetime_status = :datetime_status
        WHERE processing_no = :processing_no';

    $statement = $pdo->prepare($query);
    $statement->bindValue(':payee', $payee, PDO::PARAM_STR);
    $statement->bindValue(':address', $address, PDO::PARAM_STR);
    $statement->bindValue(':particulars', $particulars, PDO::PARAM_STR);
    $statement->bindValue(':amount', $amount, PDO::PARAM_STR);
    $statement->bindValue(':voucher_type', $voucher_type, PDO::PARAM_STR);
    $statement->bindValue(':voucher_date', $voucher_date, PDO::PARAM_STR);
    $statement->bindValue(':voucher_status', $voucher_status, PDO::PARAM_STR);
    $statement->bindValue(':datetime_status', $datetime_status, PDO::PARAM_STR);
    $statement->bindValue(':processing_no', $processing_no, PDO::PARAM_STR);
    $statement->execute();

    return $statement->rowCount() > 0;
}

/**
 * Keep dv_entries aligned when a pending voucher is edited (voucher.php Edit).
 * Updates editable fields on an existing row; inserts when none exists yet.
 */
function sync_dv_entry_after_voucher_edit(
    object $pdo,
    string $processing_no,
    string $payee,
    string $address,
    string $tin_employee_no,
    string $voucher_date,
    string $amount,
    string $voucher_type,
    string $particulars,
    string $encoded_by,
    string $office_from,
    string $encoded_from = '',
    string $datetime_encoded = ''
): bool {
    require_once __DIR__ . '/../../core/components/helpers/voucher_tracking_helper.inc.php';

    $processing_no = trim($processing_no);
    if ($processing_no === '') {
        return false;
    }

    ensure_dv_entries_table($pdo);
    $amount = ensure_amount_two_decimals($amount);

    $existsStmt = $pdo->prepare('SELECT 1 FROM dv_entries WHERE processing_no = :processing_no LIMIT 1');
    $existsStmt->bindValue(':processing_no', $processing_no, PDO::PARAM_STR);
    $existsStmt->execute();
    $exists = (bool) $existsStmt->fetchColumn();

    if ($exists) {
        $sql = 'UPDATE dv_entries SET
            payee = :payee,
            address = :address,
            tin_employee_no = :tin_employee_no,
            amount = :amount,
            voucher_type = :voucher_type,
            voucher_date = :voucher_date,
            particulars = :particulars,
            encoded_by = :encoded_by,
            office_from = :office_from
            WHERE processing_no = :processing_no';

        $statement = $pdo->prepare($sql);
        $statement->bindValue(':payee', $payee, PDO::PARAM_STR);
        $statement->bindValue(':address', $address, PDO::PARAM_STR);
        $statement->bindValue(':tin_employee_no', $tin_employee_no, PDO::PARAM_STR);
        $statement->bindValue(':amount', $amount, PDO::PARAM_STR);
        $statement->bindValue(':voucher_type', $voucher_type, PDO::PARAM_STR);
        $statement->bindValue(':voucher_date', $voucher_date, PDO::PARAM_STR);
        $statement->bindValue(':particulars', $particulars, PDO::PARAM_STR);
        $statement->bindValue(':encoded_by', $encoded_by, PDO::PARAM_STR);
        $statement->bindValue(':office_from', $office_from, PDO::PARAM_STR);
        $statement->bindValue(':processing_no', $processing_no, PDO::PARAM_STR);
        $statement->execute();

        return true;
    }

    $identifiers = voucher_fetch_identifiers($pdo, $processing_no);
    $dv_no = voucher_resolve_existing_dv_no($pdo, $processing_no, '');
    $ors_no = voucher_pick_field($identifiers['ors_no'] ?? '', 'TBD');
    $ada_check_no = voucher_pick_field($identifiers['ada_check_no'] ?? '', 'TBD');
    if ($ors_no === '') {
        $ors_no = 'TBD';
    }
    if ($ada_check_no === '') {
        $ada_check_no = 'TBD';
    }

    $final_encoded_from = trim($encoded_from);
    if ($final_encoded_from === '') {
        $final_encoded_from = trim((string) ($_SESSION['logged_user_section'] ?? ''));
    }

    $final_datetime_encoded = trim($datetime_encoded);
    if ($final_datetime_encoded === '') {
        date_default_timezone_set('Asia/Singapore');
        $final_datetime_encoded = date('Y-m-d H:i:s');
    }

    return insert_dv_entry(
        $pdo,
        $processing_no,
        $dv_no,
        $ada_check_no,
        $ors_no,
        $payee,
        $address,
        $tin_employee_no,
        $voucher_date,
        $amount,
        $voucher_type,
        $particulars,
        $final_datetime_encoded,
        $final_encoded_from,
        $encoded_by,
        $office_from
    );
}

/**
 * Mirror address, particulars, and voucher_date on voucher_tracking.
 */
function sync_voucher_tracking_details(
    object $pdo,
    string $processing_no,
    string $address,
    string $particulars,
    string $voucher_date,
    string $voucher_status,
    string $datetime_status
): bool {
    $query = 'UPDATE voucher_tracking SET
        address = :address,
        particulars = :particulars,
        voucher_date = :voucher_date,
        voucher_status = :voucher_status,
        datetime_status = :datetime_status
        WHERE processing_no = :processing_no';

    $statement = $pdo->prepare($query);
    $statement->bindValue(':address', $address, PDO::PARAM_STR);
    $statement->bindValue(':particulars', $particulars, PDO::PARAM_STR);
    $statement->bindValue(':voucher_date', $voucher_date, PDO::PARAM_STR);
    $statement->bindValue(':voucher_status', $voucher_status, PDO::PARAM_STR);
    $statement->bindValue(':datetime_status', $datetime_status, PDO::PARAM_STR);
    $statement->bindValue(':processing_no', $processing_no, PDO::PARAM_STR);
    $statement->execute();

    return $statement->rowCount() > 0;
}

/**
 * Mirror charged_amount on voucher_tracking so Voucher Status shows edited amounts.
 */
function sync_voucher_tracking_charged_amount(object $pdo, string $processing_no, ?string $charged_amount): bool
{
    vouchers_amount_ensure_string_column($pdo);

    $charged = null;
    if ($charged_amount !== null && trim($charged_amount) !== '') {
        $charged = ensure_amount_two_decimals($charged_amount);
    }

    $query = 'UPDATE voucher_tracking SET charged_amount = :charged_amount WHERE processing_no = :processing_no';
    $statement = $pdo->prepare($query);
    $statement->bindValue(':charged_amount', $charged, $charged === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $statement->bindValue(':processing_no', $processing_no, PDO::PARAM_STR);
    $statement->execute();

    return $statement->rowCount() > 0;
}