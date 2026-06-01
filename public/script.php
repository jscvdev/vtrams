<?php
require '../protected/dbconnection.inc.php'; // Replace with your actual DB connection script

try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Fetch all designation_limit records
    $query = $pdo->query("SELECT designation, designated_udc FROM designation_limit");

    while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
        $designation = trim($row['designation']);
        $udcList = array_filter(array_map('trim', explode(',', $row['designated_udc'])));
        $officeList = [];

        foreach ($udcList as $udc) {
            $stmt = $pdo->prepare("SELECT designation, office FROM user_group WHERE udc = :udc");
            $stmt->execute([':udc' => $udc]);
            $userFound = false;

            while ($user = $stmt->fetch(PDO::FETCH_ASSOC)) {
                // Split user's designation into individual roles
                $designations = array_map('trim', explode(',', $user['designation']));

                // If the current designation matches any of user's designations
                if (in_array($designation, $designations)) {
                    $officeList[] = $user['office'];
                    $userFound = true;
                    break; // Use the first match
                }
            }

            if (!$userFound) {
                $officeList[] = 'None';
            }
        }

        // Join office list
        $finalOfficeList = implode(',', $officeList);

        // Update the designated_office
        $update = $pdo->prepare("UPDATE designation_limit SET designated_office = :offices WHERE designation = :designation");
        $update->execute([
            ':offices' => $finalOfficeList,
            ':designation' => $designation
        ]);

        echo "✔ Updated '{$designation}' with offices: {$finalOfficeList}\n";
    }

    echo "✅ All designations updated.\n";

} catch (PDOException $e) {
    echo "❌ Database error: " . $e->getMessage();
}
