<?php

$dsn = 'mysql:host=localhost;dbname=dvsdb;charset=utf8';
$dbusername = 'root';
$dbpassword = '';

try {
    $pdo = new PDO($dsn, $dbusername, $dbpassword);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false) /* PREVENT PDO INJECTION  */
        /*    echo '<script>alert("Connected Successfully")</script>'*/;
} catch (PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
    require_once __DIR__ . '/core/components/redirects/redirect_config.inc.php';
    redirect_to_internal('route_404');
}
