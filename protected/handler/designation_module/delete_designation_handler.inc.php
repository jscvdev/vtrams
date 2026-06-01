<?php
if (empty($_GET['deleteid'])) {
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

$id = (int)$_GET['deleteid'];
if ($id <= 0) {
    $_SESSION['designation_error'] = 'Invalid designation ID.';
    redirect_to('designations');
    exit;
}

try {
    $stmt = $pdo->prepare("DELETE FROM designation_limit WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $_SESSION['designation_success'] = 'Designation deleted successfully.';
} catch (PDOException $e) {
    $_SESSION['designation_error'] = 'Failed to delete designation: ' . $e->getMessage();
}
redirect_to('designations');
exit;
