<?php
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['edit_designation'])) {
    require_once __DIR__ . '/../../core/components/redirects/redirect_config.inc.php';
    redirect_to('designations');
    exit;
}

require_once __DIR__ . '/../../dbconnection.inc.php';
require_once __DIR__ . '/../../core/components/security/config_session.inc.php';
require_once __DIR__ . '/../../core/components/security/router.inc.php';
require_once __DIR__ . '/../../core/components/redirects/redirect_config.inc.php';
require_once __DIR__ . '/../../core/components/security/access_control.inc.php';

if (!AccessControl::hasRole('System Admin')) {
    $_SESSION['designation_error'] = 'Access denied. System Administrator role required.';
    redirect_to('designations');
    exit;
}

$id = (int)($_POST['designation_id'] ?? 0);
$designation = trim($_POST['designation'] ?? '');
$designated_udc = trim($_POST['designated_udc'] ?? '');
$designated_office = trim($_POST['designated_office'] ?? '');
$current_designated = (int)($_POST['current_designated'] ?? 0);
$max_designated = (int)($_POST['max_designated'] ?? 0);
$visibility = isset($_POST['visibility']) ? (int)$_POST['visibility'] : 1;

if ($id <= 0 || $designation === '') {
    $_SESSION['designation_error'] = 'Invalid designation or ID.';
    redirect_to('designations');
    exit;
}

try {
    $pdo->beginTransaction();
    $has_extra = false;
    try {
        $pdo->query("SELECT designated_office, visibility FROM designation_limit LIMIT 1");
        $has_extra = true;
    } catch (PDOException $e) {
        // Columns may not exist
    }

    if ($has_extra) {
        $stmt = $pdo->prepare("UPDATE designation_limit SET designation = :designation, designated_udc = :designated_udc, designated_office = :designated_office, current_designated = :current_designated, max_designated = :max_designated, visibility = :visibility WHERE id = :id");
        $stmt->execute([
            ':designation' => $designation,
            ':designated_udc' => $designated_udc,
            ':designated_office' => $designated_office,
            ':current_designated' => $current_designated,
            ':max_designated' => $max_designated,
            ':visibility' => $visibility,
            ':id' => $id
        ]);
    } else {
        $stmt = $pdo->prepare("UPDATE designation_limit SET designation = :designation, designated_udc = :designated_udc, current_designated = :current_designated, max_designated = :max_designated WHERE id = :id");
        $stmt->execute([
            ':designation' => $designation,
            ':designated_udc' => $designated_udc,
            ':current_designated' => $current_designated,
            ':max_designated' => $max_designated,
            ':id' => $id
        ]);
    }
    $pdo->commit();
    $_SESSION['designation_success'] = 'Designation updated successfully.';
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $_SESSION['designation_error'] = 'Failed to update designation: ' . $e->getMessage();
}
redirect_to('designations');
exit;
