<?php
// UTF-8 Support for special characters (Ñ, á, é, etc.)
require_once '../../protected/core/utf8_helper.inc.php';
initUTF8Support();

require_once __DIR__ . '/../../protected/core/components/helpers/http_cache_helper.inc.php';
send_no_cache_headers();

// Use same session as rest of app (vtrams_session) so login state is consistent
require_once '../../protected/core/components/security/config_session.inc.php';
require_once '../../protected/dbconnection.inc.php';
require_once '../../protected/core/components/security/session_login_helper.inc.php';
require_once '../../protected/core/components/redirects/redirect_config.inc.php';

// Only skip login when session is complete and matches the database (avoids blank redirect loops)
if (login_session_has_required_fields() && login_session_matches_database($pdo)) {
    $voucherUrl = get_redirect_url('voucher');
    if ($voucherUrl !== null) {
        header('Location: ' . $voucherUrl);
        exit;
    }
}

// Stale or partial login cookie: clear it and show the login form
if (!empty($_SESSION['logged_in'])) {
    invalidate_login_session();
}

require_once '../../protected/page_title_helper.inc.php';
require_once __DIR__ . '/../../protected/core/components/helpers/asset_version_helper.inc.php';

// Initialize page title helper
/** @var PageTitleHelper $pageTitleHelper */
$pageTitleHelper = new PageTitleHelper($pdo);
$page_title = $pageTitleHelper->getBrowserTitle();
$header_text = $pageTitleHelper->getHeaderText();
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <?php require_once __DIR__ . '/../../protected/core/components/helpers/analytics_guard.inc.php'; ?>
    <meta name="viewport"
        content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <base href="/vtrams/public/documents/">
    <title><?php echo htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8'); ?></title>
    <?php
    $loginStylesDir = __DIR__ . '/../styles/css';
    $loginStylesHref = '/vtrams/public/styles/css/';
    asset_login_stylesheets($loginStylesHref, $loginStylesDir);
    ?>
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link rel="shortcut icon" href="../assets/icons/vtlogo.ico">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/remixicon/4.1.0/remixicon.css" />
    <?php require_once '../../protected/core/components/notifications/notification.inc.php'; ?>
</head>

<body>
    <!-- Header -->
    <header class="header">
        <div class="header__container">
            <div class="menu-logo__container">
                <div class="header__toggle" id="header-toggle">
                    <i class="ri-menu-2-line"></i>
                </div>
                <div class="header-logo__container">
                    <a href="#" class="header__logo">
                        <img src="../assets/img/vtlogo2.png" alt="DENR Logo">
                    </a>
                    <p><?php echo htmlspecialchars($header_text, ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
            </div>
            <div class="sidebar__account">
                <p id="time"></p>
            </div>
        </div>
    </header>

    <!-- Loader -->
    <div id="loaderModal" class="loader-modal" aria-hidden="true">
        <div class="loader" role="status" aria-label="Loading"></div>
    </div>

    <!-- Auth Section -->
    <section class="auth-section">
        <div class="auth-container">
            <div class="auth-box">
                <img src="../assets/img/vtlogo.png">
                <h2>Login</h2>
                <form id="loginForm" method="POST">
                    <input type="hidden" name="action" value="login">
                    <input type="text" id="loginUsername" name="username" placeholder="Username" required>
                    <input type="password" id="loginPassword" name="password" placeholder="Password" required>
                    <button type="submit">Login</button>
                </form>
            </div>
        </div>
    </section>

    <!--=============== JS ===============-->
    <script>
        /* Loader */
        function showLoader() {
            const l = document.getElementById('loaderModal');
            l.classList.add('show');
            l.style.display = 'flex';
            l.setAttribute('aria-hidden', 'false');
        }

        function hideLoader() {
            const l = document.getElementById('loaderModal');
            l.classList.remove('show');
            l.style.display = 'none';
            l.setAttribute('aria-hidden', 'true');
        }

        <?php
        // Compute app base path dynamically so deployments work both in subfolders (e.g. /vtrams)
        // and at the domain root (e.g. /).
        $script = $_SERVER['SCRIPT_NAME'] ?? '/public/documents/index.php';
        $appBase = preg_replace('#/public/documents/index\.php$#', '', $script);
        $appBase = rtrim($appBase, '/');
        ?>

        const APP_BASE = <?= json_encode($appBase, JSON_UNESCAPED_SLASHES) ?>;

        /* Login */
        document.getElementById('loginForm').addEventListener('submit', async (e) => {
            e.preventDefault();

            const username = document.getElementById('loginUsername').value.trim();
            const password = document.getElementById('loginPassword').value;

            const formData = new FormData();
            formData.append('action', 'login');
            formData.append('username', username);
            formData.append('password', password);

            // Show processing notification
            showNotify("Processing… Logging in", "processing", 3000);

            try {
                const res = await fetch(`${APP_BASE}/protected/core/components/security/auth.php`, {
                    method: 'POST',
                    body: formData
                });

                const rawText = await res.text();
                const isJson = res.headers.get('content-type')?.includes('application/json');

                let resp;
                if (isJson) {
                    resp = JSON.parse(rawText);
                } else {
                    // Likely receiving an HTML page (auth.php not found / access denied / PHP error)
                    console.error('Auth response was not JSON. Status:', res.status, 'Body:', rawText);
                    const snippet = rawText.slice(0, 500);
                    throw new Error(`Unexpected response (${res.status}). Snippet: ${snippet}`);
                }

                if (resp.status === "success") {
                    showNotify(resp.message || 'Login successful', 'success', 2500);
                    setTimeout(() => window.location.href = `${APP_BASE}/public/vouchers/voucher.php`, 800);
                } else {
                    showNotify(resp.message || 'Login failed', 'error', 2500);
                }
            } catch (err) {
                showNotify(err?.message || 'Network error', 'error', 3500);
            }
        });
    </script>
</body>

</html>