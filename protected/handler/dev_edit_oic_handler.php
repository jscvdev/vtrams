<?php

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once '../dbconnection.inc.php';
    require_once '../core/components/security/config_session.inc.php';
    require_once '../core/components/security/router.inc.php';
    $id = htmlspecialchars($_POST['id']);
    $so_no = htmlspecialchars($_POST['so_no']);
    $date_start = htmlspecialchars($_POST['date_start']);
    $date_end = htmlspecialchars($_POST['date_end']);
    $oic = htmlspecialchars($_POST['oic']);

    try {
        $temp_dump = [];

        // MULTIPLE FILE UPLOAD SUPPORTED 27/04/2024
        try {
            if (isset($_REQUEST['edit_oic'])) {

                //INSERT QUERY
                $query = "UPDATE oic SET so_no=:so_no, date_start=:date_start, date_end=:date_end, oic=:oic WHERE id=:id";
                $statement = $pdo->prepare($query);
                $statement->bindParam(":so_no", $so_no);
                $statement->bindParam(":date_start", $date_start);
                $statement->bindParam(":date_end", $date_end);
                $statement->bindParam(":oic", $oic);
                $statement->bindParam(":id", $id);
                if ($statement->execute()) {
                    echo "<script>alert('success'); window.location.href='../edit_form.php'</script>";
                }
            } else {
                echo "<script>alert('Error3');</script>";
            }
        } catch (PDOException $e) {
            echo $e->getMessage();
        }

        $pdo = null;
        $statement = null;

        die();
    } catch (PDOException $e) {
        echo $e->getMessage();
    }
} else {
    require_once __DIR__ . '/../../core/components/redirects/redirect_config.inc.php';
    redirect_to('documents_edit_form');
    die();
}
