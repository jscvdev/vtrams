<?php
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['add_designation'])) {
    require_once __DIR__ . '/../../core/components/redirects/redirect_config.inc.php';
    redirect_to('designations');
    exit;
}

require_once __DIR__ . '/../../dbconnection.inc.php';
require_once __DIR__ . '/../../core/components/security/config_session.inc.php';
require_once __DIR__ . '/../../core/components/security/router.inc.php';
require_once __DIR__ . '/../../core/components/redirects/redirect_config.inc.php';
require_once __DIR__ . '/../../core/components/security/access_control.inc.php';
require_once __DIR__ . '/../../core/components/helpers/handler_transaction_helper.inc.php';

if (!AccessControl::hasRole('System Admin')) {
    $_SESSION['designation_error'] = 'Access denied. System Administrator role required.';
    redirect_to('designations');
    exit;
}

$designation = trim($_POST['designation'] ?? '');
$designated_udc = trim($_POST['designated_udc'] ?? '');
$designated_office = trim($_POST['designated_office'] ?? '');
$current_designated = (int)($_POST['current_designated'] ?? 0);
$max_designated = (int)($_POST['max_designated'] ?? 0);
$visibility = isset($_POST['visibility']) ? (int)$_POST['visibility'] : 1;

if ($designation === '') {
    $_SESSION['designation_error'] = 'Designation name is required.';
    redirect_to('designations');
    exit;
}

if ($max_designated <= 0) {
    $_SESSION['designation_error'] = 'Max Designated must be greater than 0.';
    redirect_to('designations');
    exit;
}

$tx = db_transaction($pdo, function (PDO $pdo) use ($designation, $designated_udc, $designated_office, $current_designated, $max_designated, $visibility) {
    $has_extra = false;
    try {
        $pdo->query('SELECT designated_office, visibility FROM designation_limit LIMIT 1');
        $has_extra = true;
    } catch (PDOException $e) {
        // Columns may not exist
    }

    if ($has_extra) {
        $stmt = $pdo->prepare('INSERT INTO designation_limit (designation, designated_udc, designated_office, current_designated, max_designated, visibility) VALUES (:designation, :designated_udc, :designated_office, :current_designated, :max_designated, :visibility)');
        $stmt->execute([
            ':designation' => $designation,
            ':designated_udc' => $designated_udc,
            ':designated_office' => $designated_office,
            ':current_designated' => $current_designated,
            ':max_designated' => $max_designated,
            ':visibility' => $visibility,
        ]);
    } else {
        $stmt = $pdo->prepare('INSERT INTO designation_limit (designation, designated_udc, current_designated, max_designated) VALUES (:designation, :designated_udc, :current_designated, :max_designated)');
        $stmt->execute([
            ':designation' => $designation,
            ':designated_udc' => $designated_udc,
            ':current_designated' => $current_designated,
            ':max_designated' => $max_designated,
        ]);
    }

    return true;
}, handler_is_dry_run());

if ($tx['ok']) {
    $_SESSION['designation_success'] = ($tx['dry_run'] ?? false)
        ? 'Dry-run OK (rolled back). Designation would be added.'
        : 'Designation added successfully.';
} else {
    $_SESSION['designation_error'] = handler_format_transaction_error(
        'Failed to add designation.',
        $tx['error'] ?? null
    );
}
redirect_to('designations');
exit;
