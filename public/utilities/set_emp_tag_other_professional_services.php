<?php
include('../includes/header.php');
require_once __DIR__ . '/../../protected/core/components/helpers/audit_helper.inc.php';
AuditHelper::logPageView('Set emp_tag - Other Professional Services');

// Utilities: System Admin only
$target = explode(",", $_SESSION['logged_user_designation'] ?? '');
$can_view_utilities = in_array("System Admin", $target, true);
if (!$can_view_utilities) {
    echo "<script>process_functionAlert('Access denied!', 'dashboard_redirect')</script>";
    die();
}

$flash = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tokenOk = isset($_POST['token'], $_SESSION['token']) && hash_equals((string)$_SESSION['token'], (string)$_POST['token']);

    if (!$tokenOk) {
        $flash = ['type' => 'error', 'msg' => 'Invalid token. Please refresh and try again.'];
    } else {
        $tag = 'Other Professional Services';
        try {
            // Only fill empty tags to avoid overwriting any manually set values.
            $stmt = $pdo->prepare("UPDATE user_group SET emp_tag = :tag WHERE emp_tag IS NULL OR emp_tag = ''");
            $stmt->execute([':tag' => $tag]);

            $updated = $stmt->rowCount();
            $flash = ['type' => 'success', 'msg' => "Updated {$updated} user(s) with emp_tag = {$tag} (only where emp_tag was empty)."];
        } catch (PDOException $e) {
            $flash = ['type' => 'error', 'msg' => 'Database error: ' . $e->getMessage()];
        }
    }
}
?>

<div class="main main--dashboard util-page" id="main">
    <header class="voucher-dashboard-header">
        <h1 class="voucher-dashboard-title">Utilities</h1>
    </header>

    <div class="voucher-card voucher-card--table">
        <h2 class="voucher-card-title"><i class="ri-price-tag-3-line ri-icon"></i> Set emp_tag</h2>
        <div class="content-wrapper">
            <?php if ($flash): ?>
                <div class="util-alert <?= htmlspecialchars($flash['type'], ENT_QUOTES, 'UTF-8') ?>">
                    <?= htmlspecialchars($flash['msg'], ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <p class="util-section-title" style="border-bottom:none; padding-bottom:0; margin-bottom:0.75rem;">
                Set missing <code>emp_tag</code> to <strong>Other Professional Services</strong>
            </p>

            <form method="post" style="display:flex; gap:0.75rem; align-items:center; flex-wrap:wrap;">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($_SESSION['token'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                <button class="btn primary" type="submit" name="run" value="1"
                        onclick="return confirm('Set emp_tag to Other Professional Services for users with empty emp_tag?');">
                    Run Update
                </button>
            </form>
        </div>
    </div>
</div>

<script src="../../protected/js/main.js"></script>
</body>

</html>

