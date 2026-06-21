<?php

declare(strict_types=1);

/**
 * ACID transaction helpers for HTTP handlers and maintenance scripts.
 *
 * - Validate before calling handler_execute_writes() / db_transaction().
 * - db_transaction() rolls back on any Throwable.
 * - Dry-run executes writes then rolls back (no commit).
 * - On failure, handler_emit_notify() uses the top-right toast (showNotify).
 * - DDL (ALTER/CREATE/DROP) must run only via vouchers_bootstrap_schema() before beginTransaction();
 *   MySQL implicitly commits open transactions on DDL.
 */
class TransactionImplicitCommitException extends RuntimeException
{
}

/** Apply strict PDO settings (idempotent). */
function pdo_configure(PDO $pdo): void
{
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
}

function sql_is_implicit_commit_statement(string $sql): bool
{
    $upper = ltrim(strtoupper(preg_replace('/\s+/', ' ', $sql)));

    foreach ([
        'ALTER ',
        'CREATE ',
        'DROP ',
        'TRUNCATE ',
        'RENAME ',
        'LOCK TABLES',
        'UNLOCK TABLES',
        'GRANT ',
        'REVOKE ',
        'SET AUTOCOMMIT',
    ] as $prefix) {
        if (str_starts_with($upper, $prefix)) {
            return true;
        }
    }

    return false;
}

function pdo_safe_rollback(PDO $pdo): void
{
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
}

function pdo_safe_commit(PDO $pdo): void
{
    if (!$pdo->inTransaction()) {
        throw new TransactionImplicitCommitException(
            'There is no active transaction. A prior statement (often ALTER TABLE or CREATE TABLE) may have caused an implicit commit in MySQL.'
        );
    }

    $pdo->commit();
}

/**
 * After beginTransaction(), MySQL DDL ends the transaction without throwing.
 * Detect that so we do not report success after a broken transaction boundary.
 */
function pdo_assert_transaction_active(PDO $pdo, string $context): void
{
    if (!$pdo->inTransaction()) {
        throw new TransactionImplicitCommitException(
            $context . ' Transaction is no longer active (likely implicit commit from DDL such as ALTER TABLE or CREATE TABLE).'
        );
    }
}

/**
 * True when dry-run is requested (CLI: --dry-run, HTTP: dry_run=1).
 */
function handler_is_dry_run(): bool
{
    if (PHP_SAPI === 'cli') {
        return in_array('--dry-run', $GLOBALS['argv'] ?? [], true);
    }

    return isset($_POST['dry_run'], $_REQUEST['dry_run'])
        && ((string) ($_POST['dry_run'] ?? $_REQUEST['dry_run']) === '1');
}

/**
 * @template T
 * @param callable(PDO): T $callback
 * @return array{ok: bool, result: mixed, error: ?Throwable, dry_run: bool}
 */
function db_transaction(PDO $pdo, callable $callback, bool $dryRun = false): array
{
    pdo_configure($pdo);

    try {
        if ($pdo->inTransaction()) {
            throw new RuntimeException('Nested transactions are not supported.');
        }

        $pdo->beginTransaction();
        $result = $callback($pdo);

        pdo_assert_transaction_active(
            $pdo,
            'Before completing the database transaction.'
        );

        if ($dryRun) {
            pdo_safe_rollback($pdo);

            return [
                'ok' => true,
                'result' => $result,
                'error' => null,
                'dry_run' => true,
            ];
        }

        pdo_safe_commit($pdo);

        return [
            'ok' => true,
            'result' => $result,
            'error' => null,
            'dry_run' => false,
        ];
    } catch (Throwable $e) {
        pdo_safe_rollback($pdo);

        error_log('db_transaction failed: ' . $e->getMessage());

        return [
            'ok' => false,
            'result' => null,
            'error' => $e,
            'dry_run' => $dryRun,
            'implicit_commit' => $e instanceof TransactionImplicitCommitException,
        ];
    }
}

/** Store a toast message for the next full page load (after redirect). */
function handler_set_flash_notify(string $message, string $type = 'success', int $ms = 2800): void
{
    $_SESSION['flash_notify'] = [
        'message' => $message,
        'type' => $type,
        'ms' => $ms,
    ];
}

/**
 * Redirect after storing a flash toast for the destination page (not the handler response).
 *
 * @return never
 */
function handler_redirect_with_notify(
    string $message,
    string $redirectCode,
    string $type = 'success',
    int $notifyMs = 2800
): void {
    if (!function_exists('redirect_to_by_code')) {
        require_once __DIR__ . '/../redirects/redirect_config.inc.php';
    }

    handler_set_flash_notify($message, $type, $notifyMs);

    if (function_exists('generateToken')) {
        $_SESSION['token'] = generateToken();
    }

    redirect_to_by_code($redirectCode);
}

/**
 * Emit top-right toast when the parent page defines showNotify (notification.inc.php).
 */
function handler_emit_notify(string $message, string $type = 'error', int $ms = 5000): void
{
    $jsonMessage = json_encode(
        $message,
        JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
    );
    $jsonType = json_encode($type, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);

    echo '<script>if (typeof showNotify === "function") { showNotify('
        . $jsonMessage . ', ' . $jsonType . ', ' . $ms . '); }</script>';
}

function handler_format_transaction_error(string $failureMessage, ?Throwable $error): string
{
    $message = trim($failureMessage);
    if ($error !== null && trim($error->getMessage()) !== '') {
        $message = $message !== '' ? $message . ' ' . $error->getMessage() : $error->getMessage();
    }

    return $message !== '' ? $message : 'Transaction failed.';
}

/** Clear stale validation errors so a later success redirect does not show old failures. */
function handler_clear_form_errors(): void
{
    if (!isset($_SESSION) || !is_array($_SESSION)) {
        return;
    }

    foreach (array_keys($_SESSION) as $key) {
        if (str_starts_with((string) $key, 'error_')) {
            unset($_SESSION[$key]);
        }
    }
}

/**
 * @return array<string, mixed>|null
 */
function handler_parse_json_response(string $raw): ?array
{
    $raw = trim($raw);
    if ($raw === '') {
        return null;
    }

    try {
        $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        return is_array($decoded) ? $decoded : null;
    } catch (JsonException) {
        $start = strpos($raw, '{');
        $end = strrpos($raw, '}');
        if ($start === false || $end === false || $end <= $start) {
            return null;
        }

        try {
            $decoded = json_decode(substr($raw, $start, $end - $start + 1), true, 512, JSON_THROW_ON_ERROR);
            return is_array($decoded) ? $decoded : null;
        } catch (JsonException) {
            return null;
        }
    }
}

function handler_begin_clean_output(): void
{
    if (ob_get_level() === 0) {
        ob_start();
    }
}

function handler_flush_json_response(array $payload, int $statusCode = 200): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    if (!headers_sent()) {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
    }

    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Run validated writes in a transaction; notify + redirect on result.
 *
 * @param callable(PDO): mixed $writes Runs inside the transaction (throw to roll back).
 * @param callable(mixed): void|null $afterCommit Runs only after a successful commit.
 */
function handler_execute_writes(
    PDO $pdo,
    callable $writes,
    string $successMessage,
    string $failureMessage,
    string $redirectCode,
    ?callable $afterCommit = null
): void {
    if (!function_exists('generateToken')) {
        throw new RuntimeException('generateToken() is not available. Include a handler required module first.');
    }

    pdo_configure($pdo);
    if (function_exists('vouchers_bootstrap_schema')) {
        vouchers_bootstrap_schema($pdo);
    }

    $dryRun = handler_is_dry_run();
    $tx = db_transaction($pdo, $writes, $dryRun);

    if (!$tx['ok']) {
        $detail = handler_format_transaction_error($failureMessage, $tx['error'] ?? null);
        if (!empty($tx['implicit_commit'])) {
            $detail .= ' Some changes may already be saved because MySQL committed earlier statements. Re-run vouchers_bootstrap_schema before transactions and avoid DDL inside writes.';
        }
        $_SESSION['token'] = generateToken();
        handler_redirect_with_notify($detail, $redirectCode, 'error', 6000);
    }

    if ($dryRun) {
        $_SESSION['token'] = generateToken();
        handler_redirect_with_notify('Dry-run completed successfully. No changes were saved.', $redirectCode, 'info', 5000);
    }

    handler_clear_form_errors();

    if ($afterCommit !== null) {
        try {
            $afterCommit($tx['result'] ?? null);
        } catch (Throwable $e) {
            error_log('handler_execute_writes afterCommit failed: ' . $e->getMessage());
        }
    }

    $_SESSION['token'] = generateToken();
    handler_redirect_with_notify($successMessage, $redirectCode, 'success', 2500);
}

/**
 * JSON API handlers (e.g. ADA multi save).
 *
 * @return never
 */
function handler_json_transaction_response(
    PDO $pdo,
    callable $writes,
    string $successMessage = 'Data saved successfully!',
    string $failureMessage = 'Transaction failed.'
): void {
    handler_begin_clean_output();
    pdo_configure($pdo);
    if (function_exists('vouchers_bootstrap_schema')) {
        vouchers_bootstrap_schema($pdo);
    }

    $dryRun = handler_is_dry_run();
    $tx = db_transaction($pdo, $writes, $dryRun);

    if (!$tx['ok']) {
        handler_flush_json_response([
            'ok' => false,
            'error' => handler_format_transaction_error($failureMessage, $tx['error'] ?? null),
            'notify_type' => 'error',
        ], 500);
    }

    if (!$dryRun) {
        handler_clear_form_errors();
    }

    handler_flush_json_response([
        'ok' => true,
        'dry_run' => (bool) ($tx['dry_run'] ?? false),
        'message' => $dryRun ? 'Dry-run OK (rolled back)' : $successMessage,
        'notify_type' => $dryRun ? 'info' : 'success',
    ], 200);
}
