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
        require_once __DIR__ . '/../../core/components/redirects/redirect_config.inc.php';
        $logoutUrl = redirect_base_url() . '/protected/core/components/security/logout_handler.inc.php';
        echo "<script>alert('success'); window.location.href=" . json_encode($logoutUrl) . ";</script>";
    }
}
