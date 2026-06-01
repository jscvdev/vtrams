<?php
require '../../core/components/security/err_blocker.inc.php';
require '../../dbconnection.inc.php';
require '../../core/components/security/config_session.inc.php';
require '../../core/components/security/router.inc.php';

$fetch_document_status_query = "SELECT * FROM document_tracking;";
$fetch_document_status_statement = $pdo->prepare($fetch_document_status_query);
$fetch_document_status_statement->execute();

$fetched_data = array();
while ($row = $fetch_document_status_statement->fetch(PDO::FETCH_ASSOC)) {
    // Encode non-UTF-8 characters to prevent encoding issues
    array_walk_recursive($row, function (&$item, $key) {
        if (!mb_check_encoding($item, 'UTF-8')) {
            $item = utf8_encode($item);
        }
    });

    // Build an array of rows to be returned
    $fetched_data[] = $row;
}

// Return the data as JSON
header('Content-Type: application/json; charset=UTF-8');
echo json_encode($fetched_data, JSON_UNESCAPED_UNICODE);

?>