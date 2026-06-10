<?php

require_once __DIR__ . '/core/components/helpers/handler_transaction_helper.inc.php';

$dsn = 'mysql:host=localhost;dbname=dvsdb;charset=utf8';
$dbusername = 'root';
$dbpassword = '';

try {
    $pdo = new PDO($dsn, $dbusername, $dbpassword);
    pdo_configure($pdo);
} catch (PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
    require_once __DIR__ . '/core/components/redirects/redirect_config.inc.php';
    redirect_to_internal('route_404');
}
