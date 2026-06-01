<?php
require '../../core/components/security/err_blocker.inc.php';
require '../../dbconnection.inc.php';
require '../../core/components/security/config_session.inc.php';
require '../../core/components/security/router.inc.php';

$fetch_incoming_data_query = "SELECT * FROM incoming WHERE receiver_udc LIKE :udc";
$fetch_incoming_data = $pdo->prepare($fetch_incoming_data_query);
$udc_param = '%' . $_SESSION["logged_user_udc"] . '%'; // Prepare the parameter with '%' wildcards
$fetch_incoming_data->bindParam(":udc", $udc_param, PDO::PARAM_STR);
$fetch_incoming_data->execute();

$fetched_data = array();
while ($row = $fetch_incoming_data->fetch(PDO::FETCH_ASSOC)) {
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

