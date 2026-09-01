<?php
require_once __DIR__ . '/../vendor/autoload.php';

require_once __DIR__ . '/core/components/helpers/handler_transaction_helper.inc.php';


$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

$dsn = 'mysql:host=' . $_ENV['DB_HOST']
     . ';dbname=' . $_ENV['DB_NAME']
     . ';charset=' . $_ENV['DB_CHARSET'];

$dbusername = $_ENV['DB_USERNAME'];
$dbpassword = $_ENV['DB_PASSWORD'];

try {
    $pdo = new PDO($dsn, $dbusername, $dbpassword);
    pdo_configure($pdo);
} catch (PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
    require_once __DIR__ . '/core/components/redirects/redirect_config.inc.php';
    redirect_to_internal('route_404');
}
