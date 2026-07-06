<?php
require_once __DIR__ . '/error_page_common.php';

http_response_code(403);
header('X-Robots-Tag: noindex, nofollow');

$homeUrl = error_page_web_base() . '/';
$cssUrl = error_page_asset_url('protected/routes/etc.css');
$iconUrl = error_page_asset_url('public/assets/icons/DENR3.ico');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Access Denied</title>
    <link rel="stylesheet" href="<?php echo htmlspecialchars($cssUrl, ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="shortcut icon" href="<?php echo htmlspecialchars($iconUrl, ENT_QUOTES, 'UTF-8'); ?>">
</head>

<body>
    <div class="container">
        <h1 class="code-forbidden">403</h1>
        <p>Access to this area is not allowed.</p>
        <a href="<?php echo htmlspecialchars($homeUrl, ENT_QUOTES, 'UTF-8'); ?>">Go back to safety</a>
    </div>
</body>

</html>
