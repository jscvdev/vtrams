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
    $sql = "SELECT vt.processing_no,
                   vt.dv_no,
                   vt.payee,
                   vt.status AS tracking_status,
                   vt.voucher_status,
                   vt.datetime_status AS datetime_action,
                   CASE
                       WHEN LOWER(TRIM(vt.status)) = 'paid' THEN COALESCE(va.datetime_action, vt.datetime_status)
                       ELSE NULL
                   END AS datetime_paid
            FROM voucher_tracking vt
            LEFT JOIN voucher_archives va ON va.processing_no = vt.processing_no
            WHERE vt.processing_no LIKE :q
               OR IFNULL(vt.dv_no, '') LIKE :q2
               OR vt.payee LIKE :q3
               OR REPLACE(REPLACE(vt.processing_no, '-', ''), ' ', '') LIKE :q4
            ORDER BY vt.datetime_status DESC LIMIT 100";

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
