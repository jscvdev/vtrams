<?php
include('../includes/header.php');

require_once __DIR__ . '/../../protected/core/components/redirects/redirect_config.inc.php';
require_once __DIR__ . '/../../protected/core/components/security/access_control.inc.php';
// Allow access if user has System Admin role (any ACL); redirect others via JS (headers already sent by header.php)
if (!AccessControl::hasRole('System Admin')) {
    echo '<script>window.location.href="' . htmlspecialchars(get_redirect_url('voucher'), ENT_QUOTES, 'UTF-8') . '";</script>';
    echo '<p>Redirecting...</p>';
    exit;
}
require_once __DIR__ . '/../../protected/core/components/helpers/audit_helper.inc.php';
AuditHelper::logPageView('Settings');

// Fetch current settings
$settings_query = "SELECT * FROM system_settings WHERE id = 1";
$settings_stmt = $pdo->prepare($settings_query);
$settings_stmt->execute();
$current_settings = $settings_stmt->fetch(PDO::FETCH_ASSOC);

// If no settings exist, use defaults
if (!$current_settings) {
    $current_settings = [
        'system_name' => 'PENRO Disbursement Voucher System',
        'page_title' => 'PENRO Disbursement Voucher System',
        'company_name' => 'Provincial Environment and Natural Resources Office',
        'browser_title' => 'PENRO-DVS',
        'header_text' => 'PENRO Disbursement Voucher System v1.0'
    ];
}
?>
<style>
    .settings-container {
        padding: 20px;
        max-width: 1200px;
        margin: 0 auto;
    }

    .settings-header {
        margin-bottom: 30px;
    }

    .settings-header h1 {
        color: rgb(75 85 99 / 0.9);
        font-size: 24px;
        font-weight: 600;
        margin-bottom: 10px;
    }

    .settings-header p {
        color: rgb(75 85 99 / 0.7);
        font-size: 14px;
    }

    .settings-card {
        background-color: #fff;
        border-radius: 8px;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        padding: 30px;
        margin-bottom: 20px;
    }

    .settings-section {
        margin-bottom: 30px;
    }

    .settings-section:last-child {
        margin-bottom: 0;
    }

    .settings-section-title {
        font-size: 18px;
        font-weight: 600;
        color: rgb(75 85 99 / 0.9);
        margin-bottom: 20px;
        padding-bottom: 10px;
        border-bottom: 2px solid #e9e7e7;
    }

    .form-row {
        display: flex;
        gap: 20px;
        margin-bottom: 20px;
    }

    .form-row:last-child {
        margin-bottom: 0;
    }

    .label-input__container {
        display: flex;
        flex-direction: column;
        gap: 10px;
        flex: 1;
    }

    .label-input__container label {
        width: 100%;
        color: darkslategray;
        font-size: 14px;
        text-align: left;
        font-weight: 400;
    }

    .form-custom-input {
        width: 100%;
        height: 40px;
        font-size: 14px;
        padding: 10px 15px;
        text-align: left;
        border: 1px solid #737475;
        border-radius: 4px;
        background-color: #ffffff;
        color: #2a4d4b;
    }

    .form-custom-input:focus {
        outline: none;
        border-color: hsl(189, 69%, 60%);
        box-shadow: 0 0 0 3px rgba(189, 69%, 60%, 0.1);
    }

    .settings-actions {
        display: flex;
        gap: 15px;
        justify-content: flex-end;
        margin-top: 30px;
        padding-top: 20px;
        border-top: 1px solid #e9e7e7;
    }

    .btn {
        padding: 10px 20px;
        border: none;
        border-radius: 4px;
        font-size: 14px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .btn-success {
        background-color: #76BC71;
        color: white;
    }

    .btn-success:hover {
        background-color: #5fa05a;
    }

    .btn-secondary {
        background-color: #6c757d;
        color: white;
    }

    .btn-secondary:hover {
        background-color: #5a6268;
    }

    .info-text {
        font-size: 12px;
        color: rgb(75 85 99 / 0.6);
        margin-top: 5px;
        font-style: italic;
    }
</style>

<!--=============== MAIN ===============-->
<div class="main" id="main">
    <div class="settings-container">
        <div class="settings-header">
            <h1>System Settings</h1>
            <p>Configure system-wide settings and preferences</p>
            <?php if (isset($_SESSION['settings_error'])): ?>
                <div style="background-color: #fee; color: #c33; padding: 10px; border-radius: 4px; margin-top: 10px; border: 1px solid #fcc;">
                    <?php echo htmlspecialchars($_SESSION['settings_error']);
                    unset($_SESSION['settings_error']); ?>
                </div>
            <?php endif; ?>
            <?php if (isset($_SESSION['settings_success'])): ?>
                <div style="background-color: #dfd; color: #3c3; padding: 10px; border-radius: 4px; margin-top: 10px; border: 1px solid #cfc;">
                    <?php echo htmlspecialchars($_SESSION['settings_success']);
                    unset($_SESSION['settings_success']); ?>
                </div>
            <?php endif; ?>
        </div>

        <form action="../../protected/handler/settings_module/settings_handler.php" method="post" id="settingsForm">
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($_SESSION['token'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
            <div class="settings-card">
                <div class="settings-section">
                    <h3 class="settings-section-title">General Settings</h3>

                    <div class="form-row">
                        <div class="label-input__container">
                            <label for="system_name">System Name</label>
                            <input type="text"
                                class="form-custom-input"
                                id="system_name"
                                name="system_name"
                                value="<?php echo htmlspecialchars($current_settings['system_name'] ?? ''); ?>"
                                placeholder="PENRO Disbursement Voucher System"
                                required>
                            <span class="info-text">The name of the system displayed throughout the application</span>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="label-input__container">
                            <label for="page_title">Page Title</label>
                            <input type="text"
                                class="form-custom-input"
                                id="page_title"
                                name="page_title"
                                value="<?php echo htmlspecialchars($current_settings['page_title'] ?? ''); ?>"
                                placeholder="PENRO Disbursement Voucher System"
                                required>
                            <span class="info-text">The default page title used in browser tabs</span>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="label-input__container">
                            <label for="company_name">Company/Organization Name</label>
                            <input type="text"
                                class="form-custom-input"
                                id="company_name"
                                name="company_name"
                                value="<?php echo htmlspecialchars($current_settings['company_name'] ?? ''); ?>"
                                placeholder="Provincial Environment and Natural Resources Office"
                                required>
                            <span class="info-text">The name of your organization or company</span>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="label-input__container">
                            <label for="browser_title">Browser Title</label>
                            <input type="text"
                                class="form-custom-input"
                                id="browser_title"
                                name="browser_title"
                                value="<?php echo htmlspecialchars($current_settings['browser_title'] ?? 'eNGP VMS'); ?>"
                                placeholder="PENRO-DVS"
                                required>
                            <span class="info-text">The title displayed in the browser tab</span>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="label-input__container">
                            <label for="header_text">Header Text</label>
                            <input type="text"
                                class="form-custom-input"
                                id="header_text"
                                name="header_text"
                                value="<?php echo htmlspecialchars($current_settings['header_text'] ?? 'eNGP Verification & Monitoring System v1.0'); ?>"
                                placeholder="PENRO Disbursement Voucher System v1.0"
                                required>
                            <span class="info-text">The text displayed in the header next to the logo</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="settings-actions">
                <button type="button" class="btn btn-secondary" onclick="window.location.reload()">Cancel</button>
                <button type="submit" class="btn btn-success" name="update_settings">Save Settings</button>
            </div>
        </form>
    </div>
</div>

<script src="../../protected/js/main.js"></script>
<script src="../../protected/js/popscript.js"></script>
<script>
    // Form validation
    document.getElementById('settingsForm').addEventListener('submit', function(e) {
        const systemName = document.getElementById('system_name').value.trim();
        const pageTitle = document.getElementById('page_title').value.trim();
        const companyName = document.getElementById('company_name').value.trim();
        const browserTitle = document.getElementById('browser_title').value.trim();
        const headerText = document.getElementById('header_text').value.trim();

        if (!systemName || !pageTitle || !companyName || !browserTitle || !headerText) {
            e.preventDefault();
            showNotify('Please fill in all required fields.', 'warning', 3000);
            return false;
        }
    });
</script>

</body>

</html>