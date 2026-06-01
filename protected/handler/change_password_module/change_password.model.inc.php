<?php

function change_user_password(object $pdo, string $confirm_password, string $emp_id){
    //QUERY
    $query= "UPDATE user_group SET password = :password WHERE emp_id = :emp_id";

    $statement = $pdo->prepare($query);

    $options = [
        'cost' => 12
    ];
    $hashedPwd = password_hash($confirm_password, PASSWORD_BCRYPT, $options);

    $statement->bindParam(":password",$hashedPwd);
    $statement->bindParam(":emp_id",$emp_id);

    if ($statement->execute()) {
        echo "<script>alert('success'); window.location.href='../../core/components/security/logout_handler.inc.php'</script>";
    }
}
