<?php

/**
 * Login session validation for the landing page and logout flow.
 */

function login_session_required_keys(): array
{
    return [
        'logged_in',
        'logged_user_emp_id',
        'logged_user_emp_name',
        'logged_user_password',
        'acl',
        'logged_user_designation',
        'logged_user_office',
        'logged_user_section',
        'logged_user_division',
    ];
}

/**
 * True when all fields set by auth.php are present.
 */
function login_session_has_required_fields(): bool
{
    if (empty($_SESSION['logged_in']) || $_SESSION['logged_in'] !== 'true') {
        return false;
    }

    foreach (login_session_required_keys() as $key) {
        if ($key === 'logged_in') {
            continue;
        }
        if (!isset($_SESSION[$key]) || $_SESSION[$key] === '' || $_SESSION[$key] === null) {
            return false;
        }
    }

    return true;
}

/**
 * Compare session to user_group row (same rules as user_check.php).
 */
function login_session_matches_database(PDO $pdo): bool
{
    if (!login_session_has_required_fields()) {
        return false;
    }

    $query = 'SELECT * FROM user_group WHERE emp_id = :emp_id LIMIT 1';
    $statement = $pdo->prepare($query);
    $statement->bindParam(':emp_id', $_SESSION['logged_user_emp_id']);
    $statement->execute();
    $result = $statement->fetch(PDO::FETCH_ASSOC);

    if ($result === false) {
        return false;
    }

    $fullNameFromDb = trim(preg_replace('/\s+/', ' ', ($result['emp_fn'] ?? '') . ' ' . ($result['emp_mi'] ?? '') . ' ' . ($result['emp_ln'] ?? '')));
    $fullNameFromSession = trim(preg_replace('/\s+/', ' ', $_SESSION['logged_user_emp_name'] ?? ''));

    if ($fullNameFromDb !== $fullNameFromSession) {
        return false;
    }
    if (($result['password'] ?? '') != ($_SESSION['logged_user_password'] ?? '')) {
        return false;
    }
    if ((int) ($result['access_level'] ?? 0) !== (int) ($_SESSION['acl'] ?? 0)) {
        return false;
    }
    if ($result['designation'] != ($_SESSION['logged_user_designation'] ?? '')) {
        return false;
    }
    if ($result['section'] != ($_SESSION['logged_user_section'] ?? '')) {
        return false;
    }
    if ($result['division'] != ($_SESSION['logged_user_division'] ?? '')) {
        return false;
    }
    if ($result['office'] != ($_SESSION['logged_user_office'] ?? '')) {
        return false;
    }

    return true;
}

/**
 * Drop stale/partial login state without destroying the PHP session container.
 */
function invalidate_login_session(): void
{
    $keys = array_merge(login_session_required_keys(), [
        'logged_user_udc',
        'username',
        'user_id',
        'routing',
        'change_type',
        'last_regeneration',
    ]);

    foreach ($keys as $key) {
        unset($_SESSION[$key]);
    }
}

/**
 * Full logout: clear session data and remove the session cookie.
 */
function destroy_login_session(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        $expires = time() - 42000;

        if (PHP_VERSION_ID >= 70300) {
            setcookie(session_name(), '', [
                'expires' => $expires,
                'path' => $params['path'],
                'domain' => $params['domain'],
                'secure' => (bool) $params['secure'],
                'httponly' => (bool) $params['httponly'],
                'samesite' => $params['samesite'] ?? 'Lax',
            ]);
        } else {
            setcookie(
                session_name(),
                '',
                $expires,
                $params['path'],
                $params['domain'],
                (bool) $params['secure'],
                (bool) $params['httponly']
            );
        }
    }

    session_destroy();
}
