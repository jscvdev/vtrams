<?php

require_once '../../dbconnection.inc.php';
require_once '../../core/components/security/config_session.inc.php';
require_once '../../core/components/security/router.inc.php';

if (isset($_GET['deleteid'])){
    $id = $_GET['deleteid'];
    $query = "DELETE FROM oic WHERE id = :id";
    $statement = $pdo->prepare($query);
    $statement->bindParam(":id",$id);
    if ($statement->execute()){
        echo "<script>alert('success'); window.location.href='../edit_form.php'</script>";
    }
}
