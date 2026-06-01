<?php

$loggedUserOffice = $_SESSION['logged_user_office'] ?? null;

if ($loggedUserOffice) {
    $sql = "SELECT designation, designated_office 
            FROM designation_limit 
            WHERE FIND_IN_SET(:office, designated_office) > 0 
              AND visibility = 1
            ORDER BY designation";

    $stmt = $pdo->prepare($sql);
    $stmt->execute(['office' => $loggedUserOffice]);

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $label = $row['designation'];
        $value = $row['designation'];
        echo '<option value="' . htmlspecialchars($value) . '">'
            . htmlspecialchars($label) . '</option>';
    }
} else {
    echo '<option disabled>No office context available</option>';
}
?>
