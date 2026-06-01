<?php
// Router already redirects if not logged in; only validate when session says logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== 'true') {
    return;
}
// Session claims logged in but missing emp_id (corrupt/incomplete) -> logout to avoid errors and loop
if (empty($_SESSION['logged_user_emp_id'])) {
    require_once __DIR__ . '/../redirects/redirect_config.inc.php';
    header('Location: ' . redirect_base_url() . '/protected/core/components/security/logout_handler.inc.php');
    exit;
}

$query = "SELECT * FROM user_group WHERE emp_id = :emp_id LIMIT 1";
$statement = $pdo->prepare($query);
$statement->bindParam(":emp_id", $_SESSION['logged_user_emp_id']);
$statement->execute();

$result = $statement->fetch(PDO::FETCH_ASSOC);

// User not found in DB (deleted or invalid session) -> logout once and stop to avoid loop
if ($result === false) {
    require_once __DIR__ . '/../redirects/redirect_config.inc.php';
    $logoutUrl = redirect_base_url() . '/protected/core/components/security/logout_handler.inc.php';
    header('Location: ' . $logoutUrl);
    exit;
}

// Normalize name: trim and collapse multiple spaces (must match auth.php stored value)
$fullNameFromDb = trim(preg_replace('/\s+/', ' ', ($result['emp_fn'] ?? '') . ' ' . ($result['emp_mi'] ?? '') . ' ' . ($result['emp_ln'] ?? '')));
$fullNameFromSession = trim(preg_replace('/\s+/', ' ', $_SESSION['logged_user_emp_name'] ?? ''));

if ($fullNameFromDb !== $fullNameFromSession) {
    require_once __DIR__ . '/../redirects/redirect_config.inc.php';
    header('Location: ' . redirect_base_url() . '/protected/core/components/security/logout_handler.inc.php');
    exit;
}
if (($result['password'] ?? '') != ($_SESSION['logged_user_password'] ?? '')) {
    require_once __DIR__ . '/../redirects/redirect_config.inc.php';
    header('Location: ' . redirect_base_url() . '/protected/core/components/security/logout_handler.inc.php');
    exit;
}
if ((int)($result['access_level'] ?? 0) !== (int)($_SESSION['acl'] ?? 0)) {
    require_once __DIR__ . '/../redirects/redirect_config.inc.php';
    header('Location: ' . redirect_base_url() . '/protected/core/components/security/logout_handler.inc.php');
    exit;
}
if ($result['designation'] != ($_SESSION['logged_user_designation'] ?? '')) {
    require_once __DIR__ . '/../redirects/redirect_config.inc.php';
    header('Location: ' . redirect_base_url() . '/protected/core/components/security/logout_handler.inc.php');
    exit;
}
if ($result['section'] != ($_SESSION['logged_user_section'] ?? '')) {
    require_once __DIR__ . '/../redirects/redirect_config.inc.php';
    header('Location: ' . redirect_base_url() . '/protected/core/components/security/logout_handler.inc.php');
    exit;
}
if ($result['division'] != ($_SESSION['logged_user_division'] ?? '')) {
    require_once __DIR__ . '/../redirects/redirect_config.inc.php';
    header('Location: ' . redirect_base_url() . '/protected/core/components/security/logout_handler.inc.php');
    exit;
}
if ($result['office'] != ($_SESSION['logged_user_office'] ?? '')) {
    require_once __DIR__ . '/../redirects/redirect_config.inc.php';
    header('Location: ' . redirect_base_url() . '/protected/core/components/security/logout_handler.inc.php');
    exit;
}
?>




