<?php
/**
 * One-off script to default `user_group.emp_tag`.
 *
 * Usage:
 *  - Run once from the browser or PHP CLI (make sure you trust this environment).
 */
require '../protected/dbconnection.inc.php';

$defaultTag = "Other Professional Services";

// Set to true if you want to overwrite ALL rows, otherwise it only fills NULL/empty.
$updateAll = false;

try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if ($updateAll) {
        $sql = "UPDATE user_group SET emp_tag = :emp_tag";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':emp_tag', $defaultTag, PDO::PARAM_STR);
        $stmt->execute();
    } else {
        $sql = "UPDATE user_group SET emp_tag = :emp_tag WHERE emp_tag IS NULL OR emp_tag = ''";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':emp_tag', $defaultTag, PDO::PARAM_STR);
        $stmt->execute();
    }

    $affected = $stmt->rowCount();
    echo "Updated emp_tag to '{$defaultTag}'. Rows affected: {$affected}\n";
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
    exit(1);
}

