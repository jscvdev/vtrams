<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../protected/dbconnection.inc.php';
require_once __DIR__ . '/../protected/core/components/helpers/voucher_tracking_helper.inc.php';

$query = trim((string)($_POST['query'] ?? ''));
$query = preg_replace('/\s+/u', ' ', $query);

if ($query === '') {
    echo json_encode([]);
    exit;
}

$like = '%' . $query . '%';
$compact = preg_replace('/[\s\-]+/u', '', $query);
$likeCompact = ($compact !== '') ? ('%' . $compact . '%') : $like;

try {
    $sql = "SELECT processing_no,
                   dv_no,
                   payee,
                   status AS tracking_status,
                   voucher_status
            FROM voucher_tracking
            WHERE processing_no LIKE :q
               OR IFNULL(dv_no, '') LIKE :q2
               OR payee LIKE :q3
               OR REPLACE(REPLACE(processing_no, '-', ''), ' ', '') LIKE :q4
            ORDER BY datetime_status DESC LIMIT 100";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':q', $like, PDO::PARAM_STR);
    $stmt->bindValue(':q2', $like, PDO::PARAM_STR);
    $stmt->bindValue(':q3', $like, PDO::PARAM_STR);
    $stmt->bindValue(':q4', $likeCompact, PDO::PARAM_STR);
    $stmt->execute();

    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($results as &$row) {
        $row['voucher_status'] = voucher_tracking_action_label($row['voucher_status'] ?? '');
    }
    unset($row);
    echo json_encode($results, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
} catch (PDOException $e) {
    echo json_encode(['error' => 'Query error: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
