<?php

// Get the current page, rows per page, and search query from the request
$currentPage = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$searchQuery = isset($_GET['search']) ? $_GET['search'] : '';
$documentType = isset($_GET['document_type']) ? $_GET['document_type'] : '';
$rowsPerPage = isset($_GET['rowsPerPage']) ? (int)$_GET['rowsPerPage'] : 200;

// Ensure page number is at least 1
$currentPage = max(1, $currentPage);

// Calculate the offset for pagination
$offset = ($currentPage - 1) * $rowsPerPage;

// Fetch total number of rows that match the search query
$action_logs_queryCount = "SELECT COUNT(*) AS total FROM action_logs WHERE office_from = :office_from AND 
                            (document_title LIKE :search OR document_desc LIKE :search OR document_receive_type LIKE :search OR document_type LIKE :search)";
$action_logs_statementCount = $pdo->prepare($action_logs_queryCount);
$action_logs_statementCount->bindParam(':office_from', $_SESSION["logged_user_office"]);
$action_logs_statementCount->bindValue(':search', '%' . $searchQuery . '%');
$action_logs_statementCount->execute();
$totalRows = $action_logs_statementCount->fetch(PDO::FETCH_ASSOC)['total'];

// Prepare and execute the query to fetch the actual data based on search query
$fetch_action_logs_query = "SELECT * FROM action_logs WHERE office_from = :office_from AND 
                            (document_title LIKE :search OR document_desc LIKE :search OR document_receive_type LIKE :search OR document_type LIKE :search) 
                            ORDER BY document_id DESC LIMIT :offset, :rowsPerPage";
$fetch_action_logs = $pdo->prepare($fetch_action_logs_query);
$fetch_action_logs->bindParam(":office_from", $_SESSION["logged_user_office"]);
$fetch_action_logs->bindValue(':search', '%' . $searchQuery . '%');
$fetch_action_logs->bindValue(':offset', $offset, PDO::PARAM_INT);
$fetch_action_logs->bindValue(':rowsPerPage', $rowsPerPage, PDO::PARAM_INT);
$fetch_action_logs->execute();

// Fetch the data
$data = $fetch_action_logs->fetchAll(PDO::FETCH_ASSOC);

// Return the data as JSON
echo json_encode([
    'data' => $data,
    'totalRows' => $totalRows,
    'rowsPerPage' => $rowsPerPage,
    'currentPage' => $currentPage
]);

exit();
?>
