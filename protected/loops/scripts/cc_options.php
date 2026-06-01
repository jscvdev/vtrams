<?php

$loggedUserOffice = $_SESSION['logged_user_office'] ?? null;

if ($loggedUserOffice) {
    $sql = "SELECT designation 
            FROM designation_limit 
            WHERE FIND_IN_SET(:office, designated_office) > 0 
              AND visibility = 1 
            ORDER BY designation";

    $stmt = $pdo->prepare($sql);
    $stmt->execute(['office' => $loggedUserOffice]);

    $foundAny = false;
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $foundAny = true;
        $designation = htmlspecialchars($row['designation']);
        echo "<label><input type='checkbox' name='internal_office[]' value='{$designation}'> {$designation}</label>";
    }

    if (!$foundAny) {
        echo "<label><em>No designations available</em></label>";
    }
} else {
    echo "<label><em>No office context available</em></label>";
}
?>
