<?php
session_start();

// Enable error reporting for development (disable in production)
ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

include("core/components/security/config_session.inc.php");

// DB connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "dmsdb";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    echo json_encode([]);
    exit;
}

// Set charset to utf8mb4 to avoid encoding issues
$conn->set_charset("utf8mb4");

// Get office from session
$currentOffice = $_SESSION['logged_user_office'] ?? '';
if (!$currentOffice) {
    echo json_encode([]);
    exit;
}

$officeEscaped = $conn->real_escape_string($currentOffice);

// Query
$sql = "
    SELECT DISTINCT 
        CONCAT(emp_fn, ' ', 
               IF(emp_mi IS NOT NULL AND emp_mi != '', CONCAT(emp_mi, ' '), ''), 
               emp_ln) AS full_name
    FROM user_group
    WHERE office = '$officeEscaped'
    ORDER BY emp_ln, emp_fn
";

$result = $conn->query($sql);

$users = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        // Make sure string is valid UTF-8 (optional, for safety)
        $fullName = mb_convert_encoding(trim($row['full_name']), 'UTF-8', 'UTF-8');
        $users[] = $fullName;
    }
}

echo json_encode($users);

$conn->close();
