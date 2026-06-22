<?php

declare(strict_types=1);

const USER_LOGIN_MAX_FAILED_ATTEMPTS = 3;

function user_login_try_exec(PDO $pdo, string $sql): void
{
    try {
        $pdo->exec($sql);
    } catch (PDOException) {
        // Column or index may already exist.
    }
}

function user_login_ensure_schema(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    user_login_try_exec(
        $pdo,
        'ALTER TABLE `user_group` ADD COLUMN `is_blocked` TINYINT(1) NOT NULL DEFAULT 0 AFTER `access_level`'
    );
    user_login_try_exec(
        $pdo,
        'ALTER TABLE `user_group` ADD COLUMN `failed_login_attempts` TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER `is_blocked`'
    );
}

function user_login_is_blocked(array|false|null $user): bool
{
    if (!is_array($user)) {
        return false;
    }

    return (int) ($user['is_blocked'] ?? 0) === 1;
}

/**
 * Increment failed attempts; block account at USER_LOGIN_MAX_FAILED_ATTEMPTS.
 * Returns the new attempt count.
 */
function user_login_record_failed_attempt(PDO $pdo, string $emp_id): int
{
    user_login_ensure_schema($pdo);

    $stmt = $pdo->prepare(
        'UPDATE user_group
         SET failed_login_attempts = failed_login_attempts + 1
         WHERE emp_id = :emp_id'
    );
    $stmt->bindValue(':emp_id', $emp_id, PDO::PARAM_STR);
    $stmt->execute();

    $countStmt = $pdo->prepare(
        'SELECT failed_login_attempts FROM user_group WHERE emp_id = :emp_id LIMIT 1'
    );
    $countStmt->bindValue(':emp_id', $emp_id, PDO::PARAM_STR);
    $countStmt->execute();
    $attempts = (int) $countStmt->fetchColumn();

    if ($attempts >= USER_LOGIN_MAX_FAILED_ATTEMPTS) {
        $blockStmt = $pdo->prepare(
            'UPDATE user_group SET is_blocked = 1 WHERE emp_id = :emp_id'
        );
        $blockStmt->bindValue(':emp_id', $emp_id, PDO::PARAM_STR);
        $blockStmt->execute();
    }

    return $attempts;
}

function user_login_reset_attempts(PDO $pdo, string $emp_id): void
{
    user_login_ensure_schema($pdo);

    $stmt = $pdo->prepare(
        'UPDATE user_group SET failed_login_attempts = 0 WHERE emp_id = :emp_id'
    );
    $stmt->bindValue(':emp_id', $emp_id, PDO::PARAM_STR);
    $stmt->execute();
}

function user_login_unblock(PDO $pdo, string $emp_id): bool
{
    user_login_ensure_schema($pdo);

    $stmt = $pdo->prepare(
        'UPDATE user_group
         SET is_blocked = 0, failed_login_attempts = 0
         WHERE emp_id = :emp_id'
    );
    $stmt->bindValue(':emp_id', $emp_id, PDO::PARAM_STR);
    $stmt->execute();

    return $stmt->rowCount() > 0;
}

function user_login_blocked_message(): string
{
    return 'Account is blocked due to multiple failed login attempts. Contact your administrator.';
}

function user_login_failed_password_message(int $attempts): string
{
    if ($attempts >= USER_LOGIN_MAX_FAILED_ATTEMPTS) {
        return user_login_blocked_message();
    }

    $remaining = USER_LOGIN_MAX_FAILED_ATTEMPTS - $attempts;

    return 'Incorrect login Info/Password (' . $remaining . ' attempt(s) remaining)';
}
